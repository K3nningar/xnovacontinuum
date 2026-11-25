<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['universe_id', 'key', 'name', 'description', 'image', 'settings'])]
class Faction extends Model
{
    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }
}
