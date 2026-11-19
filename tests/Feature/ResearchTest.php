<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Tests the application's ResearchTest behavior.
 */

namespace Tests\Feature;

use App\Models\Planet;
use App\Models\ResearchQueue;
use App\Models\Universe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_research_catalog_requires_authentication_and_is_scoped_to_owned_planets(): void
    {
        $planet = $this->createPlanet(['laboratory_level' => 1, 'crystal' => 1000, 'deuterium' => 500]);
        $otherPlanet = $this->createPlanet(['laboratory_level' => 1]);

        $this->get(route('research.index'))->assertRedirect(route('login'));
        $this->actingAs($planet->user)->get(route('research.index', ['planet' => $otherPlanet->id]))->assertRedirect(route('dashboard'));
        $this->actingAs($planet->user)->get(route('research.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Research/Index')
                ->where('selectedPlanetId', $planet->id)
                ->where('planets.0.metal', 500)
                ->where('planets.0.crystal', 1000)
                ->where('planets.0.deuterium', 500)
                ->has('researches', 16)
                ->where('researches.5.key', 'energy')->where('researches.5.cost.crystal', 800)
                ->where('researches.0.rarity', 'remarkable')->where('researches.3.rarity', 'exceptional')
                ->where('researches.4.rarity', 'remarkable')->where('researches.15.rarity', 'mythic')
                ->where('researches.5.canResearch', true)->where('researches.0.canResearch', false)
                ->where('activeResearch', null));
    }

    public function test_technology_tree_has_its_own_page_and_uses_the_selected_owned_planet(): void
    {
        $planet = $this->createPlanet(['laboratory_level' => 1, 'crystal' => 1000, 'deuterium' => 500]);

        $otherPlanet = $this->createPlanet();

        $this->get(route('research.tree'))->assertRedirect(route('login'));
        $this->actingAs($planet->user)->get(route('research.tree', ['planet' => $otherPlanet->id]))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($planet->user)->get(route('research.tree', ['planet' => $planet->id]))
            ->assertInertia(fn (Assert $page) => $page->component('Research/TechnologyGraph')
                ->where('selectedPlanetId', $planet->id)
                ->where('planets.0.id', $planet->id)
                ->has('researches', 16)
                ->where('researches.5.key', 'energy')
                ->where('researches.5.requirements.0.key', 'laboratory_level')
                ->has('treeElements', 73)
                ->where('treeElements.0.id', 'building:1')
                ->where('treeElements.0.rarity', 'ordinary')
                ->where('treeElements.15.id', 'research:106')
                ->where('treeElements.15.rarity', 'remarkable')
                ->where('treeElements.31.id', 'ship:202')
                ->where('treeElements.31.rarity', 'ordinary')
                ->where('treeElements.47.id', 'defence:401')
                ->where('treeElements.47.rarity', 'ordinary')
                ->where('treeElements.58.id', 'officer:601')
                ->where('treeElements.58.rarity', 'remarkable')
                ->where('treeElements.4.requirements.0.code', 3)
                ->where('treeElements.6.requirements.0.code', 14)
                ->where('treeElements.31.kind', 'ship')
                ->where('treeElements.58.kind', 'officer')
                ->where('activeResearch', null));
    }

    public function test_obsolete_sensor_research_cannot_be_started_but_pending_research_can_be_cancelled(): void
    {
        $planet = $this->createPlanet(['laboratory_level' => 3, 'metal' => 1000]);
        $this->actingAs($planet->user)->from(route('research.index'))
            ->post(route('planet.researches.store', $planet), ['technology' => 'sensors'])->assertSessionHasErrors('technology');
        ResearchQueue::create(['universe_id' => $planet->universe_id, 'user_id' => $planet->user_id,
            'planet_id' => $planet->id, 'technology_key' => 'sensors', 'technology_code' => 107,
            'target_level' => 1, 'cost_metal' => 200, 'cost_crystal' => 400, 'cost_deuterium' => 200,
            'completes_at' => now()->addHour()]);

        $this->get(route('research.index'))->assertInertia(fn (Assert $page) => $page
            ->has('researches', 16)->where('activeResearch.technologyKey', 'sensors'));
        $this->delete(route('researches.destroy'))->assertRedirect(route('research.index'));
        $this->assertDatabaseCount('research_queues', 0);
        $this->assertSame(1200, $planet->fresh()->metal);
    }

    public function test_existing_sensor_research_can_complete_without_changing_spy_level(): void
    {
        $planet = $this->createPlanet();
        ResearchQueue::create(['universe_id' => $planet->universe_id, 'user_id' => $planet->user_id,
            'planet_id' => $planet->id, 'technology_key' => 'sensors', 'technology_code' => 107,
            'target_level' => 2, 'cost_metal' => 200, 'cost_crystal' => 400, 'cost_deuterium' => 200,
            'completes_at' => now()->subSecond()]);

        $this->actingAs($planet->user)->get(route('research.index'))->assertOk();
        $this->assertDatabaseHas('users', ['id' => $planet->user_id, 'sensor_technology_level' => 2, 'spy_technology_level' => 0]);
        $this->assertDatabaseCount('research_queues', 0);
    }

    public function test_commander_can_start_research_and_pay_legacy_costs(): void
    {
        $planet = $this->createPlanet(['laboratory_level' => 1, 'metal' => 1000, 'crystal' => 2000, 'deuterium' => 1000]);

        $this->actingAs($planet->user)->post(route('planet.researches.store', $planet), ['technology' => 'energy'])
            ->assertRedirect(route('research.index', ['planet' => $planet->id]));

        $this->assertDatabaseHas('research_queues', ['user_id' => $planet->user_id, 'planet_id' => $planet->id,
            'technology_key' => 'energy', 'technology_code' => 113, 'target_level' => 1, 'cost_crystal' => 800, 'cost_deuterium' => 400]);
        $this->assertSame(1000, $planet->fresh()->metal);
        $this->assertSame(1200, $planet->fresh()->crystal);
        $this->assertSame(600, $planet->fresh()->deuterium);
    }

    public function test_research_rejects_missing_prerequisites_and_insufficient_resources_without_charging(): void
    {
        $planet = $this->createPlanet(['metal' => 100, 'crystal' => 50, 'laboratory_level' => 0]);
        $this->actingAs($planet->user)->from(route('research.index'))
            ->post(route('planet.researches.store', $planet), ['technology' => 'energy'])->assertSessionHasErrors('technology');
        $this->assertDatabaseCount('research_queues', 0);
        $this->assertSame(100, $planet->fresh()->metal);

        $planet->laboratory_level = 1;
        $planet->save();
        $this->actingAs($planet->user)->from(route('research.index'))
            ->post(route('planet.researches.store', $planet), ['technology' => 'energy'])->assertSessionHasErrors('resources');
        $this->assertDatabaseCount('research_queues', 0);
        $this->assertSame(50, $planet->fresh()->crystal);
    }

    public function test_only_one_research_can_run_and_cancelling_refunds_resources(): void
    {
        $planet = $this->createPlanet(['laboratory_level' => 1, 'metal' => 1000, 'crystal' => 2000, 'deuterium' => 1000]);
        $this->actingAs($planet->user)->post(route('planet.researches.store', $planet), ['technology' => 'energy'])->assertRedirect();
        $this->actingAs($planet->user)->from(route('research.index'))
            ->post(route('planet.researches.store', $planet), ['technology' => 'computer'])->assertSessionHasErrors('technology');
        $this->assertDatabaseCount('research_queues', 1);
        $this->actingAs($planet->user)->delete(route('researches.destroy'))->assertRedirect(route('research.index'));
        $this->assertDatabaseCount('research_queues', 0);
        $this->assertSame(1000, $planet->fresh()->metal);
        $this->assertSame(2000, $planet->fresh()->crystal);
        $this->assertSame(1000, $planet->fresh()->deuterium);
    }

    public function test_cancelling_an_already_completed_research_completes_it_without_a_refund(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 12:00:00', 'UTC'));
        $planet = $this->createPlanet(['laboratory_level' => 1, 'crystal' => 100]);
        ResearchQueue::create(['universe_id' => $planet->universe_id, 'user_id' => $planet->user_id,
            'planet_id' => $planet->id, 'technology_key' => 'energy', 'technology_code' => 113,
            'target_level' => 1, 'cost_metal' => 0, 'cost_crystal' => 800, 'cost_deuterium' => 400,
            'completes_at' => now()->subSecond()]);

        $this->actingAs($planet->user)->delete(route('researches.destroy'))->assertSessionHasErrors('research');
        $this->assertDatabaseHas('users', ['id' => $planet->user_id, 'energy_technology_level' => 1]);
        $this->assertDatabaseCount('research_queues', 0);
        $this->assertSame(100, $planet->fresh()->crystal);
        $this->travelBack();
    }

    public function test_completed_research_updates_the_level_on_the_next_game_request(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 12:00:00', 'UTC'));
        $planet = $this->createPlanet(['laboratory_level' => 1]);
        ResearchQueue::create(['universe_id' => $planet->universe_id, 'user_id' => $planet->user_id,
            'planet_id' => $planet->id, 'technology_key' => 'energy', 'technology_code' => 113,
            'target_level' => 1, 'cost_metal' => 0, 'cost_crystal' => 800, 'cost_deuterium' => 400,
            'completes_at' => now()->subSecond()]);
        $this->actingAs($planet->user)->get(route('research.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Research/Index')
                ->where('researches.5.level', 1)->where('activeResearch', null));
        $this->assertDatabaseHas('users', ['id' => $planet->user_id, 'energy_technology_level' => 1]);
        $this->assertDatabaseCount('research_queues', 0);
        $this->travelBack();
    }

    public function test_laboratory_can_be_built_and_cannot_be_queued_twice(): void
    {
        $planet = $this->createPlanet(['metal' => 500, 'crystal' => 500, 'deuterium' => 500]);
        $this->actingAs($planet->user)->post(route('planet.buildings.upgrade', $planet), ['building' => 'laboratory'])
            ->assertRedirect(route('buildings.index', ['planet' => $planet->id]));
        $this->assertDatabaseHas('planet_building_queues', ['planet_id' => $planet->id, 'building_key' => 'laboratory',
            'building_code' => 31, 'cost_metal' => 200, 'cost_crystal' => 400, 'cost_deuterium' => 200]);
        $this->actingAs($planet->user)->from(route('buildings.index'))
            ->post(route('planet.buildings.upgrade', $planet), ['building' => 'laboratory'])->assertSessionHasErrors('building');
        $this->assertDatabaseCount('planet_building_queues', 1);
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
}
