<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $routeName = $request->route()?->getName();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'status' => $request->session()->get('status'),
                'enquiryWhatsAppUrl' => $request->session()->get('enquiryWhatsAppUrl'),
            ],
            'businessEngagement' => $routeName === 'business.show'
                ? $this->businessEngagement($request)
                : null,
            'ownerActivity' => $routeName === 'dashboard'
                ? $this->ownerActivity($request)
                : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state')
                || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function businessEngagement(Request $request): array
    {
        if (
            ! Schema::hasTable('business_reviews')
            || ! Schema::hasTable('business_enquiries')
        ) {
            return $this->emptyBusinessEngagement();
        }

        $slug = (string) ($request->route('slug') ?? '');

        $business = DB::table('businesses')
            ->where('slug', $slug)
            ->select([
                'id',
                'owner_user_id',
                'claim_status',
            ])
            ->first();

        if ($business === null) {
            return $this->emptyBusinessEngagement();
        }

        $reviewBase = DB::table('business_reviews')
            ->where('business_id', $business->id)
            ->where('status', 'approved')
            ->whereNotNull('published_at');

        if ($business->owner_user_id !== null) {
            $reviewBase->where(
                'user_id',
                '<>',
                $business->owner_user_id
            );
        }

        $reviewCount = (clone $reviewBase)->count();
        $averageRating = $reviewCount > 0
            ? round((float) (clone $reviewBase)->avg('rating'), 1)
            : null;

        $distributionRows = (clone $reviewBase)
            ->select('rating', DB::raw('COUNT(*) as total'))
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $ratingDistribution = collect([5, 4, 3, 2, 1])
            ->map(function (int $rating) use ($distributionRows, $reviewCount) {
                $count = (int) ($distributionRows[$rating] ?? 0);

                return [
                    'rating' => $rating,
                    'count' => $count,
                    'percent' => $reviewCount > 0
                        ? (int) round(($count / $reviewCount) * 100)
                        : 0,
                ];
            })
            ->values();

        $reviews = DB::table('business_reviews as br')
            ->join('users as u', 'u.id', '=', 'br.user_id')
            ->where('br.business_id', $business->id)
            ->where('br.status', 'approved')
            ->whereNotNull('br.published_at')
            ->when(
                $business->owner_user_id !== null,
                fn ($query) => $query->where(
                    'br.user_id',
                    '<>',
                    $business->owner_user_id
                )
            )
            ->orderByDesc('br.published_at')
            ->orderByDesc('br.id')
            ->limit(12)
            ->get([
                'br.id',
                'br.rating',
                'br.title',
                'br.body',
                'br.published_at',
                'u.name as reviewer_name',
            ])
            ->map(fn ($review) => [
                'id' => (int) $review->id,
                'rating' => (int) $review->rating,
                'title' => $review->title,
                'body' => $review->body,
                'publishedAt' => $review->published_at,
                'reviewerName' => $this->publicReviewerName(
                    (string) $review->reviewer_name
                ),
            ])
            ->values();

        $user = $request->user();
        $isOwner = $user !== null
            && (int) $business->owner_user_id === (int) $user->id;

        $userReview = null;

        if ($user !== null) {
            $row = DB::table('business_reviews')
                ->where('business_id', $business->id)
                ->where('user_id', $user->id)
                ->first([
                    'rating',
                    'title',
                    'body',
                    'status',
                    'moderation_notes',
                    'updated_at',
                ]);

            if ($row !== null) {
                $userReview = [
                    'rating' => (int) $row->rating,
                    'title' => $row->title,
                    'body' => $row->body,
                    'status' => $row->status,
                    'moderationNotes' => $row->moderation_notes,
                    'updatedAt' => $row->updated_at,
                ];
            }
        }

        $canReview = $user !== null
            && $user->hasVerifiedEmail()
            && ! $isOwner;

        $reviewReason = null;

        if ($user === null) {
            $reviewReason = 'Sign in to submit a review.';
        } elseif (! $user->hasVerifiedEmail()) {
            $reviewReason = 'Verify your email before submitting a review.';
        } elseif ($isOwner) {
            $reviewReason = 'Business owners cannot review their own listing.';
        }

        return [
            'summary' => [
                'count' => $reviewCount,
                'average' => $averageRating,
                'distribution' => $ratingDistribution,
            ],
            'reviews' => $reviews,
            'userReview' => $userReview,
            'canReview' => $canReview,
            'reviewReason' => $reviewReason,
            'isOwner' => $isOwner,
            'managedEnquiries' => $business->claim_status === 'claimed'
                && $business->owner_user_id !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ownerActivity(Request $request): array
    {
        if (
            $request->user() === null
            || ! Schema::hasTable('business_reviews')
            || ! Schema::hasTable('business_enquiries')
        ) {
            return $this->emptyOwnerActivity();
        }

        $businessIds = DB::table('businesses')
            ->where('owner_user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->pluck('id');

        if ($businessIds->isEmpty()) {
            return $this->emptyOwnerActivity();
        }

        $leadBase = DB::table('business_enquiries')
            ->whereIn('business_id', $businessIds)
            ->where('status', '<>', 'spam');

        $reviewBase = DB::table('business_reviews')
            ->whereIn('business_id', $businessIds)
            ->where('user_id', '<>', $request->user()->id);

        $approvedReviewBase = (clone $reviewBase)
            ->where('status', 'approved')
            ->whereNotNull('published_at');

        $approvedReviews = (clone $approvedReviewBase)->count();
        $averageRating = $approvedReviews > 0
            ? round((float) (clone $approvedReviewBase)->avg('rating'), 1)
            : null;

        $metrics = [
            'newLeads' => (clone $leadBase)
                ->where('status', 'new')
                ->count(),
            'totalLeads' => (clone $leadBase)->count(),
            'quoteRequests' => (clone $leadBase)
                ->where('request_type', 'quote')
                ->count(),
            'leadsLast30Days' => (clone $leadBase)
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
            'pendingReviews' => (clone $reviewBase)
                ->where('status', 'pending')
                ->count(),
            'approvedReviews' => $approvedReviews,
            'averageRating' => $averageRating,
        ];

        $recentLeads = DB::table('business_enquiries as be')
            ->join('businesses as b', 'b.id', '=', 'be.business_id')
            ->whereIn('be.business_id', $businessIds)
            ->where('be.status', '<>', 'spam')
            ->orderByDesc('be.created_at')
            ->limit(12)
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
                'be.created_at',
                'b.name as business_name',
                'b.slug as business_slug',
                'b.listing_status',
                'b.is_active',
                'b.published_at',
            ])
            ->map(function ($lead) {
                $public = $lead->listing_status === 'listed'
                    && (bool) $lead->is_active
                    && $lead->published_at !== null;

                return [
                    'id' => (int) $lead->id,
                    'requestType' => $lead->request_type,
                    'contactName' => $lead->contact_name,
                    'contactEmail' => $lead->contact_email,
                    'contactPhone' => $lead->contact_phone,
                    'preferredChannel' => $lead->preferred_channel,
                    'message' => $lead->message,
                    'status' => $lead->status,
                    'deliveryStatus' => $lead->delivery_status,
                    'createdAt' => $lead->created_at,
                    'replyWhatsappUrl' => $this->whatsappUrl(
                        $lead->contact_phone
                    ),
                    'business' => [
                        'name' => $lead->business_name,
                        'slug' => $lead->business_slug,
                        'publicUrl' => $public
                            ? route('business.show', [
                                'slug' => $lead->business_slug,
                            ])
                            : null,
                    ],
                ];
            })
            ->values();

        $recentReviews = DB::table('business_reviews as br')
            ->join('businesses as b', 'b.id', '=', 'br.business_id')
            ->join('users as u', 'u.id', '=', 'br.user_id')
            ->whereIn('br.business_id', $businessIds)
            ->where('br.user_id', '<>', $request->user()->id)
            ->orderByDesc('br.updated_at')
            ->limit(12)
            ->get([
                'br.id',
                'br.rating',
                'br.title',
                'br.body',
                'br.status',
                'br.published_at',
                'br.updated_at',
                'b.name as business_name',
                'b.slug as business_slug',
                'u.name as reviewer_name',
            ])
            ->map(fn ($review) => [
                'id' => (int) $review->id,
                'rating' => (int) $review->rating,
                'title' => $review->title,
                'body' => $review->body,
                'status' => $review->status,
                'publishedAt' => $review->published_at,
                'updatedAt' => $review->updated_at,
                'reviewerName' => $this->publicReviewerName(
                    (string) $review->reviewer_name
                ),
                'business' => [
                    'name' => $review->business_name,
                    'slug' => $review->business_slug,
                ],
            ])
            ->values();

        return [
            'metrics' => $metrics,
            'recentLeads' => $recentLeads,
            'recentReviews' => $recentReviews,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyBusinessEngagement(): array
    {
        return [
            'summary' => [
                'count' => 0,
                'average' => null,
                'distribution' => collect([5, 4, 3, 2, 1])
                    ->map(fn (int $rating) => [
                        'rating' => $rating,
                        'count' => 0,
                        'percent' => 0,
                    ])
                    ->values(),
            ],
            'reviews' => [],
            'userReview' => null,
            'canReview' => false,
            'reviewReason' => null,
            'isOwner' => false,
            'managedEnquiries' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyOwnerActivity(): array
    {
        return [
            'metrics' => [
                'newLeads' => 0,
                'totalLeads' => 0,
                'quoteRequests' => 0,
                'leadsLast30Days' => 0,
                'pendingReviews' => 0,
                'approvedReviews' => 0,
                'averageRating' => null,
            ],
            'recentLeads' => [],
            'recentReviews' => [],
        ];
    }

    private function publicReviewerName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_values(array_filter($parts));

        if ($parts === []) {
            return 'BusinessFinder user';
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[0] . ' ' . mb_strtoupper(
            mb_substr($parts[count($parts) - 1], 0, 1)
        ) . '.';
    }

    private function whatsappUrl(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = '234' . substr($digits, 1);
        }

        return 'https://wa.me/' . $digits;
    }
}
