<?php

use App\Http\Controllers\Admin\ListingModerationController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\OwnerBusinessController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard/businesses', [OwnerBusinessController::class, 'index'])
        ->name('dashboard.businesses.index');
    Route::get('/dashboard/businesses/{business}/edit', [OwnerBusinessController::class, 'edit'])
        ->name('dashboard.businesses.edit');
    Route::patch('/dashboard/businesses/{business}', [OwnerBusinessController::class, 'update'])
        ->name('dashboard.businesses.update');
    Route::post(
        '/dashboard/businesses/{business}/publication-request',
        [OwnerBusinessController::class, 'requestPublication']
    )->name('dashboard.businesses.publication-request');
});

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/listings', [ListingModerationController::class, 'index'])
            ->name('listings.index');
        Route::post('/listings/{publicationRequest}/approve', [ListingModerationController::class, 'approve'])
            ->name('listings.approve');
        Route::post('/listings/{publicationRequest}/reject', [ListingModerationController::class, 'reject'])
            ->name('listings.reject');
    });

Route::get('/locations', [DirectoryController::class, 'locations'])
    ->name('directory.locations');

Route::get('/sitemap.xml', [DirectoryController::class, 'sitemap'])
    ->name('directory.sitemap');

$statePattern =
    '(?!login$|register$|logout$|dashboard$|search$|categories$|locations$|business$|admin$|settings$|email$|forgot-password$|reset-password$|add-business$|sitemap\.xml$)[a-z0-9-]+';

Route::get('/{stateSlug}/{lgaSlug}/{categorySlug}', [DirectoryController::class, 'lgaCategory'])
    ->where([
        'stateSlug' => $statePattern,
        'lgaSlug' => '[a-z0-9-]+',
        'categorySlug' => '[a-z0-9-]+',
    ])
    ->name('directory.lga.category');

Route::get('/{stateSlug}/{categorySlug}', [DirectoryController::class, 'stateCategory'])
    ->where([
        'stateSlug' => $statePattern,
        'categorySlug' => '[a-z0-9-]+',
    ])
    ->name('directory.state.category');

Route::get('/{stateSlug}', [DirectoryController::class, 'state'])
    ->where('stateSlug', $statePattern)
    ->name('directory.state');
