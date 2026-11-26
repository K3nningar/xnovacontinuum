<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Tests the application's UnitProductionTest behavior.
 */

namespace Tests\Feature;

use App\Models\Planet;
use App\Models\ResearchQueue;
use App\Models\Universe;
use App\Models\User;
use App\Services\ResearchService;
use App\Services\UnitProductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UnitProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_completion_acquires_the_user_before_the_planet(): void
    {
        $planet = $this->createPlanet();
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        app(UnitProductionService::class)->complete($planet);

        $queries = collect($connection->getQueryLog())
            ->pluck('query')
            ->map(fn (string $query): string => str_replace(['"', '`'], '', strtolower($query)))
            ->filter(fn (string $query): bool => str_starts_with($query, 'select')
                && (str_contains($query, 'from users') || str_contains($query, 'from planets')))
            ->values();
        $connection->disableQueryLog();
        $connection->flushQueryLog();

        $this->assertStringContainsString('from users', $queries[0]);
        $this->assertStringContainsString('from planets', $queries[1]);
        if ($connection->getDriverName() === 'mysql') {
            $this->assertStringContainsString('for update', $queries[0]);
            $this->assertStringContainsString('for update', $queries[1]);
        }
    }

    public function test_shipyard_and_defence_pages_render_their_catalogs_for_owned_planets(): void
    {
        $planet = $this->createPlanet(['hangar_level' => 2, 'defence_center_level' => 2, 'robot_factory_level' => 2, 'metal' => 10000, 'crystal' => 10000]);
        $planet->user->forceFill(['combustion_technology_level' => 2])->save();
        $otherPlanet = $this->createPlanet(['hangar_level' => 2, 'robot_factory_level' => 2]);

        $this->get(route('shipyard.index'))->assertRedirect(route('login'));
        $this->actingAs($planet->user)->get(route('shipyard.index', ['planet' => $otherPlanet->id]))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($planet->user)->get(route('shipyard.index', ['planet' => $planet->id]))
            ->assertInertia(fn (Assert $page) => $page->component('Shipyard/Index')
                ->where('selectedPlanetId', $planet->id)->has('units', 16)
                ->where('units.0.code', 202)->where('units.0.rarity', 'ordinary')->where('units.0.requirementsMet', true)
                ->where('units.15.rarity', 'signature')->where('units.0.canBuild', true)->has('queue', 0));
        $this->actingAs($planet->user)->get(route('defences.index', ['planet' => $planet->id]))
            ->assertInertia(fn (Assert $page) => $page->component('Defences/Index')
                ->where('selectedPlanetId', $planet->id)->has('units', 11)
                ->where('units.0.code', 401)->where('units.0.rarity', 'ordinary')
                ->where('units.8.rarity', 'mythic')->where('units.0.canBuild', true));
    }

    public function test_bunker_unlocks_the_unique_planetary_protector(): void
    {
        $planet = $this->createPlanet([
            'defence_center_level' => 1, 'robot_factory_level' => 1,
            'metal' => 1000000, 'crystal' => 500000, 'deuterium' => 100000,
        ]);
        $user = $planet->user;

        $this->actingAs($user)->get(route('defences.index', ['planet' => $planet->id]))
            ->assertInertia(fn (Assert $page) => $page->where('units.8.code', 409)
                ->where('units.8.requirements.0.code', 609)->where('units.8.requirementsMet', false));
        $this->actingAs($user)->from(route('defences.index'))->post(route('planet.defences.store', $planet), ['code' => 409, 'quantity' => 1])
            ->assertSessionHasErrors(['unit' => 'Les prérequis de cette unité ne sont pas remplis.']);

        $user->forceFill([
            'officer_points' => 1,
            'officer_geologist_level' => 20,
            'officer_engineer_level' => 10,
            'officer_constructor_level' => 3,
            'officer_scientist_level' => 3,
            'officer_stockman_level' => 2,
            'officer_defender_level' => 2,
        ])->save();
        $production = app(UnitProductionService::class);
        $production->recruit($user, $planet, 609);
        DB::table('planet_unit_queues')->where('planet_id', $planet->id)->where('unit_code', 609)
            ->update(['completes_at' => now()->subSecond()]);
        $production->complete($planet);
        $this->assertSame(1, (int) $user->fresh()->officer_bunker_level);

        $this->actingAs($user)->post(route('planet.defences.store', $planet), ['code' => 409, 'quantity' => 1])
            ->assertRedirect(route('defences.index', ['planet' => $planet->id]));

        $this->assertDatabaseHas('planet_unit_queues', [
            'planet_id' => $planet->id, 'unit_code' => 409, 'unit_type' => 'defences',
            'quantity' => 1, 'cost_metal' => 250000, 'cost_crystal' => 100000, 'cost_deuterium' => 25000,
        ]);

        DB::table('planet_unit_queues')->where('planet_id', $planet->id)->where('unit_code', 409)
            ->update(['completes_at' => now()->subSecond()]);
        $production->complete($planet);
        $this->assertSame(1, (int) $planet->fresh()->unit_409);
    }

    public function test_raider_unlocks_the_supernova_for_production(): void
    {
        $planet = $this->createPlanet([
            'hangar_level' => 1, 'robot_factory_level' => 1,
            'metal' => 500000, 'crystal' => 250000, 'deuterium' => 100000,
        ]);
        $user = $planet->user;

        $this->actingAs($user)->get(route('shipyard.index', ['planet' => $planet->id]))
            ->assertInertia(fn (Assert $page) => $page->where('units.14.code', 216)
                ->where('units.14.requirements.0.code', 614)->where('units.14.requirementsMet', false));
        $this->actingAs($user)->from(route('shipyard.index'))->post(route('planet.ships.store', $planet), ['code' => 216, 'quantity' => 1])
            ->assertSessionHasErrors(['unit' => 'Les prérequis de cette unité ne sont pas remplis.']);

        $user->forceFill(['officer_raider_level' => 1])->save();
        $this->actingAs($user)->post(route('planet.ships.store', $planet), ['code' => 216, 'quantity' => 1])
            ->assertRedirect(route('shipyard.index', ['planet' => $planet->id]));

        $this->assertDatabaseHas('planet_unit_queues', [
            'planet_id' => $planet->id, 'unit_code' => 216, 'unit_type' => 'ships',
            'quantity' => 1, 'cost_metal' => 100000, 'cost_crystal' => 50000, 'cost_deuterium' => 20000,
        ]);

        DB::table('planet_unit_queues')->where('planet_id', $planet->id)->where('unit_code', 216)
            ->update(['completes_at' => now()->subSecond()]);
        app(UnitProductionService::class)->complete($planet);
        $this->assertSame(1, (int) $planet->fresh()->unit_216);
        $this->actingAs($user)->get(route('fleet.index', ['planet' => $planet->id]))
            ->assertInertia(fn (Assert $page) => $page->where('planets.0.unit_216', 1));
    }

    public function test_emperor_unlocks_a_single_planetary_destroyer_per_empire(): void
    {
        $firstPlanet = $this->createPlanet([
            'hangar_level' => 1, 'robot_factory_level' => 1,
            'metal' => 3000000, 'crystal' => 3000000, 'deuterium' => 1500000,
        ]);
        $secondPlanet = $this->createAdditionalPlanet($firstPlanet->user);
        $secondPlanet->forceFill(['hangar_level' => 1, 'robot_factory_level' => 1])->save();
        $user = $firstPlanet->user;

        $this->actingAs($user)->get(route('shipyard.index', ['planet' => $firstPlanet->id]))
            ->assertInertia(fn (Assert $page) => $page->where('units.15.code', 217)
                ->where('units.15.requirements.0.code', 615)->where('units.15.requirementsMet', false));
        $this->actingAs($user)->from(route('shipyard.index'))->post(route('planet.ships.store', $firstPlanet), ['code' => 217, 'quantity' => 1])
            ->assertSessionHasErrors(['unit' => 'Les prérequis de cette unité ne sont pas remplis.']);

        $user->forceFill(['officer_emperor_level' => 1])->save();
        $this->actingAs($user)->post(route('planet.ships.store', $firstPlanet), ['code' => 217, 'quantity' => 1])
            ->assertRedirect(route('shipyard.index', ['planet' => $firstPlanet->id]));
        $this->actingAs($user)->from(route('shipyard.index'))->post(route('planet.ships.store', $secondPlanet), ['code' => 217, 'quantity' => 1])
            ->assertSessionHasErrors(['unit' => 'Cette unité ne peut être construite qu’une seule fois à l’échelle de l’empire.']);

        $this->assertDatabaseCount('planet_unit_queues', 1);
        $this->assertDatabaseHas('planet_unit_queues', [
            'planet_id' => $firstPlanet->id, 'unit_code' => 217, 'unit_type' => 'ships',
            'quantity' => 1, 'cost_metal' => 1000000, 'cost_crystal' => 1000000, 'cost_deuterium' => 500000,
        ]);

        DB::table('planet_unit_queues')->where('planet_id', $firstPlanet->id)->where('unit_code', 217)
            ->update(['completes_at' => now()->subSecond()]);
        app(UnitProductionService::class)->complete($firstPlanet);
        $this->assertSame(1, (int) $firstPlanet->fresh()->unit_217);
        $this->actingAs($user)->from(route('shipyard.index'))->post(route('planet.ships.store', $secondPlanet), ['code' => 217, 'quantity' => 1])
            ->assertSessionHasErrors(['unit' => 'Cette unité ne peut être construite qu’une seule fois à l’échelle de l’empire.']);
        $this->assertDatabaseCount('planet_unit_queues', 0);
    }

    public function test_destroyer_reconstruction_cost_doubles_after_each_recorded_loss(): void
    {
        $planet = $this->createPlanet([
            'hangar_level' => 1, 'robot_factory_level' => 1,
            'metal' => 5000000, 'crystal' => 5000000, 'deuterium' => 2500000,
        ]);
        $user = $planet->user;
        $user->forceFill(['officer_emperor_level' => 1])->save();
        $production = app(UnitProductionService::class);

        $production->recordPlanetaryDestroyerLoss($user);
        $production->recordPlanetaryDestroyerLoss($user);

        $unit = collect($production->catalog($planet, $user->fresh(), 'ships')['units'])->firstWhere('code', 217);
        $this->assertSame(['metal' => 4000000, 'crystal' => 4000000, 'deuterium' => 2000000], $unit['cost']);

        $production->build($user->fresh(), $planet, 'ships', 217, 1);
        $this->assertDatabaseHas('planet_unit_queues', [
            'planet_id' => $planet->id, 'unit_code' => 217, 'cost_metal' => 4000000,
            'cost_crystal' => 4000000, 'cost_deuterium' => 2000000,
        ]);
        $this->assertSame(2, (int) $user->fresh()->planetary_destroyer_losses);
    }

    public function test_ship_production_debits_resources_and_queues_units(): void
    {
        $planet = $this->createPlanet(['hangar_level' => 2, 'robot_factory_level' => 2, 'metal' => 10000, 'crystal' => 10000]);
        $planet->user->forceFill(['combustion_technology_level' => 2])->save();

        $this->actingAs($planet->user)->post(route('planet.ships.store', $planet), ['code' => 202, 'quantity' => 2])
            ->assertRedirect(route('shipyard.index', ['planet' => $planet->id]));

        $this->assertSame(6000, $planet->fresh()->metal);
        $this->assertSame(6000, $planet->fresh()->crystal);
        $this->assertDatabaseHas('planet_unit_queues', [
            'planet_id' => $planet->id, 'unit_code' => 202, 'unit_type' => 'ships',
            'quantity' => 2, 'quantity_completed' => 0, 'cost_metal' => 4000, 'cost_crystal' => 4000,
        ]);
    }

    public function test_defences_require_the_defence_center_instead_of_the_shipyard(): void
    {
        $planet = $this->createPlanet(['hangar_level' => 8, 'robot_factory_level' => 1, 'metal' => 10000, 'crystal' => 10000]);
        $user = $planet->user;
        $production = app(UnitProductionService::class);

        $rocket = collect($production->defenceCatalog($planet, $user)['units'])->firstWhere('code', 401);
        $this->assertFalse($rocket['requirementsMet']);
        $this->actingAs($user)->from(route('defences.index'))->post(route('planet.defences.store', $planet), ['code' => 401, 'quantity' => 1])
            ->assertSessionHasErrors('unit');

        $planet->forceFill(['hangar_level' => 0, 'defence_center_level' => 1])->save();
        $rocket = collect($production->defenceCatalog($planet->fresh(), $user)['units'])->firstWhere('code', 401);
        $this->assertTrue($rocket['requirementsMet']);
        $this->assertSame(20, $rocket['requirements'][0]['code']);
        $this->assertSame(720, $rocket['durationSeconds']);
        $ship = collect($production->catalog($planet->fresh(), $user, 'ships')['units'])->firstWhere('code', 204);
        $this->assertFalse($ship['requirementsMet']);

        $planet->forceFill(['defence_center_level' => 3])->save();
        $fasterRocket = collect($production->defenceCatalog($planet->fresh(), $user)['units'])->firstWhere('code', 401);
        $this->assertSame(360, $fasterRocket['durationSeconds']);
        $this->assertSame($ship['durationSeconds'], collect($production->catalog($planet->fresh(), $user, 'ships')['units'])->firstWhere('code', 204)['durationSeconds']);

        $this->actingAs($user)->post(route('planet.defences.store', $planet), ['code' => 401, 'quantity' => 1])
            ->assertRedirect(route('defences.index', ['planet' => $planet->id]));
        $this->assertDatabaseHas('planet_unit_queues', ['planet_id' => $planet->id, 'unit_code' => 401, 'unit_type' => 'defences']);
    }

    public function test_all_defences_require_the_center_without_changing_other_requirements(): void
    {
        $planet = $this->createPlanet(['defence_center_level' => 8, 'robot_factory_level' => 1, 'missile_silo_level' => 4]);
        $units = app(UnitProductionService::class)->defenceCatalog($planet, $planet->user)['units'];

        foreach ($units as $unit) {
            $requirements = collect($unit['requirements']);
            $this->assertTrue($requirements->contains('code', 20));
            $this->assertFalse($requirements->contains('code', 21));
        }
        $this->assertSame(8, collect($units)->firstWhere('code', 406)['requirements'][0]['level']);
        $this->assertTrue(collect(collect($units)->firstWhere('code', 405)['requirements'])->contains('code', 121));
        $this->assertTrue(collect(collect($units)->firstWhere('code', 503)['requirements'])->contains('code', 44));
    }

    public function test_production_rejects_missing_prerequisites_and_insufficient_resources(): void
    {
        $planet = $this->createPlanet(['hangar_level' => 0, 'robot_factory_level' => 0, 'metal' => 100, 'crystal' => 100]);
        $planet->user->forceFill(['combustion_technology_level' => 20])->save();

        $this->actingAs($planet->user)->from(route('shipyard.index'))
            ->post(route('planet.ships.store', $planet), ['code' => 204, 'quantity' => 1])
            ->assertSessionHasErrors('unit');
        $this->assertDatabaseCount('planet_unit_queues', 0);
        $this->assertSame(100, $planet->fresh()->metal);

        $planet->forceFill(['hangar_level' => 1, 'robot_factory_level' => 1]);
        $planet->save();

        $this->actingAs($planet->user)->from(route('shipyard.index'))
            ->post(route('planet.ships.store', $planet), ['code' => 204, 'quantity' => 1])
            ->assertSessionHasErrors('resources');
        $this->assertDatabaseCount('planet_unit_queues', 0);
    }

    public function test_commander_can_recruit_an_officer_and_officer_points_are_spent(): void
    {
        $planet = $this->createPlanet(['hangar_level' => 1, 'robot_factory_level' => 1]);
        $planet->user->forceFill(['officer_points' => 1])->save();

        $this->actingAs($planet->user)->post(route('officers.store'), ['code' => 601])
            ->assertRedirect(route('officers.index'));

        $this->assertSame(0, (int) $planet->user->fresh()->officer_points);
        $this->assertDatabaseHas('planet_unit_queues', [
            'planet_id' => $planet->id, 'unit_code' => 601, 'unit_type' => 'officers', 'quantity' => 1,
        ]);
    }

    public function test_officer_recruitment_rejects_insufficient_points_and_locked_officers(): void
    {
        $planet = $this->createPlanet(['hangar_level' => 1, 'robot_factory_level' => 1]);

        $this->actingAs($planet->user)->from(route('officers.index'))
            ->post(route('officers.store'), ['code' => 601])->assertSessionHasErrors('officer_points');
        $this->assertDatabaseCount('planet_unit_queues', 0);

        $planet->user->forceFill(['officer_points' => 1])->save();
        $this->actingAs($planet->user)->from(route('officers.index'))
            ->post(route('officers.store'), ['code' => 603])->assertSessionHasErrors('officer');
        $this->assertSame(1, (int) $planet->user->fresh()->officer_points);
    }

    public function test_commander_can_view_the_officer_catalog_and_current_points(): void
    {
        $planet = $this->createPlanet(['hangar_level' => 1, 'robot_factory_level' => 1]);
        $planet->user->forceFill(['officer_points' => 7])->save();

        $this->actingAs($planet->user)->get(route('officers.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Officers/Index')
                ->where('officersEnabled', true)->where('points', 7)->has('officers', 15)->where('officers.0.code', 601)
                ->where('officers.0.rarity', 'remarkable')->where('officers.14.rarity', 'signature'));
    }

    public function test_officer_page_reflects_the_saved_module_setting(): void
    {
        config()->set('modules.officers.enabled', true);
        $planet = $this->createPlanet();
        DB::table('game_module_settings')->insert(['key' => 'module.officers', 'enabled' => false]);

        $this->actingAs($planet->user)->get(route('officers.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Officers/Index')
            ->where('officersEnabled', false)->has('officers', 0));

        DB::table('game_module_settings')->where('key', 'module.officers')->update(['enabled' => true]);
        $this->get(route('officers.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Officers/Index')
            ->where('officersEnabled', true)->has('officers', 15));
    }

    public function test_disabled_officer_is_not_recruitable_or_required_by_another_officer(): void
    {
        config()->set('modules.officers.disabled', ['601']);
        $planet = $this->createPlanet();
        $planet->user->forceFill(['officer_points' => 2])->save();
        $production = app(UnitProductionService::class);

        $officers = collect($production->officerCatalog($planet->user)['officers']);
        $this->assertNull($officers->firstWhere('code', 601));
        $this->assertTrue($officers->firstWhere('code', 603)['requirementsMet']);
        $this->actingAs($planet->user)->from(route('officers.index'))
            ->post(route('officers.store'), ['code' => 601])->assertSessionHasErrors('officer');
        $this->assertSame(2, (int) $planet->user->fresh()->officer_points);
        $this->post(route('officers.store'), ['code' => 603])->assertSessionHasNoErrors();
        DB::table('planet_unit_queues')->where('planet_id', $planet->id)->where('unit_code', 603)
            ->update(['completes_at' => now()->subSecond()]);
        $production->complete($planet);
        $this->assertSame(1, (int) $planet->user->fresh()->officer_engineer_level);
    }

    public function test_disabled_officer_prerequisite_is_ignored_for_unit_catalog_and_build(): void
    {
        config()->set('modules.officers.disabled', ['609']);
        $planet = $this->createPlanet([
            'defence_center_level' => 1, 'robot_factory_level' => 1,
            'metal' => 1000000, 'crystal' => 500000, 'deuterium' => 100000,
        ]);
        $unit = collect(app(UnitProductionService::class)->defenceCatalog($planet, $planet->user)['units'])->firstWhere('code', 409);

        $this->assertTrue($unit['requirementsMet']);
        $this->assertNotContains(609, array_column($unit['requirements'], 'code'));
        $this->actingAs($planet->user)->post(route('planet.defences.store', $planet), ['code' => 409, 'quantity' => 1])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('planet_unit_queues', ['planet_id' => $planet->id, 'unit_code' => 409]);
    }

    public function test_disabled_officer_module_removes_officer_prerequisites(): void
    {
        config()->set('modules.officers.enabled', false);
        $planet = $this->createPlanet(['defence_center_level' => 1, 'robot_factory_level' => 1]);
        $production = app(UnitProductionService::class);

        $this->assertSame([], $production->officerCatalog($planet->user)['officers']);
        $unit = collect($production->defenceCatalog($planet, $planet->user)['units'])->firstWhere('code', 409);
        $this->assertTrue($unit['requirementsMet']);
        $this->actingAs($planet->user)->from(route('officers.index'))
            ->post(route('officers.store'), ['code' => 601])->assertSessionHasErrors('officer');
    }

    public function test_disabled_officer_production_bonuses_are_suspended_without_losing_levels(): void
    {
        $planet = $this->createPlanet(['hangar_level' => 1, 'defence_center_level' => 1, 'robot_factory_level' => 1]);
        $user = $planet->user;
        $user->forceFill(['officer_technocrat_level' => 2, 'officer_defender_level' => 1])->save();
        $production = app(UnitProductionService::class);
        $ship = fn () => collect($production->catalog($planet, $user, 'ships')['units'])->firstWhere('code', 202)['durationSeconds'];
        $defence = fn () => collect($production->defenceCatalog($planet, $user)['units'])->firstWhere('code', 401)['durationSeconds'];
        $enabledShipDuration = $ship();
        $enabledDefenceDuration = $defence();

        config()->set('modules.officers.disabled', ['604', '608']);
        $this->assertGreaterThan($enabledShipDuration, $ship());
        $this->assertGreaterThan($enabledDefenceDuration, $defence());
        $this->assertSame(2, (int) $user->fresh()->officer_technocrat_level);
        $this->assertSame(1, (int) $user->fresh()->officer_defender_level);
        config()->set('modules.officers.disabled', []);
        $this->assertSame($enabledShipDuration, $ship());
        $this->assertSame($enabledDefenceDuration, $defence());
    }

    public function test_disabling_a_queued_officer_refunds_the_point_on_completion(): void
    {
        $planet = $this->createPlanet();
        $planet->user->forceFill(['officer_points' => 1])->save();
        $production = app(UnitProductionService::class);
        $production->recruit($planet->user, $planet, 601);
        config()->set('modules.officers.disabled', ['601']);
        DB::table('planet_unit_queues')->where('planet_id', $planet->id)->where('unit_code', 601)
            ->update(['completes_at' => now()->subSecond()]);
        $production->complete($planet);

        $this->assertSame(1, (int) $planet->user->fresh()->officer_points);
        $this->assertSame(0, (int) $planet->user->fresh()->officer_geologist_level);
        $this->assertDatabaseMissing('planet_unit_queues', ['planet_id' => $planet->id, 'unit_code' => 601]);
    }

    public function test_spy_and_commander_bonuses_are_suspended_and_restored_independently(): void
    {
        $planet = $this->createPlanet();
        $user = $planet->user;
        $user->forceFill([
            'spy_technology_level' => 2, 'computer_technology_level' => 4,
            'officer_points' => 2, 'officer_admiral_level' => 10, 'officer_technocrat_level' => 5,
        ])->save();
        $production = app(UnitProductionService::class);

        foreach ([610, 611] as $code) {
            $production->recruit($user->fresh(), $planet, $code);
            DB::table('planet_unit_queues')->where('planet_id', $planet->id)->where('unit_code', $code)
                ->update(['completes_at' => now()->subSecond()]);
            $production->complete($planet);
        }

        $user = $user->fresh();
        $this->assertSame(7, $user->spy_technology_level);
        $this->assertSame(7, $user->computer_technology_level);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'officer_spy_research_bonus' => 5, 'officer_commander_research_bonus' => 3]);

        config()->set('modules.officers.disabled', ['610']);
        $this->assertSame(2, $user->spy_technology_level);
        $this->assertSame(7, $user->computer_technology_level);
        config()->set('modules.officers.disabled', ['611']);
        $this->assertSame(7, $user->spy_technology_level);
        $this->assertSame(4, $user->computer_technology_level);
        config()->set('modules.officers.enabled', false);
        $this->assertSame(2, $user->spy_technology_level);
        $this->assertSame(4, $user->computer_technology_level);
        config()->set('modules.officers.enabled', true);
        config()->set('modules.officers.disabled', []);
        $this->assertSame(7, $user->spy_technology_level);
        $this->assertSame(7, $user->computer_technology_level);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'spy_technology_level' => 7, 'computer_technology_level' => 7]);
    }

    public function test_recruiting_spy_preserves_a_research_already_in_progress(): void
    {
        $planet = $this->createPlanet();
        $user = $planet->user;
        $user->forceFill(['spy_technology_level' => 2, 'officer_points' => 1, 'officer_admiral_level' => 10, 'officer_technocrat_level' => 5])->save();
        $research = ResearchQueue::create([
            'user_id' => $user->id, 'universe_id' => $planet->universe_id, 'planet_id' => $planet->id,
            'technology_key' => 'spy', 'technology_code' => 106, 'target_level' => 3,
            'cost_metal' => 0, 'cost_crystal' => 0, 'cost_deuterium' => 0, 'completes_at' => now()->addHour(),
        ]);
        $production = app(UnitProductionService::class);
        $production->recruit($user, $planet, 610);
        DB::table('planet_unit_queues')->where('planet_id', $planet->id)->where('unit_code', 610)->update(['completes_at' => now()->subSecond()]);
        $production->complete($planet);
        $this->assertSame(8, (int) $research->fresh()->target_level);
        $research->update(['completes_at' => now()->subSecond()]);
        app(ResearchService::class)->complete($user);
        $this->assertSame(8, $user->fresh()->spy_technology_level);
    }

    public function test_bonus_migration_preserves_research_and_excludes_audited_admin_officer_levels(): void
    {
        $planet = $this->createPlanet();
        $user = $planet->user;
        $user->forceFill(['spy_technology_level' => 12, 'computer_technology_level' => 10, 'officer_spy_level' => 2, 'officer_commander_level' => 1])->save();
        DB::table('admin_audit_logs')->insert([
            'actor_id' => $user->id, 'actor_level' => 4, 'target_user_id' => $user->id, 'planet_id' => $planet->id,
            'action' => 'element_added', 'details' => json_encode(['category' => 'officers', 'key' => '610', 'amount' => 1]),
        ]);
        $migration = require database_path('migrations/2026_11_26_000002_track_officer_research_bonuses.php');
        $migration->down();
        $migration->up();

        $this->assertDatabaseHas('users', [
            'id' => $user->id, 'spy_technology_level' => 12, 'computer_technology_level' => 10,
            'officer_spy_research_bonus' => 5, 'officer_commander_research_bonus' => 3,
        ]);
        config()->set('modules.officers.enabled', false);
        $this->assertSame(7, $user->fresh()->spy_technology_level);
        $this->assertSame(7, $user->fresh()->computer_technology_level);
    }

    private function createPlanet(array $attributes = []): Planet
    {
        $universe = Universe::create(['name' => 'AndromÃƒÂ¨de', 'slug' => fake()->unique()->slug()]);
        $user = User::factory()->create([
            'universe_id' => $universe->id,
            'spy_technology_level' => 20,
            'computer_technology_level' => 20,
            'military_technology_level' => 20,
            'defence_technology_level' => 20,
            'shield_technology_level' => 20,
            'energy_technology_level' => 20,
            'hyperspace_technology_level' => 20,
            'combustion_technology_level' => 20,
            'impulse_technology_level' => 20,
            'hyperspace_drive_level' => 20,
            'laser_technology_level' => 20,
            'ion_technology_level' => 20,
            'plasma_technology_level' => 20,
            'intergalactic_research_level' => 20,
            'expedition_technology_level' => 20,
            'graviton_technology_level' => 20,
        ]);
        $planet = new Planet(['name' => 'Nova Prime']);
        $planet->universe()->associate($universe);
        $planet->user()->associate($user);
        $planet->galaxy = 1;
        $planet->system = 1;
        $planet->position = fake()->unique()->numberBetween(1, 15);
        $planet->metal = 500;
        $planet->crystal = 500;
        $planet->deuterium = 500;
        $planet->fields = 163;
        $planet->resources_updated_at = now();
        $planet->forceFill($attributes);
        $planet->save();

        return $planet;
    }

    private function createAdditionalPlanet(User $user): Planet
    {
        $planet = new Planet(['name' => 'Nova Secunda']);
        $planet->universe()->associate($user->universe);
        $planet->user()->associate($user);
        $planet->galaxy = 1;
        $planet->system = 1;
        $planet->position = fake()->unique()->numberBetween(1, 15);
        $planet->fields = 163;
        $planet->resources_updated_at = now();
        $planet->save();

        return $planet;
    }
}
