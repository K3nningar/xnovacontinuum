<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Tests the application's ExplorationMissionsTest behavior.
 */

namespace Tests\Feature;

use App\Models\Alliance;
use App\Models\Fleet;
use App\Models\Planet;
use App\Models\ResearchQueue;
use App\Models\Universe;
use App\Models\User;
use App\Services\CombatSimulatorService;
use App\Services\ExplorationMissions;
use App\Services\GalaxyMap;
use App\Services\MoonEconomy;
use App\Services\PlanetEconomy;
use App\Services\ResearchService;
use App\Services\UnitProductionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $payload['position'] = 16;
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

    public function test_recycling_uses_sensors_from_a_colony_in_the_target_system(): void
    {
        [$user, $origin] = $this->player();
        $user->forceFill(['spy_technology_level' => 13])->save();
        $system = null;
        $position = null;
        for ($candidateSystem = 2; $candidateSystem <= 100; $candidateSystem++) {
            $slots = app(GalaxyMap::class)->system($user->universe, $user, 1, $candidateSystem, 8)['slots'];
            foreach ([2, 3, 4] as $candidatePosition) {
                if ($slots[$candidatePosition - 2]['kind'] === 'asteroid') {
                    $system = $candidateSystem;
                    $position = $candidatePosition;
                    break 2;
                }
            }
        }
        $this->assertNotNull($system);
        $payload = ['mission' => 'recycle', 'galaxy' => 1, 'system' => $system, 'position' => $position, 'ships' => [209 => 1]];
        $this->actingAs($user)->from(route('galaxy.index'))->post(route('planet.missions.store', $origin), $payload)->assertSessionHasErrors('target');

        $colony = new Planet(['name' => 'Sensor colony']);
        $colony->forceFill(['universe_id' => $user->universe_id, 'user_id' => $user->id, 'galaxy' => 1, 'system' => $system,
            'position' => $position + 1, 'resources_updated_at' => now()])->save();
        $user->forceFill(['spy_technology_level' => 1])->save();
        $this->post(route('planet.missions.store', $origin), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $fleet = Fleet::firstOrFail();
        $this->assertSame('asteroid', $fleet->target_type);
        $this->travelTo($fleet->arrives_at->addSecond());
        app(ExplorationMissions::class)->processDue();
        $this->assertSame('recycled', $fleet->fresh()->outcome);
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
                parent::__construct($map, $economy, $production, $research, $combat, app(MoonEconomy::class));
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

    public function test_fleet_dispatch_locks_both_planets_in_id_order(): void
    {
        [$user, $target] = $this->player();
        $origin = $this->planetAt($user, 7, 'Outpost');
        $origin->forceFill(['unit_202' => 1, 'metal' => 100, 'deuterium' => 10000])->save();
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        app(ExplorationMissions::class)->send($user, $origin, 'transport', 1, 1, 8, [202 => 1], ['cargo' => ['metal' => 1]]);

        $lockedQueries = collect($connection->getQueryLog())->filter(function (array $query): bool {
            $sql = str_replace(['"', '`'], '', strtolower($query['query']));

            return str_starts_with($sql, 'select * from planets where planets.id = ?')
                && count($query['bindings']) === 1;
        })->values();
        $connection->disableQueryLog();
        $connection->flushQueryLog();

        $this->assertSame([$target->id, $origin->id], $lockedQueries->pluck('bindings.0')->take(2)->all());
        if ($connection->getDriverName() === 'mysql') {
            $this->assertStringContainsString('for update', strtolower($lockedQueries[0]['query']));
            $this->assertStringContainsString('for update', strtolower($lockedQueries[1]['query']));
        }
    }

    public function test_transport_delivers_cargo_and_returns_ships_once(): void
    {
        [$user, $origin] = $this->player();
        $destination = $this->planetAt($user, 7, 'Outpost');
        $origin->forceFill(['unit_202' => 2, 'metal' => 10000])->save();
        $destination->forceFill(['metal' => 10000])->save();
        $this->actingAs($user)->post(route('planet.missions.store', $origin), [
            'mission' => 'transport', 'galaxy' => 1, 'system' => 1, 'position' => 7,
            'ships' => [202 => 1], 'cargo' => ['metal' => 5000],
        ])->assertRedirect();
        $fleet = Fleet::firstOrFail();
        $this->assertSame(5000, (int) $origin->fresh()->metal);
        $this->assertSame(10000, (int) $destination->fresh()->metal);
        $this->travelTo($fleet->arrives_at->addSecond());
        app(ExplorationMissions::class)->processDue();
        $this->assertSame('transport_delivered', $fleet->fresh()->outcome);
        $this->assertSame(15000, (int) $destination->fresh()->metal);
        $this->travelTo($fleet->returns_at->addSecond());
        app(ExplorationMissions::class)->processDue();
        $ships = (int) $origin->fresh()->unit_202;
        app(ExplorationMissions::class)->processDue();
        $this->assertSame($ships, (int) $origin->fresh()->unit_202);
        $this->assertSame('completed', $fleet->fresh()->status);
    }

    public function test_transport_rejects_capacity_resources_and_enemy_planets(): void
    {
        [$user, $origin] = $this->player();
        [$enemy, $enemyPlanet] = $this->opponent($user);
        $this->actingAs($user)->from(route('galaxy.index'))->post(route('planet.missions.store', $origin), [
            'mission' => 'transport', 'galaxy' => 1, 'system' => 1, 'position' => 7,
            'ships' => [202 => 1], 'cargo' => ['metal' => 5001],
        ])->assertSessionHasErrors('cargo');
        $this->post(route('planet.missions.store', $origin), [
            'mission' => 'transport', 'galaxy' => 1, 'system' => 1, 'position' => 7,
            'ships' => [202 => 1], 'cargo' => ['metal' => 600],
        ])->assertSessionHasErrors('target');
        $this->post(route('planet.missions.store', $origin), [
            'mission' => 'transport', 'galaxy' => 1, 'system' => 1, 'position' => 9,
            'ships' => [202 => 1], 'cargo' => ['metal' => 100],
        ])->assertSessionHasErrors('target');
        $this->assertDatabaseCount('fleets', 0);
    }

    public function test_attack_persists_defender_losses_combat_report_and_debris(): void
    {
        [$attacker, $home] = $this->player();
        [$defender, $target] = $this->opponent($attacker);
        $home->forceFill(['unit_213' => 10])->save();
        $target->forceFill(['unit_202' => 2, 'unit_401' => 2])->save();
        $this->actingAs($attacker)->post(route('planet.missions.store', $home), [
            'mission' => 'attack', 'galaxy' => 1, 'system' => 1, 'position' => 9, 'ships' => [213 => 2],
        ])->assertRedirect();
        $fleet = Fleet::firstOrFail();
        $this->travelTo($fleet->arrives_at->addSecond());
        app(ExplorationMissions::class)->processDue();
        $fleet->refresh();
        $this->assertNotNull($fleet->combat_report);
        $this->assertSame('returning', $fleet->status);
        $this->assertLessThan(4, (int) $target->fresh()->unit_202 + (int) $target->fresh()->unit_401);
        $this->assertDatabaseHas('orbital_debris', ['universe_id' => $attacker->universe_id, 'galaxy' => 1, 'system' => 1, 'position' => 9]);
    }

    public function test_attack_preserves_each_defender_unit_type_when_garrison_exceeds_combat_limit(): void
    {
        [$attacker, $home] = $this->player();
        [, $target] = $this->opponent($attacker);
        $home->forceFill(['unit_204' => 1])->save();
        $target->forceFill(['unit_202' => 600, 'unit_401' => 600, 'unit_409' => 1])->save();

        $this->actingAs($attacker)->post(route('planet.missions.store', $home), [
            'mission' => 'attack', 'galaxy' => 1, 'system' => 1, 'position' => 9, 'ships' => [204 => 1],
        ])->assertRedirect();
        $fleet = Fleet::firstOrFail();
        $this->travelTo($fleet->arrives_at->addSecond());
        app(ExplorationMissions::class)->processDue();
        $fleet->refresh();

        $this->assertNotNull($fleet->combat_report);
        $this->assertSame(500, $fleet->combat_report['defender']['totalInitial']);
        $this->assertSame([202, 401, 409], array_column($fleet->combat_report['defender']['units'], 'code'));
        $this->assertSame([true, true, true], array_map(
            fn (array $unit): bool => $unit['initial'] > 0,
            $fleet->combat_report['defender']['units'],
        ));
        $this->assertSame(1201 - $fleet->combat_report['defender']['totalLosses'], (int) $target->fresh()->unit_202
            + (int) $target->fresh()->unit_401 + (int) $target->fresh()->unit_409);
    }

    public function test_stationing_requires_same_alliance_and_active_recipient_depot(): void
    {
        [$user, $home] = $this->player();
        [$ally, $target] = $this->opponent($user);
        $alliance = Alliance::create(['universe_id' => $user->universe_id, 'name' => 'Guild', 'tag' => 'G'.random_int(100, 999)]);
        $user->forceFill(['alliance_id' => $alliance->id])->save();
        $ally->forceFill(['alliance_id' => $alliance->id])->save();
        $home->forceFill(['unit_202' => 2])->save();
        $this->actingAs($user)->from(route('galaxy.index'))->post(route('planet.missions.store', $home), [
            'mission' => 'station', 'galaxy' => 1, 'system' => 1, 'position' => 9, 'ships' => [202 => 1],
        ])->assertSessionHasErrors('target');
        $target->forceFill(['alliance_depot_level' => 1])->save();
        $this->post(route('planet.missions.store', $home), [
            'mission' => 'station', 'galaxy' => 1, 'system' => 1, 'position' => 9, 'ships' => [202 => 1],
        ])->assertRedirect();
        $fleet = Fleet::firstOrFail();
        $this->travelTo($fleet->arrives_at->addSecond());
        app(ExplorationMissions::class)->processDue();
        $this->assertSame('stationed', $fleet->fresh()->status);
        $this->assertSame(1, (int) $home->fresh()->unit_202);
    }

    public function test_spy_requires_probes_and_persists_visibility_report_without_leaking_it(): void
    {
        [$user, $home] = $this->player();
        [$enemy, $target] = $this->opponent($user);
        $home->forceFill(['unit_210' => 3])->save();
        $target->forceFill(['metal' => 9876, 'unit_204' => 4])->save();
        $this->actingAs($user)->from(route('galaxy.index'))->post(route('planet.missions.store', $home), [
            'mission' => 'spy', 'galaxy' => 1, 'system' => 1, 'position' => 9, 'ships' => [210 => 1, 204 => 1],
        ])->assertSessionHasErrors('ships');
        $this->post(route('planet.missions.store', $home), [
            'mission' => 'spy', 'galaxy' => 1, 'system' => 1, 'position' => 9, 'ships' => [210 => 3],
        ])->assertRedirect();
        $fleet = Fleet::firstOrFail();
        $this->travelTo($fleet->arrives_at->addSecond());
        app(ExplorationMissions::class)->processDue();
        $report = $fleet->fresh()->mission_report['espionage'];
        $this->assertSame(9876, $report['intelligence']['resources']['metal']);
        $this->assertArrayNotHasKey('research', $report['intelligence']);
        $this->assertSame('spy_success', $fleet->fresh()->outcome);
    }

    public function test_colonization_consumes_one_colony_ship_and_rejects_occupied_positions(): void
    {
        [$user, $home] = $this->player();
        $home->forceFill(['unit_208' => 2])->save();
        [$system, $position] = $this->emptyPlanetPosition($user);
        $payload = ['mission' => 'colonize', 'galaxy' => 1, 'system' => $system, 'position' => $position, 'ships' => [208 => 1], 'moon_orbit' => null];
        $this->actingAs($user)->get(route('galaxy.index', ['planet' => $home->id, 'system' => $system]))->assertInertia(fn (Assert $page) => $page
            ->where('systemVisible', false)->where('starName', null)->where('slots', []));
        $this->actingAs($user)->post(route('planet.missions.store', $home), $payload)->assertRedirect();
        $this->assertDatabaseHas('fleets', ['mission' => 'colonize', 'origin_planet_id' => $home->id]);
        $fleet = Fleet::firstOrFail();
        $this->travelTo($fleet->arrives_at->addSecond());
        app(ExplorationMissions::class)->processDue();
        $this->assertSame(2, $user->planets()->where('universe_id', $user->universe_id)->count());
        $colonizedPlanet = Planet::where('universe_id', $user->universe_id)->where('galaxy', 1)->where('system', $system)->where('position', $position)->firstOrFail();
        $this->assertSame(0, (int) $colonizedPlanet->unit_208);
        $this->assertSame('colonized', $fleet->fresh()->outcome);
        $generatedName = app(GalaxyMap::class)->system($user->universe, $user, 1, $system, $home->position)['slots'][$position - 2]['generatedName'];
        $this->assertSame($generatedName, $user->planets()->where('universe_id', $user->universe_id)
            ->where('galaxy', 1)->where('system', $system)->where('position', $position)->value('name'));
        $this->assertSame($generatedName, $fleet->fresh()->mission_report['colonizedPlanetName']);
        $this->get(route('galaxy.index', ['planet' => $home->id, 'system' => $system]))->assertInertia(fn (Assert $page) => $page
            ->where('systemVisible', true)->where('slots.'.($position - 2).'.planet.name', $generatedName));
    }

    public function test_recycling_debris_is_depleted_once_and_moon_stock_is_removed_when_destroyed(): void
    {
        [$user, $home] = $this->player();
        $home->forceFill(['unit_209' => 2])->save();
        DB::table('orbital_debris')->insert(['universe_id' => $user->universe_id, 'galaxy' => 1, 'system' => 1, 'position' => 7, 'metal' => 1000, 'crystal' => 500, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->post(route('planet.missions.store', $home), [
            'mission' => 'recycle', 'target_type' => 'debris', 'galaxy' => 1, 'system' => 1, 'position' => 7, 'ships' => [209 => 1],
        ])->assertRedirect();
        $fleet = Fleet::query()->where('origin_planet_id', $home->id)->where('mission', 'recycle')->where('target_position', 7)->firstOrFail();
        $this->travelTo($fleet->arrives_at->addSecond());
        app(ExplorationMissions::class)->processDue();
        $this->assertDatabaseHas('orbital_debris', ['universe_id' => $user->universe_id, 'galaxy' => 1, 'system' => 1, 'position' => 7, 'metal' => 0, 'crystal' => 0]);
        app(ExplorationMissions::class)->processDue();
        $this->assertSame(1500, array_sum($fleet->fresh()->cargo));
    }

    public function test_deathstar_destroys_only_an_existing_moon_and_clears_its_stock(): void
    {
        $this->forceOutcome(1);
        [$attacker, $home] = $this->player();
        [$defender, $target] = $this->opponent($attacker);
        $orbit = $this->moonOrbit($attacker, $target);
        $home->forceFill(['unit_214' => 1])->save();
        $attacker->forceFill(['military_technology_level' => 100, 'spy_technology_level' => abs($target->position - $home->position)])->save();
        $this->travelTo(now()->startOfSecond());
        DB::table('moon_stocks')->insert(['planet_id' => $target->id, 'orbit' => $orbit, 'metal' => 900, 'crystal' => 800, 'deuterium' => 700, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($attacker)->post(route('planet.missions.store', $home), [
            'mission' => 'destroy_moon', 'target_type' => 'moon', 'galaxy' => 1, 'system' => $target->system, 'position' => $target->position,
            'moon_orbit' => $orbit, 'ships' => [214 => 1],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $fleet = Fleet::where('origin_planet_id', $home->id)->where('mission', 'destroy_moon')->firstOrFail();
        $this->assertSame($orbit, (int) $fleet->target_orbit);
        $this->travelTo($fleet->arrives_at->addSecond());
        app(ExplorationMissions::class)->processDue();
        $this->assertSame('moon_destroyed', $fleet->fresh()->outcome);
        $this->assertDatabaseMissing('moon_stocks', ['planet_id' => $target->id, 'orbit' => $orbit]);
        $slot = app(GalaxyMap::class)->system($attacker->universe, $attacker, 1, $target->system, $home->position)['slots'][7];
        $this->assertFalse(collect($slot['naturalMoons'] ?? [])->contains(fn (array $moon): bool => $moon['orbit'] === $orbit));
        $this->from(route('galaxy.index'))->post(route('planet.missions.store', $home), [
            'mission' => 'destroy_moon', 'target_type' => 'moon', 'galaxy' => 1, 'system' => $target->system, 'position' => $target->position,
            'moon_orbit' => $orbit, 'ships' => [214 => 1],
        ])->assertSessionHasErrors('moon_orbit');
    }

    public function test_experimental_destroyer_is_disabled_by_default_and_requires_feature_flag(): void
    {
        [$attacker, $home] = $this->player();
        [$defender, $target] = $this->opponent($attacker);
        $attacker->forceFill(['officer_emperor_level' => 1])->save();
        $home->forceFill(['unit_217' => 1])->save();
        $payload = ['mission' => 'destroy_planet', 'galaxy' => 1, 'system' => 1, 'position' => 9, 'ships' => [217 => 1]];
        config(['combat.experimental_planetary_destroyer' => false]);
        $this->actingAs($attacker)->from(route('galaxy.index'))->post(route('planet.missions.store', $home), $payload)->assertSessionHasErrors('mission');
        config(['combat.experimental_planetary_destroyer' => true]);
        $this->post(route('planet.missions.store', $home), $payload)->assertRedirect();
        $fleet = Fleet::firstOrFail();
        $this->travelTo($fleet->arrives_at->addSecond());
        app(ExplorationMissions::class)->processDue();
        $this->assertNotNull($target->fresh()->destroyed_at);
        $this->assertSame(0, (int) $target->fresh()->metal);
        $this->assertSame(0, (int) $target->fresh()->unit_204);
        $slot = app(GalaxyMap::class)->system($attacker->universe, $attacker, 1, $target->system, $home->position)['slots'][7];
        $this->assertArrayNotHasKey('planet', $slot);
        $this->assertArrayNotHasKey('moons', $slot);
    }

    private function moonOrbit(User $viewer, Planet $planet): int
    {
        for ($system = 1; $system <= 499; $system++) {
            $planet->forceFill(['system' => $system])->save();
            $systemData = app(GalaxyMap::class)->system($viewer->universe, $viewer, 1, $system, $planet->position);
            $slot = $systemData['slots'][$planet->position - 2];
            if (($slot['kind'] ?? null) === 'planet' && ($slot['naturalMoons'] ?? []) !== []) {
                $planet->forceFill(['system' => $system])->save();

                return (int) $slot['naturalMoons'][0]['orbit'];
            }
        }
        $this->fail('Target planet has no natural moon.');
    }

    private function planetAt(User $user, int $position, string $name): Planet
    {
        $planet = new Planet(['name' => $name]);
        $planet->forceFill(['universe_id' => $user->universe_id, 'user_id' => $user->id, 'galaxy' => 1, 'system' => 1,
            'position' => $position, 'resources_updated_at' => now()])->save();

        return $planet;
    }

    private function emptyPlanetPosition(User $user): array
    {
        for ($system = 2; $system <= 100; $system++) {
            for ($position = 2; $position <= 15; $position++) {
                $slot = app(GalaxyMap::class)->system($user->universe, $user, 1, $system, $position)['slots'][$position - 2];
                if ($position !== 8 && ($slot['kind'] ?? null) === 'planet' && $slot['planet'] === null) {
                    return [$system, $position];
                }
            }
        }
        $this->fail('No colonizable position found.');
    }

    private function opponent(User $user): array
    {
        $enemy = User::factory()->create(['universe_id' => $user->universe_id]);
        $planet = new Planet(['name' => 'Enemy']);
        $planet->forceFill(['universe_id' => $user->universe_id, 'user_id' => $enemy->id, 'galaxy' => 1, 'system' => 1,
            'position' => 9, 'resources_updated_at' => now()])->save();

        return [$enemy, $planet];
    }

    private function asteroidSystemAt(User $user, int $position): int
    {
        $user->forceFill(['spy_technology_level' => 13])->save();
        for ($system = 1; $system <= 100; $system++) {
            $slot = app(GalaxyMap::class)->system($user->universe, $user, 1, $system, 8)['slots'][$position - 2];
            if ($slot['kind'] === 'asteroid') {
                $this->establishSensorColony($user, $system);

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
                $this->establishSensorColony($user, $system);

                return [$system, $slot];
            }
        }

        $this->fail('Aucun champ d’astéroïdes trouvé.');
    }

    private function establishSensorColony(User $user, int $system): void
    {
        if ($system === 1) {
            return;
        }

        $colony = new Planet(['name' => 'Sensor colony']);
        $colony->forceFill(['universe_id' => $user->universe_id, 'user_id' => $user->id, 'galaxy' => 1, 'system' => $system,
            'position' => 8, 'resources_updated_at' => now()])->save();
    }
}
