<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_user_id',
        'name',
        'normalized_name',
        'slug',
        'legal_name',
        'cac_number',
        'primary_phone',
        'whatsapp_phone',
        'primary_email',
        'website_url',
        'short_description',
        'description',
        'listing_status',
        'claim_status',
        'verification_status',
        'dedupe_key',
        'claimed_at',
        'verified_at',
        'published_at',
        'last_checked_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'verified_at' => 'datetime',
            'published_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(BusinessLocation::class);
    }

    public function primaryLocation(): HasOne
    {
        return $this->hasOne(BusinessLocation::class)
            ->where('is_primary', true);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'business_category'
        )
            ->withPivot(['is_primary', 'sort_order'])
            ->withTimestamps();
    }

    public function sources(): HasMany
    {
        return $this->hasMany(BusinessSource::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(BusinessClaim::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(BusinessVerification::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(BusinessReview::class);
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(BusinessEnquiry::class);
    }
}
