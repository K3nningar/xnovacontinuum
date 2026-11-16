<?php

namespace Tests\Feature;

use App\Models\Planet;
use App\Models\Universe;
use App\Models\User;
use App\Services\UnitProductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UnitProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipyard_and_defence_pages_render_their_catalogs_for_owned_planets(): void
    {
        $planet = $this->createPlanet(['hangar_level' => 2, 'robot_factory_level' => 2, 'metal' => 10000, 'crystal' => 10000]);
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
            'hangar_level' => 1, 'robot_factory_level' => 1,
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
                ->where('points', 7)->has('officers', 15)->where('officers.0.code', 601)
                ->where('officers.0.rarity', 'remarkable')->where('officers.14.rarity', 'signature'));
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
