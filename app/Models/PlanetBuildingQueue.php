<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Defines the PlanetBuildingQueue Eloquent model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['universe_id', 'planet_id', 'building_key', 'building_code', 'target_level', 'position', 'cost_metal', 'cost_crystal', 'cost_deuterium', 'starts_at', 'completes_at'])]
class PlanetBuildingQueue extends Model
{
    /**
     * Keeps scheduled queue timestamps immutable for safe duration calculations.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'completes_at' => 'immutable_datetime',
        ];
    }

    /**
     * The planet whose serialized construction queue contains this entry.
     */
    public function planet(): BelongsTo
    {
        return $this->belongsTo(Planet::class);
    }

    /**
     * The universe scope copied onto this queued construction entry.
     *
     * @return BelongsTo<Universe, $this>
     */
    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }
}
