<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $fillable = [
        'state_id',
        'name',
        'slug',
        'is_state_capital',
        'is_major',
        'is_active',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'is_state_capital' => 'boolean',
            'is_major' => 'boolean',
            'is_active' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function localGovernmentAreas(): BelongsToMany
    {
        return $this->belongsToMany(
            LocalGovernmentArea::class,
            'city_local_government_area'
        )->withTimestamps();
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
