<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class BusinessPublicationReadiness
{
    /** @return array{ready: bool, missing: array<int, string>} */
    public function evaluate(int $businessId): array
    {
        $business = DB::table('businesses')
            ->where('id', $businessId)
            ->whereNull('deleted_at')
            ->first();

        if ($business === null) {
            return ['ready' => false, 'missing' => ['Business record']];
        }

        $location = DB::table('business_locations')
            ->where('business_id', $businessId)
            ->where('is_primary', true)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        $hasCategory = DB::table('business_category')
            ->where('business_id', $businessId)
            ->exists();

        $hasDescription =
            trim((string) $business->short_description) !== ''
            || trim((string) $business->description) !== '';

        $hasContact =
            trim((string) $business->primary_phone) !== ''
            || trim((string) $business->whatsapp_phone) !== ''
            || trim((string) $business->primary_email) !== ''
            || trim((string) $business->website_url) !== ''
            || (
                $location !== null
                && (
                    trim((string) $location->phone) !== ''
                    || trim((string) $location->whatsapp_phone) !== ''
                    || trim((string) $location->email) !== ''
                )
            );

        $hasLocation =
            $location !== null
            && $location->state_id !== null
            && (
                (bool) $location->service_area_only
                || trim((string) $location->address_line_1) !== ''
            );

        $missing = [];

        if (trim((string) $business->name) === '') {
            $missing[] = 'Business name';
        }
        if (! $hasDescription) {
            $missing[] = 'Business description';
        }
        if (! $hasContact) {
            $missing[] = 'At least one customer contact method';
        }
        if (! $hasLocation) {
            $missing[] = 'Primary Nigerian location or service area';
        }
        if (! $hasCategory) {
            $missing[] = 'Primary business category';
        }

        return ['ready' => $missing === [], 'missing' => $missing];
    }
}
