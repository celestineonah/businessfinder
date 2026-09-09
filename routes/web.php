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
    $query = trim(
        (string) $request->query(
            'q',
            ''
        )
    );

    $location = trim(
        (string) $request->query(
            'location',
            ''
        )
    );

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

    $locationLabel = null;

    if ($location !== '') {
        $locationLabel = DB::table('states')
            ->where(
                'slug',
                $location
            )
            ->value('name');
    }

    return Inertia::render(
        'Search',
        [
            'query' => $query,
            'location' => $location,
            'locationLabel' => $locationLabel,
            'states' => $states,
            'businessCount' => DB::table(
                'businesses'
            )->count(),
            'results' => [],
        ]
    );
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

Route::middleware([
    'auth',
    'verified',
])->group(function () {
    Route::inertia(
        'dashboard',
        'Dashboard'
    )->name('dashboard');
});

require __DIR__.'/settings.php';
