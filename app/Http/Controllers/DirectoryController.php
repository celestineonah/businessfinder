<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DirectoryController extends Controller
{
    private const INDEX_THRESHOLD = 10;

    public function locations(): Response
    {
        $states = $this->stateCounts();

        return Inertia::render('SeoDirectory', [
            'mode' => 'locations',
            'title' => 'Business Locations in Nigeria',
            'description' =>
                'Browse real Nigerian businesses by state and the Federal Capital Territory on BusinessFinder Nigeria.',
            'heading' => 'Browse Businesses by Location',
            'subheading' =>
                'Explore Nigerian states and the FCT using real published BusinessFinder listings.',
            'canonicalUrl' => route('directory.locations'),
            'indexable' => true,
            'total' => $states->sum('businessCount'),
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Locations', 'url' => route('directory.locations')],
            ],
            'states' => $states,
            'categories' => [],
            'lgas' => [],
            'businesses' => [],
            'pagination' => null,
        ]);
    }

    public function state(string $stateSlug)
    {
        $state = $this->resolveState($stateSlug);
        abort_if($state === null, 404);

        $canonicalSlug = $this->statePublicSlug($state);

        if ($stateSlug !== $canonicalSlug) {
            return redirect()->route(
                'directory.state',
                ['stateSlug' => $canonicalSlug],
                301
            );
        }

        $base = $this->publicBase()
            ->where('bl.state_id', $state->id);

        $total = (clone $base)->distinct()->count('b.id');
        $categories = $this->categoryCounts((int) $state->id);
        [$businesses, $pagination] = $this->paginateBusinesses($base);

        return Inertia::render('SeoDirectory', [
            'mode' => 'state',
            'title' => "Businesses in {$state->name}",
            'description' =>
                "Find real published businesses and service providers in {$state->name}, Nigeria on BusinessFinder Nigeria.",
            'heading' => "Businesses in {$state->name}",
            'subheading' =>
                "Browse published businesses, categories and local services in {$state->name}.",
            'canonicalUrl' => route(
                'directory.state',
                ['stateSlug' => $canonicalSlug]
            ),
            'indexable' => $total >= self::INDEX_THRESHOLD,
            'total' => $total,
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Locations', 'url' => route('directory.locations')],
                ['name' => $state->name, 'url' => route(
                    'directory.state',
                    ['stateSlug' => $canonicalSlug]
                )],
            ],
            'states' => [],
            'categories' => $categories->map(
                fn ($category) => [
                    ...((array) $category),
                    'url' => route(
                        'directory.state.category',
                        [
                            'stateSlug' => $canonicalSlug,
                            'categorySlug' => $category->slug,
                        ]
                    ),
                ]
            )->values(),
            'lgas' => [],
            'businesses' => $businesses,
            'pagination' => $pagination,
        ]);
    }

    public function stateCategory(
        string $stateSlug,
        string $categorySlug
    ) {
        $state = $this->resolveState($stateSlug);
        abort_if($state === null, 404);

        $category = DB::table('categories')
            ->where('slug', $categorySlug)
            ->where('is_active', true)
            ->first();

        abort_if($category === null, 404);

        $canonicalSlug = $this->statePublicSlug($state);

        if ($stateSlug !== $canonicalSlug) {
            return redirect()->route(
                'directory.state.category',
                [
                    'stateSlug' => $canonicalSlug,
                    'categorySlug' => $category->slug,
                ],
                301
            );
        }

        $base = $this->publicBase()
            ->where('bl.state_id', $state->id)
            ->whereExists(function ($query) use ($category) {
                $query
                    ->selectRaw('1')
                    ->from('business_category as bc_filter')
                    ->whereColumn('bc_filter.business_id', 'b.id')
                    ->where('bc_filter.category_id', $category->id);
            });

        $total = (clone $base)->distinct()->count('b.id');
        $lgas = $this->lgaCounts((int) $state->id, (int) $category->id);
        [$businesses, $pagination] = $this->paginateBusinesses($base);

        return Inertia::render('SeoDirectory', [
            'mode' => 'state-category',
            'title' => "{$category->name} in {$state->name}",
            'description' =>
                "Find real published {$category->name} businesses in {$state->name}, Nigeria on BusinessFinder Nigeria.",
            'heading' => "{$category->name} in {$state->name}",
            'subheading' =>
                "Compare published {$category->name} listings across {$state->name}.",
            'canonicalUrl' => route(
                'directory.state.category',
                [
                    'stateSlug' => $canonicalSlug,
                    'categorySlug' => $category->slug,
                ]
            ),
            'indexable' => $total >= self::INDEX_THRESHOLD,
            'total' => $total,
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Locations', 'url' => route('directory.locations')],
                ['name' => $state->name, 'url' => route(
                    'directory.state',
                    ['stateSlug' => $canonicalSlug]
                )],
                ['name' => $category->name, 'url' => route(
                    'directory.state.category',
                    [
                        'stateSlug' => $canonicalSlug,
                        'categorySlug' => $category->slug,
                    ]
                )],
            ],
            'states' => [],
            'categories' => [],
            'lgas' => $lgas->map(
                fn ($lga) => [
                    ...((array) $lga),
                    'url' => route(
                        'directory.lga.category',
                        [
                            'stateSlug' => $canonicalSlug,
                            'lgaSlug' => $lga->slug,
                            'categorySlug' => $category->slug,
                        ]
                    ),
                ]
            )->values(),
            'businesses' => $businesses,
            'pagination' => $pagination,
        ]);
    }

    public function lgaCategory(
        string $stateSlug,
        string $lgaSlug,
        string $categorySlug
    ) {
        $state = $this->resolveState($stateSlug);
        abort_if($state === null, 404);

        $lga = DB::table('local_government_areas')
            ->where('state_id', $state->id)
            ->where('slug', $lgaSlug)
            ->where('is_active', true)
            ->first();

        abort_if($lga === null, 404);

        $category = DB::table('categories')
            ->where('slug', $categorySlug)
            ->where('is_active', true)
            ->first();

        abort_if($category === null, 404);

        $canonicalSlug = $this->statePublicSlug($state);

        if ($stateSlug !== $canonicalSlug) {
            return redirect()->route(
                'directory.lga.category',
                [
                    'stateSlug' => $canonicalSlug,
                    'lgaSlug' => $lga->slug,
                    'categorySlug' => $category->slug,
                ],
                301
            );
        }

        $base = $this->publicBase()
            ->where('bl.state_id', $state->id)
            ->where('bl.local_government_area_id', $lga->id)
            ->whereExists(function ($query) use ($category) {
                $query
                    ->selectRaw('1')
                    ->from('business_category as bc_filter')
                    ->whereColumn('bc_filter.business_id', 'b.id')
                    ->where('bc_filter.category_id', $category->id);
            });

        $total = (clone $base)->distinct()->count('b.id');
        [$businesses, $pagination] = $this->paginateBusinesses($base);

        return Inertia::render('SeoDirectory', [
            'mode' => 'lga-category',
            'title' => "{$category->name} in {$lga->name}, {$state->name}",
            'description' =>
                "Find real published {$category->name} businesses in {$lga->name}, {$state->name} on BusinessFinder Nigeria.",
            'heading' => "{$category->name} in {$lga->name}",
            'subheading' =>
                "Published {$category->name} listings in {$lga->name}, {$state->name}.",
            'canonicalUrl' => route(
                'directory.lga.category',
                [
                    'stateSlug' => $canonicalSlug,
                    'lgaSlug' => $lga->slug,
                    'categorySlug' => $category->slug,
                ]
            ),
            'indexable' => $total >= self::INDEX_THRESHOLD,
            'total' => $total,
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Locations', 'url' => route('directory.locations')],
                ['name' => $state->name, 'url' => route(
                    'directory.state',
                    ['stateSlug' => $canonicalSlug]
                )],
                ['name' => $category->name, 'url' => route(
                    'directory.state.category',
                    [
                        'stateSlug' => $canonicalSlug,
                        'categorySlug' => $category->slug,
                    ]
                )],
                ['name' => $lga->name, 'url' => route(
                    'directory.lga.category',
                    [
                        'stateSlug' => $canonicalSlug,
                        'lgaSlug' => $lga->slug,
                        'categorySlug' => $category->slug,
                    ]
                )],
            ],
            'states' => [],
            'categories' => [],
            'lgas' => [],
            'businesses' => $businesses,
            'pagination' => $pagination,
        ]);
    }

    public function sitemap()
    {
        $urls = [
            route('home'),
            route('categories'),
            route('directory.locations'),
        ];

        $stateRows = DB::table('states as s')
            ->join('business_locations as bl', function ($join) {
                $join->on('bl.state_id', '=', 's.id')
                    ->where('bl.is_primary', true)
                    ->where('bl.is_active', true)
                    ->whereNull('bl.deleted_at');
            })
            ->join('businesses as b', 'b.id', '=', 'bl.business_id')
            ->where('s.is_active', true)
            ->where('b.listing_status', 'listed')
            ->where('b.is_active', true)
            ->whereNotNull('b.published_at')
            ->whereNull('b.deleted_at')
            ->groupBy('s.id', 's.slug', 's.code')
            ->havingRaw('COUNT(DISTINCT b.id) >= ?', [self::INDEX_THRESHOLD])
            ->get(['s.id', 's.slug', 's.code']);

        foreach ($stateRows as $state) {
            $publicSlug = $state->code === 'FC' ? 'abuja' : $state->slug;
            $urls[] = route('directory.state', ['stateSlug' => $publicSlug]);
        }

        $stateCategoryRows = DB::table('states as s')
            ->join('business_locations as bl', function ($join) {
                $join->on('bl.state_id', '=', 's.id')
                    ->where('bl.is_primary', true)
                    ->where('bl.is_active', true)
                    ->whereNull('bl.deleted_at');
            })
            ->join('businesses as b', 'b.id', '=', 'bl.business_id')
            ->join('business_category as bc', 'bc.business_id', '=', 'b.id')
            ->join('categories as c', 'c.id', '=', 'bc.category_id')
            ->where('s.is_active', true)
            ->where('c.is_active', true)
            ->where('b.listing_status', 'listed')
            ->where('b.is_active', true)
            ->whereNotNull('b.published_at')
            ->whereNull('b.deleted_at')
            ->groupBy('s.id', 's.slug', 's.code', 'c.id', 'c.slug')
            ->havingRaw('COUNT(DISTINCT b.id) >= ?', [self::INDEX_THRESHOLD])
            ->get([
                's.slug as state_slug',
                's.code as state_code',
                'c.slug as category_slug',
            ]);

        foreach ($stateCategoryRows as $row) {
            $publicSlug = $row->state_code === 'FC'
                ? 'abuja'
                : $row->state_slug;
            $urls[] = route('directory.state.category', [
                'stateSlug' => $publicSlug,
                'categorySlug' => $row->category_slug,
            ]);
        }

        $lgaCategoryRows = DB::table('states as s')
            ->join('business_locations as bl', function ($join) {
                $join->on('bl.state_id', '=', 's.id')
                    ->where('bl.is_primary', true)
                    ->where('bl.is_active', true)
                    ->whereNull('bl.deleted_at');
            })
            ->join(
                'local_government_areas as lga',
                'lga.id',
                '=',
                'bl.local_government_area_id'
            )
            ->join('businesses as b', 'b.id', '=', 'bl.business_id')
            ->join('business_category as bc', 'bc.business_id', '=', 'b.id')
            ->join('categories as c', 'c.id', '=', 'bc.category_id')
            ->where('s.is_active', true)
            ->where('lga.is_active', true)
            ->where('c.is_active', true)
            ->where('b.listing_status', 'listed')
            ->where('b.is_active', true)
            ->whereNotNull('b.published_at')
            ->whereNull('b.deleted_at')
            ->groupBy(
                's.slug',
                's.code',
                'lga.id',
                'lga.slug',
                'c.id',
                'c.slug'
            )
            ->havingRaw('COUNT(DISTINCT b.id) >= ?', [self::INDEX_THRESHOLD])
            ->get([
                's.slug as state_slug',
                's.code as state_code',
                'lga.slug as lga_slug',
                'c.slug as category_slug',
            ]);

        foreach ($lgaCategoryRows as $row) {
            $publicSlug = $row->state_code === 'FC'
                ? 'abuja'
                : $row->state_slug;
            $urls[] = route('directory.lga.category', [
                'stateSlug' => $publicSlug,
                'lgaSlug' => $row->lga_slug,
                'categorySlug' => $row->category_slug,
            ]);
        }

        $businessUrls = DB::table('businesses')
            ->where('listing_status', 'listed')
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('slug')
            ->map(fn ($slug) => route('business.show', ['slug' => $slug]))
            ->all();

        $urls = array_values(array_unique([...$urls, ...$businessUrls]));

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($urls as $url) {
            $xml .= '  <url><loc>'
                . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                . "</loc></url>\n";
        }

        $xml .= "</urlset>\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function publicBase(): Builder
    {
        return DB::table('businesses as b')
            ->join('business_locations as bl', function ($join) {
                $join->on('bl.business_id', '=', 'b.id')
                    ->where('bl.is_primary', true)
                    ->where('bl.is_active', true)
                    ->whereNull('bl.deleted_at');
            })
            ->leftJoin('states as s', 's.id', '=', 'bl.state_id')
            ->leftJoin(
                'local_government_areas as lga',
                'lga.id',
                '=',
                'bl.local_government_area_id'
            )
            ->where('b.listing_status', 'listed')
            ->where('b.is_active', true)
            ->whereNotNull('b.published_at')
            ->whereNull('b.deleted_at');
    }

    private function stateCounts()
    {
        return DB::table('states as s')
            ->leftJoin('business_locations as bl', function ($join) {
                $join->on('bl.state_id', '=', 's.id')
                    ->where('bl.is_primary', true)
                    ->where('bl.is_active', true)
                    ->whereNull('bl.deleted_at');
            })
            ->leftJoin('businesses as b', function ($join) {
                $join->on('b.id', '=', 'bl.business_id')
                    ->where('b.listing_status', 'listed')
                    ->where('b.is_active', true)
                    ->whereNotNull('b.published_at')
                    ->whereNull('b.deleted_at');
            })
            ->where('s.is_active', true)
            ->groupBy('s.id', 's.name', 's.slug', 's.code')
            ->orderByRaw("CASE WHEN s.code = 'FC' THEN 0 ELSE 1 END")
            ->orderBy('s.name')
            ->get([
                's.id',
                's.name',
                's.slug',
                's.code',
                DB::raw('COUNT(DISTINCT b.id) as businessCount'),
            ])
            ->map(fn ($state) => [
                'id' => $state->id,
                'name' => $state->name,
                'slug' => $state->slug,
                'code' => $state->code,
                'businessCount' => (int) $state->businessCount,
                'url' => route('directory.state', [
                    'stateSlug' => $state->code === 'FC'
                        ? 'abuja'
                        : $state->slug,
                ]),
            ])
            ->values();
    }

    private function categoryCounts(int $stateId)
    {
        return DB::table('categories as c')
            ->join('business_category as bc', 'bc.category_id', '=', 'c.id')
            ->join('businesses as b', 'b.id', '=', 'bc.business_id')
            ->join('business_locations as bl', function ($join) use ($stateId) {
                $join->on('bl.business_id', '=', 'b.id')
                    ->where('bl.state_id', $stateId)
                    ->where('bl.is_primary', true)
                    ->where('bl.is_active', true)
                    ->whereNull('bl.deleted_at');
            })
            ->where('c.is_active', true)
            ->where('b.listing_status', 'listed')
            ->where('b.is_active', true)
            ->whereNotNull('b.published_at')
            ->whereNull('b.deleted_at')
            ->groupBy('c.id', 'c.name', 'c.slug')
            ->orderByDesc(DB::raw('COUNT(DISTINCT b.id)'))
            ->orderBy('c.name')
            ->limit(100)
            ->get([
                'c.id',
                'c.name',
                'c.slug',
                DB::raw('COUNT(DISTINCT b.id) as businessCount'),
            ])
            ->map(function ($row) {
                $row->businessCount = (int) $row->businessCount;
                return $row;
            });
    }

    private function lgaCounts(int $stateId, int $categoryId)
    {
        return DB::table('local_government_areas as lga')
            ->join('business_locations as bl', function ($join) use ($stateId) {
                $join->on('bl.local_government_area_id', '=', 'lga.id')
                    ->where('bl.state_id', $stateId)
                    ->where('bl.is_primary', true)
                    ->where('bl.is_active', true)
                    ->whereNull('bl.deleted_at');
            })
            ->join('businesses as b', 'b.id', '=', 'bl.business_id')
            ->join('business_category as bc', function ($join) use ($categoryId) {
                $join->on('bc.business_id', '=', 'b.id')
                    ->where('bc.category_id', $categoryId);
            })
            ->where('lga.is_active', true)
            ->where('b.listing_status', 'listed')
            ->where('b.is_active', true)
            ->whereNotNull('b.published_at')
            ->whereNull('b.deleted_at')
            ->groupBy('lga.id', 'lga.name', 'lga.slug')
            ->orderByDesc(DB::raw('COUNT(DISTINCT b.id)'))
            ->orderBy('lga.name')
            ->get([
                'lga.id',
                'lga.name',
                'lga.slug',
                DB::raw('COUNT(DISTINCT b.id) as businessCount'),
            ])
            ->map(function ($row) {
                $row->businessCount = (int) $row->businessCount;
                return $row;
            });
    }

    private function paginateBusinesses(Builder $base): array
    {
        $query = clone $base;

        $query
            ->select([
                'b.id',
                'b.name',
                'b.slug',
                'b.short_description',
                'b.verification_status',
                's.name as state_name',
                'lga.name as lga_name',
            ])
            ->selectSub(function ($categoryQuery) {
                $categoryQuery
                    ->from('business_category as bc_primary')
                    ->join(
                        'categories as c_primary',
                        'c_primary.id',
                        '=',
                        'bc_primary.category_id'
                    )
                    ->whereColumn('bc_primary.business_id', 'b.id')
                    ->where('c_primary.is_active', true)
                    ->orderByDesc('bc_primary.is_primary')
                    ->orderBy('bc_primary.sort_order')
                    ->orderBy('c_primary.name')
                    ->limit(1)
                    ->select('c_primary.name');
            }, 'category_name')
            ->selectSub(function ($verificationQuery) {
                $verificationQuery
                    ->from('business_verifications as bv')
                    ->whereColumn('bv.business_id', 'b.id')
                    ->where('bv.status', 'verified')
                    ->where(function ($query) {
                        $query
                            ->whereNull('bv.expires_at')
                            ->orWhere('bv.expires_at', '>', now());
                    })
                    ->selectRaw('COUNT(*)');
            }, 'verified_evidence_count')
            ->selectSub(function ($reviewQuery) {
                $reviewQuery
                    ->from('business_reviews as br_count')
                    ->whereColumn('br_count.business_id', 'b.id')
                    ->where('br_count.status', 'approved')
                    ->selectRaw('COUNT(*)');
            }, 'review_count')
            ->selectSub(function ($reviewQuery) {
                $reviewQuery
                    ->from('business_reviews as br_avg')
                    ->whereColumn('br_avg.business_id', 'b.id')
                    ->where('br_avg.status', 'approved')
                    ->selectRaw('AVG(br_avg.rating)');
            }, 'rating_average');

        $paginator = $query
            ->distinct()
            ->orderBy('b.name')
            ->paginate(24)
            ->withQueryString();

        $businesses = collect($paginator->items())
            ->map(function ($business) {
                $verified =
                    $business->verification_status === 'verified'
                    && (int) $business->verified_evidence_count > 0;

                $reviewCount = (int) $business->review_count;

                return [
                    'name' => $business->name,
                    'slug' => $business->slug,
                    'shortDescription' => $business->short_description,
                    'category' => $business->category_name,
                    'location' => collect([
                        $business->lga_name,
                        $business->state_name,
                    ])->filter()->unique()->implode(', '),
                    'isVerified' => $verified,
                    'listingLabel' => $verified
                        ? 'Verified Business'
                        : 'Listed Business',
                    'reviewCount' => $reviewCount,
                    'ratingAverage' => $reviewCount > 0
                        ? round((float) $business->rating_average, 1)
                        : null,
                    'url' => route(
                        'business.show',
                        ['slug' => $business->slug]
                    ),
                ];
            })
            ->values();

        return [
            $businesses,
            [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'prevUrl' => $paginator->previousPageUrl(),
                'nextUrl' => $paginator->nextPageUrl(),
            ],
        ];
    }

    private function resolveState(string $slug): ?object
    {
        if ($slug === 'abuja') {
            return DB::table('states')
                ->where('code', 'FC')
                ->where('is_active', true)
                ->first();
        }

        return DB::table('states')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    private function statePublicSlug(object $state): string
    {
        return $state->code === 'FC'
            ? 'abuja'
            : $state->slug;
    }
}
