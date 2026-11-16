<?php

namespace Tests\Feature;

use App\Models\Planet;
use App\Models\Universe;
use App\Models\User;
use App\Services\PlanetEconomy;
use App\Services\UnitProductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PlanetEconomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_planet_resources_accrue_from_mine_levels_with_hourly_fractions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00', 'UTC'));
        $planet = $this->createPlanet([
            'metal_mine_level' => 1,
            'crystal_mine_level' => 1,
            'deuterium_synthesizer_level' => 1,
            'metal_mine_activity' => 0,
            'crystal_mine_activity' => 0,
            'deuterium_synthesizer_activity' => 0,
            'resources_updated_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
        ]);
        $economy = app(PlanetEconomy::class);

        $economy->materialize($planet, Carbon::parse('2026-01-01 00:01:00', 'UTC'));
        $economy->materialize($planet->fresh(), Carbon::parse('2026-01-01 00:02:00', 'UTC'));

        $planet->refresh();
        $this->assertSame(500, $planet->metal);
        $this->assertSame(500, $planet->crystal);
        $this->assertSame(0, $planet->deuterium);
        $this->assertSame(2400, $planet->metal_production_remainder);
        $this->assertSame(1200, $planet->crystal_production_remainder);
        $this->assertSame(0, $planet->deuterium_production_remainder);
        Carbon::setTestNow();
    }

    public function test_partial_production_is_carried_forward_across_multiple_refreshes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00', 'UTC'));
        $planet = $this->createPlanet([
            'metal_mine_level' => 1,
            'metal_mine_activity' => 0,
            'metal' => 0,
            'resources_updated_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
        ]);
        $economy = app(PlanetEconomy::class);

        $economy->materialize($planet, Carbon::parse('2026-01-01 00:00:30', 'UTC'));
        $this->assertSame(0, $planet->fresh()->metal);
        $economy->materialize($planet->fresh(), Carbon::parse('2026-01-01 00:01:00', 'UTC'));

        $this->assertSame(0, $planet->fresh()->metal);
        Carbon::setTestNow();
    }

    public function test_dashboard_materializes_production_and_returns_upgrade_costs(): void
    {
        $planet = $this->createPlanet([
            'metal_mine_level' => 1,
            'metal_mine_activity' => 10,
            'metal' => 100,
            'resources_updated_at' => now()->subHour(),
        ]);

        $this->actingAs($planet->user)->get('/commandcenter')->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('selectedPlanetId', null)
            ->where('planets.0.metal', 120)
            ->where('planets.0.production.metal', 33)
            ->where('planets.0.liveResources.rates.metal', 20)
            ->where('planets.0.nextCosts.metal.metal', 90)
            ->where('planets.0.crystal', 510)
            ->where('planets.0.deuterium', 0)
        );
    }

    public function test_dashboard_exposes_authoritative_rates_and_next_production_change(): void
    {
        $this->freezeSecond();
        $planet = $this->createPlanet([
            'metal_mine_level' => 1,
            'solar_plant_level' => 1,
            'deuterium_synthesizer_level' => 5,
            'deuterium_synthesizer_activity' => 0,
            'fusion_plant_level' => 1,
            'metal_production_remainder' => 3000,
            'fusion_consumption_remainder' => 660,
        ]);
        $planet->buildingQueue()->create([
            'universe_id' => $planet->universe_id, 'building_key' => 'metal', 'building_code' => 1,
            'target_level' => 2, 'position' => 1, 'cost_metal' => 0, 'cost_crystal' => 0, 'cost_deuterium' => 0,
            'starts_at' => now(), 'completes_at' => now()->addMinutes(2),
        ]);
        $planet->unitQueue()->create([
            'universe_id' => $planet->universe_id, 'unit_code' => 212, 'unit_type' => 'ships',
            'quantity' => 1, 'quantity_completed' => 0, 'position' => 1,
            'cost_metal' => 0, 'cost_crystal' => 0, 'cost_deuterium' => 0,
            'starts_at' => now(), 'completes_at' => now()->addMinute(),
        ]);

        $this->actingAs($planet->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('planets.0.liveResources.updatedAt', now()->toIso8601String())
                ->where('planets.0.liveResources.rates.metal', 53)
                ->where('planets.0.liveResources.rates.crystal', 10)
                ->where('planets.0.liveResources.rates.deuterium', 0)
                ->where('planets.0.liveResources.remainders.metal', 3000)
                ->where('planets.0.liveResources.capacities.metal', 1000000)
                ->where('planets.0.liveResources.fusionUsage', 11)
                ->where('planets.0.liveResources.fusionRemainder', 660)
                ->where('planets.0.liveResources.nextChangeAt', now()->addMinute()->toIso8601String()));
    }

    public function test_dashboard_partial_refresh_preserves_production_and_fusion_consumption_fractions(): void
    {
        $this->freezeSecond();
        $planet = $this->createPlanet([
            'metal' => 100,
            'deuterium' => 100,
            'deuterium_synthesizer_level' => 5,
            'deuterium_synthesizer_activity' => 0,
            'fusion_plant_level' => 1,
            'fusion_plant_activity' => 10,
        ]);
        $initialPage = $this->actingAs($planet->user)->get(route('dashboard', ['planet' => $planet->id]))->viewData('page');
        $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $initialPage['version'] ?? '',
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data' => 'planets',
        ]);

        $this->travel(1)->minute();
        $this->get(route('dashboard', ['planet' => $planet->id]))
            ->assertOk()->assertJsonPath('props.planets.0.metal', 100)->assertJsonPath('props.planets.0.deuterium', 100);
        $this->assertSame(660, (int) $planet->fresh()->fusion_consumption_remainder);

        $this->travel(5)->minutes();
        $this->get(route('dashboard', ['planet' => $planet->id]))
            ->assertOk()->assertJsonPath('props.planets.0.metal', 102)->assertJsonPath('props.planets.0.deuterium', 99);
        $this->assertSame(360, (int) $planet->fresh()->fusion_consumption_remainder);
    }

    public function test_fusion_consumption_does_not_carry_unpaid_fractions_after_deuterium_is_exhausted(): void
    {
        $this->freezeSecond();
        $planet = $this->createPlanet([
            'deuterium' => 1,
            'deuterium_synthesizer_level' => 5,
            'deuterium_synthesizer_activity' => 0,
            'fusion_plant_level' => 1,
            'fusion_plant_activity' => 10,
        ]);
        $economy = app(PlanetEconomy::class);

        $this->travel(6)->minutes();
        $economy->materialize($planet);
        $this->assertSame(0, (int) $planet->fresh()->deuterium);
        $this->assertSame(0, (int) $planet->fresh()->fusion_consumption_remainder);

        $planet->refresh()->forceFill(['deuterium' => 100])->save();
        $this->travel(1)->minute();
        $economy->materialize($planet);
        $this->assertSame(100, (int) $planet->fresh()->deuterium);
        $this->assertSame(660, (int) $planet->fresh()->fusion_consumption_remainder);
    }

    public function test_commander_can_queue_a_mine_upgrade_and_the_cost_is_deducted(): void
    {
        $planet = $this->createPlanet(['metal' => 100, 'crystal' => 50]);

        $this->actingAs($planet->user)
            ->post(route('planet.buildings.upgrade', $planet), ['building' => 'metal'])
            ->assertRedirect(route('buildings.index', ['planet' => $planet->id]));

        $planet->refresh();
        $this->assertSame(0, $planet->metal_mine_level);
        $this->assertSame(40, $planet->metal);
        $this->assertSame(35, $planet->crystal);
        $this->assertDatabaseHas('planet_building_queues', ['planet_id' => $planet->id, 'building_key' => 'metal', 'target_level' => 1]);

        $this->actingAs($planet->user)->get(route('buildings.index', ['planet' => $planet->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buildings/Index')
                ->where('selectedPlanetId', $planet->id)
                ->where('planets.0.metal', 40)
                ->where('planets.0.crystal', 35)
                ->where('planets.0.queue.0.building_key', 'metal')
                ->where('planets.0.queue.0.target_level', 1)
                ->where('planets.0.queue.0.duration_seconds', 108)
                ->has('planets.0.queue.0.starts_at')
                ->has('planets.0.queue.0.completes_at'));

    }

    public function test_upgrade_charges_only_the_planets_owned_resources(): void
    {
        $planet = $this->createPlanet(['metal' => 100, 'crystal' => 10]);
        $otherPlanet = new Planet(['name' => 'Rich Prime']);
        $otherPlanet->universe()->associate($planet->universe);
        $otherPlanet->user()->associate($planet->user);
        $otherPlanet->galaxy = 1;
        $otherPlanet->system = 2;
        $otherPlanet->position = 8;
        $otherPlanet->metal = 100000;
        $otherPlanet->crystal = 100000;
        $otherPlanet->deuterium = 100000;
        $otherPlanet->fields = 163;
        $otherPlanet->resources_updated_at = now();
        $otherPlanet->save();

        $this->actingAs($planet->user)
            ->from('/commandcenter')
            ->post(route('planet.buildings.upgrade', $planet), ['building' => 'crystal'])
            ->assertSessionHasErrors('resources');

        $this->assertSame(0, $planet->fresh()->crystal_mine_level);
        $this->assertSame(100, $planet->fresh()->metal);
        $this->assertSame(100000, $otherPlanet->fresh()->metal);
    }

    public function test_upgrade_is_rejected_when_resources_are_insufficient(): void
    {
        $planet = $this->createPlanet(['metal' => 59, 'crystal' => 500]);

        $this->actingAs($planet->user)
            ->from('/commandcenter')
            ->post(route('planet.buildings.upgrade', $planet), ['building' => 'metal'])
            ->assertSessionHasErrors('resources');

        $planet->refresh();
        $this->assertSame(0, $planet->metal_mine_level);
        $this->assertSame(59, $planet->metal);
    }

    public function test_commander_cannot_upgrade_another_users_planet(): void
    {
        $planet = $this->createPlanet();
        $otherCommander = User::factory()->create(['universe_id' => $planet->universe_id]);

        $this->actingAs($otherCommander)
            ->post(route('planet.buildings.upgrade', $planet), ['building' => 'metal'])
            ->assertNotFound();

        $this->assertSame(0, $planet->fresh()->metal_mine_level);
    }

    public function test_buildings_route_redirects_to_command_center_when_commander_has_no_planets(): void
    {
        $commander = User::factory()->create();

        $this->actingAs($commander)
            ->get(route('buildings.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    }

    public function test_dashboard_and_buildings_pages_use_inertia_and_expose_energy(): void
    {
        $planet = $this->createPlanet([
            'metal_mine_level' => 1,
            'metal_mine_activity' => 10,
            'solar_plant_level' => 1,
            'solar_plant_activity' => 10,
            'crystal_mine_activity' => 0,
            'deuterium_synthesizer_activity' => 0,
        ]);

        $this->actingAs($planet->user)
            ->get(route('buildings.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buildings/Index')
                ->where('selectedPlanetId', $planet->id)
                ->where('planets.0.energy.supply', 22)
                ->where('planets.0.energy.usage', 11)
                ->where('planets.0.energy.productionLevel', 100)
            );
    }

    public function test_activity_levels_are_limited_to_legacy_ten_step_scale(): void
    {
        $planet = $this->createPlanet();

        $this->actingAs($planet->user)
            ->post(route('planet.buildings.upgrade', $planet), ['building' => 'metal', 'activity' => 7])
            ->assertRedirect(route('buildings.index', ['planet' => $planet->id]));

        $this->assertSame(7, $planet->fresh()->metal_mine_activity);

        $this->actingAs($planet->user)
            ->from(route('buildings.index', ['planet' => $planet->id]))
            ->post(route('planet.buildings.upgrade', $planet), ['building' => 'metal', 'activity' => 11])
            ->assertSessionHasErrors('activity');
    }

    public function test_solar_power_and_energy_deficits_match_legacy_production_rules(): void
    {
        $planet = $this->createPlanet([
            'metal_mine_level' => 1,
            'metal_mine_activity' => 10,
            'metal' => 0,
            'resources_updated_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
        ]);
        $planet->solar_plant_level = 1;
        $planet->solar_plant_activity = 10;
        $planet->save();
        $economy = app(PlanetEconomy::class);
        $this->assertSame(100, $economy->energyBalance($planet)['productionLevel']);

        $economy->materialize($planet, Carbon::parse('2026-01-01 01:00:00', 'UTC'));
        $planet = $planet->fresh();
        $economy->completeBuildingQueue($planet);
        $this->assertSame(53, $planet->fresh()->metal);
        $this->assertSame(1, $planet->fresh()->metal_mine_level);
    }

    #[TestWith([40, 2001, 60030])]
    #[TestWith([1, 3, 60])]
    #[TestWith([-40, 3, 30])]
    #[TestWith([-80, 5, 0])]
    #[TestWith([-100, 5, 0])]
    #[TestWith([40, 0, 0])]
    public function test_solar_satellite_output_depends_on_temperature_and_quantity(int $temperature, int $satellites, int $expected): void
    {
        $planet = $this->createPlanet(['max_temperature' => $temperature, 'unit_212' => $satellites]);

        $energy = app(PlanetEconomy::class)->energyBalance($planet);

        $this->assertSame($expected, $energy['sources']['satellites']);
        $this->assertSame($expected, $energy['supply']);
    }

    public function test_fractional_mine_energy_consumption_rounds_up_like_the_legacy_game(): void
    {
        $planet = $this->createPlanet([
            'metal_mine_level' => 3,
            'metal_mine_activity' => 10,
            'crystal_mine_level' => 3,
            'crystal_mine_activity' => 10,
            'deuterium_synthesizer_level' => 3,
            'deuterium_synthesizer_activity' => 10,
        ]);

        $energy = app(PlanetEconomy::class)->energyBalance($planet);

        $this->assertSame(200, $energy['usage']);
    }

    public function test_solar_satellites_power_mines_and_are_included_in_the_buildings_page(): void
    {
        $this->freezeSecond();
        $planet = $this->createPlanet([
            'metal' => 0,
            'metal_mine_level' => 2,
            'metal_mine_activity' => 10,
            'max_temperature' => 40,
            'unit_212' => 1,
            'resources_updated_at' => now()->subHour(),
        ]);

        $this->actingAs($planet->user)->get(route('buildings.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('planets.0.energy.sources.satellites', 30)
                ->where('planets.0.energy.supply', 30)
                ->where('planets.0.energy.usage', 25)
                ->where('planets.0.energy.balance', 5)
                ->where('planets.0.energy.productionLevel', 100)
                ->where('planets.0.metal', 92)
            );
    }

    public function test_completed_satellites_power_mines_from_the_end_of_their_queue(): void
    {
        $this->freezeSecond();
        $planet = $this->createPlanet([
            'metal' => 0,
            'metal_mine_level' => 2,
            'metal_mine_activity' => 10,
            'max_temperature' => 40,
            'resources_updated_at' => now()->subHour(),
        ]);
        $planet->unitQueue()->create([
            'universe_id' => $planet->universe_id, 'unit_code' => 212, 'unit_type' => 'ships',
            'quantity' => 1, 'quantity_completed' => 0, 'position' => 1,
            'cost_metal' => 0, 'cost_crystal' => 2000, 'cost_deuterium' => 500,
            'starts_at' => now()->subHour(), 'completes_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($planet->user)->get(route('buildings.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('planets.0.energy.sources.satellites', 30)
                ->where('planets.0.metal', 56));

        $this->assertSame(1, (int) $planet->fresh()->unit_212);
        $this->assertDatabaseCount('planet_unit_queues', 0);
    }

    public function test_completing_a_shipyard_queue_does_not_apply_satellites_retroactively(): void
    {
        $this->freezeSecond();
        $planet = $this->createPlanet([
            'metal' => 0,
            'metal_mine_level' => 2,
            'metal_mine_activity' => 10,
            'max_temperature' => 40,
            'resources_updated_at' => now()->subHour(),
        ]);
        $planet->unitQueue()->create([
            'universe_id' => $planet->universe_id, 'unit_code' => 212, 'unit_type' => 'ships',
            'quantity' => 1, 'quantity_completed' => 0, 'position' => 1,
            'cost_metal' => 0, 'cost_crystal' => 2000, 'cost_deuterium' => 500,
            'starts_at' => now()->subHour(), 'completes_at' => now()->subMinutes(30),
        ]);

        app(UnitProductionService::class)->complete($planet);
        app(PlanetEconomy::class)->materialize($planet);

        $this->assertSame(56, $planet->fresh()->metal);
        $this->assertSame(1, (int) $planet->fresh()->unit_212);
        $this->assertDatabaseCount('planet_unit_queues', 0);
    }

    public function test_satellite_queue_processes_only_completed_batches_in_order(): void
    {
        $this->freezeSecond();
        $planet = $this->createPlanet([
            'metal' => 0,
            'metal_mine_level' => 2,
            'metal_mine_activity' => 10,
            'max_temperature' => 40,
            'resources_updated_at' => now()->subHour(),
        ]);
        foreach ([-40, -20, 20] as $position => $minutes) {
            $planet->unitQueue()->create([
                'universe_id' => $planet->universe_id, 'unit_code' => 212, 'unit_type' => 'ships',
                'quantity' => 1, 'quantity_completed' => 0, 'position' => $position + 1,
                'cost_metal' => 0, 'cost_crystal' => 2000, 'cost_deuterium' => 500,
                'starts_at' => now()->subHour(), 'completes_at' => now()->addMinutes($minutes),
            ]);
        }

        app(PlanetEconomy::class)->materialize($planet);

        $this->assertSame(68, $planet->fresh()->metal);
        $this->assertSame(2, (int) $planet->fresh()->unit_212);
        $this->assertDatabaseCount('planet_unit_queues', 1);
        $this->assertDatabaseHas('planet_unit_queues', ['planet_id' => $planet->id, 'unit_code' => 212, 'position' => 1]);
    }

    public function test_ship_order_preserves_resources_produced_when_satellites_complete(): void
    {
        $this->freezeSecond();
        $planet = $this->createPlanet([
            'metal' => 2000,
            'crystal' => 2000,
            'metal_mine_level' => 2,
            'metal_mine_activity' => 10,
            'max_temperature' => 40,
            'hangar_level' => 2,
            'robot_factory_level' => 2,
            'resources_updated_at' => now()->subHour(),
        ]);
        $planet->user->forceFill(['combustion_technology_level' => 2])->save();
        $planet->unitQueue()->create([
            'universe_id' => $planet->universe_id, 'unit_code' => 212, 'unit_type' => 'ships',
            'quantity' => 1, 'quantity_completed' => 0, 'position' => 1,
            'cost_metal' => 0, 'cost_crystal' => 2000, 'cost_deuterium' => 500,
            'starts_at' => now()->subHour(), 'completes_at' => now()->subMinutes(30),
        ]);

        app(UnitProductionService::class)->build($planet->user, $planet, 'ships', 202, 1);

        $this->assertSame(56, $planet->fresh()->metal);
        $this->assertSame(1, (int) $planet->fresh()->unit_212);
        $this->assertDatabaseHas('planet_unit_queues', ['planet_id' => $planet->id, 'unit_code' => 202]);
    }

    public function test_building_queue_completes_after_its_scheduled_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00', 'UTC'));
        $planet = $this->createPlanet(['metal' => 100, 'crystal' => 50]);
        $economy = app(PlanetEconomy::class);
        $economy->upgrade($planet, 'metal');
        $this->assertSame(0, $planet->fresh()->metal_mine_level);

        Carbon::setTestNow(Carbon::parse('2026-01-01 00:02:00', 'UTC'));
        $economy->completeBuildingQueue($planet->fresh());
        $this->assertSame(1, $planet->fresh()->metal_mine_level);
        $this->assertDatabaseCount('planet_building_queues', 0);
        Carbon::setTestNow();
    }

    public function test_unknown_buildings_and_maximum_level_are_rejected(): void
    {
        $planet = $this->createPlanet(['metal' => 100000, 'crystal' => 100000]);

        $this->actingAs($planet->user)
            ->from('/commandcenter')
            ->post(route('planet.buildings.upgrade', $planet), ['building' => 'missiles'])
            ->assertSessionHasErrors('building');

        $planet->metal_mine_level = 30;
        $planet->save();

        $this->actingAs($planet->user)
            ->from('/commandcenter')
            ->post(route('planet.buildings.upgrade', $planet), ['building' => 'metal'])
            ->assertSessionHasErrors('building');
    }

    public function test_snapshot_returns_upgrade_costs_and_checks_terraformer_energy_cost(): void
    {
        $planet = $this->createPlanet([
            'metal' => 2_000_000,
            'crystal' => 2_000_000,
            'deuterium' => 2_000_000,
            'robot_factory_level' => 10,
            'solar_plant_level' => 30,
            'solar_plant_activity' => 10,
        ]);
        $economy = app(PlanetEconomy::class);

        $withoutResearch = $economy->snapshot($planet, 0, 0);
        $withResearch = $economy->snapshot($planet, 12, 10);

        $this->assertSame(['metal' => 2000, 'crystal' => 0, 'deuterium' => 0, 'energy' => 0], $withoutResearch['nextCosts']['metal_storage']);
        $this->assertSame(0, $withResearch['nextCosts']['terraformer']['metal']);
        $this->assertSame(1000, $withResearch['nextCosts']['terraformer']['energy']);
        $this->assertSame(false, $withoutResearch['canUpgrade']['terraformer']);
        $this->assertSame(true, $withResearch['canUpgrade']['terraformer']);
        $this->assertSame(true, $withResearch['canUpgrade']['nano_factory']);
        $this->assertSame(163, $withResearch['maxFields']);
    }

    public function test_terraformer_adds_five_available_fields_when_queued_upgrade_completes(): void
    {
        $planet = $this->createPlanet([
            'metal' => 100,
            'crystal' => 100_000,
            'deuterium' => 200_000,
            'solar_plant_level' => 30,
            'solar_plant_activity' => 10,
        ]);
        $economy = app(PlanetEconomy::class);

        $planet->user->forceFill(['energy_technology_level' => 12])->save();
        $planet->refresh();
        $economy = app(PlanetEconomy::class);
        Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00', 'UTC'));
        $economy->upgrade($planet, 'terraformer', 12);

        Carbon::setTestNow(Carbon::parse('2026-01-05 00:00:00', 'UTC'));
        $economy->completeBuildingQueue($planet->fresh());
        $this->assertSame(168, $planet->fresh()->fields);
        Carbon::setTestNow();
    }

    public function test_production_does_not_remove_resources_above_storage_capacity(): void
    {
        $planet = $this->createPlanet(['metal' => 1_500_000, 'resources_updated_at' => now()->subHour()]);

        app(PlanetEconomy::class)->materialize($planet);

        $this->assertSame(1_500_000, (int) $planet->fresh()->metal);
    }

    public function test_storage_building_can_be_added_through_validated_building_endpoint(): void
    {
        $planet = $this->createPlanet(['metal' => 5000]);

        $this->actingAs($planet->user)
            ->post(route('planet.buildings.upgrade', $planet), ['building' => 'metal_storage'])
            ->assertRedirect(route('buildings.index', ['planet' => $planet->id]));

        $this->assertDatabaseHas('planet_building_queues', ['planet_id' => $planet->id, 'building_key' => 'metal_storage']);
    }

    public function test_buildings_page_renders_storage_robot_nano_and_terraformer_entries(): void
    {
        $planet = $this->createPlanet([
            'metal' => 2_000_000,
            'crystal' => 2_000_000,
            'deuterium' => 2_000_000,
            'robot_factory_level' => 10,
        ]);
        $planet->user->forceFill(['energy_technology_level' => 11, 'computer_technology_level' => 10])->save();

        $this->actingAs($planet->user)->get(route('buildings.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buildings/Index')
                ->where('planets.0.metal_storage_level', 0)
                ->where('planets.0.crystal_storage_level', 0)
                ->where('planets.0.deuterium_storage_level', 0)
                ->where('planets.0.terraformer_level', 0)
                ->where('planets.0.maxFields', 163)
                ->where('planets.0.canUpgrade.metal_storage', true)
                ->where('planets.0.canUpgrade.crystal_storage', true)
                ->where('planets.0.canUpgrade.deuterium_storage', true)
                ->where('planets.0.canUpgrade.terraformer', false)
                ->where('technologyComputerLevel', 10)
                ->where('technologyEnergyLevel', 11));
    }

    public function test_commander_can_queue_storage_upgrades_and_capacity_increases_after_completion(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00', 'UTC'));
        $planet = $this->createPlanet(['metal' => 5_000, 'crystal' => 2_000]);

        $this->actingAs($planet->user)
            ->post(route('planet.buildings.upgrade', $planet), ['building' => 'metal_storage'])
            ->assertRedirect(route('buildings.index', ['planet' => $planet->id]));

        $this->assertDatabaseHas('planet_building_queues', ['planet_id' => $planet->id, 'building_key' => 'metal_storage', 'building_code' => 22]);
        $this->assertSame(1_000_000, app(PlanetEconomy::class)->storageCapacities($planet->fresh())['metal']);
        $this->assertSame(1_500_000, app(PlanetEconomy::class)->buildingProduction($planet->fresh(), 'metal_storage'));

        Carbon::setTestNow(Carbon::parse('2026-01-01 01:00:00', 'UTC'));
        app(PlanetEconomy::class)->completeBuildingQueue($planet->fresh());

        $this->assertSame(1, $planet->fresh()->metal_storage_level);
        $this->assertSame(1_500_000, app(PlanetEconomy::class)->storageCapacities($planet->fresh())['metal']);
        $this->assertSame(1_500_000, app(PlanetEconomy::class)->buildingProduction($planet->fresh(), 'metal_storage'));
        Carbon::setTestNow();
    }

    public function test_storage_capacity_prevents_resource_production_from_exceeding_the_level_limit(): void
    {
        $planet = $this->createPlanet([
            'metal' => 1_099_990,
            'metal_storage_level' => 0,
            'resources_updated_at' => now()->subHour(),
        ]);

        app(PlanetEconomy::class)->materialize($planet);

        $this->assertSame(1_100_000, $planet->fresh()->metal);
    }

    public function test_robot_factory_and_nanites_reduce_building_duration(): void
    {
        $economy = app(PlanetEconomy::class);
        $cost = ['metal' => 60, 'crystal' => 15];
        $planet = $this->createPlanet(['robot_factory_level' => 1, 'nano_factory_level' => 1]);

        $this->assertSame(27, $economy->buildingDuration($cost, $planet));
    }

    public function test_nanite_factory_requires_robot_factory_and_computer_technology_level_ten(): void
    {
        $planet = $this->createPlanet(['metal' => 2_000_000, 'crystal' => 1_000_000, 'deuterium' => 500_000]);

        $this->actingAs($planet->user)
            ->from(route('buildings.index', ['planet' => $planet->id]))
            ->post(route('planet.buildings.upgrade', $planet), ['building' => 'nano_factory'])
            ->assertSessionHasErrors('building');

        $this->assertDatabaseMissing('planet_building_queues', ['planet_id' => $planet->id, 'building_key' => 'nano_factory']);
    }

    public function test_commander_can_queue_nanite_factory_when_factory_and_computer_requirements_are_met(): void
    {
        $planet = $this->createPlanet([
            'metal' => 2_000_000,
            'crystal' => 1_000_000,
            'deuterium' => 500_000,
            'robot_factory_level' => 10,
        ]);
        $planet->user->forceFill(['computer_technology_level' => 10])->save();

        $this->actingAs($planet->user)
            ->post(route('planet.buildings.upgrade', $planet), ['building' => 'nano_factory'])
            ->assertRedirect(route('buildings.index', ['planet' => $planet->id]));

        $this->assertDatabaseHas('planet_building_queues', [
            'planet_id' => $planet->id,
            'building_key' => 'nano_factory',
            'building_code' => 15,
            'target_level' => 1,
        ]);
        $this->assertSame(1_000_000, $planet->fresh()->metal);
    }

    public function test_terraformer_requires_energy_technology_level_twelve(): void
    {
        $planet = $this->createPlanet(['metal' => 500_000, 'crystal' => 500_000, 'deuterium' => 500_000]);

        $this->actingAs($planet->user)
            ->from(route('buildings.index', ['planet' => $planet->id]))
            ->post(route('planet.buildings.upgrade', $planet), ['building' => 'terraformer'])
            ->assertSessionHasErrors('building');

        $this->assertDatabaseMissing('planet_building_queues', ['planet_id' => $planet->id, 'building_key' => 'terraformer']);
    }

    private function createPlanet(array $attributes = []): Planet
    {
        $universe = Universe::create(['name' => 'Andromède', 'slug' => fake()->unique()->slug()]);
        $user = User::factory()->create(['universe_id' => $universe->id]);
        $planet = new Planet(['name' => 'Nova Prime']);
        $planet->universe()->associate($universe);
        $planet->user()->associate($user);
        $planet->galaxy = 1;
        $planet->system = 1;
        $planet->position = 8;
        $planet->metal = 500;
        $planet->crystal = 500;
        $planet->deuterium = 0;
        $planet->fields = 163;
        $planet->resources_updated_at = now();
        $planet->forceFill($attributes);
        $planet->save();

        return $planet;
    }
}
