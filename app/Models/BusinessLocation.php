<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessLocation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'state_id',
        'city_id',
        'local_government_area_id',
        'area_id',
        'name',
        'slug',
        'address_line_1',
        'address_line_2',
        'landmark',
        'postal_code',
        'phone',
        'whatsapp_phone',
        'email',
        'latitude',
        'longitude',
        'opening_hours',
        'is_primary',
        'service_area_only',
        'is_active',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'opening_hours' => 'array',
            'is_primary' => 'boolean',
            'service_area_only' => 'boolean',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function localGovernmentArea(): BelongsTo
    {
        return $this->belongsTo(LocalGovernmentArea::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(BusinessSource::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(BusinessVerification::class);
    }
}
