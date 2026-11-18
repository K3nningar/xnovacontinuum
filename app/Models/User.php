<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Defines the User Eloquent model.
 */

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'officer_points', 'officer_geologist_level', 'officer_admiral_level', 'officer_engineer_level', 'officer_technocrat_level', 'officer_constructor_level', 'officer_scientist_level', 'officer_stockman_level', 'officer_defender_level', 'officer_bunker_level', 'officer_spy_level', 'officer_commander_level', 'officer_destroyer_level', 'officer_general_level', 'officer_raider_level', 'officer_emperor_level', 'planetary_destroyer_losses'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Hashes assigned passwords and casts verification, administrator, and suspension state for safe comparisons.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'admin_level' => 'integer',
            'suspended_until' => 'datetime',
        ];
    }

    /**
     * Determines whether the suspension deadline is still in the future; null or expired deadlines are inactive.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_until !== null && $this->suspended_until->isFuture();
    }

    /**
     * The player's selected universe, which scopes owned planets and fleets.
     */
    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }

    /**
     * The player's alliance, when they belong to one.
     */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /**
     * All planets owned by this player across their universe records.
     */
    public function planets(): HasMany
    {
        return $this->hasMany(Planet::class);
    }

    /**
     * Fleets dispatched by this player, including missions in transit and completed history.
     */
    public function fleets(): HasMany
    {
        return $this->hasMany(Fleet::class);
    }

    /**
     * The player's research queue; services enforce the single active entry across the empire.
     */
    public function researchQueues(): HasMany
    {
        return $this->hasMany(ResearchQueue::class);
    }
}
