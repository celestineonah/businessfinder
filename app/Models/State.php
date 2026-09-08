<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class State extends Model
{
    protected $fillable = [
        'geopolitical_zone_id',
        'name',
        'slug',
        'code',
        'is_fct',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_fct' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function geopoliticalZone(): BelongsTo
    {
        return $this->belongsTo(GeopoliticalZone::class);
    }

    public function localGovernmentAreas(): HasMany
    {
        return $this->hasMany(LocalGovernmentArea::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function businessLocations(): HasMany
    {
        return $this->hasMany(BusinessLocation::class);
    }
}
