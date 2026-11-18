<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Tests the application's ExplorationMissionsTest behavior.
 */

namespace Tests\Feature;

use App\Models\Fleet;
use App\Models\Planet;
use App\Models\ResearchQueue;
use App\Models\Universe;
use App\Models\User;
use App\Services\CombatSimulatorService;
use App\Services\ExplorationMissions;
use App\Services\GalaxyMap;
use App\Services\PlanetEconomy;
use App\Services\ResearchService;
use App\Services\UnitProductionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ExplorationMissionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_recyclers_are_reserved_then_return_with_depleted_asteroid_resources_only_once(): void
    {
        [$user, $planet] = $this->player();
        $user->forceFill(['spy_technology_level' => 13])->save();
        [$system, $slot] = $this->asteroid($user);
        $this->actingAs($user)->post(route('planet.missions.store', $planet), [
            'mission' => 'recycle', 'galaxy' => 1, 'system' => $system, 'position' => $slot['position'], 'ships' => [209 => 1],
        ])->assertRedirect(route('fleet.index', ['planet' => $planet->id]));
        $this->assertSame(1, (int) $planet->fresh()->unit_209);
        $fleet = Fleet::firstOrFail();
        $this->travelTo($fleet->arrives_at->addSecond());
        $this->get(route('fleet.index', ['planet' => $planet->id]))->assertOk();
        $fleet->refresh();
        $this->assertSame('returning', $fleet->status);
        $this->assertSame(20000, array_sum($fleet->cargo));
        $updated = app(GalaxyMap::class)->system($user->universe, $user, 1, $system, $planet->position)['slots'][$slot['position'] - 2];
        $this->assertSame(array_sum($slot['remaining']) - 20000, array_sum($updated['remaining']));
        $this->travelTo($fleet->returns_at->addSecond());
        $this->get(route('fleet.index', ['planet' => $planet->id]))->assertOk();
        $this->assertSame('completed', $fleet->fresh()->status);
        $this->assertSame(2, (int) $planet->fresh()->unit_209);
        $metal = (int) $planet->fresh()->metal;
        app(ExplorationMissions::class)->processDue();
        $this->assertSame($metal, (int) $planet->fresh()->metal);
        $this->assertDatabaseCount('asteroid_fields', 1);
    }

    public function test_expedition_requires_research_and_rejects_foreign_or_unavailable_ships(): void
    {
        [$user, $planet] = $this->player();
        [$other, $otherPlanet] = $this->player();
        $payload = ['mission' => 'expedition', 'galaxy' => 1, 'system' => 1, 'position' => 16, 'ships' => [202 => 1]];
        $this->actingAs($user)->from(route('galaxy.index'))->post(route('planet.missions.store', $otherPlanet), $payload)->assertNotFound();
        $this->from(route('galaxy.index'))->post(route('planet.missions.store', $planet), $payload)->assertSessionHasErrors('target');
        $user->forceFill(['expedition_technology_level' => 1])->save();
        $payload['ships'] = [202 => 100];
        $this->from(route('galaxy.index'))->post(route('planet.missions.store', $planet), $payload)->assertSessionHasErrors('ships');
        $this->assertDatabaseCount('fleets', 0);
    }

    public function test_expedition_reserves_ships_and_resolves_without_duplicating_them(): void
    {
        [$user, $planet] = $this->player();
        $user->forceFill(['expedition_technology_level' => 1])->save();
        $payload = ['mission' => 'expedition', 'galaxy' => 1, 'system' => 1, 'position' => 16, 'ships' => [202 => 2]];
        $this->actingAs($user)->post(route('planet.missions.store', $planet), $payload)->assertRedirect();
        $this->assertSame(0, (int) $planet->fresh()->unit_202);
        $this->from(route('galaxy.index'))->post(route('planet.missions.store', $planet), $payload)->assertSessionHasErrors('mission');
        $fleet = Fleet::firstOrFail();
        $this->travelTo($fleet->returns_at->addHours(2));
        app(ExplorationMissions::class)->processDue();
        $fleet->refresh();
        $this->assertSame('completed', $fleet->status);
        $this->assertContains($fleet->outcome, ['resources', 'nothing', 'ship_found', 'delay', 'losses', 'pirates', 'aliens', 'early', 'lost']);
        $ships = (int) $planet->fresh()->unit_202;
        $this->assertGreaterThanOrEqual(0, $ships);
        $this->assertLessThanOrEqual($fleet->outcome === 'ship_found' ? 5 : 2, $ships);
        app(ExplorationMissions::class)->processDue();
        $this->assertSame($ships, (int) $planet->fresh()->unit_202);
    }

    public function test_recycling_rejects_hidden_targets_and_insufficient_fuel(): void
    {
        [$user, $planet] = $this->player();
        $user->forceFill(['spy_technology_level' => 13])->save();
        [$system, $slot] = $this->asteroid($user);
        $payload = ['mission' => 'recycle', 'galaxy' => 1, 'system' => $system, 'position' => $slot['position'], 'ships' => [209 => 1]];
        $user->forceFill(['spy_technology_level' => 0])->save();
        $this->actingAs($user)->from(route('galaxy.index'))->post(route('planet.missions.store', $planet), $payload)->assertSessionHasErrors('target');
        $user->forceFill(['spy_technology_level' => 13])->save();
        $planet->forceFill(['deuterium' => 0])->save();
        $this->from(route('galaxy.index'))->post(route('planet.missions.store', $planet), $payload)->assertSessionHasErrors('ships');
        $this->assertSame(2, (int) $planet->fresh()->unit_209);
        $this->assertDatabaseCount('fleets', 0);
    }

    #[TestWith([7])]
    #[TestWith([9])]
    public function test_recycling_reaches_both_sides_of_the_origin_with_level_one_sensors(int $position): void
    {
        [$user, $planet] = $this->player();
        $system = $this->asteroidSystemAt($user, $position);
        $user->forceFill(['spy_technology_level' => 1])->save();

        $this->actingAs($user)->post(route('planet.missions.store', $planet), [
            'mission' => 'recycle', 'galaxy' => 1, 'system' => $system, 'position' => $position, 'ships' => [209 => 1],
        ])->assertRedirect(route('fleet.index', ['planet' => $planet->id]));
        $fleet = Fleet::firstOrFail();
        $user->forceFill(['spy_technology_level' => 0])->save();
        $this->travelTo($fleet->arrives_at->addSecond());
        $this->get(route('fleet.index'))->assertOk();

        $this->assertSame('recycled', $fleet->fresh()->outcome);
        $this->assertSame(20000, array_sum($fleet->fresh()->cargo));
    }

    #[TestWith([6])]
    #[TestWith([10])]
    public function test_recycling_rejects_fields_beyond_both_sensor_boundaries(int $position): void
    {
        [$user, $planet] = $this->player();
        $system = $this->asteroidSystemAt($user, $position);
        $user->forceFill(['spy_technology_level' => 1])->save();

        $this->actingAs($user)->from(route('galaxy.index'))->post(route('planet.missions.store', $planet), [
            'mission' => 'recycle', 'galaxy' => 1, 'system' => $system, 'position' => $position, 'ships' => [209 => 1],
        ])->assertSessionHasErrors('target');

        $this->assertDatabaseCount('fleets', 0);
        $this->assertSame(2, (int) $planet->fresh()->unit_209);
    }

    public function test_flight_time_respects_fractional_universe_speed(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$user, $planet] = $this->player();
        $user->forceFill(['expedition_technology_level' => 1])->save();
        $user->universe->update(['fleet_speed' => 1.5]);
        $this->actingAs($user)->post(route('planet.missions.store', $planet), [
            'mission' => 'expedition', 'galaxy' => 1, 'system' => 1, 'position' => 16, 'ships' => [202 => 1],
        ])->assertRedirect();
        $fleet = Fleet::firstOrFail();
        $this->assertSame(now()->addSeconds(1094)->toDateTimeString(), $fleet->arrives_at->toDateTimeString());
    }

    public function test_delayed_processing_credits_cargo_at_the_actual_return_time(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$user, $planet] = $this->player();
        $planet->forceFill(['metal' => 1_099_000, 'resources_updated_at' => now()])->save();
        $fleet = new Fleet;
        $fleet->forceFill([
            'universe_id' => $user->universe_id, 'user_id' => $user->id, 'origin_planet_id' => $planet->id,
            'mission' => 'recycle', 'ships' => [], 'status' => 'returning', 'target_position' => 2,
            'target_galaxy' => 1, 'target_system' => 1, 'arrives_at' => now(),
            'returns_at' => now()->addHour(), 'cargo' => ['metal' => 1000, 'crystal' => 0, 'deuterium' => 0],
            'outcome' => 'recycled',
        ])->save();

        $this->travelTo(now()->addHours(2));
        $this->actingAs($user)->get(route('fleet.index'))->assertOk();
        $this->assertSame(1_100_020, (int) $planet->fresh()->metal);
        $this->assertSame('completed', $fleet->fresh()->status);
    }

    public function test_multiple_recycling_missions_cannot_collect_the_same_deposit_twice(): void
    {
        [$user, $planet] = $this->player();
        $user->forceFill(['spy_technology_level' => 13])->save();
        $planet->forceFill(['unit_209' => 400])->save();
        [$system, $slot] = $this->asteroid($user);
        $payload = ['mission' => 'recycle', 'galaxy' => 1, 'system' => $system, 'position' => $slot['position'], 'ships' => [209 => 200]];
        $this->actingAs($user)->post(route('planet.missions.store', $planet), $payload)->assertRedirect();
        $this->post(route('planet.missions.store', $planet), $payload)->assertRedirect();
        $fleets = Fleet::orderBy('id')->get();
        $this->travelTo($fleets->last()->arrives_at->addSecond());
        $this->get(route('fleet.index', ['planet' => $planet->id]))->assertOk();
        $fleets = Fleet::orderBy('id')->get();
        $this->assertSame(array_sum($slot['remaining']), array_sum($fleets[0]->cargo));
        $this->assertSame(0, array_sum($fleets[1]->cargo));
    }

    #[TestWith([82, 'pirates'])]
    #[TestWith([87, 'aliens'])]
    public function test_expedition_encounters_simulate_combat_and_persist_the_report(int $roll, string $enemy): void
    {
        $this->forceOutcome($roll);
        [$user, $planet] = $this->player();
        $user->forceFill(['expedition_technology_level' => 1])->save();
        $this->actingAs($user)->post(route('planet.missions.store', $planet), [
            'mission' => 'expedition', 'galaxy' => 1, 'system' => 1, 'position' => 16, 'ships' => [202 => 2],
        ])->assertRedirect();
        $fleet = Fleet::firstOrFail();
        $this->travelTo($fleet->arrives_at->addSecond());
        $this->get(route('fleet.index'))->assertOk();
        $fleet->refresh();
        $this->assertSame($enemy, $fleet->outcome);
        $this->assertSame(2, $fleet->combat_report['attacker']['totalInitial']);
        $this->assertSame($fleet->combat_report['attacker']['totalRemaining'], array_sum($fleet->ships));
        $this->assertNotEmpty($fleet->combat_report['rounds']);
        $this->travelTo($fleet->returns_at->addSecond());
        $this->get(route('fleet.index'))->assertInertia(fn (Assert $page) => $page
            ->where('reports.0.outcome', $enemy)
            ->where('reports.0.combat.attacker.totalInitial', 2));
        $this->assertSame(array_sum($fleet->ships), (int) $planet->fresh()->unit_202);
    }

    public function test_combat_does_not_apply_research_that_finishes_after_the_encounter(): void
    {
        $this->forceOutcome(82);
        [$user, $planet] = $this->player();
        $user->forceFill(['expedition_technology_level' => 1])->save();
        $this->actingAs($user)->post(route('planet.missions.store', $planet), [
            'mission' => 'expedition', 'galaxy' => 1, 'system' => 1, 'position' => 16, 'ships' => [202 => 2],
        ])->assertRedirect();
        $fleet = Fleet::firstOrFail();
        ResearchQueue::create(['universe_id' => $user->universe_id, 'user_id' => $user->id, 'planet_id' => $planet->id,
            'technology_key' => 'military', 'technology_code' => 109, 'target_level' => 1,
            'cost_metal' => 0, 'cost_crystal' => 0, 'cost_deuterium' => 0, 'completes_at' => $fleet->arrives_at->addSeconds(10)]);
        $this->travelTo($fleet->arrives_at->addSeconds(20));
        $this->get(route('fleet.index'))->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'military_technology_level' => 0]);
        $this->assertDatabaseHas('research_queues', ['user_id' => $user->id, 'technology_key' => 'military']);
    }

    #[TestWith([202, 501])]
    #[TestWith([216, 1])]
    public function test_unsupported_encounter_fleets_use_noncombat_losses(int $code, int $count): void
    {
        $this->forceOutcome(82);
        [$user, $planet] = $this->player();
        $user->forceFill(['expedition_technology_level' => 1])->save();
        $planet->forceFill(['unit_'.$code => $count])->save();
        $this->actingAs($user)->post(route('planet.missions.store', $planet), [
            'mission' => 'expedition', 'galaxy' => 1, 'system' => 1, 'position' => 16, 'ships' => [$code => $count],
        ])->assertRedirect();
        $fleet = Fleet::firstOrFail();
        $this->travelTo($fleet->arrives_at->addSecond());
        $this->get(route('fleet.index'))->assertOk();
        $fleet->refresh();
        $this->assertSame('losses', $fleet->outcome);
        $this->assertNull($fleet->combat_report);
        $this->assertLessThan($count, array_sum($fleet->ships));
    }

    private function forceOutcome(int $roll): void
    {
        $missions = new class(app(GalaxyMap::class), app(PlanetEconomy::class), app(UnitProductionService::class), app(ResearchService::class), app(CombatSimulatorService::class), $roll) extends ExplorationMissions
        {
            public function __construct(GalaxyMap $map, PlanetEconomy $economy, UnitProductionService $production, ResearchService $research, CombatSimulatorService $combat, private int $roll)
            {
                parent::__construct($map, $economy, $production, $research, $combat);
            }

            protected function random(int $minimum, int $maximum): int
            {
                return $this->roll;
            }
        };
        $this->app->instance(ExplorationMissions::class, $missions);
    }

    private function player(): array
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => fake()->unique()->slug()]);
        $user = User::factory()->create(['universe_id' => $universe->id]);
        $planet = new Planet(['name' => 'Home']);
        $planet->forceFill(['universe_id' => $universe->id, 'user_id' => $user->id, 'galaxy' => 1, 'system' => 1,
            'position' => 8, 'unit_209' => 2, 'unit_202' => 2, 'deuterium' => 100000, 'resources_updated_at' => now()])->save();

        return [$user, $planet];
    }

    private function asteroidSystemAt(User $user, int $position): int
    {
        $user->forceFill(['spy_technology_level' => 13])->save();
        for ($system = 1; $system <= 100; $system++) {
            $slot = app(GalaxyMap::class)->system($user->universe, $user, 1, $system, 8)['slots'][$position - 2];
            if ($slot['kind'] === 'asteroid') {
                return $system;
            }
        }

        $this->fail("No asteroid field found at position {$position}.");
    }

    private function asteroid(User $user): array
    {
        for ($system = 1; $system <= 50; $system++) {
            $slot = collect(app(GalaxyMap::class)->system($user->universe, $user, 1, $system, 8)['slots'])
                ->first(fn (array $candidate): bool => $candidate['kind'] === 'asteroid' && $candidate['position'] > 2);
            if ($slot) {
                return [$system, $slot];
            }
        }

        $this->fail('Aucun champ d’astéroïdes trouvé.');
    }
}
