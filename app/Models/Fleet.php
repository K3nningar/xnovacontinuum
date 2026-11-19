<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Defines the Fleet Eloquent model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $universe_id
 * @property int $user_id
 * @property int|null $origin_planet_id
 * @property int|null $target_planet_id
 * @property string $mission
 * @property array<int, int>|null $ships
 * @property string $status
 * @property Carbon $arrives_at
 * @property Carbon|null $returns_at
 * @property int|null $target_galaxy
 * @property int|null $target_system
 * @property int|null $target_position
 * @property array<string, int>|null $cargo
 * @property string|null $outcome
 * @property array<string, mixed>|null $combat_report
 * @property int|null $moon_orbit
 * @property array<string, mixed>|null $moon_order
 * @property string|null $target_type
 * @property array<string, mixed>|null $mission_report
 * @property int|null $target_orbit
 * @property int|null $origin_orbit
 * @property-read User $user
 * @property-read Planet|null $originPlanet
 * @property-read Planet|null $targetPlanet
 * @property-read Universe $universe
 */
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
     *
     * @return BelongsTo<Universe, $this>
     */
    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }

    /**
     * The player whose fleet is being tracked.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The planet to which surviving ships and returned cargo are delivered.
     *
     * @return BelongsTo<Planet, $this>
     */
    public function originPlanet(): BelongsTo
    {
        return $this->belongsTo(Planet::class, 'origin_planet_id');
    }

    /**
     * The destination planet for missions that target an owned planet record; coordinate-only missions may omit it.
     *
     * @return BelongsTo<Planet, $this>
     */
    public function targetPlanet(): BelongsTo
    {
        return $this->belongsTo(Planet::class, 'target_planet_id');
    }
}
