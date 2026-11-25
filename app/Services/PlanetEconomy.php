<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Implements the PlanetEconomy application service.
 */

namespace App\Services;

use App\Models\Planet;
use App\Models\PlanetBuildingQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanetEconomy
{
    /**
     * Returns the canonical building definitions used by administrative element updates.
     *
     * @return array<string, array{level: string, activity: ?string, resource: ?string, label: string, code: int}>
     */
    public function administrativeBuildings(): array
    {
        return self::BUILDINGS;
    }

    /**
     * Returns the highest level accepted by the building upgrade workflow.
     */
    public function maximumBuildingLevel(): int
    {
        return self::MAX_BUILDING_LEVEL;
    }

    private const MAX_BUILDING_LEVEL = 30;

    private const RESOURCE_MULTIPLIER = 1;

    private const BASIC_INCOME = ['metal' => 20, 'crystal' => 10, 'deuterium' => 0];

    private const STORAGE_LEVELS = [
        'metal' => 'metal_storage_level',
        'crystal' => 'crystal_storage_level',
        'deuterium' => 'deuterium_storage_level',
    ];

    private const STORAGE_BUILDINGS = [
        'metal_storage' => 'metal',
        'crystal_storage' => 'crystal',
        'deuterium_storage' => 'deuterium',
    ];

    private const BUILDINGS = [
        'metal' => ['level' => 'metal_mine_level', 'activity' => 'metal_mine_activity', 'resource' => 'metal', 'label' => 'Mine de métal', 'code' => 1],
        'crystal' => ['level' => 'crystal_mine_level', 'activity' => 'crystal_mine_activity', 'resource' => 'crystal', 'label' => 'Mine de cristal', 'code' => 2],
        'deuterium' => ['level' => 'deuterium_synthesizer_level', 'activity' => 'deuterium_synthesizer_activity', 'resource' => 'deuterium', 'label' => 'Synthétiseur de deutérium', 'code' => 3],
        'solar' => ['level' => 'solar_plant_level', 'activity' => 'solar_plant_activity', 'resource' => 'energy', 'label' => 'Centrale solaire', 'code' => 4],
        'fusion' => ['level' => 'fusion_plant_level', 'activity' => 'fusion_plant_activity', 'resource' => 'energy', 'label' => 'Centrale de fusion', 'code' => 12],
        'robot_factory' => ['level' => 'robot_factory_level', 'activity' => null, 'resource' => null, 'label' => 'Usine de robots', 'code' => 14],
        'nano_factory' => ['level' => 'nano_factory_level', 'activity' => null, 'resource' => null, 'label' => 'Usine de nanites', 'code' => 15],
        'defence_center' => ['level' => 'defence_center_level', 'activity' => null, 'resource' => null, 'label' => 'Centre de Défense', 'code' => 20],
        'hangar' => ['level' => 'hangar_level', 'activity' => null, 'resource' => null, 'label' => 'Chantier spatial', 'code' => 21],
        'missile_silo' => ['level' => 'missile_silo_level', 'activity' => null, 'resource' => null, 'label' => 'Silo de missiles', 'code' => 44],
        'laboratory' => ['level' => 'laboratory_level', 'activity' => 'laboratory_activity', 'resource' => null, 'label' => 'Laboratoire de recherche', 'code' => 31],
        'metal_storage' => ['level' => 'metal_storage_level', 'activity' => null, 'resource' => null, 'label' => 'Hangar à métal', 'code' => 22],
        'crystal_storage' => ['level' => 'crystal_storage_level', 'activity' => null, 'resource' => null, 'label' => 'Hangar à cristal', 'code' => 23],
        'deuterium_storage' => ['level' => 'deuterium_storage_level', 'activity' => null, 'resource' => null, 'label' => 'Réservoir de deutérium', 'code' => 24],
        'terraformer' => ['level' => 'terraformer_level', 'activity' => null, 'resource' => null, 'label' => 'Terraformeur', 'code' => 33],
        'alliance_depot' => ['level' => 'alliance_depot_level', 'activity' => null, 'resource' => null, 'label' => 'Dépôt d’alliance', 'code' => 34],
    ];

    private const LEGACY_PRICE_LIST = [
        'metal' => ['cost' => ['metal' => 60, 'crystal' => 15, 'deuterium' => 0], 'factor' => 1.5],
        'crystal' => ['cost' => ['metal' => 48, 'crystal' => 24, 'deuterium' => 0], 'factor' => 1.6],
        'deuterium' => ['cost' => ['metal' => 225, 'crystal' => 75, 'deuterium' => 0], 'factor' => 1.5],
        'solar' => ['cost' => ['metal' => 75, 'crystal' => 30, 'deuterium' => 0], 'factor' => 1.5],
        'fusion' => ['cost' => ['metal' => 900, 'crystal' => 360, 'deuterium' => 180], 'factor' => 1.8],
        'robot_factory' => ['cost' => ['metal' => 400, 'crystal' => 120, 'deuterium' => 200], 'factor' => 2],
        'nano_factory' => ['cost' => ['metal' => 1_000_000, 'crystal' => 500_000, 'deuterium' => 100_000], 'factor' => 2],
        'defence_center' => ['cost' => ['metal' => 400, 'crystal' => 200, 'deuterium' => 100], 'factor' => 2],
        'hangar' => ['cost' => ['metal' => 400, 'crystal' => 200, 'deuterium' => 100], 'factor' => 2],
        'missile_silo' => ['cost' => ['metal' => 20_000, 'crystal' => 20_000, 'deuterium' => 1_000], 'factor' => 2],
        'laboratory' => ['cost' => ['metal' => 200, 'crystal' => 400, 'deuterium' => 200], 'factor' => 2],
        'metal_storage' => ['cost' => ['metal' => 2000, 'crystal' => 0, 'deuterium' => 0], 'factor' => 2],
        'crystal_storage' => ['cost' => ['metal' => 2000, 'crystal' => 1000, 'deuterium' => 0], 'factor' => 2],
        'deuterium_storage' => ['cost' => ['metal' => 2000, 'crystal' => 2000, 'deuterium' => 0], 'factor' => 2],
        'terraformer' => ['cost' => ['metal' => 0, 'crystal' => 50000, 'deuterium' => 100000, 'energy' => 1000], 'factor' => 2],
        'alliance_depot' => ['cost' => ['metal' => 20000, 'crystal' => 40000, 'deuterium' => 0], 'factor' => 2],
    ];

    /**
     * Applies elapsed resource production and completed satellite deliveries to a fresh, locked planet row.
     *
     * The transaction retries up to three times; when supplied, $now is the production cutoff used for
     * deterministic catch-up calculations. The returned model is the locked planet with persisted updates.
     */
    public function materialize(Planet $planet, ?Carbon $now = null): Planet
    {
        return DB::transaction(function () use ($planet, $now): Planet {
            $planet = Planet::query()->whereKey($planet->id)->lockForUpdate()->firstOrFail();
            $this->materializeLockedPlanet($planet, $now);

            return $planet;
        }, 3);
    }

    /**
     * Builds the building-page view of current production, projected costs, queue, capacities and eligibility.
     *
     * Queued levels are included when calculating the next upgrade, while resource affordability is based on
     * the planet's current stored stock. Technology levels supply the player-wide requirement checks.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Planet $planet, int $energyTechnologyLevel = 0, int $computerTechnologyLevel = 0): array
    {
        $snapshot = $planet->only('metal', 'crystal', 'deuterium', 'metal_mine_level', 'crystal_mine_level', 'deuterium_synthesizer_level', 'solar_plant_level', 'fusion_plant_level', 'laboratory_level', 'robot_factory_level', 'nano_factory_level', 'hangar_level', 'defence_center_level', 'missile_silo_level', 'metal_storage_level', 'crystal_storage_level', 'deuterium_storage_level', 'terraformer_level', 'alliance_depot_level', 'fields', 'laboratory_activity', 'metal_mine_activity', 'crystal_mine_activity', 'deuterium_synthesizer_activity', 'solar_plant_activity', 'fusion_plant_activity');
        $snapshot['production'] = [];
        $snapshot['nextCosts'] = [];
        $snapshot['canUpgrade'] = [];

        foreach (self::BUILDINGS as $building => $definition) {
            $level = (int) $planet->{$definition['level']};
            $queuedLevels = $planet->buildingQueue->where('building_key', $building)->count();
            $projectedLevel = $level + $queuedLevels;
            $snapshot['production'][$building] = $this->buildingProduction($planet, $building);
            $snapshot['nextCosts'][$building] = $this->upgradeCost($building, $projectedLevel);
            $snapshot['nextCosts'][$building]['energy'] = $building === 'terraformer' ? 1000 : 0;
            $cost = $snapshot['nextCosts'][$building];
            $requirementsMet = match ($building) {
                'fusion' => (int) $planet->deuterium_synthesizer_level >= 5 && $energyTechnologyLevel >= 3,
                'nano_factory' => ((int) $planet->robot_factory_level + $planet->buildingQueue->where('building_key', 'robot_factory')->count()) >= 10 && $computerTechnologyLevel >= 10,
                'terraformer' => $energyTechnologyLevel >= 12,
                'alliance_depot' => $this->hasAlliance($planet),
                default => true,
            };
            $snapshot['canUpgrade'][$building] = $requirementsMet
                && (! isset($cost['energy']) || $this->energyBalance($planet)['supply'] >= $cost['energy'])
                && (int) $planet->metal >= $cost['metal']
                && (int) $planet->crystal >= $cost['crystal']
                && (int) $planet->deuterium >= $cost['deuterium'];
        }

            $snapshot['requirements']['fusion'] = 'Synthétiseur de deutérium niveau 5 · Technologie énergie niveau 3';
            $snapshot['requirements']['alliance_depot'] = 'Rejoignez une alliance pour construire un dépôt qui accepte le stationnement des flottes alliées.';

        $snapshot['queue'] = $planet->buildingQueue()->get()->map(fn (PlanetBuildingQueue $queuedBuilding) => [
            ...$queuedBuilding->only('id', 'building_key', 'building_code', 'target_level', 'position'),
            'starts_at' => $queuedBuilding->starts_at->toIso8601String(),
            'completes_at' => $queuedBuilding->completes_at->toIso8601String(),
            'duration_seconds' => max(1, $queuedBuilding->starts_at->diffInSeconds($queuedBuilding->completes_at)),
        ])->values();
        $snapshot['energy'] = $this->energyBalance($planet);
        $snapshot['maxFields'] = (int) $planet->fields;

        return $snapshot;
    }

    /**
     * Changes a production-capable building's activity from 0 to 10 after accruing production at the old rate.
     *
     * The planet row is ownership-scoped and locked within a retryable transaction; invalid building/activity
     * combinations raise a validation exception. Returns the updated locked planet.
     */
    public function setActivity(Planet $planet, string $building, int $activity): Planet
    {
        if (! isset(self::BUILDINGS[$building]) || self::BUILDINGS[$building]['activity'] === null || $activity < 0 || $activity > 10) {
            throw ValidationException::withMessages(['activity' => 'Ce taux de production n’est pas valide.']);
        }

        return DB::transaction(function () use ($planet, $building, $activity): Planet {
            $lockedPlanet = Planet::query()
                ->whereKey($planet->id)
                ->where('user_id', $planet->user_id)
                ->where('universe_id', $planet->universe_id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->materializeLockedPlanet($lockedPlanet);
            $lockedPlanet->{self::BUILDINGS[$building]['activity']} = $activity;
            $lockedPlanet->save();

            return $lockedPlanet;
        }, 3);
    }

    /**
     * Validates and queues one building level, charging its full resource cost immediately.
     *
     * Locks the planet, settles completed work and accrued production, then rechecks requirements,
     * field/queue limits, energy and affordability before persisting the queue entry. The next level
     * accounts for same-building entries already queued; failures leave the transaction unapplied.
     */
    public function upgrade(Planet $planet, string $resource, int $energyTechnologyLevel = 0, int $computerTechnologyLevel = 0): Planet
    {
        if (! array_key_exists($resource, self::BUILDINGS)) {
            throw ValidationException::withMessages(['building' => 'Ce bâtiment est inconnu.']);
        }

        return DB::transaction(function () use ($planet, $resource, $energyTechnologyLevel, $computerTechnologyLevel): Planet {
            $lockedPlanet = Planet::query()
                ->whereKey($planet->id)
                ->where('user_id', $planet->user_id)
                ->where('universe_id', $planet->universe_id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->completeBuildingQueue($lockedPlanet);
            $this->materializeLockedPlanet($lockedPlanet);

            $definition = self::BUILDINGS[$resource];
            $levelColumn = $definition['level'];
            $level = (int) $lockedPlanet->{$levelColumn};
            $queue = $lockedPlanet->buildingQueue()->orderBy('position')->get();
            $queueCount = $queue->count();
            $queuedLevels = $queue->where('building_key', $resource)->count();
            $nextLevel = $level + $queuedLevels + 1;

            if ($nextLevel > self::MAX_BUILDING_LEVEL) {
                throw ValidationException::withMessages(['building' => 'Ce bâtiment a atteint son niveau maximal.']);
            }

            $projectedDeuteriumLevel = (int) $lockedPlanet->deuterium_synthesizer_level
                + $queue->where('building_key', 'deuterium')->count();

            if ($resource === 'fusion' && ($projectedDeuteriumLevel < 5 || $energyTechnologyLevel < 3)) {
                throw ValidationException::withMessages(['building' => 'La centrale de fusion nécessite un synthétiseur de deutérium niveau 5 et la technologie énergie niveau 3.']);
            }

            $projectedRobotFactoryLevel = (int) $lockedPlanet->robot_factory_level
                + $queue->where('building_key', 'robot_factory')->count();

            if ($resource === 'nano_factory' && ($projectedRobotFactoryLevel < 10 || $computerTechnologyLevel < 10)) {
                throw ValidationException::withMessages(['building' => 'L’usine de nanites nécessite une usine de robots niveau 10 et la technologie ordinateur niveau 10.']);
            }

            if ($resource === 'terraformer' && ($energyTechnologyLevel < 12 || $this->energyBalance($lockedPlanet)['supply'] < 1000)) {
                throw ValidationException::withMessages(['building' => 'Le terraformeur nécessite la technologie énergie niveau 12.']);
            }

            if ($resource === 'alliance_depot' && ! $this->hasAlliance($lockedPlanet)) {
                throw ValidationException::withMessages(['building' => 'Le dépôt d’alliance nécessite une alliance active dans cet univers.']);
            }

            if ($lockedPlanet->buildingQueue()->count() >= 5) {
                throw ValidationException::withMessages(['building' => 'La file de construction est pleine.']);
            }

            if (in_array($resource, ['laboratory', 'robot_factory', 'nano_factory', 'hangar', 'defence_center', 'missile_silo'], true)
                && $lockedPlanet->buildingQueue()->where('building_key', $resource)->exists()) {
                throw ValidationException::withMessages(['building' => 'Ce bâtiment est déjà en cours de construction.']);
            }

            $cost = $this->upgradeCost($resource, $nextLevel - 1);
            $energyCost = (int) ($cost['energy'] ?? 0);

            if ($energyCost > 0 && $this->energyBalance($lockedPlanet)['supply'] < $energyCost) {
                throw ValidationException::withMessages(['building' => 'Le terraformeur nécessite 1 000 unités d’énergie disponibles.']);
            }

            unset($cost['energy']);

            if ((int) $lockedPlanet->metal < $cost['metal'] || (int) $lockedPlanet->crystal < $cost['crystal'] || (int) $lockedPlanet->deuterium < $cost['deuterium']) {
                throw ValidationException::withMessages(['resources' => 'Les ressources de cette planète ne suffisent pas.']);
            }

            $lastCompletion = $queue->last()?->completes_at ?? now();
            $duration = $this->buildingDuration($cost, $lockedPlanet);
            $completesAt = $lastCompletion->copy()->addSeconds($duration);

            $lockedPlanet->metal = (int) $lockedPlanet->metal - $cost['metal'];
            $lockedPlanet->crystal = (int) $lockedPlanet->crystal - $cost['crystal'];
            $lockedPlanet->deuterium = (int) $lockedPlanet->deuterium - $cost['deuterium'];
            $lockedPlanet->save();

            $lockedPlanet->buildingQueue()->create([
                'universe_id' => $lockedPlanet->universe_id,
                'building_key' => $resource,
                'building_code' => $definition['code'],
                'target_level' => $nextLevel,
                'position' => $queueCount + 1,
                'cost_metal' => $cost['metal'],
                'cost_crystal' => $cost['crystal'],
                'cost_deuterium' => $cost['deuterium'],
                'starts_at' => $lastCompletion,
                'completes_at' => $completesAt,
            ]);

            return $lockedPlanet;
        }, 3);
    }

    /**
     * Calculates whole-second construction time from metal/crystal cost and the planet's factory levels.
     *
     * Missing planet data means zero factory levels; the returned duration is never less than one second.
     *
     * @param  array{metal: int, crystal: int, deuterium?: int, energy?: int}  $cost
     */
    public function buildingDuration(array $cost, ?Planet $planet = null): int
    {
        $duration = (($cost['metal'] + $cost['crystal']) / 2500) * 3600;
        $robotFactoryLevel = (int) ($planet?->robot_factory_level ?? 0);
        $nanoFactoryLevel = (int) ($planet?->nano_factory_level ?? 0);

        $duration /= $robotFactoryLevel + 1;
        $duration *= 0.5 ** $nanoFactoryLevel;

        return max(1, (int) floor($duration));
    }

    /**
     * Applies all due construction entries, grants terraformers five fields each, and compacts queue positions.
     *
     * This method persists completed levels and deletes completed queue rows; callers that need atomicity
     * with other planet changes must provide the surrounding transaction and row lock.
     */
    public function completeBuildingQueue(Planet $planet): void
    {
        $now = now();

        $planet = Planet::query()->whereKey($planet->id)->firstOrFail();
        $completedBuildings = $planet->buildingQueue()->where('completes_at', '<=', $now)->get();

        foreach ($completedBuildings as $queuedBuilding) {
            $levelColumn = self::BUILDINGS[$queuedBuilding->building_key]['level'];
            $planet->{$levelColumn} = max((int) $planet->{$levelColumn}, (int) $queuedBuilding->target_level);

            if ($queuedBuilding->building_key === 'terraformer') {
                $planet->fields = (int) $planet->fields + 5;
            }

            $queuedBuilding->delete();
        }

        if ($completedBuildings->isNotEmpty()) {
            $planet->save();
        }

        $remaining = $planet->buildingQueue()->orderBy('position')->get();
        foreach ($remaining as $index => $queuedBuilding) {
            $queuedBuilding->position = $index + 1;
            $queuedBuilding->save();
        }
    }

    /**
     * Returns each resource's storage capacity as floor(1,000,000 × 1.5^level).
     *
     * @return array{metal: int, crystal: int, deuterium: int}
     */
    public function storageCapacities(Planet $planet): array
    {
        $capacities = [];

        foreach (self::STORAGE_LEVELS as $resource => $levelColumn) {
            $capacities[$resource] = (int) floor(1_000_000 * (1.5 ** (int) $planet->{$levelColumn}));
        }

        return $capacities;
    }

    /**
     * Calculates a building's next cost by applying its configured exponential factor to the base price.
     *
     * Unknown building keys intentionally return zero costs; negative levels use the base-price multiplier.
     *
     * @return array<string, int>
     */
    public function upgradeCost(string $resource, int $level): array
    {
        if (! isset(self::LEGACY_PRICE_LIST[$resource])) {
            return ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];
        }

        $price = self::LEGACY_PRICE_LIST[$resource];
        $multiplier = $price['factor'] ** max(0, $level);

        return array_map(
            fn ($amount) => min(PHP_INT_MAX, (int) floor($amount * $multiplier)),
            $price['cost'],
        );
    }

    /**
     * Computes the base hourly mine output at a level, before activity, concentration, temperature and energy modifiers.
     *
     * Unknown resources and non-positive levels produce zero.
     */
    public function productionPerHour(string $resource, int $level): int
    {
        $baseProduction = match ($resource) {
            'metal' => 30,
            'crystal' => 20,
            'deuterium' => 10,
            default => 0,
        };

        return $level > 0 ? (int) floor($baseProduction * $level * (1.1 ** $level)) : 0;
    }

    /**
     * Computes a building's integer output using its configured level, activity, and resource-specific modifiers.
     *
     * Storage returns projected capacity including queued storage buildings; terraformers return field expansion.
     * Unknown keys return zero, and deuterium output is clamped to zero for sufficiently high temperatures.
     */
    public function buildingProduction(Planet $planet, string $building): int
    {
        $definition = self::BUILDINGS[$building] ?? null;

        if ($definition === null) {
            return 0;
        }

        $level = (int) $planet->{$definition['level']};
        $activity = $definition['activity'] === null ? 0 : (int) $planet->{$definition['activity']} / 10;

        if (isset(self::STORAGE_BUILDINGS[$building])) {
            $resource = self::STORAGE_BUILDINGS[$building];
            $storageLevel = (int) $planet->{self::STORAGE_LEVELS[$resource]};
            $projectedLevel = $storageLevel + $planet->buildingQueue->where('building_key', $building)->count();

            return (int) floor(1_000_000 * (1.5 ** $projectedLevel));
        }

        if ($building === 'terraformer') {
            return $level * 5;
        }

        if ($building === 'metal') {
            return (int) floor(30 * $level * (1.1 ** $level) * $activity * self::RESOURCE_MULTIPLIER * (int) $planet->metal_concentration / 100);
        }

        if ($building === 'crystal') {
            return (int) floor(20 * $level * (1.1 ** $level) * $activity * self::RESOURCE_MULTIPLIER * (int) $planet->crystal_concentration / 100);
        }

        if ($building === 'deuterium') {
            return max(0, (int) floor(10 * $level * (1.1 ** $level) * (-0.002 * $planet->max_temperature + 1.28) * $activity * self::RESOURCE_MULTIPLIER * (int) $planet->deuterium_concentration / 100));
        }

        if ($building === 'solar') {
            return (int) floor(20 * $level * (1.1 ** $level) * $activity * self::RESOURCE_MULTIPLIER);
        }

        return (int) floor(50 * $level * (1.1 ** $level) * $activity * self::RESOURCE_MULTIPLIER);
    }

    /**
     * Computes planet energy supply, mine demand, net balance, production percentage and storage capacities.
     *
     * Production is capped at 100 percent and at a floor of zero; a planet with no energy demand is treated
     * as operating at 100 percent production regardless of supply.
     *
     * @return array{ supply: int, sources: array<string, int>, usage: int, balance: int, productionLevel: int, capacity: array{metal: int, crystal: int, deuterium: int} }
     */
    public function energyBalance(Planet $planet): array
    {
        $usage = 0;
        foreach (['metal', 'crystal', 'deuterium'] as $building) {
            $level = (int) $planet->{self::BUILDINGS[$building]['level']};
            $activity = (int) $planet->{self::BUILDINGS[$building]['activity']} / 10;
            $baseUsage = $building === 'deuterium' ? 30 : 10;
            $usage += (int) ceil($baseUsage * $level * (1.1 ** $level) * $activity * self::RESOURCE_MULTIPLIER);
        }

        $sources = [
            'solar' => $this->buildingProduction($planet, 'solar'),
            'fusion' => $this->buildingProduction($planet, 'fusion'),
            'satellites' => max(0, (int) floor(((int) $planet->max_temperature / 4 + 20) * (int) $planet->unit_212 * self::RESOURCE_MULTIPLIER)),
        ];
        $supply = array_sum($sources);
        $productionLevel = $usage === 0 ? 100 : min(100, (int) floor(($supply / $usage) * 100));

        return [
            'supply' => $supply,
            'sources' => $sources,
            'usage' => $usage,
            'balance' => $supply - $usage,
            'productionLevel' => max(0, $productionLevel),
            'capacity' => $this->storageCapacities($planet),
        ];
    }

    /**
     * Returns rates, fractional-hour remainders, storage, fusion demand and the next queued production change.
     *
     * This is a read snapshot only: it does not accrue resources or modify the planet.
     *
     * @return array<string, mixed>
     */
    public function liveResourceSnapshot(Planet $planet): array
    {
        $productionLevel = $this->energyBalance($planet)['productionLevel'];
        $rates = [];
        $remainders = [];
        foreach (['metal', 'crystal', 'deuterium'] as $resource) {
            $rates[$resource] = $this->resourceProductionPerHour($planet, $resource, $productionLevel);
            $remainders[$resource] = (int) $planet->{$resource.'_production_remainder'};
        }

        $nextBuilding = $planet->buildingQueue()->min('completes_at');
        $nextSatellite = $planet->unitQueue()->where('unit_type', 'ships')->where('unit_code', 212)->min('completes_at');
        $nextChange = collect([$nextBuilding, $nextSatellite])->filter()->map(fn ($time) => Carbon::parse($time))->min();

        return [
            'updatedAt' => ($planet->resources_updated_at ?? now())->toIso8601String(),
            'rates' => $rates,
            'remainders' => $remainders,
            'capacities' => $this->storageCapacities($planet),
            'fusionUsage' => $this->fusionConsumptionPerHour($planet),
            'fusionRemainder' => (int) $planet->fusion_consumption_remainder,
            'nextChangeAt' => $nextChange?->toIso8601String(),
        ];
    }

    /**
     * Combines a mine's energy-scaled output with the resource's fixed basic income.
     */
    private function resourceProductionPerHour(Planet $planet, string $resource, int $productionLevel): int
    {
        return (int) floor($this->buildingProduction($planet, $resource) * $productionLevel / 100)
            + self::BASIC_INCOME[$resource] * self::RESOURCE_MULTIPLIER;
    }

    private function hasAlliance(Planet $planet): bool
    {
        return $planet->user()->whereNotNull('alliance_id')->whereHas('alliance', fn ($query) => $query->where('universe_id', $planet->universe_id))->exists();
    }

    /**
     * Calculates fusion-plant deuterium use; consumption is disabled until a level-five synthesizer exists.
     */
    private function fusionConsumptionPerHour(Planet $planet): int
    {
        if ((int) $planet->fusion_plant_level === 0 || (int) $planet->deuterium_synthesizer_level < 5) {
            return 0;
        }

        return (int) floor(10 * (int) $planet->fusion_plant_level * (1.1 ** (int) $planet->fusion_plant_level) * ((int) $planet->fusion_plant_activity / 10) * self::RESOURCE_MULTIPLIER);
    }

    /**
     * Settles production around completed solar satellites so the new energy source affects only later time.
     *
     * The caller must hold the planet lock inside a transaction. Completed satellite queue rows are locked,
     * applied once, and removed; the timestamp and remaining queue positions are persisted as needed.
     */
    private function materializeLockedPlanet(Planet $planet, ?Carbon $now = null): void
    {
        $now ??= now();
        $lastUpdated = $planet->resources_updated_at ?? $now;
        $completedSatellites = $planet->unitQueue()
            ->where('unit_type', 'ships')
            ->where('unit_code', 212)
            ->where('completes_at', '<=', $now)
            ->reorder('completes_at')
            ->lockForUpdate()
            ->get();

        foreach ($completedSatellites as $entry) {
            if ($entry->completes_at->greaterThan($lastUpdated)) {
                $this->accrueProduction($planet, (int) $lastUpdated->diffInSeconds($entry->completes_at));
                $lastUpdated = $entry->completes_at;
            }

            $planet->unit_212 = (int) $planet->unit_212 + (int) $entry->quantity - (int) $entry->quantity_completed;
            $entry->delete();
        }

        $elapsedSeconds = max(0, $lastUpdated->diffInSeconds($now, false));
        if ($elapsedSeconds > 0) {
            $this->accrueProduction($planet, $elapsedSeconds);
        }

        if ($elapsedSeconds > 0 || $completedSatellites->isNotEmpty()) {
            $planet->resources_updated_at = $now->greaterThan($lastUpdated) ? $now : $lastUpdated;
            $planet->save();
        }

        if ($completedSatellites->isNotEmpty()) {
            foreach ($planet->unitQueue()->orderBy('position')->get() as $position => $entry) {
                $entry->position = $position + 1;
                $entry->save();
            }
        }
    }

    /**
     * Accrues resources and fusion fuel for an elapsed interval, retaining sub-unit hourly remainders.
     *
     * Production uses the energy balance after due buildings complete, caps stocks at 110% of storage,
     * clears remainders when capped or arithmetic would overflow, and never lets deuterium fall below zero.
     */
    private function accrueProduction(Planet $planet, int $elapsedSeconds): void
    {
        $this->completeBuildingQueue($planet);
        $energy = $this->energyBalance($planet);
        $productionLevel = $energy['productionLevel'];

        $capacities = $this->storageCapacities($planet);

        foreach (['metal', 'crystal', 'deuterium'] as $resource) {
            $remainderColumn = $resource.'_production_remainder';
            $hourlyProduction = $this->resourceProductionPerHour($planet, $resource, $productionLevel);
            $safeElapsedSeconds = $hourlyProduction > 0
                ? min($elapsedSeconds, intdiv(PHP_INT_MAX - (int) $planet->{$remainderColumn}, $hourlyProduction))
                : 0;
            $total = $hourlyProduction * $safeElapsedSeconds + (int) $planet->{$remainderColumn};
            $addition = intdiv($total, 3600);

            $maximumStorage = (int) ($capacities[$resource] * 1.1);
            $currentStock = (int) $planet->{$resource};
            $planet->{$resource} = $currentStock + min($addition, max(0, $maximumStorage - $currentStock));
            $planet->{$remainderColumn} = (int) $planet->{$resource} >= $maximumStorage
                ? 0
                : (($elapsedSeconds > $safeElapsedSeconds) ? 0 : $total % 3600);
        }

        $deuteriumUsage = $this->fusionConsumptionPerHour($planet);
        if ($deuteriumUsage > 0) {
            $remainder = (int) $planet->fusion_consumption_remainder;
            $safeElapsedSeconds = min($elapsedSeconds, intdiv(PHP_INT_MAX - $remainder, $deuteriumUsage));
            $total = $deuteriumUsage * $safeElapsedSeconds + $remainder;
            $planet->deuterium = max(0, (int) $planet->deuterium - intdiv($total, 3600));
            $planet->fusion_consumption_remainder = (int) $planet->deuterium === 0 || $safeElapsedSeconds < $elapsedSeconds
                ? 0
                : $total % 3600;
        }
    }
}
