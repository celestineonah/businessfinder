<?php

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
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
