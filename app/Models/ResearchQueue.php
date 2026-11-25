<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Defines the ResearchQueue Eloquent model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $universe_id
 * @property int $user_id
 * @property int $planet_id
 * @property string $technology_key
 * @property int $technology_code
 * @property int $target_level
 * @property int $cost_metal
 * @property int $cost_crystal
 * @property int $cost_deuterium
 * @property Carbon $completes_at
 * @property-read int $remaining_seconds
 * @property-read User $user
 * @property-read Planet $planet
 * @property-read Universe $universe
 */
#[Fillable(['universe_id', 'user_id', 'planet_id', 'technology_key', 'technology_code', 'target_level', 'cost_metal', 'cost_crystal', 'cost_deuterium', 'completes_at'])]
class ResearchQueue extends Model
{
    /**
     * Casts the scheduled completion time for consistent due checks and response serialization.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['completes_at' => 'immutable_datetime'];
    }

    /**
     * Computes non-negative seconds until completion; overdue entries report zero.
     */
    protected function remainingSeconds(): Attribute
    {
        return Attribute::get(fn (): int => max(0, (int) now()->diffInSeconds($this->completes_at, false)));
    }

    /**
     * The empire owner whose single active research slot this entry occupies.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The planet from which research resources were deducted.
     *
     * @return BelongsTo<Planet, $this>
     */
    public function planet(): BelongsTo
    {
        return $this->belongsTo(Planet::class);
    }

    /**
     * The universe scope under which this research entry was created.
     *
     * @return BelongsTo<Universe, $this>
     */
    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }
}
