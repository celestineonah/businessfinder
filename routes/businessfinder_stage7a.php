<?php

use App\Http\Controllers\Admin\EngagementModerationController;
use App\Http\Controllers\BusinessEngagementController;
use App\Http\Controllers\OwnerEnquiryController;
use Illuminate\Support\Facades\Route;

Route::post(
    '/business/{slug}/enquiry',
    [BusinessEngagementController::class, 'enquiry']
)
    ->middleware('throttle:5,1')
    ->name('business.enquiry.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post(
        '/business/{slug}/review',
        [BusinessEngagementController::class, 'review']
    )
        ->middleware('throttle:5,1')
        ->name('business.review.store');

    Route::patch(
        '/dashboard/enquiries/{enquiry}',
        [OwnerEnquiryController::class, 'update']
    )->name('dashboard.enquiries.update');
});

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get(
            '/engagement',
            [EngagementModerationController::class, 'index']
        )->name('engagement.index');

        Route::post(
            '/reviews/{review}/approve',
            [EngagementModerationController::class, 'approveReview']
        )->name('reviews.approve');

        Route::post(
            '/reviews/{review}/reject',
            [EngagementModerationController::class, 'rejectReview']
        )->name('reviews.reject');

        Route::post(
            '/enquiries/{enquiry}/spam',
            [EngagementModerationController::class, 'markEnquirySpam']
        )->name('enquiries.spam');
    });
