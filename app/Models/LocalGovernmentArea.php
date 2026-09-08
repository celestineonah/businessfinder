<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocalGovernmentArea extends Model
{
    protected $fillable = [
        'state_id',
        'name',
        'slug',
        'administrative_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(
            City::class,
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
