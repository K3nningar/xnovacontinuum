<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['planet_id', 'orbit', 'building_key', 'building_code', 'target_level', 'position', 'cost_metal', 'cost_crystal', 'cost_deuterium', 'starts_at', 'completes_at'])]
class MoonBuildingQueue extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'completes_at' => 'immutable_datetime',
        ];
    }

    public function planet(): BelongsTo
    {
        return $this->belongsTo(Planet::class);
    }
}
