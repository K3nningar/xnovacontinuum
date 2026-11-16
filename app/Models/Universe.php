<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'status', 'galaxies', 'systems_per_galaxy', 'economy_speed', 'fleet_speed'])]
class Universe extends Model
{
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function planets(): HasMany
    {
        return $this->hasMany(Planet::class);
    }

    public function alliances(): HasMany
    {
        return $this->hasMany(Alliance::class);
    }

    public function fleets(): HasMany
    {
        return $this->hasMany(Fleet::class);
    }
}
