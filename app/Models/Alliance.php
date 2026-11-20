<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Defines the Alliance Eloquent model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['universe_id', 'name', 'tag', 'description', 'leader_id'])]
class Alliance extends Model
{
    /**
     * The universe in which this alliance exists.
     */
    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }

    /**
     * The user designated as the alliance leader.
     */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    /**
     * Users currently assigned this alliance id.
     */
    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'alliance_id');
    }
}
