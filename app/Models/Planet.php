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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     */
    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }

    /**
     * The player who owns this planet.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Construction entries ordered by their scheduled queue position.
     */
    public function buildingQueue(): HasMany
    {
        return $this->hasMany(PlanetBuildingQueue::class)->orderBy('position');
    }

    /**
     * Research entries associated with this planet; empire-wide serialization is enforced through the user relation.
     */
    public function researchQueues(): HasMany
    {
        return $this->hasMany(ResearchQueue::class);
    }

    /**
     * Ship, defence, and officer production entries ordered by their scheduled queue position.
     */
    public function unitQueue(): HasMany
    {
        return $this->hasMany(PlanetUnitQueue::class)->orderBy('position');
    }
}
