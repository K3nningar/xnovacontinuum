<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['universe_id', 'name', 'tag', 'description', 'leader_id'])]
class Alliance extends Model
{
    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'alliance_id');
    }
}
