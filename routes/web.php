<?php

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

Route::get('/', function () {
    $states = DB::table('states')
        ->select([
            'name',
            'slug',
            'code',
        ])
        ->orderByRaw(
            "CASE WHEN code = 'FC' THEN 0 ELSE 1 END"
        )
        ->orderBy('name')
        ->get()
        ->map(
            fn ($state) => [
                'name' => $state->name,
                'slug' => $state->slug,
                'code' => $state->code,
            ]
        )
        ->values();

    return Inertia::render(
        'Welcome',
        [
            'states' => $states,
            'businessCount' => DB::table('businesses')->count(),
        ]
    );
})->name('home');

Route::get('/categories', function () {
    $states = DB::table('states')
        ->select([
            'name',
            'slug',
            'code',
        ])
        ->orderByRaw(
            "CASE WHEN code = 'FC' THEN 0 ELSE 1 END"
        )
        ->orderBy('name')
        ->get()
        ->map(
            fn ($state) => [
                'name' => $state->name,
                'slug' => $state->slug,
                'code' => $state->code,
            ]
        )
        ->values();

    return Inertia::render(
        'Categories',
        [
            'states' => $states,
            'businessCount' => DB::table('businesses')->count(),
        ]
    );
})->name('categories');

Route::get('/search', function (Request $request) {
    $query = trim((string) $request->query('q', ''));
    $location = trim((string) $request->query('location', ''));

    $states = DB::table('states')
        ->select(['name', 'slug', 'code'])
        ->orderByRaw("CASE WHEN code = 'FC' THEN 0 ELSE 1 END")
        ->orderBy('name')
        ->get()
        ->map(fn ($state) => [
            'name' => $state->name,
            'slug' => $state->slug,
            'code' => $state->code,
        ])
        ->values();

    $state = null;
    $locationLabel = null;

    if ($location !== '') {
        $state = DB::table('states')
            ->select(['id', 'name', 'slug'])
            ->where('slug', $location)
            ->first();

        $locationLabel = $state?->name;
    }

    $publicBase = DB::table('businesses as b')
        ->leftJoin('business_locations as bl', function ($join) {
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

    $businessCount = (clone $publicBase)->distinct()->count('b.id');

    if ($state !== null) {
        $publicBase->where('bl.state_id', $state->id);
    } elseif ($location !== '') {
        $publicBase->whereRaw('1 = 0');
    }

    if ($query !== '') {
        $like = '%' . $query . '%';

        $publicBase->where(function ($builder) use ($like) {
            $builder
                ->where('b.name', 'like', $like)
                ->orWhere('b.legal_name', 'like', $like)
                ->orWhere('b.short_description', 'like', $like)
                ->orWhere('s.name', 'like', $like)
                ->orWhere('lga.name', 'like', $like)
                ->orWhereExists(function ($categoryQuery) use ($like) {
                    $categoryQuery
                        ->selectRaw('1')
                        ->from('business_category as bc')
                        ->join('categories as c', 'c.id', '=', 'bc.category_id')
                        ->whereColumn('bc.business_id', 'b.id')
                        ->where('c.is_active', true)
                        ->where('c.name', 'like', $like);
                })
                ->orWhereExists(function ($sourceQuery) use ($like) {
                    $sourceQuery
                        ->selectRaw('1')
                        ->from('business_sources as bs')
                        ->whereColumn('bs.business_id', 'b.id')
                        ->where('bs.is_active', true)
                        ->whereNotNull('bs.metadata')
                        ->whereRaw(
                            "JSON_UNQUOTE(JSON_EXTRACT(bs.metadata, '$.category_hint')) like ?",
                            [$like]
                        );
                });
        });
    }

    $publicBase
        ->select([
            'b.id',
            'b.name',
            'b.slug',
            'b.short_description',
            'b.claim_status',
            'b.verification_status',
            's.name as state_name',
            'lga.name as lga_name',
        ])
        ->selectSub(function ($categoryQuery) {
            $categoryQuery
                ->from('business_category as bc')
                ->join('categories as c', 'c.id', '=', 'bc.category_id')
                ->whereColumn('bc.business_id', 'b.id')
                ->where('c.is_active', true)
                ->orderByDesc('bc.is_primary')
                ->orderBy('bc.sort_order')
                ->orderBy('c.name')
                ->limit(1)
                ->select('c.name');
        }, 'category_name')
        ->selectSub(function ($sourceQuery) {
            $sourceQuery
                ->from('business_sources as bs')
                ->whereColumn('bs.business_id', 'b.id')
                ->where('bs.is_active', true)
                ->whereNotNull('bs.metadata')
                ->orderByDesc('bs.confidence_score')
                ->orderBy('bs.id')
                ->limit(1)
                ->selectRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(bs.metadata, '$.category_hint'))"
                );
        }, 'category_hint')
        ->selectSub(function ($verificationQuery) {
            $verificationQuery
                ->from('business_verifications as bv')
                ->whereColumn('bv.business_id', 'b.id')
                ->where('bv.status', 'verified')
                ->where(function ($query) {
                    $query->whereNull('bv.expires_at')
                        ->orWhere('bv.expires_at', '>', now());
                })
                ->selectRaw('COUNT(*)');
        }, 'verified_evidence_count');

    $paginator = $publicBase
        ->orderBy('b.name')
        ->paginate(24)
        ->withQueryString();

    $results = collect($paginator->items())
        ->map(function ($business) {
            $isVerified =
                $business->verification_status === 'verified'
                && (int) $business->verified_evidence_count > 0;

            $location = collect([
                $business->lga_name,
                $business->state_name,
            ])->filter()->unique()->implode(', ');

            $category = $business->category_name;

            if (! $category && $business->category_hint) {
                $category = Str::of((string) $business->category_hint)
                    ->after(':')
                    ->replace('_', ' ')
                    ->title()
                    ->toString();
            }

            return [
                'name' => $business->name,
                'slug' => $business->slug,
                'shortDescription' => $business->short_description,
                'category' => $category,
                'location' => $location !== '' ? $location : null,
                'state' => $business->state_name,
                'lga' => $business->lga_name,
                'claimStatus' => $business->claim_status,
                'verificationStatus' => $business->verification_status,
                'isVerified' => $isVerified,
                'listingLabel' => $isVerified ? 'Verified Business' : 'Listed Business',
            ];
        })
        ->values();

    return Inertia::render('Search', [
        'query' => $query,
        'location' => $location,
        'locationLabel' => $locationLabel,
        'states' => $states,
        'businessCount' => $businessCount,
        'results' => $results,
        'pagination' => [
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'prevUrl' => $paginator->previousPageUrl(),
            'nextUrl' => $paginator->nextPageUrl(),
        ],
    ]);
})->name('search');

