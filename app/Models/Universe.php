<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Defines the Universe Eloquent model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $galaxies
 * @property int $systems_per_galaxy
 */
#[Fillable(['name', 'slug', 'status', 'galaxies', 'systems_per_galaxy', 'economy_speed', 'fleet_speed'])]
class Universe extends Model
{
    public function factions(): HasMany
    {
        return $this->hasMany(Faction::class)->orderBy('name');
    }

    /**
     * Players who have selected this universe.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Planets whose coordinates and economy belong to this universe.
     *
     * @return HasMany<Planet, $this>
     */
    public function planets(): HasMany
    {
        return $this->hasMany(Planet::class);
    }

    /**
     * Alliances registered within this universe.
     *
     * @return HasMany<Alliance, $this>
     */
    public function alliances(): HasMany
    {
        return $this->hasMany(Alliance::class);
    }

    /**
     * Fleets operating under this universe's mission and speed rules.
     *
     * @return HasMany<Fleet, $this>
     */
    public function fleets(): HasMany
    {
        return $this->hasMany(Fleet::class);
    }
}
