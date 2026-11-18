<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Implements the UnitProductionService application service.
 */

namespace App\Services;

use App\Models\Planet;
use App\Models\PlanetUnitQueue;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnitProductionService
{
    /**
     * Injects the economy service used to apply due satellites before their production effect is observed.
     */
    public function __construct(private PlanetEconomy $economy) {}

    /**
     * Exposes ship and defence definitions for administrative element operations.
     *
     * @return array{ships: array<int, array<string, mixed>>, defences: array<int, array<string, mixed>>}
     */
    public function administrativeUnits(): array
    {
        return ['ships' => self::SHIPS, 'defences' => self::DEFENCES];
    }

    /**
     * Returns officer definitions keyed by their stable unit codes for administrative updates.
     *
     * @return array<int, array<string, mixed>>
     */
    public function administrativeOfficers(): array
    {
        return self::OFFICERS;
    }

    /**
     * Maps supported ship/defence codes to their persisted planet inventory columns.
     *
     * @return array<int, string>
     */
    public function administrativeUnitColumns(): array
    {
        return self::COLUMNS;
    }

    /**
     * Maps officer codes to the user-level columns updated when recruitment completes.
     *
     * @return array<int, string>
     */
    public function administrativeOfficerColumns(): array
    {
        return self::OFFICER_COLUMNS;
    }

    private const SHIPS = [
        202 => ['key' => 'small_cargo', 'name' => 'Petit transporteur', 'rarity' => 'ordinary', 'cost' => [2000, 2000, 0], 'requirements' => [21 => 2, 14 => 1, 115 => 2]],
        203 => ['key' => 'large_cargo', 'name' => 'Grand transporteur', 'rarity' => 'ordinary', 'cost' => [6000, 6000, 0], 'requirements' => [21 => 4, 14 => 1, 115 => 6]],
        204 => ['key' => 'light_fighter', 'name' => 'Chasseur léger', 'rarity' => 'ordinary', 'cost' => [3000, 1000, 0], 'requirements' => [21 => 1, 14 => 1, 115 => 1]],
        205 => ['key' => 'heavy_fighter', 'name' => 'Chasseur lourd', 'rarity' => 'remarkable', 'cost' => [6000, 4000, 0], 'requirements' => [21 => 3, 14 => 1, 111 => 2, 117 => 2]],
        206 => ['key' => 'cruiser', 'name' => 'Croiseur', 'rarity' => 'exceptional', 'cost' => [20000, 7000, 2000], 'requirements' => [21 => 5, 14 => 1, 117 => 4, 121 => 2]],
        207 => ['key' => 'battleship', 'name' => 'Vaisseau de bataille', 'rarity' => 'exceptional', 'cost' => [45000, 15000, 0], 'requirements' => [21 => 7, 14 => 1, 118 => 4]],
        208 => ['key' => 'colony_ship', 'name' => 'Vaisseau de colonisation', 'rarity' => 'remarkable', 'cost' => [10000, 20000, 10000], 'requirements' => [21 => 4, 14 => 1, 117 => 3]],
        209 => ['key' => 'recycler', 'name' => 'Recycleur', 'rarity' => 'remarkable', 'cost' => [10000, 6000, 2000], 'requirements' => [21 => 4, 14 => 1, 115 => 6, 110 => 2]],
        210 => ['key' => 'espionage_probe', 'name' => 'Sonde espionnage', 'rarity' => 'epic', 'cost' => [0, 1000, 0], 'requirements' => [21 => 3, 14 => 1, 115 => 3, 106 => 2]],
        211 => ['key' => 'bomber', 'name' => 'Bombardier', 'rarity' => 'exceptional', 'cost' => [50000, 25000, 15000], 'requirements' => [117 => 6, 21 => 8, 14 => 1, 122 => 5]],
        212 => ['key' => 'solar_satellite', 'name' => 'Satellite solaire', 'rarity' => 'ordinary', 'cost' => [0, 2000, 500], 'requirements' => [21 => 1, 14 => 1]],
        213 => ['key' => 'destroyer', 'name' => 'Destructeur', 'rarity' => 'epic', 'cost' => [60000, 50000, 15000], 'requirements' => [21 => 9, 14 => 1, 118 => 6, 114 => 5]],
        214 => ['key' => 'deathstar', 'name' => 'Étoile de la mort', 'rarity' => 'legendary', 'cost' => [5000000, 4000000, 1000000], 'requirements' => [21 => 12, 14 => 1, 118 => 7, 114 => 6, 199 => 1]],
        215 => ['key' => 'battlecruiser', 'name' => 'Traqueur', 'rarity' => 'legendary', 'cost' => [30000, 40000, 15000], 'requirements' => [114 => 5, 120 => 12, 118 => 5, 21 => 8, 14 => 1]],
        216 => ['key' => 'supernova', 'name' => 'SuperNova', 'rarity' => 'mythic', 'cost' => [100000, 50000, 20000], 'requirements' => [614 => 1]],
        217 => ['key' => 'planetary_destroyer', 'name' => 'Destructeur planétaire', 'rarity' => 'signature', 'cost' => [1000000, 1000000, 500000], 'requirements' => [615 => 1], 'unique' => true, 'uniqueEmpire' => true],
    ];

    private const DEFENCES = [
        401 => ['key' => 'rocket_launcher', 'name' => 'Lanceur de missiles', 'rarity' => 'ordinary', 'cost' => [2000, 0, 0], 'requirements' => [21 => 1]],
        402 => ['key' => 'light_laser', 'name' => 'Artillerie laser légère', 'rarity' => 'remarkable', 'cost' => [1500, 500, 0], 'requirements' => [113 => 1, 21 => 2, 120 => 3]],
        403 => ['key' => 'heavy_laser', 'name' => 'Artillerie laser lourde', 'rarity' => 'remarkable', 'cost' => [6000, 2000, 0], 'requirements' => [113 => 3, 21 => 4, 120 => 6]],
        404 => ['key' => 'gauss_cannon', 'name' => 'Canon de Gauss', 'rarity' => 'exceptional', 'cost' => [20000, 15000, 2000], 'requirements' => [21 => 6, 113 => 6, 109 => 3, 110 => 1]],
        405 => ['key' => 'ion_cannon', 'name' => 'Canon à ions', 'rarity' => 'epic', 'cost' => [2000, 6000, 0], 'requirements' => [21 => 4, 121 => 4]],
        406 => ['key' => 'plasma_turret', 'name' => 'Lanceur de plasma', 'rarity' => 'legendary', 'cost' => [50000, 50000, 30000], 'requirements' => [21 => 8, 122 => 7]],
        407 => ['key' => 'small_shield_dome', 'name' => 'Petit bouclier', 'rarity' => 'epic', 'cost' => [10000, 10000, 0], 'requirements' => [110 => 2, 21 => 1], 'unique' => true],
        408 => ['key' => 'large_shield_dome', 'name' => 'Grand bouclier', 'rarity' => 'legendary', 'cost' => [50000, 50000, 0], 'requirements' => [110 => 6, 21 => 6], 'unique' => true],
        409 => ['key' => 'planetary_protector', 'name' => 'Protecteur planétaire', 'rarity' => 'mythic', 'cost' => [250000, 100000, 25000], 'requirements' => [609 => 1], 'unique' => true],
        502 => ['key' => 'anti_ballistic_missile', 'name' => 'Missile d’interception', 'rarity' => 'exceptional', 'cost' => [8000, 2000, 0], 'requirements' => [44 => 2]],
        503 => ['key' => 'interplanetary_missile', 'name' => 'Missile interplanétaire', 'rarity' => 'exceptional', 'cost' => [12500, 2500, 10000], 'requirements' => [44 => 4]],
    ];

    private const COLUMNS = [
        202 => 'unit_202', 203 => 'unit_203', 204 => 'unit_204', 205 => 'unit_205', 206 => 'unit_206',
        207 => 'unit_207', 208 => 'unit_208', 209 => 'unit_209', 210 => 'unit_210', 211 => 'unit_211',
        212 => 'unit_212', 213 => 'unit_213', 214 => 'unit_214', 215 => 'unit_215', 216 => 'unit_216',
        217 => 'unit_217', 401 => 'unit_401',
        402 => 'unit_402', 403 => 'unit_403', 404 => 'unit_404', 405 => 'unit_405', 406 => 'unit_406',
        407 => 'unit_407', 408 => 'unit_408', 409 => 'unit_409', 502 => 'unit_502', 503 => 'unit_503',
    ];

    private const RESEARCH_COLUMNS = [
        106 => 'spy_technology_level', 108 => 'computer_technology_level', 109 => 'military_technology_level',
        110 => 'defence_technology_level', 111 => 'shield_technology_level', 113 => 'energy_technology_level',
        114 => 'hyperspace_technology_level', 115 => 'combustion_technology_level', 117 => 'impulse_technology_level',
        118 => 'hyperspace_drive_level', 120 => 'laser_technology_level', 121 => 'ion_technology_level',
        122 => 'plasma_technology_level', 123 => 'intergalactic_research_level', 124 => 'expedition_technology_level',
        199 => 'graviton_technology_level',
    ];

    private const BUILDING_COLUMNS = [14 => 'robot_factory_level', 15 => 'nano_factory_level', 21 => 'hangar_level', 31 => 'laboratory_level', 44 => 'missile_silo_level'];

    private const OFFICER_COLUMNS = [
        601 => 'officer_geologist_level', 602 => 'officer_admiral_level', 603 => 'officer_engineer_level',
        604 => 'officer_technocrat_level', 605 => 'officer_constructor_level', 606 => 'officer_scientist_level',
        607 => 'officer_stockman_level', 608 => 'officer_defender_level', 609 => 'officer_bunker_level',
        610 => 'officer_spy_level', 611 => 'officer_commander_level', 612 => 'officer_destroyer_level',
        613 => 'officer_general_level', 614 => 'officer_raider_level', 615 => 'officer_emperor_level',
    ];

    private const OFFICERS = [
        601 => ['key' => 'geologist', 'name' => 'Géologue', 'rarity' => 'remarkable', 'max' => 20, 'requirements' => []],
        602 => ['key' => 'admiral', 'name' => 'Amiral', 'rarity' => 'remarkable', 'max' => 20, 'requirements' => []],
        603 => ['key' => 'engineer', 'name' => 'Ingénieur', 'rarity' => 'exceptional', 'max' => 10, 'requirements' => [601 => 5]],
        604 => ['key' => 'technocrat', 'name' => 'Technocrate', 'rarity' => 'epic', 'max' => 10, 'requirements' => [602 => 5]],
        605 => ['key' => 'constructor', 'name' => 'Constructeur', 'rarity' => 'exceptional', 'max' => 3, 'requirements' => [601 => 10, 603 => 2]],
        606 => ['key' => 'scientist', 'name' => 'Scientifique', 'rarity' => 'epic', 'max' => 3, 'requirements' => [601 => 10, 603 => 2]],
        607 => ['key' => 'stockman', 'name' => 'Stockeur', 'rarity' => 'epic', 'max' => 2, 'requirements' => [605 => 1]],
        608 => ['key' => 'defender', 'name' => 'Défenseur', 'rarity' => 'legendary', 'max' => 2, 'requirements' => [606 => 1]],
        609 => ['key' => 'bunker', 'name' => 'Bunker', 'rarity' => 'legendary', 'max' => 1, 'requirements' => [601 => 20, 603 => 10, 605 => 3, 606 => 3, 607 => 2, 608 => 2]],
        610 => ['key' => 'spy', 'name' => 'Espion', 'rarity' => 'epic', 'max' => 2, 'requirements' => [602 => 10, 604 => 5]],
        611 => ['key' => 'commander', 'name' => 'Commandant', 'rarity' => 'legendary', 'max' => 2, 'requirements' => [602 => 10, 604 => 5]],
        612 => ['key' => 'destroyer', 'name' => 'Destructeur', 'rarity' => 'legendary', 'max' => 1, 'requirements' => [610 => 1]],
        613 => ['key' => 'general', 'name' => 'Général', 'rarity' => 'legendary', 'max' => 3, 'requirements' => [611 => 1]],
        614 => ['key' => 'raider', 'name' => 'Raideur', 'rarity' => 'mythic', 'max' => 1, 'requirements' => [602 => 20, 604 => 10, 610 => 2, 611 => 2, 612 => 1, 613 => 3]],
        615 => ['key' => 'emperor', 'name' => 'Empereur', 'rarity' => 'signature', 'max' => 1, 'requirements' => [614 => 1, 609 => 1]],
    ];

    /**
     * Describes ship or defence build options using planet inventory, pending quantities, and user technologies.
     *
     * Includes empire-wide uniqueness checks where configured; this is a presentation snapshot, so build()
     * repeats all consequential checks under locks before charging resources.
     *
     * @return array{type: string, units: array<int, array<string, mixed>>}
     */
    public function catalog(Planet $planet, User $user, string $type): array
    {
        $definitions = $type === 'ships' ? self::SHIPS : self::DEFENCES;
        $units = [];

        foreach ($definitions as $code => $definition) {
            $queueCount = (int) $planet->unitQueue()->where('unit_code', $code)
                ->selectRaw('COALESCE(SUM(quantity - quantity_completed), 0) as remaining')->value('remaining');
            $count = (int) $planet->{self::COLUMNS[$code]};
            $uniqueEmpireAvailable = true;
            if ($definition['uniqueEmpire'] ?? false) {
                $count = (int) Planet::query()->where('user_id', $user->id)
                    ->where('universe_id', $user->universe_id)->sum(self::COLUMNS[$code]);
                $queueCount = (int) PlanetUnitQueue::query()->where('unit_code', $code)->where('unit_type', $type)
                    ->whereHas('planet', fn ($query) => $query->where('user_id', $user->id)->where('universe_id', $user->universe_id))
                    ->selectRaw('COALESCE(SUM(quantity - quantity_completed), 0) as remaining')->value('remaining');
                $uniqueEmpireAvailable = $count === 0 && $queueCount === 0;
            }

            $requirements = [];
            $requirementsMet = true;

            foreach ($definition['requirements'] as $requirementCode => $requiredLevel) {
                $currentLevel = $this->requirementLevel($requirementCode, $planet, $user);
                $requirements[] = ['code' => $requirementCode, 'level' => $requiredLevel, 'current' => $currentLevel];
                $requirementsMet = $requirementsMet && $currentLevel >= $requiredLevel;
            }

            $hangarLevel = (int) $planet->hangar_level;
            $robotFactoryLevel = (int) $planet->robot_factory_level;
            $requiredHangarLevel = (int) ($definition['requirements'][21] ?? 0);
            $requirements[] = ['code' => 21, 'level' => max(1, $requiredHangarLevel), 'current' => $hangarLevel];
            $requirements[] = ['code' => 14, 'level' => 1, 'current' => $robotFactoryLevel];
            $requirementsMet = $requirementsMet && $hangarLevel >= max(1, $requiredHangarLevel) && $robotFactoryLevel >= 1;

            $unitCost = $this->costFor($definition, $user);
            $cost = ['metal' => $unitCost[0], 'crystal' => $unitCost[1], 'deuterium' => $unitCost[2]];
            $affordable = true;
            foreach ($cost as $resource => $amount) {
                $affordable = $affordable && ($amount === 0 || (int) $planet->{$resource} >= $amount);
            }
            if ($definition['unique'] ?? false) {
                $affordable = $affordable && $uniqueEmpireAvailable
                    && (($definition['uniqueEmpire'] ?? false) || ((int) $planet->{self::COLUMNS[$code]} === 0 && $queueCount === 0));
            }

            $maxAffordable = PHP_INT_MAX;
            foreach ($cost as $resource => $amount) {
                if ($amount > 0) {
                    $maxAffordable = min($maxAffordable, intdiv((int) $planet->{$resource}, $amount));
                }
            }
            $maxAffordable = $maxAffordable === PHP_INT_MAX ? 0 : min(100000, $maxAffordable);
            $canBuild = $requirementsMet && $affordable && $uniqueEmpireAvailable && $planet->unitQueue()->count() < 5;

            $units[] = [
                'code' => $code, 'key' => $definition['key'], 'name' => $definition['name'],
                'rarity' => $definition['rarity'],
                'count' => $count, 'queued' => $queueCount,
                'cost' => $cost,
                'durationSeconds' => $this->duration($unitCost, $planet, $user, $type),
                'requirements' => $requirements,
                'requirementsMet' => $requirementsMet,
                'maxAffordable' => $maxAffordable,
                'canBuild' => $canBuild && (! ($definition['unique'] ?? false) || ((int) $planet->{self::COLUMNS[$code]} === 0 && $queueCount === 0)),
                'unique' => (bool) ($definition['unique'] ?? false),
            ];
        }

        return ['type' => $type, 'units' => $units];
    }

    /**
     * Charges a planet for a validated ship/defence order and appends it to its five-entry production queue.
     *
     * Locks both user and owned planet, applies due work, then rechecks prerequisites, uniqueness, queue space,
     * and affordability. Unique items are forced to quantity one; costs are deducted immediately and completion
     * is scheduled after the preceding entry. Any rejected order rolls back all changes.
     */
    public function build(User $user, Planet $planet, string $type, int $code, int $quantity): void
    {
        $definitions = $type === 'ships' ? self::SHIPS : ($type === 'defences' ? self::DEFENCES : []);
        if (! isset($definitions[$code]) || $quantity < 1 || $quantity > 100000) {
            throw ValidationException::withMessages(['unit' => 'Cette unité ou quantité est invalide.']);
        }

        DB::transaction(function () use ($user, $planet, $type, $code, $quantity, $definitions): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedPlanet = Planet::query()->whereKey($planet->id)->where('user_id', $user->id)
                ->where('universe_id', $lockedUser->universe_id)->lockForUpdate()->firstOrFail();
            $this->complete($lockedPlanet);
            $lockedPlanet->refresh();
            $definition = $definitions[$code];

            $requiredHangarLevel = (int) ($definition['requirements'][21] ?? 1);
            if ((int) $lockedPlanet->hangar_level < max(1, $requiredHangarLevel)
                || (int) $lockedPlanet->robot_factory_level < 1) {
                throw ValidationException::withMessages(['unit' => 'Le chantier spatial et l’usine de robots sont requis pour produire.']);
            }

            foreach ($definition['requirements'] as $requirementCode => $requiredLevel) {
                $currentLevel = $this->requirementLevel($requirementCode, $lockedPlanet, $lockedUser);
                if ($currentLevel < $requiredLevel) {
                    throw ValidationException::withMessages(['unit' => 'Les prérequis de cette unité ne sont pas remplis.']);
                }
            }

            $column = self::COLUMNS[$code];
            if (($definition['unique'] ?? false) && ! ($definition['uniqueEmpire'] ?? false)
                && ((int) $lockedPlanet->{$column} > 0 || $lockedPlanet->unitQueue()->where('unit_code', $code)->exists())) {
                throw ValidationException::withMessages(['unit' => 'Cette défense ne peut être construite qu’une seule fois.']);
            }

            if (($definition['uniqueEmpire'] ?? false)
                && (Planet::query()->where('user_id', $lockedUser->id)->where('universe_id', $lockedUser->universe_id)
                    ->where($column, '>', 0)->exists()
                    || PlanetUnitQueue::query()->where('unit_code', $code)->where('unit_type', $type)
                        ->whereHas('planet', fn ($query) => $query->where('user_id', $lockedUser->id)->where('universe_id', $lockedUser->universe_id))
                        ->exists())) {
                throw ValidationException::withMessages(['unit' => 'Cette unité ne peut être construite qu’une seule fois à l’échelle de l’empire.']);
            }

            if ($lockedPlanet->unitQueue()->count() >= 5) {
                throw ValidationException::withMessages(['unit' => 'La file de production est pleine.']);
            }

            $quantity = ($definition['unique'] ?? false) ? 1 : $quantity;
            $unitCost = $this->costFor($definition, $lockedUser);
            $cost = [
                'metal' => $unitCost[0] * $quantity,
                'crystal' => $unitCost[1] * $quantity,
                'deuterium' => $unitCost[2] * $quantity,
            ];

            foreach ($cost as $resource => $amount) {
                if ((int) $lockedPlanet->{$resource} < $amount) {
                    throw ValidationException::withMessages(['resources' => 'Les ressources de cette planète ne suffisent pas.']);
                }
            }

            $queue = $lockedPlanet->unitQueue()->orderBy('position')->get();
            $startsAt = $queue->last()?->completes_at ?? now();
            $completesAt = $startsAt->copy()->addSeconds($this->duration($unitCost, $lockedPlanet, $lockedUser, $type) * $quantity);
            $lockedPlanet->metal -= $cost['metal'];
            $lockedPlanet->crystal -= $cost['crystal'];
            $lockedPlanet->deuterium -= $cost['deuterium'];
            $lockedPlanet->save();

            $lockedPlanet->unitQueue()->create([
                'universe_id' => $lockedPlanet->universe_id,
                'unit_code' => $code,
                'unit_type' => $type,
                'quantity' => $quantity,
                'quantity_completed' => 0,
                'position' => $queue->count() + 1,
                'cost_metal' => $cost['metal'],
                'cost_crystal' => $cost['crystal'],
                'cost_deuterium' => $cost['deuterium'],
                'starts_at' => $startsAt,
                'completes_at' => $completesAt,
            ]);
        }, 3);
    }

    /**
     * Applies all due unit and officer queue entries and normalizes the remaining queue positions.
     *
     * A planet lock and retryable transaction prevent duplicate completion. Due solar satellites are first
     * materialized through the economy service so their energy contribution is time-aligned with production.
     */
    public function complete(Planet $planet): void
    {
        DB::transaction(function () use ($planet): void {
            $lockedPlanet = Planet::query()->whereKey($planet->id)->lockForUpdate()->firstOrFail();
            $now = now();
            $ready = $lockedPlanet->unitQueue()->where('completes_at', '<=', $now)->orderBy('position')->get();

            if ($ready->contains(fn ($entry): bool => $entry->unit_type === 'ships' && (int) $entry->unit_code === 212)) {
                $lockedPlanet = $this->economy->materialize($lockedPlanet, $now);
                $ready = $lockedPlanet->unitQueue()->where('completes_at', '<=', $now)->orderBy('position')->get();
            }

            foreach ($ready as $entry) {
                if ($entry->unit_type === 'officers') {
                    $this->completeOfficerQueueEntry($lockedPlanet, $entry);
                } else {
                    $column = self::COLUMNS[$entry->unit_code];
                    $lockedPlanet->{$column} = (int) $lockedPlanet->{$column} + (int) $entry->quantity - (int) $entry->quantity_completed;
                }
                $entry->delete();
            }

            if ($ready->isNotEmpty()) {
                $lockedPlanet->save();
            }

            foreach ($lockedPlanet->unitQueue()->orderBy('position')->get() as $position => $entry) {
                $entry->position = $position + 1;
                $entry->save();
            }
        }, 3);
    }

    /**
     * Returns the defence-only build catalog for the supplied planet and user.
     *
     * @return array{type: string, units: array<int, array<string, mixed>>}
     */
    public function defenceCatalog(Planet $planet, User $user): array
    {
        return $this->catalog($planet, $user, 'defences');
    }

    /**
     * Returns the planet's queued defence entries in queue order as client-facing data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function defencesQueueSnapshot(Planet $planet): array
    {
        return $this->queueSnapshot($planet, 'defences');
    }

    /**
     * Returns the planet's queued officer recruitments in queue order as client-facing data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function officersQueueSnapshot(Planet $planet): array
    {
        return $this->queueSnapshot($planet, 'officers');
    }

    /**
     * Builds empire-wide officer progression options, including prerequisites, caps, and remaining points.
     *
     * @return array{officers: array<int, array<string, mixed>>, points: int}
     */
    public function officerCatalog(User $user): array
    {
        $officers = [];
        foreach (self::OFFICERS as $code => $officer) {
            $column = $this->officerColumn($code);
            $requirementsMet = true;
            $requirements = [];
            foreach ($officer['requirements'] as $requiredCode => $requiredLevel) {
                $requiredColumn = self::OFFICER_COLUMNS[$requiredCode];
                $current = (int) $user->{$requiredColumn};
                $requirements[] = ['code' => $requiredCode, 'name' => self::OFFICERS[$requiredCode]['name'], 'level' => $requiredLevel, 'current' => $current];
                $requirementsMet = $requirementsMet && $current >= $requiredLevel;
            }

            $level = (int) $user->{$column};
            $officers[] = [
                'code' => $code, 'key' => $officer['key'], 'name' => $officer['name'],
                'rarity' => $officer['rarity'],
                'level' => $level, 'max' => $officer['max'], 'requirements' => $requirements,
                'requirementsMet' => $requirementsMet, 'canRecruit' => $requirementsMet && $level < $officer['max'],
            ];
        }

        return ['officers' => $officers, 'points' => (int) $user->officer_points];
    }

    /**
     * Spends one empire officer point and queues recruitment on an owned planet.
     *
     * The locked transaction rechecks prerequisites, level cap, and queue capacity before decrementing the point;
     * recruitment is serialized behind existing planet work and completes after one second.
     */
    public function recruit(User $user, Planet $planet, int $code): void
    {
        if (! isset(self::OFFICERS[$code])) {
            throw ValidationException::withMessages(['officer' => 'Cet officier est inconnu.']);
        }

        DB::transaction(function () use ($user, $planet, $code): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedPlanet = Planet::query()->whereKey($planet->id)->where('user_id', $user->id)
                ->where('universe_id', $lockedUser->universe_id)->lockForUpdate()->firstOrFail();
            $this->complete($lockedPlanet);
            $lockedPlanet->refresh();
            $officer = self::OFFICERS[$code];
            $column = $this->officerColumn($code);

            foreach ($officer['requirements'] as $requiredCode => $requiredLevel) {
                if ((int) $lockedUser->{self::OFFICER_COLUMNS[$requiredCode]} < $requiredLevel) {
                    throw ValidationException::withMessages(['officer' => 'Les prérequis de cet officier ne sont pas remplis.']);
                }
            }

            if ((int) $lockedUser->officer_points < 1) {
                throw ValidationException::withMessages(['officer_points' => 'Il ne vous reste aucun point d’officier.']);
            }
            if ((int) $lockedUser->{$column} >= $officer['max']) {
                throw ValidationException::withMessages(['officer' => 'Cet officier a atteint son niveau maximal.']);
            }

            if ($lockedPlanet->unitQueue()->count() >= 5) {
                throw ValidationException::withMessages(['officer' => 'La file de production est pleine.']);
            }

            $queue = $lockedPlanet->unitQueue()->orderBy('position')->get();
            $startsAt = $queue->last()?->completes_at ?? now();
            $completesAt = $startsAt->copy()->addSecond();
            $lockedUser->officer_points--;
            $lockedUser->save();
            $lockedPlanet->unitQueue()->create([
                'universe_id' => $lockedPlanet->universe_id,
                'unit_code' => $code,
                'unit_type' => 'officers',
                'quantity' => 1,
                'quantity_completed' => 0,
                'position' => $queue->count() + 1,
                'cost_metal' => 0,
                'cost_crystal' => 0,
                'cost_deuterium' => 0,
                'starts_at' => $startsAt,
                'completes_at' => $completesAt,
            ]);
        }, 3);
    }

    /**
     * Resolves a prerequisite code to its storage scope and column; unknown codes are invalid configuration.
     *
     * @return array{0: 'user'|'planet', 1: string}
     */
    private function requirementColumn(int $code): array
    {
        if (isset(self::RESEARCH_COLUMNS[$code])) {
            return ['user', self::RESEARCH_COLUMNS[$code]];
        }

        if (isset(self::OFFICER_COLUMNS[$code])) {
            return ['user', self::OFFICER_COLUMNS[$code]];
        }

        if (isset(self::BUILDING_COLUMNS[$code])) {
            return ['planet', self::BUILDING_COLUMNS[$code]];
        }

        throw ValidationException::withMessages(['unit' => 'Configuration de prérequis invalide.']);
    }

    /**
     * Reads a building prerequisite from the planet and research/officer prerequisites from the user.
     */
    private function requirementLevel(int $code, Planet $planet, User $user): int
    {
        [$scope, $column] = $this->requirementColumn($code);

        return (int) ($scope === 'user' ? $user->{$column} : $planet->{$column});
    }

    /**
     * Returns the configured unit cost, doubling planetary destroyer costs once per recorded empire loss.
     *
     * @param  array<string, mixed>  $definition
     * @return array<int, int>
     */
    private function costFor(array $definition, User $user): array
    {
        if ($definition['key'] !== 'planetary_destroyer') {
            return $definition['cost'];
        }

        $cost = $definition['cost'];
        $losses = (int) $user->planetary_destroyer_losses;
        foreach ($cost as &$resourceCost) {
            for ($loss = 0; $loss < $losses && $resourceCost < PHP_INT_MAX; $loss++) {
                $resourceCost = $resourceCost > intdiv(PHP_INT_MAX, 2)
                    ? PHP_INT_MAX
                    : $resourceCost * 2;
            }
        }
        unset($resourceCost);

        return $cost;
    }

    /**
     * Atomically increments the empire's destroyer-loss counter used to scale future replacement costs.
     */
    public function recordPlanetaryDestroyerLoss(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedUser->increment('planetary_destroyer_losses');
        }, 3);
    }

    /**
     * Applies one completed officer entry, restoring its point if prerequisites or the level cap no longer permit it.
     *
     * Requires the planet completion transaction; the associated user is locked before empire-wide state changes.
     */
    private function completeOfficerQueueEntry(Planet $planet, PlanetUnitQueue $entry): void
    {
        $officer = self::OFFICERS[$entry->unit_code];
        $user = User::query()->whereKey($planet->user_id)->lockForUpdate()->firstOrFail();
        $column = self::OFFICER_COLUMNS[$entry->unit_code];

        foreach ($officer['requirements'] as $requiredCode => $requiredLevel) {
            if ((int) $user->{self::OFFICER_COLUMNS[$requiredCode]} < $requiredLevel) {
                $user->officer_points++;
                $user->save();

                return;
            }
        }

        if ((int) $user->{$column} < $officer['max']) {
            $user->{$column}++;
            if ($entry->unit_code === 610) {
                $user->spy_technology_level += 5;
            } elseif ($entry->unit_code === 611) {
                $user->computer_technology_level += 3;
            }
            $user->save();
        } else {
            $user->officer_points++;
            $user->save();
        }
    }

    /**
     * Exposes the same duration calculation used when scheduling a production queue entry.
     *
     * @param  array<int, int>  $baseCosts  Per-unit metal, crystal, and deuterium costs.
     */
    public function estimateDuration(array $baseCosts, Planet $planet, User $user, string $type): int
    {
        return $this->duration($baseCosts, $planet, $user, $type);
    }

    /**
     * Serializes a supported production queue type, returning an empty list for unsupported types.
     *
     * @return array<int, array<string, mixed>>
     */
    public function queueSnapshot(Planet $planet, string $type): array
    {
        $definitions = match ($type) {
            'ships' => self::SHIPS,
            'defences' => self::DEFENCES,
            'officers' => self::OFFICERS,
            default => [],
        };

        if ($definitions === []) {
            return [];
        }

        return $planet->unitQueue()->where('unit_type', $type)->get()->map(fn (PlanetUnitQueue $entry): array => [
            'id' => $entry->id,
            'code' => $entry->unit_code,
            'name' => $definitions[$entry->unit_code]['name'],
            'unitType' => $entry->unit_type,
            'quantity' => $entry->quantity,
            'completed' => $entry->quantity_completed,
            'position' => $entry->position,
            'startsAt' => $entry->starts_at->toIso8601String(),
            'completesAt' => $entry->completes_at->toIso8601String(),
            'remainingSeconds' => $entry->remaining_seconds,
        ])->values()->all();
    }

    private function officerColumn(int $code): string
    {
        return self::OFFICER_COLUMNS[$code];
    }

    /**
     * Calculates per-unit production seconds from factories and applies the relevant officer discount.
     *
     * The discount cannot reduce the base duration below ten percent, and the result is at least one second.
     *
     * @param  array<int, int>  $unitCost
     */
    private function duration(array $unitCost, Planet $planet, User $user, string $type): int
    {
        $baseSeconds = (int) floor((($unitCost[0] + $unitCost[1]) / 2500) / (((int) $planet->hangar_level + 1) * (2 ** (int) $planet->nano_factory_level) * ((int) $planet->robot_factory_level + 1)) * 3600);
        $officerDiscount = $type === 'ships' ? (int) $user->officer_technocrat_level * 5 : (int) $user->officer_defender_level * 37.5;

        return max(1, (int) floor($baseSeconds * max(0.1, 1 - ($officerDiscount / 100))));
    }
}
