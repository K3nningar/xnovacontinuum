<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['universe_id', 'user_id', 'origin_planet_id', 'target_planet_id', 'mission', 'ships', 'status', 'arrives_at', 'returns_at'])]
class Fleet extends Model
{
    protected function casts(): array
    {
        return [
            'ships' => 'array',
            'cargo' => 'array',
            'combat_report' => 'array',
            'moon_order' => 'array',
            'arrives_at' => 'immutable_datetime',
            'returns_at' => 'immutable_datetime',
        ];
    }

    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function originPlanet(): BelongsTo
    {
        return $this->belongsTo(Planet::class, 'origin_planet_id');
    }

    public function targetPlanet(): BelongsTo
    {
        return $this->belongsTo(Planet::class, 'target_planet_id');
    }
}
