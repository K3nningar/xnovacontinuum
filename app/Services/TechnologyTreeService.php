<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Implements the TechnologyTreeService application service.
 */

namespace App\Services;

use App\Models\Planet;
use App\Models\User;

class TechnologyTreeService
{
    private const RESEARCH_CODES = [
        'spy_technology_level' => 106, 'computer_technology_level' => 108, 'military_technology_level' => 109,
        'defence_technology_level' => 110, 'shield_technology_level' => 111, 'energy_technology_level' => 113,
        'hyperspace_technology_level' => 114, 'combustion_technology_level' => 115, 'impulse_technology_level' => 117,
        'hyperspace_drive_level' => 118, 'laser_technology_level' => 120, 'ion_technology_level' => 121,
        'plasma_technology_level' => 122, 'intergalactic_research_level' => 123,
        'expedition_technology_level' => 124, 'graviton_technology_level' => 199,
    ];

    private const OFFICER_COLUMNS = [
        601 => 'officer_geologist_level', 602 => 'officer_admiral_level', 603 => 'officer_engineer_level',
        604 => 'officer_technocrat_level', 605 => 'officer_constructor_level', 606 => 'officer_scientist_level',
        607 => 'officer_stockman_level', 608 => 'officer_defender_level', 609 => 'officer_bunker_level',
        610 => 'officer_spy_level', 611 => 'officer_commander_level', 612 => 'officer_destroyer_level',
        613 => 'officer_general_level', 614 => 'officer_raider_level', 615 => 'officer_emperor_level',
    ];

    /**
     * Assembles a unified client-facing tree from building, research, unit, and officer catalogs.
     *
     * Availability is inherited from the source catalogs and building snapshot; prerequisite codes are
     * normalized here to the common tree-element format.
     *
     * @param  array<int, array<string, mixed>>  $researches
     * @param  array<string, mixed>  $buildingSnapshot
     * @return array<int, array<string, mixed>>
     */
    public function catalog(User $user, Planet $planet, array $researches, array $buildingSnapshot, UnitProductionService $production): array
    {
        $elements = [];
        $buildingRarities = config('technology-tree.building_rarities');
        foreach (config('technology-tree.buildings') as $building) {
            [$key, $code, $column] = $building;
            $level = (int) $planet->{$column};
            $requirements = $this->requirements($building[3] ?? [], $planet, $user);
            $canUpgrade = (bool) ($buildingSnapshot['canUpgrade'][$key] ?? false)
                && collect($requirements)->every(fn (array $requirement) => $requirement['current'] >= $requirement['required']);
            $elements[] = $this->element('building', $key, $code, $level, $level + 1,
                $requirements, $buildingSnapshot['nextCosts'][$key] ?? [], $canUpgrade, $buildingRarities[$code]);
        }

        foreach ($researches as $research) {
            $requirements = array_map(fn (array $requirement): array => [
                'code' => $requirement['key'] === 'laboratory_level' ? 31 : (self::RESEARCH_CODES[$requirement['key']] ?? null),
                'required' => (int) $requirement['required'], 'current' => (int) $requirement['current'],
            ], $research['requirements']);
            $elements[] = $this->element('research', $research['key'], $research['code'], $research['level'], $research['targetLevel'],
                array_values(array_filter($requirements, fn (array $requirement) => $requirement['code'] !== null)),
                $research['cost'], $research['canResearch'], $research['rarity']);
        }

        foreach (['ships' => 'ship', 'defences' => 'defence'] as $type => $kind) {
            foreach ($production->catalog($planet, $user, $type)['units'] as $unit) {
                $requirements = $this->uniqueRequirements($unit['requirements']);
                $elements[] = $this->element($kind, $unit['key'], $unit['code'], $unit['count'], $unit['count'] + 1,
                    $requirements, $unit['cost'], $unit['canBuild'], $unit['rarity']);
            }
        }

        foreach ($production->officerCatalog($user)['officers'] as $officer) {
            $requirements = array_map(fn (array $requirement): array => [
                'code' => (int) $requirement['code'], 'required' => (int) $requirement['level'],
                'current' => (int) $requirement['current'],
            ], $officer['requirements']);
            $elements[] = $this->element('officer', $officer['key'], $officer['code'], $officer['level'], $officer['level'] + 1,
                $requirements, ['points' => 1], $officer['canRecruit'] && (int) $user->officer_points > 0, $officer['rarity']);
        }

        return array_values($elements);
    }

    /**
     * Resolves configured prerequisite codes to their current planet or empire levels.
     *
     * @param  array<int|string, int|string>  $requirements
     * @return array<int, array{code: int, required: int, current: int}>
     */
    private function requirements(array $requirements, Planet $planet, User $user): array
    {
        $elements = [];
        foreach ($requirements as $code => $required) {
            $elements[] = ['code' => (int) $code, 'required' => (int) $required, 'current' => $this->level((int) $code, $planet, $user)];
        }

        return $elements;
    }

    /**
     * Merges duplicate prerequisite codes, retaining the greatest required level for each code.
     *
     * @param  array<int, array{code: int|string, level: int|string, current: int|string}>  $requirements
     * @return array<int, array{code: int, required: int, current: int}>
     */
    private function uniqueRequirements(array $requirements): array
    {
        $unique = [];
        foreach ($requirements as $requirement) {
            $code = (int) $requirement['code'];
            $unique[$code] = [
                'code' => $code,
                'required' => max((int) ($unique[$code]['required'] ?? 0), (int) $requirement['level']),
                'current' => (int) $requirement['current'],
            ];
        }

        return array_values($unique);
    }

    /**
     * Resolves a tree code to its planet building level or empire research/officer level; unknown codes are zero.
     */
    private function level(int $code, Planet $planet, User $user): int
    {
        foreach (config('technology-tree.buildings') as [, $buildingCode, $column]) {
            if ($buildingCode === $code) {
                return (int) $planet->{$column};
            }
        }

        $researchColumn = array_search($code, self::RESEARCH_CODES, true);
        if ($researchColumn !== false) {
            return (int) $user->{$researchColumn};
        }

        return isset(self::OFFICER_COLUMNS[$code]) ? (int) $user->{self::OFFICER_COLUMNS[$code]} : 0;
    }

    /**
     * Normalizes one catalog item into the shared technology-tree response shape.
     *
     * Missing resource, energy, or point costs are exposed as zero; requirements retain the supplied current levels.
     *
     * @param  array<int, array<string, int>>  $requirements
     * @param  array<string, int>  $cost
     * @return array<string, mixed>
     */
    private function element(string $kind, string $key, int $code, int $level, int $targetLevel, array $requirements, array $cost, bool $canBuild, string $rarity): array
    {
        return [
            'id' => $kind.':'.$code,
            'kind' => $kind,
            'category' => $kind,
            'key' => $key,
            'code' => $code,
            'rarity' => $rarity,
            'level' => (int) $level,
            'targetLevel' => (int) $targetLevel,
            'requirements' => array_values($requirements),
            'cost' => [
                'metal' => (int) ($cost['metal'] ?? 0),
                'crystal' => (int) ($cost['crystal'] ?? 0),
                'deuterium' => (int) ($cost['deuterium'] ?? 0),
                'energy' => (int) ($cost['energy'] ?? 0),
                'points' => (int) ($cost['points'] ?? 0),
            ],
            'canBuild' => (bool) $canBuild,
        ];
    }
}
