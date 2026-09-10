<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Services\BusinessPublicationReadiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OwnerBusinessController extends Controller
{
    public function __construct(
        private readonly BusinessPublicationReadiness $readiness
    ) {
    }

    public function index(Request $request): Response
    {
        $businesses = Business::query()
            ->where('owner_user_id', $request->user()->id)
            ->with([
                'primaryLocation.state',
                'primaryLocation.localGovernmentArea',
                'categories',
            ])
            ->latest('updated_at')
            ->get()
            ->map(function (Business $business) {
                $latestRequest = DB::table('business_publication_requests')
                    ->where('business_id', $business->id)
                    ->latest('id')
                    ->first();

                $location = $business->primaryLocation;
                $locationLabel = collect([
                    $location?->localGovernmentArea?->name,
                    $location?->state?->name,
                ])->filter()->unique()->implode(', ');

                $primaryCategory = $business->categories
                    ->sortByDesc(fn ($category) => (bool) $category->pivot->is_primary)
                    ->first();

                $isPublished =
                    $business->listing_status === 'listed'
                    && $business->is_active
                    && $business->published_at !== null;

                return [
                    'id' => $business->id,
                    'name' => $business->name,
                    'slug' => $business->slug,
                    'category' => $primaryCategory?->name,
                    'location' => $locationLabel !== '' ? $locationLabel : null,
                    'claimStatus' => $business->claim_status,
                    'verificationStatus' => $business->verification_status,
                    'listingStatus' => $business->listing_status,
                    'isPublished' => $isPublished,
                    'updatedAt' => $business->updated_at?->toIso8601String(),
                    'publicUrl' => $isPublished
                        ? route('business.show', ['slug' => $business->slug])
                        : null,
                    'manageUrl' => route(
                        'dashboard.businesses.edit',
                        ['business' => $business->id]
                    ),
                    'readiness' => $this->readiness->evaluate($business->id),
                    'publicationRequest' => $latestRequest ? [
                        'status' => $latestRequest->status,
                        'requestedAt' => $latestRequest->requested_at,
                        'reviewNotes' => $latestRequest->review_notes,
                    ] : null,
                ];
            })
            ->values();

        return Inertia::render('OwnerBusinesses', [
            'businesses' => $businesses,
            'status' => session('status'),
        ]);
    }

    public function edit(Request $request, Business $business): Response
    {
        $this->ensureOwner($request, $business);

        $business->load([
            'primaryLocation.state',
            'primaryLocation.localGovernmentArea',
            'categories',
        ]);

        $location = $business->primaryLocation;
        $primaryCategory = $business->categories
            ->sortByDesc(fn ($category) => (bool) $category->pivot->is_primary)
            ->first();

        $latestRequest = DB::table('business_publication_requests')
            ->where('business_id', $business->id)
            ->latest('id')
            ->first();

        $states = DB::table('states')
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN code = 'FC' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'code'])
            ->values();

        $lgas = DB::table('local_government_areas')
            ->where('is_active', true)
            ->orderBy('state_id')
            ->orderBy('name')
            ->get(['id', 'state_id', 'name', 'slug', 'administrative_type'])
            ->values();

        $categories = DB::table('categories')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'depth'])
            ->values();

        $isPublished =
            $business->listing_status === 'listed'
            && $business->is_active
            && $business->published_at !== null;

        return Inertia::render('OwnerBusinessEdit', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'legalName' => $business->legal_name,
                'cacNumber' => $business->cac_number,
                'primaryPhone' => $business->primary_phone,
                'whatsappPhone' => $business->whatsapp_phone,
                'primaryEmail' => $business->primary_email,
                'websiteUrl' => $business->website_url,
                'shortDescription' => $business->short_description,
                'description' => $business->description,
                'claimStatus' => $business->claim_status,
                'verificationStatus' => $business->verification_status,
                'listingStatus' => $business->listing_status,
                'isPublished' => $isPublished,
                'publicUrl' => $isPublished
                    ? route('business.show', ['slug' => $business->slug])
                    : null,
                'categoryId' => $primaryCategory?->id,
            ],
            'location' => $location ? [
                'id' => $location->id,
                'stateId' => $location->state_id,
                'lgaId' => $location->local_government_area_id,
                'addressLine1' => $location->address_line_1,
                'addressLine2' => $location->address_line_2,
                'landmark' => $location->landmark,
                'postalCode' => $location->postal_code,
                'serviceAreaOnly' => (bool) $location->service_area_only,
            ] : null,
            'states' => $states,
            'lgas' => $lgas,
            'categories' => $categories,
            'readiness' => $this->readiness->evaluate($business->id),
            'publicationRequest' => $latestRequest ? [
                'status' => $latestRequest->status,
                'requestedAt' => $latestRequest->requested_at,
                'reviewNotes' => $latestRequest->review_notes,
            ] : null,
            'status' => session('status'),
        ]);
    }

    public function update(
        Request $request,
        Business $business
    ): RedirectResponse {
        $this->ensureOwner($request, $business);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'cac_number' => ['nullable', 'string', 'max:80'],
            'primary_phone' => ['nullable', 'string', 'max:40'],
            'whatsapp_phone' => ['nullable', 'string', 'max:40'],
            'primary_email' => ['nullable', 'email', 'max:180'],
            'website_url' => ['nullable', 'string', 'max:500'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'lga_id' => ['nullable', 'integer', 'exists:local_government_areas,id'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'service_area_only' => ['boolean'],
        ]);

        $serviceAreaOnly = (bool) ($validated['service_area_only'] ?? false);

        if (
            ! $serviceAreaOnly
            && trim((string) ($validated['address_line_1'] ?? '')) === ''
        ) {
            throw ValidationException::withMessages([
                'address_line_1' =>
                    'Enter a primary address or mark this as a service-area business.',
            ]);
        }

        if (! DB::table('categories')
            ->where('id', $validated['category_id'])
            ->where('is_active', true)
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'category_id' => 'Select an active business category.',
            ]);
        }

        if (! DB::table('states')
            ->where('id', $validated['state_id'])
            ->where('is_active', true)
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'state_id' => 'Select an active Nigerian state or the FCT.',
            ]);
        }

        if (! empty($validated['lga_id'])) {
            $validLga = DB::table('local_government_areas')
                ->where('id', $validated['lga_id'])
                ->where('state_id', $validated['state_id'])
                ->where('is_active', true)
                ->exists();

            if (! $validLga) {
                throw ValidationException::withMessages([
                    'lga_id' =>
                        'The selected LGA/Area Council does not belong to that state.',
                ]);
            }
        }

        DB::transaction(function () use (
            $request,
            $business,
            $validated,
            $serviceAreaOnly
        ) {
            $lockedBusiness = DB::table('businesses')
                ->where('id', $business->id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            abort_if($lockedBusiness === null, 404);
            abort_unless(
                (int) $lockedBusiness->owner_user_id === (int) $request->user()->id,
                403
            );

            $now = now();
            $name = trim($validated['name']);

            $location = DB::table('business_locations')
                ->where('business_id', $business->id)
                ->where('is_primary', true)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            $newLgaId = ! empty($validated['lga_id'])
                ? (int) $validated['lga_id']
                : null;

            $businessIdentityChanged =
                trim((string) $lockedBusiness->name) !== $name
                || trim((string) $lockedBusiness->legal_name)
                    !== trim((string) ($validated['legal_name'] ?? ''))
                || trim((string) $lockedBusiness->cac_number)
                    !== trim((string) ($validated['cac_number'] ?? ''))
                || trim((string) $lockedBusiness->primary_phone)
                    !== trim((string) ($validated['primary_phone'] ?? ''))
                || trim((string) $lockedBusiness->whatsapp_phone)
                    !== trim((string) ($validated['whatsapp_phone'] ?? ''))
                || strtolower(trim((string) $lockedBusiness->primary_email))
                    !== strtolower(trim((string) ($validated['primary_email'] ?? '')))
                || trim((string) $lockedBusiness->website_url)
                    !== trim((string) ($validated['website_url'] ?? ''));

            $locationIdentityChanged =
                $location === null
                || (int) $location->state_id !== (int) $validated['state_id']
                || (
                    $location->local_government_area_id !== null
                        ? (int) $location->local_government_area_id
                        : null
                ) !== $newLgaId
                || trim((string) $location->address_line_1)
                    !== trim((string) ($validated['address_line_1'] ?? ''))
                || trim((string) $location->address_line_2)
                    !== trim((string) ($validated['address_line_2'] ?? ''))
                || trim((string) $location->landmark)
                    !== trim((string) ($validated['landmark'] ?? ''))
                || trim((string) $location->postal_code)
                    !== trim((string) ($validated['postal_code'] ?? ''))
                || (bool) $location->service_area_only !== $serviceAreaOnly;

            $businessUpdate = [
                'name' => $name,
                'normalized_name' => mb_strtolower(
                    preg_replace('/\s+/', ' ', $name)
                ),
                'legal_name' => $validated['legal_name'] ?? null,
                'cac_number' => $validated['cac_number'] ?? null,
                'primary_phone' => $validated['primary_phone'] ?? null,
                'whatsapp_phone' => $validated['whatsapp_phone'] ?? null,
                'primary_email' => $validated['primary_email'] ?? null,
                'website_url' => $validated['website_url'] ?? null,
                'short_description' => $validated['short_description'] ?? null,
                'description' => $validated['description'] ?? null,
                'updated_at' => $now,
            ];

            if (
                $lockedBusiness->verification_status === 'verified'
                && ($businessIdentityChanged || $locationIdentityChanged)
            ) {
                $businessUpdate['verification_status'] = 'unverified';
                $businessUpdate['verified_at'] = null;

                DB::table('business_verifications')
                    ->where('business_id', $business->id)
                    ->where('status', 'verified')
                    ->update([
                        'status' => 'expired',
                        'expires_at' => $now,
                        'updated_at' => $now,
                    ]);
            }

            DB::table('businesses')
                ->where('id', $business->id)
                ->update($businessUpdate);

            $locationPayload = [
                'state_id' => (int) $validated['state_id'],
                'local_government_area_id' => $newLgaId,
                'address_line_1' => $validated['address_line_1'] ?? null,
                'address_line_2' => $validated['address_line_2'] ?? null,
                'landmark' => $validated['landmark'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'phone' => $validated['primary_phone'] ?? null,
                'whatsapp_phone' => $validated['whatsapp_phone'] ?? null,
                'email' => $validated['primary_email'] ?? null,
                'service_area_only' => $serviceAreaOnly,
                'is_primary' => true,
                'is_active' => true,
                'updated_at' => $now,
            ];

            if ($location !== null) {
                $oldLgaId = $location->local_government_area_id !== null
                    ? (int) $location->local_government_area_id
                    : null;

                $geographyChanged =
                    (int) $location->state_id !== (int) $validated['state_id']
                    || $oldLgaId !== $newLgaId;

                $addressChanged =
                    trim((string) $location->address_line_1)
                        !== trim((string) ($validated['address_line_1'] ?? ''))
                    || trim((string) $location->address_line_2)
                        !== trim((string) ($validated['address_line_2'] ?? ''))
                    || trim((string) $location->landmark)
                        !== trim((string) ($validated['landmark'] ?? ''));

                if ($geographyChanged) {
                    $locationPayload['city_id'] = null;
                    $locationPayload['area_id'] = null;
                }

                if ($geographyChanged || $addressChanged) {
                    $locationPayload['latitude'] = null;
                    $locationPayload['longitude'] = null;
                }

                DB::table('business_locations')
                    ->where('id', $location->id)
                    ->update($locationPayload);
            } else {
                DB::table('business_locations')->insert([
                    ...$locationPayload,
                    'business_id' => $business->id,
                    'city_id' => null,
                    'area_id' => null,
                    'name' => 'Primary Location',
                    'slug' => 'owner-primary-' . $business->id,
                    'latitude' => null,
                    'longitude' => null,
                    'opening_hours' => null,
                    'last_checked_at' => null,
                    'created_at' => $now,
                    'deleted_at' => null,
                ]);
            }

            DB::table('business_category')
                ->where('business_id', $business->id)
                ->update([
                    'is_primary' => false,
                    'updated_at' => $now,
                ]);

            $existingCategory = DB::table('business_category')
                ->where('business_id', $business->id)
                ->where('category_id', $validated['category_id'])
                ->first();

            if ($existingCategory) {
                DB::table('business_category')
                    ->where('id', $existingCategory->id)
                    ->update([
                        'is_primary' => true,
                        'sort_order' => 0,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('business_category')->insert([
                    'business_id' => $business->id,
                    'category_id' => $validated['category_id'],
                    'is_primary' => true,
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $ownerSourceExists = DB::table('business_sources')
                ->where('business_id', $business->id)
                ->where('source_type', 'owner')
                ->where('source_name', 'Owner profile management')
                ->exists();

            if (! $ownerSourceExists) {
                DB::table('business_sources')->insert([
                    'business_id' => $business->id,
                    'business_location_id' => null,
                    'source_type' => 'owner',
                    'source_name' => 'Owner profile management',
                    'source_url' => null,
                    'external_id' => null,
                    'confidence_score' => null,
                    'metadata' => json_encode([
                        'owner_user_id' => $request->user()->id,
                        'purpose' => 'owner_profile_update',
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'first_seen_at' => $now,
                    'last_checked_at' => $now,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('business_sources')
                    ->where('business_id', $business->id)
                    ->where('source_type', 'owner')
                    ->where('source_name', 'Owner profile management')
                    ->update([
                        'last_checked_at' => $now,
                        'updated_at' => $now,
                    ]);
            }
        }, 3);

        return back()->with(
            'status',
            'Business profile updated. Sensitive identity/contact/location changes automatically invalidate prior verification evidence until re-verification.'
        );
    }

    public function requestPublication(
        Request $request,
        Business $business
    ): RedirectResponse {
        $this->ensureOwner($request, $business);

        if (
            $business->listing_status === 'listed'
            && $business->is_active
            && $business->published_at !== null
        ) {
            return back()->with('status', 'This business is already public.');
        }

        $readiness = $this->readiness->evaluate($business->id);

        if (! $readiness['ready']) {
            throw ValidationException::withMessages([
                'publication' =>
                    'Complete the publication checklist first: '
                    . implode(', ', $readiness['missing']),
            ]);
        }

        DB::transaction(function () use ($request, $business) {
            $locked = DB::table('businesses')
                ->where('id', $business->id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            abort_if($locked === null, 404);
            abort_unless(
                (int) $locked->owner_user_id === (int) $request->user()->id,
                403
            );

            $pending = DB::table('business_publication_requests')
                ->where('business_id', $business->id)
                ->where('status', 'pending')
                ->exists();

            if ($pending) {
                return;
            }

            $now = now();

            DB::table('business_publication_requests')->insert([
                'business_id' => $business->id,
                'user_id' => $request->user()->id,
                'reviewed_by_user_id' => null,
                'status' => 'pending',
                'review_notes' => null,
                'requested_at' => $now,
                'reviewed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, 3);

        return back()->with(
            'status',
            'Publication request submitted. BusinessFinder will review the listing before it goes public.'
        );
    }

    private function ensureOwner(Request $request, Business $business): void
    {
        abort_unless(
            (int) $business->owner_user_id === (int) $request->user()->id,
            403
        );
    }
}