Route::get('/add-business', function () {
    $states = DB::table('states')
        ->select([
            'id',
            'name',
            'slug',
            'code',
        ])
        ->orderByRaw(
            "CASE WHEN code = 'FC' THEN 0 ELSE 1 END"
        )
        ->orderBy('name')
        ->get()
        ->map(
            fn ($state) => [
                'id' => $state->id,
                'name' => $state->name,
                'slug' => $state->slug,
                'code' => $state->code,
            ]
        )
        ->values();

    $categories = DB::table('categories')
        ->where('is_active', true)
        ->select([
            'id',
            'name',
            'slug',
        ])
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get()
        ->map(
            fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ]
        )
        ->values();

    return Inertia::render(
        'AddBusiness',
        [
            'states' => $states,
            'categories' => $categories,
            'status' => session('status'),
            'createdBusiness' =>
                session('createdBusiness'),
        ]
    );
})->name('add-business.create');

Route::post('/add-business', function (Request $request) {
    $validated = $request->validate([
        'business_name' => [
            'required',
            'string',
            'max:180',
        ],

        'legal_name' => [
            'nullable',
            'string',
            'max:200',
        ],

        'short_description' => [
            'nullable',
            'string',
            'max:500',
        ],

        'description' => [
            'nullable',
            'string',
            'max:10000',
        ],

        'primary_phone' => [
            'nullable',
            'string',
            'max:40',
        ],

        'whatsapp_phone' => [
            'nullable',
            'string',
            'max:40',
        ],

        'primary_email' => [
            'nullable',
            'email',
            'max:180',
        ],

        'website_url' => [
            'nullable',
            'string',
            'max:500',
        ],

        'category_id' => [
            'nullable',
            'integer',
            'exists:categories,id',
        ],

        'state_id' => [
            'required',
            'integer',
            'exists:states,id',
        ],

        'address_line_1' => [
            'required',
            'string',
            'max:255',
        ],

        'address_line_2' => [
            'nullable',
            'string',
            'max:255',
        ],

        'landmark' => [
            'nullable',
            'string',
            'max:255',
        ],

        'postal_code' => [
            'nullable',
            'string',
            'max:30',
        ],

        'service_area_only' => [
            'boolean',
        ],

        'opening_hours' => [
            'nullable',
            'array',
        ],

        'opening_hours.*' => [
            'nullable',
            'string',
            'max:100',
        ],
    ]);

    $business = DB::transaction(
        function () use (
            $request,
            $validated
        ) {
            $name = trim(
                $validated[
                    'business_name'
                ]
            );

            $normalizedName =
                mb_strtolower(
                    preg_replace(
                        '/\s+/',
                        ' ',
                        $name
                    )
                );

            $baseSlug =
                Str::slug($name);

            if ($baseSlug === '') {
                $baseSlug = 'business';
            }

            $slug = $baseSlug;
            $counter = 2;

            while (
                Business::withTrashed()
                    ->where(
                        'slug',
                        $slug
                    )
                    ->exists()
            ) {
                $slug =
                    $baseSlug
                    . '-'
                    . $counter;

                $counter++;
            }

            $business =
                $request
                    ->user()
                    ->businesses()
                    ->create([
                        'name' =>
                            $name,

                        'normalized_name' =>
                            $normalizedName,

                        'slug' =>
                            $slug,

                        'legal_name' =>
                            $validated[
                                'legal_name'
                            ] ?? null,

                        'primary_phone' =>
                            $validated[
                                'primary_phone'
                            ] ?? null,

                        'whatsapp_phone' =>
                            $validated[
                                'whatsapp_phone'
                            ] ?? null,

                        'primary_email' =>
                            $validated[
                                'primary_email'
                            ] ?? null,

                        'website_url' =>
                            $validated[
                                'website_url'
                            ] ?? null,

                        'short_description' =>
                            $validated[
                                'short_description'
                            ] ?? null,

                        'description' =>
                            $validated[
                                'description'
                            ] ?? null,

                        'listing_status' =>
                            'listed',

                        'claim_status' =>
                            'claimed',

                        'verification_status' =>
                            'unverified',

                        'claimed_at' =>
                            now(),

                        'published_at' =>
                            null,

                        'verified_at' =>
                            null,

                        'is_active' =>
                            true,
                    ]);

            $openingHours =
                collect(
                    $validated[
                        'opening_hours'
                    ] ?? []
                )
                    ->map(
                        fn ($value) =>
                            trim(
                                (string) $value
                            )
                    )
                    ->filter(
                        fn ($value) =>
                            $value !== ''
                    )
                    ->all();

            $location =
                $business
                    ->locations()
                    ->create([
                        'state_id' =>
                            $validated[
                                'state_id'
                            ],

                        'name' =>
                            'Primary Location',

                        'slug' =>
                            'primary',

                        'address_line_1' =>
                            $validated[
                                'address_line_1'
                            ],

                        'address_line_2' =>
                            $validated[
                                'address_line_2'
                            ] ?? null,

                        'landmark' =>
                            $validated[
                                'landmark'
                            ] ?? null,

                        'postal_code' =>
                            $validated[
                                'postal_code'
                            ] ?? null,

                        'phone' =>
                            $validated[
                                'primary_phone'
                            ] ?? null,

                        'whatsapp_phone' =>
                            $validated[
                                'whatsapp_phone'
                            ] ?? null,

                        'email' =>
                            $validated[
                                'primary_email'
                            ] ?? null,

                        'opening_hours' =>
                            $openingHours !== []
                                ? $openingHours
                                : null,

                        'is_primary' =>
                            true,

                        'service_area_only' =>
                            (bool) (
                                $validated[
                                    'service_area_only'
                                ] ?? false
                            ),

                        'is_active' =>
                            true,
                    ]);

            if (
                ! empty(
                    $validated[
                        'category_id'
                    ]
                )
            ) {
                $business
                    ->categories()
                    ->attach(
                        $validated[
                            'category_id'
                        ],
                        [
                            'is_primary' =>
                                true,

                            'sort_order' =>
                                0,
                        ]
                    );
            }

            DB::table(
                'business_sources'
            )->insert([
                'business_id' =>
                    $business->id,

                'business_location_id' =>
                    $location->id,

                'source_type' =>
                    'owner',

                'source_name' =>
                    'Owner submission',

                'source_url' =>
                    null,

                'external_id' =>
                    null,

                'confidence_score' =>
                    null,

                'metadata' =>
                    json_encode([
                        'submitted_by_user_id' =>
                            $request
                                ->user()
                                ->id,
                    ]),

                'first_seen_at' =>
                    now(),

                'last_checked_at' =>
                    null,

                'is_active' =>
                    true,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);

            return $business;
        }
    );

    return redirect()
        ->route(
            'add-business.create'
        )
        ->with([
            'status' =>
                'Your business draft was created successfully. It is not public or verified yet.',

            'createdBusiness' => [
                'name' =>
                    $business->name,

                'slug' =>
                    $business->slug,
            ],
        ]);
})
    ->middleware('auth')
    ->name('add-business.store');

Route::get('/business/{slug}', function (string $slug) {
    $business = Business::query()
        ->with([
            'primaryLocation.state',
            'primaryLocation.city',
            'primaryLocation.localGovernmentArea',
            'primaryLocation.area',
            'categories',
            'sources' => fn ($query) => $query
                ->where('is_active', true),
            'verifications' => fn ($query) => $query
                ->where('status', 'verified')
                ->where(function ($query) {
                    $query
                        ->whereNull('expires_at')
                        ->orWhere(
                            'expires_at',
                            '>',
                            now()
                        );
                }),
        ])
        ->where('slug', $slug)
        ->where('listing_status', 'listed')
        ->where('is_active', true)
        ->whereNotNull('published_at')
        ->firstOrFail();

    $location =
        $business->primaryLocation;

    $categories =
        $business->categories
            ->sort(function ($a, $b) {
                $aPrimary =
                    (bool) $a->pivot->is_primary;

                $bPrimary =
                    (bool) $b->pivot->is_primary;

                if ($aPrimary !== $bPrimary) {
                    return $aPrimary
                        ? -1
                        : 1;
                }

                $sort =
                    ((int) $a->pivot->sort_order)
                    <=>
                    ((int) $b->pivot->sort_order);

                if ($sort !== 0) {
                    return $sort;
                }

                return strcasecmp(
                    $a->name,
                    $b->name
                );
            })
            ->values()
            ->map(
                fn ($category) => [
                    'name' =>
                        $category->name,

                    'slug' =>
                        $category->slug,

                    'singularName' =>
                        $category->singular_name,

                    'isPrimary' =>
                        (bool) $category
                            ->pivot
                            ->is_primary,
                ]
            );

    $openingHours =
        $location?->opening_hours;

    if (is_string($openingHours)) {
        $decoded =
            json_decode(
                $openingHours,
                true
            );

        $openingHours =
            is_array($decoded)
                ? $decoded
                : null;
    }

    if (! is_array($openingHours)) {
        $openingHours = null;
    }

    $phone =
        $location?->phone
        ?: $business->primary_phone;

    $whatsapp =
        $location?->whatsapp_phone
        ?: $business->whatsapp_phone;

    $email =
        $location?->email
        ?: $business->primary_email;

    $website =
        trim(
            (string) $business->website_url
        );

    $websiteUrl = null;

    if ($website !== '') {
        $websiteUrl =
            preg_match(
                '~^https?://~i',
                $website
            )
                ? $website
                : 'https://' . $website;
    }

    $whatsappUrl = null;

    if ($whatsapp) {
        $digits =
            preg_replace(
                '/\D+/',
                '',
                $whatsapp
            );

        if (
            is_string($digits)
            &&
            $digits !== ''
        ) {
            if (
                strlen($digits) === 11
                &&
                str_starts_with(
                    $digits,
                    '0'
                )
            ) {
                $digits =
                    '234'
                    . substr(
                        $digits,
                        1
                    );
            }

            $whatsappUrl =
                'https://wa.me/'
                . $digits;
        }
    }

    $isVerified =
        $business->verification_status
            === 'verified'
        &&
        $business->verifications
            ->isNotEmpty();

    $verificationTypes =
        $business->verifications
            ->pluck(
                'verification_type'
            )
            ->unique()
            ->sort()
            ->values();

    $sourceNames =
        $business->sources
            ->pluck('source_name')
            ->filter()
            ->unique()
            ->sort()
            ->values();

    return Inertia::render(
        'BusinessShow',
        [
            'business' => [
                'name' =>
                    $business->name,

                'slug' =>
                    $business->slug,

                'legalName' =>
                    $business->legal_name,

                'shortDescription' =>
                    $business->short_description,

                'description' =>
                    $business->description,

                'phone' =>
                    $phone,

                'whatsappPhone' =>
                    $whatsapp,

                'whatsappUrl' =>
                    $whatsappUrl,

                'email' =>
                    $email,

                'websiteUrl' =>
                    $websiteUrl,

                'listingLabel' =>
                    $isVerified
                        ? 'Verified Business'
                        : 'Listed Business',

                'isVerified' =>
                    $isVerified,

                'claimStatus' =>
                    $business->claim_status,

                'categories' =>
                    $categories,

                'verificationTypes' =>
                    $verificationTypes,

                'sourceCount' =>
                    $business->sources->count(),

                'sourceNames' =>
                    $sourceNames,

                'publishedAt' =>
                    $business
                        ->published_at
                        ?->toDateString(),

                'lastCheckedAt' =>
                    $business
                        ->last_checked_at
                        ?->toDateString(),

                'location' =>
                    $location
                        ? [
                            'name' =>
                                $location->name,

                            'addressLine1' =>
                                $location->address_line_1,

                            'addressLine2' =>
                                $location->address_line_2,

                            'landmark' =>
                                $location->landmark,

                            'postalCode' =>
                                $location->postal_code,

                            'latitude' =>
                                $location->latitude,

                            'longitude' =>
                                $location->longitude,

                            'serviceAreaOnly' =>
                                (bool) $location
                                    ->service_area_only,

                            'state' =>
                                $location->state
                                    ? [
                                        'name' =>
                                            $location
                                                ->state
                                                ->name,

                                        'slug' =>
                                            $location
                                                ->state
                                                ->slug,
                                    ]
                                    : null,

                            'city' =>
                                $location->city
                                    ? [
                                        'name' =>
                                            $location
                                                ->city
                                                ->name,

                                        'slug' =>
                                            $location
                                                ->city
                                                ->slug,
                                    ]
                                    : null,

                            'lga' =>
                                $location
                                    ->localGovernmentArea
                                    ? [
                                        'name' =>
                                            $location
                                                ->localGovernmentArea
                                                ->name,

                                        'slug' =>
                                            $location
                                                ->localGovernmentArea
                                                ->slug,
                                    ]
                                    : null,

                            'area' =>
                                $location->area
                                    ? [
                                        'name' =>
                                            $location
                                                ->area
                                                ->name,

                                        'slug' =>
                                            $location
                                                ->area
                                                ->slug,
                                    ]
                                    : null,

                            'openingHours' =>
                                $openingHours,
                        ]
                        : null,
            ],

            'canonicalUrl' =>
                route(
                    'business.show',
                    [
                        'slug' =>
                            $business->slug,
                    ]
                ),
        ]
    );
})->name('business.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/business/{slug}/claim', [\App\Http\Controllers\BusinessClaimController::class, 'create'])
        ->name('business.claim.create');
    Route::post('/business/{slug}/claim', [\App\Http\Controllers\BusinessClaimController::class, 'store'])
        ->name('business.claim.store');
});

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/claims', [\App\Http\Controllers\Admin\BusinessClaimReviewController::class, 'index'])
            ->name('claims.index');
        Route::post('/claims/{claim}/approve', [\App\Http\Controllers\Admin\BusinessClaimReviewController::class, 'approve'])
            ->name('claims.approve');
        Route::post('/claims/{claim}/reject', [\App\Http\Controllers\Admin\BusinessClaimReviewController::class, 'reject'])
            ->name('claims.reject');
        Route::post('/verifications/{verification}/verify', [\App\Http\Controllers\Admin\BusinessClaimReviewController::class, 'verify'])
            ->name('verifications.verify');
        Route::post('/verifications/{verification}/fail', [\App\Http\Controllers\Admin\BusinessClaimReviewController::class, 'fail'])
            ->name('verifications.fail');
    });
