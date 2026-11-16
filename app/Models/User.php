<?php

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
     * Get the attributes that should be cast.
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

    public function isSuspended(): bool
    {
        return $this->suspended_until !== null && $this->suspended_until->isFuture();
    }

    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }

    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    public function planets(): HasMany
    {
        return $this->hasMany(Planet::class);
    }

    public function fleets(): HasMany
    {
        return $this->hasMany(Fleet::class);
    }

    public function researchQueues(): HasMany
    {
        return $this->hasMany(ResearchQueue::class);
    }
}
