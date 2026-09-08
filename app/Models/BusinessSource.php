<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessSource extends Model
{
    protected $fillable = [
        'business_id',
        'business_location_id',
        'source_type',
        'source_name',
        'source_url',
        'external_id',
        'confidence_score',
        'metadata',
        'first_seen_at',
        'last_checked_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'decimal:4',
            'metadata' => 'array',
            'first_seen_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function businessLocation(): BelongsTo
    {
        return $this->belongsTo(BusinessLocation::class);
    }
}
