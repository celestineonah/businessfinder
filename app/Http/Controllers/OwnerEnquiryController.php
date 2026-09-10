<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OwnerEnquiryController extends Controller
{
    public function update(
        Request $request,
        int $enquiry
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in(['new', 'viewed', 'responded', 'closed']),
            ],
            'owner_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $enquiry, $validated) {
            $lead = DB::table('business_enquiries as be')
                ->join('businesses as b', 'b.id', '=', 'be.business_id')
                ->where('be.id', $enquiry)
                ->select([
                    'be.id',
                    'be.status',
                    'be.seen_at',
                    'be.responded_at',
                    'be.closed_at',
                    'b.owner_user_id',
                ])
                ->lockForUpdate()
                ->first();

            abort_if($lead === null, 404);
            abort_unless(
                (int) $lead->owner_user_id === (int) $request->user()->id,
                403
            );

            $now = now();
            $updates = [
                'status' => $validated['status'],
                'updated_at' => $now,
            ];

            if ($request->exists('owner_notes')) {
                $updates['owner_notes'] = $validated['owner_notes'] ?? null;
            }

            if (
                in_array($validated['status'], ['viewed', 'responded', 'closed'], true)
                && $lead->seen_at === null
            ) {
                $updates['seen_at'] = $now;
            }

            if (
                in_array($validated['status'], ['responded', 'closed'], true)
                && $lead->responded_at === null
            ) {
                $updates['responded_at'] = $now;
            }

            if ($validated['status'] === 'closed') {
                $updates['closed_at'] = $now;
            } elseif ($lead->closed_at !== null) {
                $updates['closed_at'] = null;
            }

            DB::table('business_enquiries')
                ->where('id', $lead->id)
                ->update($updates);
        }, 3);

        return back()->with('status', 'Lead status updated.');
    }
}
