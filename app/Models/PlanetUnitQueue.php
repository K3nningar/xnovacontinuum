<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Defines the PlanetUnitQueue Eloquent model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $universe_id
 * @property int|null $planet_id
 * @property int $unit_code
 * @property string $unit_type
 * @property int $quantity
 * @property int $quantity_completed
 * @property int $position
 * @property int $cost_metal
 * @property int $cost_crystal
 * @property int $cost_deuterium
 * @property Carbon|null $starts_at
 * @property Carbon|null $completes_at
 * @property-read int $remaining_seconds
 * @property-read Planet|null $planet
 * @property-read Universe|null $universe
 */
#[Fillable(['universe_id', 'planet_id', 'unit_code', 'unit_type', 'quantity', 'quantity_completed', 'position', 'cost_metal', 'cost_crystal', 'cost_deuterium', 'starts_at', 'completes_at'])]

class PlanetUnitQueue extends Model
{
    /**
     * Casts queue scheduling timestamps to immutable dates.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'completes_at' => 'immutable_datetime'];
    }

    /**
     * Computes seconds until completion, clamping overdue entries to zero.
     */
    protected function remainingSeconds(): Attribute
    {
        return Attribute::get(fn (): int => max(0, (int) now()->diffInSeconds($this->completes_at, false)));
    }

    /**
     * The planet whose production queue contains this entry.
     *
     * @return BelongsTo<Planet, $this>
     */
    public function planet(): BelongsTo
    {
        return $this->belongsTo(Planet::class);
    }

    /**
     * The universe scope under which this unit order was placed.
     *
     * @return BelongsTo<Universe, $this>
     */
    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }
}
