<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Implements the CombatSimulatorService application service.
 */

namespace App\Services;

use Random\Engine\Mt19937;
use Random\Engine\Secure;
use Random\Randomizer;

class CombatSimulatorService
{
    /**
     * Simulates both sides until one is eliminated or the configured round limit is reached.
     *
     * Each invocation uses a secure random source when no seed is provided; a supplied seed selects the
     * deterministic Mt19937 engine for repeatable outcomes. Both sides fire using their pre-round shooter
     * lists, so losses in a round do not suppress that round's already-scheduled return fire.
     *
     * @param  array{fleet: array<int, int>, technologies: array{weapons: int, shields: int, armour: int}}  $attackerInput
     * @param  array{fleet: array<int, int>, technologies: array{weapons: int, shields: int, armour: int}}  $defenderInput
     * @param  array<int, array{attack: int, shield: int, hull: int}>  $specialProfiles
     * @return array<string, mixed>
     */
    public function simulate(array $attackerInput, array $defenderInput, ?int $seed = null, array $specialProfiles = []): array
    {
        $randomizer = new Randomizer($seed === null ? new Secure : new Mt19937($seed));
        $attacker = $this->buildSide($attackerInput, $specialProfiles);
        $defender = $this->buildSide($defenderInput, $specialProfiles);
        $initialAttacker = $this->unitCounts($attacker);
        $initialDefender = $this->unitCounts($defender);
        $rounds = [];

        for ($round = 1; $round <= config('combat.max_rounds'); $round++) {
            if ($attacker['alive'] === [] || $defender['alive'] === []) {
                break;
            }

            $attackerShooters = $attacker['alive'];
            $defenderShooters = $defender['alive'];
            $attackerBefore = count($attacker['alive']);
            $defenderBefore = count($defender['alive']);
            $attackerShots = 0;
            $defenderShots = 0;

            $this->resetShields($attacker);
            $this->resetShields($defender);

            $this->shoot($attackerShooters, $attacker, $defender, $randomizer, $attackerShots);
            $this->shoot($defenderShooters, $defender, $attacker, $randomizer, $defenderShots);

            $rounds[] = [
                'number' => $round,
                'attacker' => [
                    'units' => $this->unitLines($this->unitCounts($attacker)),
                    'losses' => $attackerBefore - count($attacker['alive']),
                    'shots' => $attackerShots,
                ],
                'defender' => [
                    'units' => $this->unitLines($this->unitCounts($defender)),
                    'losses' => $defenderBefore - count($defender['alive']),
                    'shots' => $defenderShots,
                ],
            ];
        }

        $attackerCounts = $this->unitCounts($attacker);
        $defenderCounts = $this->unitCounts($defender);

        return [
            'winner' => match (true) {
                $attacker['alive'] !== [] && $defender['alive'] === [] => 'attacker',
                $defender['alive'] !== [] && $attacker['alive'] === [] => 'defender',
                default => 'draw',
            },
            'seed' => $seed,
            'rounds' => $rounds,
            'attacker' => $this->summary($initialAttacker, $attackerCounts),
            'defender' => $this->summary($initialDefender, $defenderCounts),
        ];
    }

    /**
     * Expands one side's fleet counts into individual units with technology-adjusted attack, shields, and hull.
     *
     * Special profiles replace configured base stats for custom-profile units before technology multipliers apply.
     *
     * @param  array{fleet: array<int, int>, technologies: array{weapons: int, shields: int, armour: int}}  $input
     * @param  array<int, array{attack: int, shield: int, hull: int}>  $specialProfiles
     * @return array{units: array<int, array<string, int|float>>, alive: array<int, int>, positions: array<int, int>}
     */
    private function buildSide(array $input, array $specialProfiles): array
    {
        $side = ['units' => [], 'alive' => [], 'positions' => []];
        $technologyBonuses = [
            'attack' => 1 + ($input['technologies']['weapons'] / 10),
            'shield' => 1 + ($input['technologies']['shields'] / 10),
            'hull' => 1 + ($input['technologies']['armour'] / 10),
        ];

        foreach (config('combat.units') as $code => $definition) {
            $count = (int) ($input['fleet'][$code] ?? 0);

            if ($count > 0 && ($definition['custom_profile'] ?? false)) {
                $definition = array_replace($definition, $specialProfiles[$code]);
            }

            for ($unit = 0; $unit < $count; $unit++) {
                $id = count($side['units']);
                $side['units'][$id] = [
                    'code' => (int) $code,
                    'attack' => $definition['attack'] * $technologyBonuses['attack'],
                    'maxShield' => $definition['shield'] * $technologyBonuses['shield'],
                    'shield' => $definition['shield'] * $technologyBonuses['shield'],
                    'maxHull' => $definition['hull'] * $technologyBonuses['hull'],
                    'hull' => $definition['hull'] * $technologyBonuses['hull'],
                    'alive' => true,
                ];
                $side['alive'][] = $id;
                $side['positions'][$id] = count($side['alive']) - 1;
            }
        }

        return $side;
    }

