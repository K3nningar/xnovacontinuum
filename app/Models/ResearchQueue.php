<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['universe_id', 'user_id', 'planet_id', 'technology_key', 'technology_code', 'target_level', 'cost_metal', 'cost_crystal', 'cost_deuterium', 'completes_at'])]
class ResearchQueue extends Model
{
    protected function casts(): array
    {
        return ['completes_at' => 'immutable_datetime'];
    }

    protected function remainingSeconds(): Attribute
    {
        return Attribute::get(fn (): int => max(0, (int) now()->diffInSeconds($this->completes_at, false)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
