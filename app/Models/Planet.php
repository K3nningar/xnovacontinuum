<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Defines the Planet Eloquent model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $universe_id
 * @property int $user_id
 * @property string $name
 * @property int $galaxy
 * @property int $system
 * @property int $position
 * @property string $coordinates
 * @property int $metal
 * @property int $crystal
 * @property int $deuterium
 * @property int $fields
 * @property int $metal_mine_level
 * @property int $crystal_mine_level
 * @property int $deuterium_synthesizer_level
 * @property int $metal_production_remainder
 * @property int $crystal_production_remainder
 * @property int $deuterium_production_remainder
 * @property Carbon|null $resources_updated_at
 * @property int $solar_plant_level
 * @property int $fusion_plant_level
 * @property int $metal_storage_level
 * @property int $crystal_storage_level
 * @property int $deuterium_storage_level
 * @property int $max_temperature
 * @property int $metal_mine_activity
 * @property int $crystal_mine_activity
 * @property int $deuterium_synthesizer_activity
 * @property int $solar_plant_activity
 * @property int $fusion_plant_activity
 * @property int $laboratory_level
 * @property int $laboratory_activity
 * @property int $hangar_level
 * @property int $robot_factory_level
 * @property int $nano_factory_level
 * @property int $missile_silo_level
 * @property int $unit_202
 * @property int $unit_203
 * @property int $unit_204
 * @property int $unit_205
 * @property int $unit_206
 * @property int $unit_207
 * @property int $unit_208
 * @property int $unit_209
 * @property int $unit_210
 * @property int $unit_211
 * @property int $unit_212
 * @property int $unit_213
 * @property int $unit_214
 * @property int $unit_215
 * @property int $unit_216
 * @property int $unit_217
 * @property int $unit_401
 * @property int $unit_402
 * @property int $unit_403
 * @property int $unit_404
 * @property int $unit_405
 * @property int $unit_406
 * @property int $unit_407
 * @property int $unit_408
 * @property int $unit_409
 * @property int $unit_502
 * @property int $unit_503
 * @property int $terraformer_level
 * @property int $metal_concentration
 * @property int $crystal_concentration
 * @property int $deuterium_concentration
 * @property int $fusion_consumption_remainder
 * @property int $alliance_depot_level
 * @property Carbon|null $destroyed_at
 * @property-read User $user
 * @property-read Universe $universe
 * @property-read Collection<int, PlanetBuildingQueue> $buildingQueue
 * @property-read Collection<int, PlanetUnitQueue> $unitQueue
 */
#[Fillable(['name', 'metal_mine_level', 'crystal_mine_level', 'deuterium_synthesizer_level', 'solar_plant_level', 'fusion_plant_level', 'laboratory_level', 'laboratory_activity', 'hangar_level', 'robot_factory_level', 'nano_factory_level', 'missile_silo_level', 'terraformer_level', 'unit_202', 'unit_203', 'unit_204', 'unit_205', 'unit_206', 'unit_207', 'unit_208', 'unit_209', 'unit_210', 'unit_211', 'unit_212', 'unit_213', 'unit_214', 'unit_215', 'unit_216', 'unit_217', 'unit_401', 'unit_402', 'unit_403', 'unit_404', 'unit_405', 'unit_406', 'unit_407', 'unit_408', 'unit_409', 'unit_502', 'unit_503', 'metal_storage_level', 'crystal_storage_level', 'deuterium_storage_level', 'metal_mine_activity', 'crystal_mine_activity', 'deuterium_synthesizer_activity', 'solar_plant_activity', 'fusion_plant_activity', 'metal', 'crystal', 'deuterium', 'metal_production_remainder', 'crystal_production_remainder', 'deuterium_production_remainder', 'resources_updated_at', 'alliance_depot_level'])]
class Planet extends Model
{
    /**
     * Casts the resource checkpoint to immutable time so accrued intervals cannot be mutated accidentally.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resources_updated_at' => 'immutable_datetime',
            'destroyed_at' => 'immutable_datetime',
        ];
    }

    /**
     * Exposes the planet's stable galaxy:system:position coordinate string.
     */
    protected function coordinates(): Attribute
    {
        return Attribute::get(fn () => sprintf('%d:%d:%d', $this->galaxy, $this->system, $this->position));
    }

    /**
     * The universe in which this planet's coordinates and game state exist.
     *
     * @return BelongsTo<Universe, $this>
     */
    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }

    /**
     * The player who owns this planet.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Construction entries ordered by their scheduled queue position.
     *
     * @return HasMany<PlanetBuildingQueue, $this>
     */
    public function buildingQueue(): HasMany
    {
        return $this->hasMany(PlanetBuildingQueue::class)->orderBy('position');
    }

    /**
     * Research entries associated with this planet; empire-wide serialization is enforced through the user relation.
     *
     * @return HasMany<ResearchQueue, $this>
     */
    public function researchQueues(): HasMany
    {
        return $this->hasMany(ResearchQueue::class);
    }

    /**
     * Ship, defence, and officer production entries ordered by their scheduled queue position.
     *
     * @return HasMany<PlanetUnitQueue, $this>
     */
    public function unitQueue(): HasMany
    {
        return $this->hasMany(PlanetUnitQueue::class)->orderBy('position');
    }
}