    /**
     * Fires once per initially alive shooter, applying rapid fire while each shot destroys its target.
     *
     * Target selection and rapid-fire continuation consume the supplied randomizer, keeping seeded runs repeatable.
     *
     * @param  array<int, int>  $shooterIds
     * @param  array{units: array<int, array<string, int|float>>, alive: array<int, int>, positions: array<int, int>}  $shooters
     * @param  array{units: array<int, array<string, int|float>>, alive: array<int, int>, positions: array<int, int>}  $targets
     */
    private function shoot(array $shooterIds, array &$shooters, array &$targets, Randomizer $randomizer, int &$shots): void
    {
        foreach ($shooterIds as $shooterId) {
            if ($targets['alive'] === []) {
                return;
            }

            $shooter = $shooters['units'][$shooterId];

            while ($targets['alive'] !== []) {
                $targetIndex = $randomizer->getInt(0, count($targets['alive']) - 1);
                $targetId = $targets['alive'][$targetIndex];
                $targetCode = $targets['units'][$targetId]['code'];
                $rapidFire = (int) (config("combat.units.{$shooter['code']}.rapid_fire.{$targetCode}") ?? 1);
                $destroyed = $this->fire($shooter, $targetId, $targets, $randomizer);
                $shots++;

                if (! $destroyed || $rapidFire <= 1 || $randomizer->getInt(1, $rapidFire) === 1) {
                    break;
                }
            }
        }
    }

    /**
     * Applies one shot to a target, returning true only when that shot destroys the unit.
     *
     * Damage that does not exceed one percent of maximum shields is discarded; remaining shield absorbs damage
     * before hull, with a random explosion check once hull reaches 30 percent or less.
     *
     * @param  array<string, int|float>  $shooter
     * @param  array{units: array<int, array<string, int|float>>, alive: array<int, int>, positions: array<int, int>}  $targets
     */
    private function fire(array $shooter, int $targetId, array &$targets, Randomizer $randomizer): bool
    {
        $damage = (float) $shooter['attack'];
        $target = &$targets['units'][$targetId];

        if ($damage <= $target['maxShield'] / 100) {
            return false;
        }

        if ($damage <= $target['shield']) {
            $target['shield'] -= $damage;

            return false;
        }

        $damage -= $target['shield'];
        $target['shield'] = 0;
        $target['hull'] -= $damage;

        if ($target['hull'] <= 0) {
            $this->removeUnit($targetId, $targets);

            return true;
        }

        $hullRatio = $target['hull'] / $target['maxHull'];

        if ($hullRatio <= 0.3) {
            $explosionChance = (int) floor((0.3 - $hullRatio) * 100);

            if ($randomizer->getInt(1, 100) <= $explosionChance) {
                $this->removeUnit($targetId, $targets);

                return true;
            }
        }

        return false;
    }

    /**
     * Removes a destroyed unit from the live index using swap-with-last to keep the index compact.
     *
     * The unit record remains in the full unit list for stable IDs, but its alive position is removed.
     *
     * @param  array{units: array<int, array<string, int|float>>, alive: array<int, int>, positions: array<int, int>}  $fleet
     */
    private function removeUnit(int $unitId, array &$fleet): void
    {
        $fleet['units'][$unitId]['alive'] = false;
        $position = $fleet['positions'][$unitId];
        $lastId = array_pop($fleet['alive']);

        if ($position < count($fleet['alive'])) {
            $fleet['alive'][$position] = $lastId;
            $fleet['positions'][$lastId] = $position;
        }

        unset($fleet['positions'][$unitId]);
    }

    /**
     * Restores every surviving unit's shield to its technology-adjusted maximum at the start of a round.
     *
     * @param  array{units: array<int, array<string, int|float>>, alive: array<int, int>, positions: array<int, int>}  $fleet
     */
    private function resetShields(array &$fleet): void
    {
        foreach ($fleet['alive'] as $unitId) {
            $fleet['units'][$unitId]['shield'] = $fleet['units'][$unitId]['maxShield'];
        }
    }

    /**
     * Counts surviving units by code and sorts codes numerically for stable report serialization.
     *
     * @param  array{units: array<int, array<string, int|float>>, alive: array<int, int>, positions: array<int, int>}  $fleet
     * @return array<int, int>
     */
    private function unitCounts(array $fleet): array
    {
        $counts = [];

        foreach ($fleet['alive'] as $unitId) {
            $code = $fleet['units'][$unitId]['code'];
            $counts[$code] = ($counts[$code] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * Converts sorted code-to-count data into the compact unit-line format used by round reports.
     *
     * @param  array<int, int>  $counts
     * @return array<int, array{code: int, count: int}>
     */
    private function unitLines(array $counts): array
    {
        $units = [];

        foreach ($counts as $code => $count) {
            $units[] = ['code' => $code, 'count' => $count];
        }

        return $units;
    }

    /**
     * Summarizes initial and surviving counts, including per-code and aggregate losses.
     *
     * Unit rows are emitted for codes present initially; absent survivors count as zero.
     *
     * @param  array<int, int>  $initial
     * @param  array<int, int>  $remaining
     * @return array{units: array<int, array{code: int, initial: int, remaining: int, losses: int}>, totalInitial: int, totalRemaining: int, totalLosses: int}
     */
    private function summary(array $initial, array $remaining): array
    {
        $units = [];
        $totalInitial = array_sum($initial);
        $totalRemaining = array_sum($remaining);

        foreach ($initial as $code => $count) {
            $units[] = [
                'code' => $code,
                'initial' => $count,
                'remaining' => $remaining[$code] ?? 0,
                'losses' => $count - ($remaining[$code] ?? 0),
            ];
        }

        return [
            'units' => $units,
            'totalInitial' => $totalInitial,
            'totalRemaining' => $totalRemaining,
            'totalLosses' => $totalInitial - $totalRemaining,
        ];
    }
}
