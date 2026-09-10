<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EngagementModerationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureAdmin($request);

        $pendingReviews = DB::table('business_reviews as br')
            ->join('businesses as b', 'b.id', '=', 'br.business_id')
            ->join('users as u', 'u.id', '=', 'br.user_id')
            ->where('br.status', 'pending')
            ->orderBy('br.created_at')
            ->limit(100)
            ->get([
                'br.id',
                'br.rating',
                'br.title',
                'br.body',
                'br.created_at',
                'b.name as business_name',
                'b.slug as business_slug',
                'u.name as reviewer_name',
                'u.email as reviewer_email',
            ])
            ->map(fn ($review) => [
                'id' => (int) $review->id,
                'rating' => (int) $review->rating,
                'title' => $review->title,
                'body' => $review->body,
                'createdAt' => $review->created_at,
                'business' => [
                    'name' => $review->business_name,
                    'slug' => $review->business_slug,
                ],
                'reviewer' => [
                    'name' => $review->reviewer_name,
                    'email' => $review->reviewer_email,
                ],
            ])
            ->values();

        $enquiries = DB::table('business_enquiries as be')
            ->join('businesses as b', 'b.id', '=', 'be.business_id')
            ->leftJoin('users as u', 'u.id', '=', 'be.user_id')
            ->orderByDesc('be.created_at')
            ->limit(100)
            ->get([
                'be.id',
                'be.request_type',
                'be.contact_name',
                'be.contact_email',
                'be.contact_phone',
                'be.preferred_channel',
                'be.message',
                'be.status',
                'be.delivery_status',
                'be.admin_notes',
                'be.created_at',
                'b.name as business_name',
                'b.slug as business_slug',
                'b.owner_user_id',
                'u.name as account_name',
            ])
            ->map(fn ($lead) => [
                'id' => (int) $lead->id,
                'requestType' => $lead->request_type,
                'contactName' => $lead->contact_name,
                'contactEmail' => $lead->contact_email,
                'contactPhone' => $lead->contact_phone,
                'preferredChannel' => $lead->preferred_channel,
                'message' => $lead->message,
                'status' => $lead->status,
                'deliveryStatus' => $lead->delivery_status,
                'adminNotes' => $lead->admin_notes,
                'createdAt' => $lead->created_at,
                'accountName' => $lead->account_name,
                'business' => [
                    'name' => $lead->business_name,
                    'slug' => $lead->business_slug,
                    'claimed' => $lead->owner_user_id !== null,
                ],
            ])
            ->values();

        $metrics = [
            'pendingReviews' => DB::table('business_reviews')
                ->where('status', 'pending')
                ->count(),
            'approvedReviews' => DB::table('business_reviews')
                ->where('status', 'approved')
                ->count(),
            'rejectedReviews' => DB::table('business_reviews')
                ->where('status', 'rejected')
                ->count(),
            'newEnquiries' => DB::table('business_enquiries')
                ->where('status', 'new')
                ->count(),
            'totalEnquiries' => DB::table('business_enquiries')
                ->where('status', '<>', 'spam')
                ->count(),
            'unassignedEnquiries' => DB::table('business_enquiries as be')
                ->join('businesses as b', 'b.id', '=', 'be.business_id')
                ->whereNull('b.owner_user_id')
                ->where('be.status', '<>', 'spam')
                ->count(),
        ];

        return Inertia::render('AdminEngagement', [
            'metrics' => $metrics,
            'pendingReviews' => $pendingReviews,
            'enquiries' => $enquiries,
            'status' => session('status'),
        ]);
    }

    public function approveReview(
        Request $request,
        int $review
    ): RedirectResponse {
        $this->ensureAdmin($request);

        $approved = DB::transaction(function () use ($request, $review): bool {
            $row = DB::table('business_reviews')
                ->where('id', $review)
                ->lockForUpdate()
                ->first();

            abort_if($row === null, 404);

            if ($row->status !== 'pending') {
                return false;
            }

            $business = DB::table('businesses')
                ->where('id', $row->business_id)
                ->lockForUpdate()
                ->first(['id', 'owner_user_id']);

            abort_if($business === null, 404);

            $now = now();

            if (
                $business->owner_user_id !== null
                && (int) $business->owner_user_id === (int) $row->user_id
            ) {
                DB::table('business_reviews')
                    ->where('id', $row->id)
                    ->update([
                        'status' => 'rejected',
                        'moderated_by_user_id' => $request->user()->id,
                        'moderation_notes' => 'Business owners cannot publish reviews of their own listing.',
                        'moderated_at' => $now,
                        'published_at' => null,
                        'updated_at' => $now,
                    ]);

                return false;
            }

            DB::table('business_reviews')
                ->where('id', $row->id)
                ->update([
                    'status' => 'approved',
                    'moderated_by_user_id' => $request->user()->id,
                    'moderation_notes' => null,
                    'moderated_at' => $now,
                    'published_at' => $now,
                    'updated_at' => $now,
                ]);

            return true;
        }, 3);

        return back()->with(
            'status',
            $approved
                ? 'Review approved and published.'
                : 'Review was not published. It was already processed or conflicts with current business ownership.'
        );
    }

    public function rejectReview(
        Request $request,
        int $review
    ): RedirectResponse {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'moderation_notes' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $review, $validated) {
            $row = DB::table('business_reviews')
                ->where('id', $review)
                ->lockForUpdate()
                ->first();

            abort_if($row === null, 404);

            if ($row->status !== 'pending') {
                return;
            }

            $now = now();

            DB::table('business_reviews')
                ->where('id', $row->id)
                ->update([
                    'status' => 'rejected',
                    'moderated_by_user_id' => $request->user()->id,
                    'moderation_notes' => trim($validated['moderation_notes']),
                    'moderated_at' => $now,
                    'published_at' => null,
                    'updated_at' => $now,
                ]);
        }, 3);

        return back()->with(
            'status',
            'Review rejected. It remains hidden from the public listing.'
        );
    }

    public function markEnquirySpam(
        Request $request,
        int $enquiry
    ): RedirectResponse {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $exists = DB::table('business_enquiries')
            ->where('id', $enquiry)
            ->exists();

        abort_unless($exists, 404);

        DB::table('business_enquiries')
            ->where('id', $enquiry)
            ->update([
                'status' => 'spam',
                'admin_notes' => $validated['admin_notes'] ?? null,
                'updated_at' => now(),
            ]);

        return back()->with(
            'status',
            'Enquiry marked as spam.'
        );
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(
            (bool) ($request->user()?->is_admin ?? false),
            403
        );
    }
}