Route::middleware([
    'auth',
    'verified',
])->group(function () {
    Route::get('dashboard', function (Request $request) {
        $businesses = $request
            ->user()
            ->businesses()
            ->with([
                'primaryLocation.state',
                'primaryLocation.city',
                'categories',
            ])
            ->latest('updated_at')
            ->get();

        $published = $businesses
            ->filter(
                fn ($business) =>
                    $business->listing_status === 'listed'
                    && $business->is_active
                    && $business->published_at !== null
            )
            ->count();

        $dashboardBusinesses = $businesses
            ->map(function ($business) {
                $location =
                    $business->primaryLocation;

                $category =
                    $business->categories
                        ->sortByDesc(
                            fn ($category) =>
                                (bool) $category
                                    ->pivot
                                    ->is_primary
                        )
                        ->first();

                $locationParts = collect([
                    $location?->city?->name,
                    $location?->state?->name,
                ])
                    ->filter()
                    ->unique()
                    ->values();

                $isPublished =
                    $business->listing_status === 'listed'
                    && $business->is_active
                    && $business->published_at !== null;

                return [
                    'name' =>
                        $business->name,

                    'slug' =>
                        $business->slug,

                    'category' =>
                        $category?->name,

                    'location' =>
                        $locationParts->implode(', '),

                    'listingStatus' =>
                        $business->listing_status,

                    'claimStatus' =>
                        $business->claim_status,

                    'verificationStatus' =>
                        $business->verification_status,

                    'isPublished' =>
                        $isPublished,

                    'updatedAt' =>
                        $business
                            ->updated_at
                            ?->toDateString(),

                    'publicUrl' =>
                        $isPublished
                            ? route(
                                'business.show',
                                [
                                    'slug' =>
                                        $business->slug,
                                ]
                            )
                            : null,
                ];
            })
            ->values();

        return Inertia::render(
            'Dashboard',
            [
                'metrics' => [
                    'totalBusinesses' =>
                        $businesses->count(),

                    'publishedListings' =>
                        $published,

                    'draftListings' =>
                        $businesses
                            ->whereNull(
                                'published_at'
                            )
                            ->count(),

                    'verifiedBusinesses' =>
                        $businesses
                            ->where(
                                'verification_status',
                                'verified'
                            )
                            ->count(),

                    'pendingVerification' =>
                        $businesses
                            ->where(
                                'verification_status',
                                'pending'
                            )
                            ->count(),
                ],

                'businesses' =>
                    $dashboardBusinesses,
            ]
        );
    })->name('dashboard');
});

require __DIR__.'/settings.php';

require __DIR__.'/businessfinder_stage7a.php';

require __DIR__.'/businessfinder_stage7b.php';
