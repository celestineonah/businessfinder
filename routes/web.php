<?php

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
