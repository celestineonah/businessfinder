<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessEnquiry extends Model
{
    protected $fillable = [
        'business_id',
        'user_id',
        'request_type',
        'contact_name',
        'contact_email',
        'contact_phone',
        'preferred_channel',
        'message',
        'status',
        'delivery_status',
        'owner_notes',
        'admin_notes',
        'source_url',
        'ip_hash',
        'consent_at',
        'notified_at',
        'seen_at',
        'responded_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'consent_at' => 'datetime',
            'notified_at' => 'datetime',
            'seen_at' => 'datetime',
            'responded_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
