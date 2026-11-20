<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Tests the application's CombatSimulatorTest behavior.
 */

namespace Tests\Feature;

use App\Models\Planet;
use App\Models\Universe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CombatSimulatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_the_combat_simulator(): void
    {
        $this->get(route('combat-simulator.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_commander_can_open_the_combat_simulator(): void
    {
        $user = User::factory()->create(['military_technology_level' => 3]);

        $this->actingAs($user)->get(route('combat-simulator.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Combat/Index')
                ->has('units', 25)
                ->where('units.0.code', 202)
                ->where('maxUnitsPerSide', 500)
                ->where('technologies.weapons', 3)
                ->where('result', null));
    }

    public function test_simulation_returns_rounds_without_changing_game_state(): void
    {
        $planet = $this->createPlanetWithDeathstar();

        $this->actingAs($planet->user)->post(route('combat-simulator.simulate'), $this->simulationPayload([
            'attacker' => [214 => 1],
            'defender' => [202 => 1],
        ]))
            ->assertRedirect(route('combat-simulator.index'))
            ->assertSessionHas('combatResult', fn (array $result): bool => $result['winner'] === 'attacker'
                && $result['seed'] === 123
                && $result['rounds'][0]['number'] === 1
                && $result['rounds'][0]['attacker']['units'] === [['code' => 214, 'count' => 1]]
                && $result['attacker']['totalRemaining'] === 1
                && $result['defender']['totalRemaining'] === 0);

        $this->assertSame(1, (int) $planet->fresh()->unit_214);
        $this->assertDatabaseCount('fleets', 0);
    }

    public function test_simulation_reports_the_defender_as_winner_when_the_attacker_is_destroyed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('combat-simulator.simulate'), $this->simulationPayload([
            'attacker' => [210 => 1],
            'defender' => [204 => 1],
        ]))
            ->assertRedirect(route('combat-simulator.index'))
            ->assertSessionHas('combatResult', fn (array $result): bool => $result['winner'] === 'defender'
                && $result['attacker']['totalRemaining'] === 0
                && $result['defender']['totalRemaining'] === 1);
    }

    public function test_simulation_stops_after_six_rounds_when_both_sides_survive(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('combat-simulator.simulate'), $this->simulationPayload([
            'attacker' => [210 => 1],
            'defender' => [408 => 1],
        ]))
            ->assertRedirect(route('combat-simulator.index'))
            ->assertSessionHas('combatResult', fn (array $result): bool => $result['winner'] === 'draw'
                && count($result['rounds']) === 6
                && $result['attacker']['totalRemaining'] === 1
                && $result['defender']['totalRemaining'] === 1);
    }

    public function test_fixed_seed_replays_the_same_simulation_result(): void
    {
        $user = User::factory()->create();
        $payload = $this->simulationPayload([
            'attacker' => [204 => 3, 205 => 2],
            'defender' => [401 => 4, 402 => 3],
        ]);
        $firstResult = null;

        $this->actingAs($user)->post(route('combat-simulator.simulate'), $payload)
            ->assertSessionHas('combatResult', function (array $result) use (&$firstResult): bool {
                $firstResult = $result;

                return true;
            });

        $this->actingAs($user)->post(route('combat-simulator.simulate'), $payload)
            ->assertSessionHas('combatResult', $firstResult);
    }

    public function test_special_ships_and_defender_protector_can_fight_with_custom_profiles(): void
    {
        $planet = $this->createPlanetWithDeathstar();
        $planet->update(['unit_216' => 1, 'unit_217' => 1, 'unit_409' => 1]);
        $profiles = [
            216 => ['attack' => 1000, 'shield' => 0, 'hull' => 15000],
            217 => ['attack' => 1000, 'shield' => 0, 'hull' => 200000],
            409 => ['attack' => 1000, 'shield' => 0, 'hull' => 35000],
        ];

        $this->actingAs($planet->user)->get(route('combat-simulator.index'))
            ->assertInertia(fn (Assert $page) => $page->where('presetFleet.216', 1)
                ->where('presetFleet.217', 1)
                ->missing('presetFleet.409'));

        foreach (['attacker', 'defender'] as $side) {
            $opponent = $side === 'attacker' ? 'defender' : 'attacker';
            $payload = $this->simulationPayload([
                $side => $side === 'attacker' ? [216 => 1, 217 => 1] : [216 => 1, 217 => 1, 409 => 1],
                $opponent => [210 => 3],
            ]);
            $payload['special_profiles'] = $profiles;

            $this->actingAs($planet->user)->post(route('combat-simulator.simulate'), $payload)
                ->assertRedirect(route('combat-simulator.index'))
                ->assertSessionHas('combatResult', fn (array $result): bool => $result['winner'] === $side
                    && $result[$side]['totalRemaining'] === ($side === 'attacker' ? 2 : 3)
                    && array_column($result[$side]['units'], 'code') === ($side === 'attacker' ? [216, 217] : [216, 217, 409]));
        }

        $this->assertSame(1, (int) $planet->fresh()->unit_216);
        $this->assertSame(1, (int) $planet->fresh()->unit_217);
        $this->assertSame(1, (int) $planet->fresh()->unit_409);
    }

    public function test_special_units_require_valid_custom_combat_profiles(): void
    {
        $user = User::factory()->create();
        $payload = $this->simulationPayload([
            'attacker' => [216 => 1],
            'defender' => [409 => 1],
        ]);
        $payload['special_profiles'] = [
            216 => ['attack' => null, 'shield' => -1, 'hull' => 0],
        ];

        $this->actingAs($user)->from(route('combat-simulator.index'))
            ->post(route('combat-simulator.simulate'), $payload)
            ->assertRedirect(route('combat-simulator.index'))
            ->assertSessionHasErrors([
                'special_profiles.216.attack', 'special_profiles.216.shield', 'special_profiles.216.hull',
                'special_profiles.409.attack', 'special_profiles.409.shield', 'special_profiles.409.hull',
            ]);
    }

    public function test_custom_shield_and_hull_affect_the_special_unit_survival(): void
    {
        $user = User::factory()->create();
        $payload = $this->simulationPayload([
            'attacker' => [204 => 1],
            'defender' => [216 => 1],
        ]);
        $payload['special_profiles'] = [216 => ['attack' => 0, 'shield' => 0, 'hull' => 1]];

        $this->actingAs($user)->post(route('combat-simulator.simulate'), $payload)
            ->assertSessionHas('combatResult', fn (array $result): bool => $result['winner'] === 'attacker');

        $payload['special_profiles'][216]['shield'] = 100;
        $this->actingAs($user)->post(route('combat-simulator.simulate'), $payload)
            ->assertSessionHas('combatResult', fn (array $result): bool => $result['winner'] === 'draw'
                && $result['defender']['totalRemaining'] === 1);

        $payload['special_profiles'][216]['shield'] = 0;
        $payload['special_profiles'][216]['hull'] = 1000;
        $this->actingAs($user)->post(route('combat-simulator.simulate'), $payload)
            ->assertSessionHas('combatResult', fn (array $result): bool => $result['winner'] === 'draw'
                && $result['defender']['totalRemaining'] === 1);
    }

    public function test_simulation_rejects_a_side_without_any_units(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('combat-simulator.index'))
            ->post(route('combat-simulator.simulate'), $this->simulationPayload([
                'attacker' => [214 => 0],
                'defender' => [202 => 1],
            ]))
            ->assertRedirect(route('combat-simulator.index'))
            ->assertSessionHasErrors('attacker.fleet');
    }

    public function test_simulation_rejects_unit_codes_outside_the_supported_catalog(): void
    {
        $user = User::factory()->create();
        $payload = $this->simulationPayload([
            'attacker' => [999 => 1],
            'defender' => [202 => 1],
        ]);

        $this->actingAs($user)->from(route('combat-simulator.index'))
            ->post(route('combat-simulator.simulate'), $payload)
            ->assertRedirect(route('combat-simulator.index'))
            ->assertSessionHasErrors('attacker.fleet');
    }

    public function test_simulation_rejects_defences_in_the_attacking_fleet(): void
    {
        $user = User::factory()->create();

        foreach ([401, 409] as $code) {
            $payload = $this->simulationPayload([
                'attacker' => [202 => 1, $code => 1],
                'defender' => [202 => 1],
            ]);

            $this->actingAs($user)->from(route('combat-simulator.index'))
                ->post(route('combat-simulator.simulate'), $payload)
                ->assertRedirect(route('combat-simulator.index'))
                ->assertSessionHasErrors(['attacker.fleet' => 'La flotte de l’attaquant contient une unité non prise en charge.']);
        }
    }

    public function test_simulation_rejects_armies_over_the_configured_limit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('combat-simulator.index'))
            ->post(route('combat-simulator.simulate'), $this->simulationPayload([
                'attacker' => [202 => 250, 203 => 251],
                'defender' => [202 => 1],
            ]))
            ->assertRedirect(route('combat-simulator.index'))
            ->assertSessionHasErrors('attacker.fleet');
    }

    /**
     * @param  array{attacker?: array<int, int>, defender?: array<int, int>}  $fleets
     * @return array<string, mixed>
     */
    private function simulationPayload(array $fleets = []): array
    {
        $unitCodes = array_keys(config('combat.units'));
        $shipCodes = array_keys(array_filter(config('combat.units'), fn (array $unit): bool => $unit['type'] === 'ships'));
        $attackerFleet = array_fill_keys($shipCodes, 0);
        $defenderFleet = array_fill_keys($unitCodes, 0);

        foreach ($fleets['attacker'] ?? [] as $code => $count) {
            $attackerFleet[$code] = $count;
        }

        foreach ($fleets['defender'] ?? [] as $code => $count) {
            $defenderFleet[$code] = $count;
        }

        return [
            'attacker' => [
                'fleet' => $attackerFleet,
                'technologies' => ['weapons' => 0, 'shields' => 0, 'armour' => 0],
            ],
            'defender' => [
                'fleet' => $defenderFleet,
                'technologies' => ['weapons' => 0, 'shields' => 0, 'armour' => 0],
            ],
            'seed' => 123,
        ];
    }

    private function createPlanetWithDeathstar(): Planet
    {
        $universe = Universe::create(['name' => 'Combat simulator', 'slug' => fake()->unique()->slug()]);
        $user = User::factory()->create(['universe_id' => $universe->id]);
        $planet = new Planet(['name' => 'Nova Prime', 'unit_214' => 1]);
        $planet->universe()->associate($universe);
        $planet->user()->associate($user);
        $planet->galaxy = 1;
        $planet->system = 1;
        $planet->position = 1;
        $planet->fields = 163;
        $planet->resources_updated_at = now();
        $planet->save();

        return $planet;
    }
}
