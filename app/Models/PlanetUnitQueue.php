<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['universe_id', 'planet_id', 'unit_code', 'unit_type', 'quantity', 'quantity_completed', 'position', 'cost_metal', 'cost_crystal', 'cost_deuterium', 'starts_at', 'completes_at'])]
class PlanetUnitQueue extends Model
{
    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'completes_at' => 'immutable_datetime'];
    }

    protected function remainingSeconds(): Attribute
    {
        return Attribute::get(fn (): int => max(0, (int) now()->diffInSeconds($this->completes_at, false)));
    }

    public function planet(): BelongsTo
    {
        return $this->belongsTo(Planet::class);
    }

    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }
}
