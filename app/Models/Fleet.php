<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Defines the Fleet Eloquent model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['universe_id', 'user_id', 'origin_planet_id', 'target_planet_id', 'target_type', 'target_orbit', 'origin_orbit', 'moon_orbit', 'moon_order', 'mission_report', 'mission', 'ships', 'status', 'arrives_at', 'returns_at'])]
class Fleet extends Model
{
    /**
     * Casts serialized fleet payloads to arrays and timestamps to immutable date objects.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ships' => 'array',
            'cargo' => 'array',
            'combat_report' => 'array',
            'mission_report' => 'array',
            'moon_order' => 'array',
            'arrives_at' => 'immutable_datetime',
            'returns_at' => 'immutable_datetime',
        ];
    }

    /**
     * The universe whose rules and coordinates govern this fleet's mission.
     */
    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }

    /**
     * The player whose fleet is being tracked.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The planet to which surviving ships and returned cargo are delivered.
     */
    public function originPlanet(): BelongsTo
    {
        return $this->belongsTo(Planet::class, 'origin_planet_id');
    }

    /**
     * The destination planet for missions that target an owned planet record; coordinate-only missions may omit it.
     */
    public function targetPlanet(): BelongsTo
    {
        return $this->belongsTo(Planet::class, 'target_planet_id');
    }
}
