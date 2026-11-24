<?php

declare(strict_types=1);

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Defines the User Eloquent model.
 */

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $commander_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property int|null $universe_id
 * @property int|null $alliance_id
 * @property int $energy_technology_level
 * @property int $spy_technology_level
 * @property int $computer_technology_level
 * @property int $military_technology_level
 * @property int $defence_technology_level
 * @property int $shield_technology_level
 * @property int $hyperspace_technology_level
 * @property int $combustion_technology_level
 * @property int $impulse_technology_level
 * @property int $hyperspace_drive_level
 * @property int $laser_technology_level
 * @property int $ion_technology_level
 * @property int $plasma_technology_level
 * @property int $intergalactic_research_level
 * @property int $expedition_technology_level
 * @property int $graviton_technology_level
 * @property int $officer_points
 * @property int $officer_geologist_level
 * @property int $officer_admiral_level
 * @property int $officer_engineer_level
 * @property int $officer_technocrat_level
 * @property int $officer_constructor_level
 * @property int $officer_scientist_level
 * @property int $officer_stockman_level
 * @property int $officer_defender_level
 * @property int $officer_bunker_level
 * @property int $officer_spy_level
 * @property int $officer_commander_level
 * @property int $officer_destroyer_level
 * @property int $officer_general_level
 * @property int $officer_raider_level
 * @property int $officer_emperor_level
 * @property int $planetary_destroyer_losses
 * @property int $admin_level
 * @property Carbon|null $suspended_until
 * @property string|null $suspension_reason
 * @property int $sensor_technology_level
 * @property-read Universe|null $universe
 * @property-read Alliance|null $alliance
 * @property-read Collection<int, Planet> $planets
 * @property-read Collection<int, Fleet> $fleets
 * @property-read Collection<int, ResearchQueue> $researchQueues
 */
#[Fillable(['name', 'email', 'password', 'officer_points', 'officer_geologist_level', 'officer_admiral_level', 'officer_engineer_level', 'officer_technocrat_level', 'officer_constructor_level', 'officer_scientist_level', 'officer_stockman_level', 'officer_defender_level', 'officer_bunker_level', 'officer_spy_level', 'officer_commander_level', 'officer_destroyer_level', 'officer_general_level', 'officer_raider_level', 'officer_emperor_level', 'planetary_destroyer_losses'])]
#[Hidden(['password', 'remember_token'])]

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->commander_id ??= (string) Str::uuid();
        });
    }

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
            'two_factor_secret' => 'encrypted',
            'two_factor_enabled_at' => 'datetime',
        ];
    }

    /**
     * Determines whether any profile belonging to this account has an active suspension.
     */
    public function isSuspended(): bool
    {
        return User::query()->where('commander_id', $this->commander_id)
            ->where('suspended_until', '>', now())->exists();
    }

    /**
     * Profiles owned by the same account, one for each universe.
     *
     * @return HasMany<User, $this>
     */
    public function commanderProfiles(): HasMany
    {
        return $this->hasMany(self::class, 'commander_id', 'commander_id')->whereNotNull('universe_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(self::class, 'commander_id', 'commander_id')->whereNull('universe_id');
    }

    /** Univers surveillés par le commandant selon les attributions d’administration. */
    public function staffUniverses(): BelongsToMany
    {
        return $this->belongsToMany(Universe::class, 'universe_staff', 'commander_id', 'universe_id', 'commander_id', 'id')
            ->withPivot('level')->withTimestamps();
    }

    /**
     * The player's selected universe, which scopes owned planets and fleets.
     *
     * @return BelongsTo<Universe, $this>
     */
    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }

    /**
     * The player's alliance, when they belong to one.
     *
     * @return BelongsTo<Alliance, $this>
     */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /**
     * All planets owned by this player across their universe records.
     *
     * @return HasMany<Planet, $this>
     */
    public function planets(): HasMany
    {
        return $this->hasMany(Planet::class);
    }

    /**
     * Fleets dispatched by this player, including missions in transit and completed history.
     *
     * @return HasMany<Fleet, $this>
     */
    public function fleets(): HasMany
    {
        return $this->hasMany(Fleet::class);
    }

    /**
     * The player's research queue; services enforce the single active entry across the empire.
     *
     * @return HasMany<ResearchQueue, $this>
     */
    public function researchQueues(): HasMany
    {
        return $this->hasMany(ResearchQueue::class);
    }
}
