<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BusinessClaimController extends Controller
{
    public function create(Request $request, string $slug): Response
    {
        $business = DB::table('businesses')
            ->where('slug', $slug)
            ->where('listing_status', 'listed')
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->whereNull('deleted_at')
            ->first();

        abort_if($business === null, 404);

        $location = DB::table('business_locations as bl')
            ->leftJoin('states as s', 's.id', '=', 'bl.state_id')
            ->leftJoin('local_government_areas as lga', 'lga.id', '=', 'bl.local_government_area_id')
            ->where('bl.business_id', $business->id)
            ->where('bl.is_primary', true)
            ->where('bl.is_active', true)
            ->whereNull('bl.deleted_at')
            ->select(['bl.address_line_1', 'bl.address_line_2', 's.name as state_name', 'lga.name as lga_name'])
            ->first();

        $existingClaim = DB::table('business_claims')
            ->where('business_id', $business->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        $locationLabel = collect([
            $location?->address_line_1,
            $location?->address_line_2,
            $location?->lga_name,
            $location?->state_name,
        ])->filter()->unique()->implode(', ');

        return Inertia::render('BusinessClaim', [
            'business' => [
                'name' => $business->name,
                'slug' => $business->slug,
                'location' => $locationLabel,
                'claimStatus' => $business->claim_status,
                'verificationStatus' => $business->verification_status,
                'publicUrl' => route('business.show', ['slug' => $business->slug]),
            ],
            'existingClaim' => $existingClaim ? [
                'status' => $existingClaim->status,
                'claimMethod' => $existingClaim->claim_method,
                'submittedAt' => $existingClaim->submitted_at,
            ] : null,
            'canSubmit' => $business->claim_status === 'unclaimed',
            'status' => session('status'),
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $validated = $request->validate([
            'claim_method' => ['required', 'string', 'in:phone,email,website,cac'],
            'reference' => ['required', 'string', 'max:255'],
            'claimant_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $business = DB::transaction(function () use ($request, $slug, $validated) {
            $lockedBusiness = DB::table('businesses')
                ->where('slug', $slug)
                ->where('listing_status', 'listed')
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            abort_if($lockedBusiness === null, 404);

            if ($lockedBusiness->claim_status !== 'unclaimed') {
                throw ValidationException::withMessages([
                    'claim_method' => 'This business already has a claim under review or has been claimed.',
                ]);
            }

            $pendingExists = DB::table('business_claims')
                ->where('business_id', $lockedBusiness->id)
                ->where('status', 'pending')
                ->exists();

            if ($pendingExists) {
                throw ValidationException::withMessages([
                    'claim_method' => 'This business already has an ownership claim under review.',
                ]);
            }

            $now = now();

            DB::table('business_claims')->insert([
                'business_id' => $lockedBusiness->id,
                'user_id' => $request->user()->id,
                'reviewed_by_user_id' => null,
                'claim_method' => $validated['claim_method'],
                'status' => 'pending',
                'claimant_notes' => $validated['claimant_notes'] ?? null,
                'review_notes' => null,
                'metadata' => json_encode([
                    'evidence_reference' => trim($validated['reference']),
                    'submitted_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'submitted_at' => $now,
                'reviewed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('businesses')
                ->where('id', $lockedBusiness->id)
                ->update(['claim_status' => 'pending', 'updated_at' => $now]);

            return $lockedBusiness;
        }, 3);

        return redirect()
            ->route('business.claim.create', ['slug' => $business->slug])
            ->with('status', 'Your ownership claim has been submitted for review.');
    }
}
