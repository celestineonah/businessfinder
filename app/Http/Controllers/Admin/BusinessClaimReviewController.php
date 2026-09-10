<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BusinessClaimReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureAdmin($request);

        $pendingClaims = DB::table('business_claims as bc')
            ->join('businesses as b', 'b.id', '=', 'bc.business_id')
            ->join('users as u', 'u.id', '=', 'bc.user_id')
            ->where('bc.status', 'pending')
            ->orderBy('bc.submitted_at')
            ->limit(100)
            ->get([
                'bc.id', 'bc.claim_method', 'bc.claimant_notes', 'bc.metadata', 'bc.submitted_at',
                'b.id as business_id', 'b.name as business_name', 'b.slug as business_slug',
                'b.claim_status', 'b.verification_status',
                'u.id as user_id', 'u.name as claimant_name', 'u.email as claimant_email',
            ])
            ->map(fn ($claim) => $this->claimPayload($claim));

        $pendingVerifications = DB::table('business_verifications as bv')
            ->join('businesses as b', 'b.id', '=', 'bv.business_id')
            ->where('bv.status', 'pending')
            ->orderBy('bv.created_at')
            ->limit(100)
            ->get([
                'bv.id', 'bv.verification_type', 'bv.reference', 'bv.metadata', 'bv.created_at',
                'b.id as business_id', 'b.name as business_name', 'b.slug as business_slug',
                'b.verification_status',
            ])
            ->map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->verification_type,
                'reference' => $item->reference,
                'createdAt' => $item->created_at,
                'business' => [
                    'id' => $item->business_id,
                    'name' => $item->business_name,
                    'slug' => $item->business_slug,
                    'verificationStatus' => $item->verification_status,
                ],
            ]);

        $metrics = [
            'pendingClaims' => DB::table('business_claims')->where('status', 'pending')->count(),
            'approvedClaims' => DB::table('business_claims')->where('status', 'approved')->count(),
            'pendingVerifications' => DB::table('business_verifications')->where('status', 'pending')->count(),
            'verifiedEvidence' => DB::table('business_verifications')->where('status', 'verified')->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })->count(),
        ];

        return Inertia::render('AdminClaims', [
            'metrics' => $metrics,
            'pendingClaims' => $pendingClaims,
            'pendingVerifications' => $pendingVerifications,
            'status' => session('status'),
        ]);
    }

    public function approve(Request $request, int $claim): RedirectResponse
    {
        $this->ensureAdmin($request);
        $validated = $request->validate(['review_notes' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($request, $claim, $validated) {
            $claimRow = DB::table('business_claims')->where('id', $claim)->lockForUpdate()->first();
            abort_if($claimRow === null, 404);

            if ($claimRow->status !== 'pending') {
                return;
            }

            $business = DB::table('businesses')->where('id', $claimRow->business_id)->lockForUpdate()->first();
            abort_if($business === null, 404);

            if ($business->owner_user_id !== null && (int) $business->owner_user_id !== (int) $claimRow->user_id) {
                abort(409, 'This business already has a different owner.');
            }

            $now = now();
            DB::table('business_claims')->where('id', $claimRow->id)->update([
                'status' => 'approved',
                'reviewed_by_user_id' => $request->user()->id,
                'review_notes' => $validated['review_notes'] ?? null,
                'reviewed_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('business_claims')
                ->where('business_id', $business->id)
                ->where('status', 'pending')
                ->where('id', '<>', $claimRow->id)
                ->update([
                    'status' => 'rejected',
                    'reviewed_by_user_id' => $request->user()->id,
                    'review_notes' => 'Superseded by an approved ownership claim.',
                    'reviewed_at' => $now,
                    'updated_at' => $now,
                ]);

            DB::table('businesses')->where('id', $business->id)->update([
                'owner_user_id' => $claimRow->user_id,
                'claim_status' => 'claimed',
                'claimed_at' => $now,
                'updated_at' => $now,
            ]);

            $meta = $this->decodeMetadata($claimRow->metadata);
            $reference = trim((string) ($meta['evidence_reference'] ?? ''));
            $type = in_array($claimRow->claim_method, ['phone', 'email', 'website', 'cac'], true)
                ? $claimRow->claim_method
                : 'representative';

            if ($reference !== '') {
                $exists = DB::table('business_verifications')
                    ->where('business_id', $business->id)
                    ->where('verification_type', $type)
                    ->where('reference', $reference)
                    ->whereIn('status', ['pending', 'verified'])
                    ->exists();

                if (! $exists) {
                    DB::table('business_verifications')->insert([
                        'business_id' => $business->id,
                        'business_location_id' => null,
                        'verified_by_user_id' => null,
                        'verification_type' => $type,
                        'status' => 'pending',
                        'reference' => $reference,
                        'metadata' => json_encode([
                            'origin' => 'approved_business_claim',
                            'claim_id' => $claimRow->id,
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'verified_at' => null,
                        'expires_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('businesses')->where('id', $business->id)->update([
                        'verification_status' => 'pending',
                        'updated_at' => $now,
                    ]);
                }
            }
        }, 3);

        return back()->with('status', 'Ownership claim approved. Ownership was transferred; verification still requires evidence approval.');
    }

    public function reject(Request $request, int $claim): RedirectResponse
    {
        $this->ensureAdmin($request);
        $validated = $request->validate(['review_notes' => ['required', 'string', 'max:2000']]);

        DB::transaction(function () use ($request, $claim, $validated) {
            $claimRow = DB::table('business_claims')->where('id', $claim)->lockForUpdate()->first();
            abort_if($claimRow === null, 404);
            if ($claimRow->status !== 'pending') {
                return;
            }

            $business = DB::table('businesses')->where('id', $claimRow->business_id)->lockForUpdate()->first();
            abort_if($business === null, 404);
            $now = now();

            DB::table('business_claims')->where('id', $claimRow->id)->update([
                'status' => 'rejected',
                'reviewed_by_user_id' => $request->user()->id,
                'review_notes' => $validated['review_notes'],
                'reviewed_at' => $now,
                'updated_at' => $now,
            ]);

            $otherPending = DB::table('business_claims')
                ->where('business_id', $business->id)
                ->where('status', 'pending')
                ->exists();

            if (! $otherPending && $business->owner_user_id === null) {
                DB::table('businesses')->where('id', $business->id)->update([
                    'claim_status' => 'unclaimed',
                    'updated_at' => $now,
                ]);
            }
        }, 3);

        return back()->with('status', 'Ownership claim rejected. No ownership or verification was granted.');
    }

    public function verify(Request $request, int $verification): RedirectResponse
    {
        $this->ensureAdmin($request);

        DB::transaction(function () use ($request, $verification) {
            $evidence = DB::table('business_verifications')->where('id', $verification)->lockForUpdate()->first();
            abort_if($evidence === null, 404);
            if ($evidence->status !== 'pending') {
                return;
            }

            DB::table('businesses')->where('id', $evidence->business_id)->lockForUpdate()->first();
            $now = now();

            DB::table('business_verifications')->where('id', $evidence->id)->update([
                'status' => 'verified',
                'verified_by_user_id' => $request->user()->id,
                'verified_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('businesses')->where('id', $evidence->business_id)->update([
                'verification_status' => 'verified',
                'verified_at' => $now,
                'updated_at' => $now,
            ]);
        }, 3);

        return back()->with('status', 'Verification evidence approved. The Verified badge can now display for this business.');
    }

    public function fail(Request $request, int $verification): RedirectResponse
    {
        $this->ensureAdmin($request);
        $validated = $request->validate(['review_notes' => ['required', 'string', 'max:2000']]);

        DB::transaction(function () use ($request, $verification, $validated) {
            $evidence = DB::table('business_verifications')->where('id', $verification)->lockForUpdate()->first();
            abort_if($evidence === null, 404);
            if ($evidence->status !== 'pending') {
                return;
            }

            DB::table('businesses')->where('id', $evidence->business_id)->lockForUpdate()->first();
            $now = now();
            $meta = $this->decodeMetadata($evidence->metadata);
            $meta['review_notes'] = $validated['review_notes'];

            DB::table('business_verifications')->where('id', $evidence->id)->update([
                'status' => 'failed',
                'verified_by_user_id' => $request->user()->id,
                'metadata' => json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);

            $hasVerifiedEvidence = DB::table('business_verifications')
                ->where('business_id', $evidence->business_id)
                ->where('status', 'verified')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->exists();

            if (! $hasVerifiedEvidence) {
                DB::table('businesses')->where('id', $evidence->business_id)->update([
                    'verification_status' => 'unverified',
                    'verified_at' => null,
                    'updated_at' => $now,
                ]);
            }
        }, 3);

        return back()->with('status', 'Verification evidence failed. No Verified badge was granted.');
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless((bool) ($request->user()?->is_admin ?? false), 403);
    }

    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }
        if (! is_string($metadata) || trim($metadata) === '') {
            return [];
        }
        $decoded = json_decode($metadata, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function claimPayload(object $claim): array
    {
        $meta = $this->decodeMetadata($claim->metadata);
        return [
            'id' => $claim->id,
            'method' => $claim->claim_method,
            'reference' => $meta['evidence_reference'] ?? null,
            'notes' => $claim->claimant_notes,
            'submittedAt' => $claim->submitted_at,
            'business' => [
                'id' => $claim->business_id,
                'name' => $claim->business_name,
                'slug' => $claim->business_slug,
                'claimStatus' => $claim->claim_status,
                'verificationStatus' => $claim->verification_status,
            ],
            'claimant' => [
                'id' => $claim->user_id,
                'name' => $claim->claimant_name,
                'email' => $claim->claimant_email,
            ],
        ];
    }
}
