<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessEnquiry;
use App\Models\BusinessReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class BusinessEngagementController extends Controller
{
    public function review(Request $request, string $slug): RedirectResponse
    {
        $business = $this->publicBusiness($slug);

        $request->merge([
            'title' => $this->nullableTrim($request->input('title')),
            'body' => trim((string) $request->input('body', '')),
        ]);

        if ((int) $business->owner_user_id === (int) $request->user()->id) {
            throw ValidationException::withMessages([
                'rating' => 'Business owners cannot review their own listing.',
            ]);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:20', 'max:3000'],
        ]);

        $review = BusinessReview::query()->firstOrNew([
            'business_id' => $business->id,
            'user_id' => $request->user()->id,
        ]);

        $review->fill([
            'rating' => (int) $validated['rating'],
            'title' => $this->nullableTrim($validated['title'] ?? null),
            'body' => trim($validated['body']),
            'status' => 'pending',
            'moderated_by_user_id' => null,
            'moderation_notes' => null,
            'moderated_at' => null,
            'published_at' => null,
        ]);

        $review->save();

        return back()->with(
            'status',
            $review->wasRecentlyCreated
                ? 'Your review was submitted for moderation. It will appear publicly only after approval.'
                : 'Your review was updated and returned to moderation before it can appear publicly.'
        );
    }

    public function enquiry(Request $request, string $slug): RedirectResponse
    {
        $business = $this->publicBusiness($slug);
        $business->load(['owner', 'primaryLocation']);

        $request->merge([
            'contact_name' => trim((string) $request->input('contact_name', '')),
            'contact_email' => $this->nullableTrim($request->input('contact_email')),
            'contact_phone' => $this->nullableTrim($request->input('contact_phone')),
            'message' => trim((string) $request->input('message', '')),
        ]);

        $validated = $request->validate([
            'request_type' => [
                'required',
                'string',
                Rule::in(['general', 'quote']),
            ],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_email' => [
                'nullable',
                'email',
                'max:180',
                'required_without:contact_phone',
                'required_if:preferred_channel,email',
            ],
            'contact_phone' => [
                'nullable',
                'string',
                'max:40',
                'required_without:contact_email',
                'required_if:preferred_channel,whatsapp',
                'required_if:preferred_channel,phone',
            ],
            'preferred_channel' => [
                'required',
                'string',
                Rule::in(['whatsapp', 'phone', 'email']),
            ],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            'consent' => ['accepted'],
            // Honeypot. Human users should never fill this field.
            'website' => ['nullable', 'string', 'max:0'],
        ]);

        $ipHash = $this->ipHash($request);

        if ($ipHash !== null) {
            $duplicate = BusinessEnquiry::query()
                ->where('business_id', $business->id)
                ->where('ip_hash', $ipHash)
                ->where('request_type', $validated['request_type'])
                ->where('message', trim($validated['message']))
                ->where('created_at', '>=', now()->subMinutes(2))
                ->exists();

            if ($duplicate) {
                return back()->with([
                    'status' =>
                        'This enquiry was already recorded recently. It was not submitted twice.',
                    'enquiryWhatsAppUrl' => $this->businessWhatsappUrl(
                        $business,
                        $validated['request_type']
                    ),
                ]);
            }
        }

        $now = now();

        $enquiry = BusinessEnquiry::query()->create([
            'business_id' => $business->id,
            'user_id' => $request->user()?->id,
            'request_type' => $validated['request_type'],
            'contact_name' => trim($validated['contact_name']),
            'contact_email' => $this->nullableTrim($validated['contact_email'] ?? null),
            'contact_phone' => $this->nullableTrim($validated['contact_phone'] ?? null),
            'preferred_channel' => $validated['preferred_channel'],
            'message' => trim($validated['message']),
            'status' => 'new',
            'delivery_status' => 'recorded',
            'source_url' => route('business.show', ['slug' => $business->slug]),
            'ip_hash' => $ipHash,
            'consent_at' => $now,
        ]);

        $ownerCanReceive = $business->claim_status === 'claimed'
            && $business->owner !== null
            && $business->owner->hasVerifiedEmail();

        $platformAddress = trim(
            (string) config('mail.from.address')
        );

        if ($platformAddress === '') {
            $platformAddress = 'support@businessfinder.com.ng';
        }

        $recipient = $ownerCanReceive
            ? $business->owner->email
            : $platformAddress;

        $recipientType = $ownerCanReceive ? 'owner' : 'platform';
        $notificationSent = false;

        try {
            Mail::raw(
                $this->notificationBody($business, $enquiry, $recipientType),
                function ($message) use ($recipient, $business, $enquiry) {
                    $type = $enquiry->request_type === 'quote'
                        ? 'Quote request'
                        : 'Business enquiry';

                    $message
                        ->to($recipient)
                        ->subject(
                            $type . ' for ' . $business->name
                            . ' — BusinessFinder Nigeria'
                        );
                }
            );

            $enquiry->forceFill([
                'delivery_status' => $ownerCanReceive
                    ? 'owner_notified'
                    : 'platform_notified',
                'notified_at' => now(),
            ])->save();

            $notificationSent = true;
        } catch (Throwable $exception) {
            $enquiry->forceFill([
                'delivery_status' => 'notification_failed',
            ])->save();

            Log::warning('Business enquiry notification failed.', [
                'enquiry_id' => $enquiry->id,
                'business_id' => $business->id,
                'exception' => $exception::class,
            ]);
        }

        if ($notificationSent && $ownerCanReceive) {
            $status = 'Your enquiry was recorded and the claimed business owner was notified.';
        } elseif ($notificationSent) {
            $status = 'Your enquiry was recorded by BusinessFinder. This listing is not yet connected to a claimed owner dashboard, so platform support was notified.';
        } else {
            $status = 'Your enquiry was recorded, but the email notification could not be delivered. Use the direct contact or WhatsApp options as well when available.';
        }

        return back()->with([
            'status' => $status,
            'enquiryWhatsAppUrl' => $this->businessWhatsappUrl(
                $business,
                $validated['request_type']
            ),
        ]);
    }

    private function publicBusiness(string $slug): Business
    {
        return Business::query()
            ->where('slug', $slug)
            ->where('listing_status', 'listed')
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->firstOrFail();
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function ipHash(Request $request): ?string
    {
        $ip = $request->ip();

        if (! is_string($ip) || $ip === '') {
            return null;
        }

        return hash_hmac(
            'sha256',
            $ip,
            (string) config('app.key')
        );
    }

    private function notificationBody(
        Business $business,
        BusinessEnquiry $enquiry,
        string $recipientType
    ): string {
        $lines = [
            'BusinessFinder Nigeria received a new customer enquiry.',
            '',
            'Business: ' . $business->name,
            'Request type: ' . ($enquiry->request_type === 'quote' ? 'Quote request' : 'General enquiry'),
            'Preferred reply channel: ' . ucfirst($enquiry->preferred_channel),
            'Customer: ' . $enquiry->contact_name,
        ];

        if ($enquiry->contact_email) {
            $lines[] = 'Email: ' . $enquiry->contact_email;
        }

        if ($enquiry->contact_phone) {
            $lines[] = 'Phone: ' . $enquiry->contact_phone;
        }

        $lines[] = '';
        $lines[] = 'Message:';
        $lines[] = $enquiry->message;
        $lines[] = '';
        $lines[] = $recipientType === 'owner'
            ? 'Open your BusinessFinder dashboard to manage this lead.'
            : 'This business is not currently connected to a claimed owner dashboard. Review the lead in the BusinessFinder admin engagement queue.';
        $lines[] = '';
        $lines[] = 'Lead ID: ' . $enquiry->id;

        return implode(PHP_EOL, $lines);
    }

    private function businessWhatsappUrl(
        Business $business,
        string $requestType
    ): ?string {
        $phone = $business->primaryLocation?->whatsapp_phone
            ?: $business->primaryLocation?->phone
            ?: $business->whatsapp_phone
            ?: $business->primary_phone;

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

        $text = $requestType === 'quote'
            ? 'Hello, I found your business on BusinessFinder Nigeria and would like to request a quote.'
            : 'Hello, I found your business on BusinessFinder Nigeria and would like to make an enquiry.';

        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($text);
    }
}
