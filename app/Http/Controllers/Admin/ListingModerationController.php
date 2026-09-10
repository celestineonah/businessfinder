<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BusinessPublicationReadiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ListingModerationController extends Controller
{
    public function __construct(
        private readonly BusinessPublicationReadiness $readiness
    ) {
    }

    public function index(Request $request): Response
    {
        $this->ensureAdmin($request);

        $pending = DB::table('business_publication_requests as pr')
            ->join('businesses as b', 'b.id', '=', 'pr.business_id')
            ->join('users as u', 'u.id', '=', 'pr.user_id')
            ->where('pr.status', 'pending')
            ->whereNull('b.deleted_at')
            ->orderBy('pr.requested_at')
            ->limit(100)
            ->get([
                'pr.id',
                'pr.requested_at',
                'b.id as business_id',
                'b.name as business_name',
                'b.slug as business_slug',
                'b.verification_status',
                'b.published_at',
                'u.id as owner_id',
                'u.name as owner_name',
                'u.email as owner_email',
            ])
            ->map(function ($row) {
                $profile = DB::table('businesses as b')
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
                    ->where('b.id', $row->business_id)
                    ->select([
                        'b.legal_name',
                        'b.cac_number',
                        'b.primary_phone',
                        'b.whatsapp_phone',
                        'b.primary_email',
                        'b.website_url',
                        'b.short_description',
                        'b.description',
                        'bl.address_line_1',
                        'bl.address_line_2',
                        'bl.landmark',
                        'bl.service_area_only',
                        's.name as state_name',
                        'lga.name as lga_name',
                    ])
                    ->first();

                $category = DB::table('business_category as bc')
                    ->join('categories as c', 'c.id', '=', 'bc.category_id')
                    ->where('bc.business_id', $row->business_id)
                    ->orderByDesc('bc.is_primary')
                    ->orderBy('bc.sort_order')
                    ->value('c.name');

                return [
                    'id' => $row->id,
                    'requestedAt' => $row->requested_at,
                    'business' => [
                        'id' => $row->business_id,
                        'name' => $row->business_name,
                        'slug' => $row->business_slug,
                        'verificationStatus' => $row->verification_status,
                        'isPublished' => $row->published_at !== null,
                        'category' => $category,
                        'legalName' => $profile?->legal_name,
                        'cacNumber' => $profile?->cac_number,
                        'phone' => $profile?->primary_phone,
                        'whatsappPhone' => $profile?->whatsapp_phone,
                        'email' => $profile?->primary_email,
                        'websiteUrl' => $profile?->website_url,
                        'shortDescription' => $profile?->short_description,
                        'description' => $profile?->description,
                        'location' => collect([
                            $profile?->address_line_1,
                            $profile?->address_line_2,
                            $profile?->landmark,
                            $profile?->lga_name,
                            $profile?->state_name,
                        ])->filter()->unique()->implode(', '),
                        'serviceAreaOnly' => (bool) ($profile?->service_area_only ?? false),
                    ],
                    'owner' => [
                        'id' => $row->owner_id,
                        'name' => $row->owner_name,
                        'email' => $row->owner_email,
                    ],
                    'readiness' => $this->readiness->evaluate($row->business_id),
                ];
            })
            ->values();

        $recent = DB::table('business_publication_requests as pr')
            ->join('businesses as b', 'b.id', '=', 'pr.business_id')
            ->leftJoin('users as reviewer', 'reviewer.id', '=', 'pr.reviewed_by_user_id')
            ->whereIn('pr.status', ['approved', 'rejected'])
            ->orderByDesc('pr.reviewed_at')
            ->limit(30)
            ->get([
                'pr.id',
                'pr.status',
                'pr.review_notes',
                'pr.reviewed_at',
                'b.name as business_name',
                'b.slug as business_slug',
                'reviewer.name as reviewer_name',
            ])
            ->map(fn ($row) => [
                'id' => $row->id,
                'status' => $row->status,
                'reviewNotes' => $row->review_notes,
                'reviewedAt' => $row->reviewed_at,
                'businessName' => $row->business_name,
                'businessSlug' => $row->business_slug,
                'reviewerName' => $row->reviewer_name,
            ])
            ->values();

        return Inertia::render('AdminListings', [
            'metrics' => [
                'pending' => DB::table('business_publication_requests')
                    ->where('status', 'pending')
                    ->count(),
                'approved' => DB::table('business_publication_requests')
                    ->where('status', 'approved')
                    ->count(),
                'rejected' => DB::table('business_publication_requests')
                    ->where('status', 'rejected')
                    ->count(),
                'ownerPublished' => DB::table('businesses')
                    ->whereNotNull('owner_user_id')
                    ->whereNotNull('published_at')
                    ->where('listing_status', 'listed')
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->count(),
            ],
            'pendingRequests' => $pending,
            'recentRequests' => $recent,
            'status' => session('status'),
        ]);
    }

    public function approve(
        Request $request,
        int $publicationRequest
    ): RedirectResponse {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use (
            $request,
            $publicationRequest,
            $validated
        ) {
            $row = DB::table('business_publication_requests')
                ->where('id', $publicationRequest)
                ->lockForUpdate()
                ->first();

            abort_if($row === null, 404);

            if ($row->status !== 'pending') {
                return;
            }

            $business = DB::table('businesses')
                ->where('id', $row->business_id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            abort_if($business === null, 404);

            if ((int) $business->owner_user_id !== (int) $row->user_id) {
                abort(
                    409,
                    'Publication request owner no longer matches the business owner.'
                );
            }

            $readiness = $this->readiness->evaluate((int) $business->id);

            if (! $readiness['ready']) {
                abort(
                    409,
                    'Business is no longer publication-ready: '
                    . implode(', ', $readiness['missing'])
                );
            }

            $now = now();

            DB::table('business_publication_requests')
                ->where('id', $row->id)
                ->update([
                    'status' => 'approved',
                    'reviewed_by_user_id' => $request->user()->id,
                    'review_notes' => $validated['review_notes'] ?? null,
                    'reviewed_at' => $now,
                    'updated_at' => $now,
                ]);

            DB::table('businesses')
                ->where('id', $business->id)
                ->update([
                    'listing_status' => 'listed',
                    'is_active' => true,
                    'published_at' => $business->published_at ?? $now,
                    'updated_at' => $now,
                ]);
        }, 3);

        return back()->with(
            'status',
            'Listing publication approved. This does not grant a Verified badge.'
        );
    }

    public function reject(
        Request $request,
        int $publicationRequest
    ): RedirectResponse {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use (
            $request,
            $publicationRequest,
            $validated
        ) {
            $row = DB::table('business_publication_requests')
                ->where('id', $publicationRequest)
                ->lockForUpdate()
                ->first();

            abort_if($row === null, 404);

            if ($row->status !== 'pending') {
                return;
            }

            $now = now();

            DB::table('business_publication_requests')
                ->where('id', $row->id)
                ->update([
                    'status' => 'rejected',
                    'reviewed_by_user_id' => $request->user()->id,
                    'review_notes' => $validated['review_notes'],
                    'reviewed_at' => $now,
                    'updated_at' => $now,
                ]);
        }, 3);

        return back()->with(
            'status',
            'Listing publication request rejected. The owner can correct the profile and request review again.'
        );
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless((bool) ($request->user()?->is_admin ?? false), 403);
    }
}
