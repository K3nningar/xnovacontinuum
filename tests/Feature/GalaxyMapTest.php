<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Tests the application's GalaxyMapTest behavior.
 */

namespace Tests\Feature;

use App\Models\Planet;
use App\Models\ResearchQueue;
use App\Models\Universe;
use App\Models\User;
use App\Services\GalaxyMap;
use App\Services\PlanetEconomy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class GalaxyMapTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_map_hides_distant_players_on_the_server_but_shows_own_planets(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $user = User::factory()->create(['universe_id' => $universe->id]);
        $enemy = User::factory()->create(['universe_id' => $universe->id, 'name' => 'Secret commander']);
        $own = $this->planet($user, 8, 'Home');
        $this->planet($enemy, 15, 'Secret planet');

        $response = $this->actingAs($user)->get(route('galaxy.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Galaxy/Index')
            ->where('galaxy', 1)->where('system', 1)->where('spyLevel', 0)
            ->where('planets.0.coordinates', '1:1:8')
            ->has('slots', 14)
            ->where('slots.0.visible', false)
            ->where('slots.0.requiredSpyLevel', 6)
            ->where('slots.6.planet.name', 'Home')
            ->where('slots.6.planet.owned', true)
            ->where('slots.13.visible', false)
            ->where('slots.13.requiredSpyLevel', 7)
            ->missing('slots.13.planet')
            ->missing('slots.13.kind'));
        $this->assertStringNotContainsString('Secret commander', json_encode($response->inertiaProps(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Secret planet', json_encode($response->inertiaProps(), JSON_THROW_ON_ERROR));

        $user->forceFill(['spy_technology_level' => 7])->save();
        $this->get(route('galaxy.index', ['planet' => $own->id]))->assertInertia(fn (Assert $page) => $page
            ->where('slots.13.planet.name', 'Secret planet')
            ->where('slots.13.planet.owner', 'Secret commander')
            ->where('slots.13.planet.owned', false));
    }

    #[TestWith([8, 0, [8]])]
    #[TestWith([8, 1, [7, 8, 9]])]
    #[TestWith([8, 2, [6, 7, 8, 9, 10]])]
    #[TestWith([2, 2, [2, 3, 4]])]
    #[TestWith([15, 2, [13, 14, 15]])]
    public function test_spy_range_is_centered_on_the_selected_planet(int $origin, int $level, array $visiblePositions): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $user = User::factory()->create(['universe_id' => $universe->id, 'spy_technology_level' => $level]);
        $planet = $this->planet($user, $origin, 'Home');

        $response = $this->actingAs($user)->get(route('galaxy.index', ['planet' => $planet->id, 'system' => 2]));

        $response->assertOk();
        $slots = $response->inertiaProps('slots');
        $this->assertSame($visiblePositions, array_column(array_filter($slots, fn (array $slot): bool => $slot['visible']), 'position'));
        foreach (array_filter($slots, fn (array $slot): bool => ! $slot['visible']) as $slot) {
            $this->assertSame(['position', 'visible', 'requiredSpyLevel'], array_keys($slot));
        }
    }

    public function test_other_owned_planets_remain_visible_outside_selected_planet_spy_range(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $user = User::factory()->create(['universe_id' => $universe->id]);
        $origin = $this->planet($user, 8, 'Home');
        $colony = $this->planet($user, 14, 'Colony');

        $this->actingAs($user)->get(route('galaxy.index', ['planet' => $origin->id]))->assertInertia(fn (Assert $page) => $page
            ->where('slots.12.planet.name', 'Colony')->where('slots.12.planet.owned', true)
            ->where('slots.11.visible', false)->where('slots.11.requiredSpyLevel', 5));
        $this->get(route('galaxy.index', ['planet' => $colony->id]))->assertInertia(fn (Assert $page) => $page
            ->where('slots.6.planet.name', 'Home')->where('slots.11.visible', false)
            ->where('slots.11.requiredSpyLevel', 1));
    }

    public function test_system_generation_is_stable_and_contains_diverse_bodies_and_moons(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $user = User::factory()->create(['universe_id' => $universe->id]);
        $user->forceFill(['spy_technology_level' => 13])->save();
        $map = app(GalaxyMap::class);
        $first = $map->system($universe, $user, 1, 1, 8);
        $types = [];
        $stars = [];
        $hasMultipleMoons = false;
        $hasAsteroids = false;
        for ($system = 1; $system <= 50; $system++) {
            $generated = $map->system($universe, $user, 1, $system, 8);
            $stars[] = $generated['star'];
            foreach ($generated['slots'] as $slot) {
                if ($slot['kind'] === 'planet') {
                    $types[] = $slot['type'];
                    if (! $hasMultipleMoons && $slot['moons'] > 1) {
                        $this->assertCount($slot['moons'], $slot['naturalMoons']);
                        $this->assertSame(1, $slot['naturalMoons'][0]['orbit']);
                        $this->assertSame(2, $slot['naturalMoons'][1]['orbit']);
                        $hasMultipleMoons = true;
                    }
                } else {
                    $hasAsteroids = true;
                }
            }
        }

        $this->assertSame($first, $map->system($universe, $user, 1, 1, 8));
        $this->assertCount(7, array_unique($types));
        $this->assertGreaterThan(1, count(array_unique($stars)));
        $this->assertTrue($hasMultipleMoons);
        $this->assertTrue($hasAsteroids);
    }

    public function test_completed_spy_research_reveals_the_next_position(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $user = User::factory()->create(['universe_id' => $universe->id]);
        $planet = $this->planet($user, 8, 'Home');
        $planet->forceFill(['laboratory_level' => 3, 'metal' => 1000, 'crystal' => 1000, 'deuterium' => 1000])->save();
        $this->actingAs($user)->post(route('planet.researches.store', $planet), ['technology' => 'spy'])->assertRedirect();
        $queue = ResearchQueue::where('user_id', $user->id)->firstOrFail();
        $this->travelTo($queue->completes_at->addSecond());

        $this->get(route('galaxy.index'))->assertInertia(fn (Assert $page) => $page
            ->where('spyLevel', 1)
            ->where('slots.4.visible', false)->where('slots.5.visible', true)
            ->where('slots.7.visible', true)->where('slots.8.visible', false));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'spy_technology_level' => 1]);
        $this->assertDatabaseMissing('research_queues', ['id' => $queue->id]);
    }

    public function test_old_sensor_level_does_not_increase_galaxy_visibility(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $user = User::factory()->create(['universe_id' => $universe->id]);
        $user->forceFill(['sensor_technology_level' => 13, 'spy_technology_level' => 0])->save();
        $this->planet($user, 8, 'Home');

        $this->actingAs($user)->get(route('galaxy.index'))->assertInertia(fn (Assert $page) => $page
            ->where('spyLevel', 0)->where('slots.5.visible', false)->where('slots.7.visible', false));
    }

    public function test_map_rejects_foreign_planets_and_out_of_range_coordinates(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test', 'galaxies' => 12, 'systems_per_galaxy' => 600]);
        $user = User::factory()->create(['universe_id' => $universe->id]);
        $enemy = User::factory()->create(['universe_id' => $universe->id]);
        $this->planet($user, 8, 'Home');
        $foreign = $this->planet($enemy, 9, 'Foreign');

        $this->get(route('galaxy.index'))->assertRedirect(route('login'));
        $this->actingAs($user)->get(route('galaxy.index', ['planet' => $foreign->id]))->assertNotFound();
        $this->from(route('galaxy.index'))->get(route('galaxy.index', ['galaxy' => 10]))->assertSessionHasErrors('galaxy');
        $this->from(route('galaxy.index'))->get(route('galaxy.index', ['system' => 500]))->assertSessionHasErrors('system');
        $this->get(route('galaxy.index', ['galaxy' => 9, 'system' => 499]))->assertOk();
    }

    public function test_reserved_positions_with_existing_planets_are_not_silently_overwritten(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $user = User::factory()->create(['universe_id' => $universe->id]);
        $this->planet($user, 8, 'Home');
        $legacy = $this->planet($user, 1, 'Legacy');

        $this->actingAs($user)->get(route('galaxy.index'))->assertStatus(409);
        $legacy->forceFill(['position' => 16])->save();
        $this->get(route('galaxy.index'))->assertStatus(409);
        $this->assertDatabaseHas('planets', ['id' => $legacy->id, 'name' => 'Legacy', 'position' => 16]);
    }

    public function test_concentrations_affect_new_planet_mines_without_changing_existing_planets(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $legacyUser = User::factory()->create(['universe_id' => $universe->id]);
        $legacy = $this->planet($legacyUser, 8, 'Legacy');
        $this->assertSame(100, (int) $legacy->fresh()->metal_concentration);
        $newUser = User::factory()->create();
        $this->actingAs($newUser)->post(route('universe.join'), ['universe_id' => $universe->id, 'name' => 'New home'])->assertRedirect();
        $planet = $newUser->fresh()->planets()->firstOrFail();
        $concentrations = app(GalaxyMap::class)->planetConcentrations($universe, $planet->galaxy, $planet->system, $planet->position);
        $this->assertSame($concentrations['metal'], (int) $planet->metal_concentration);
        $this->assertSame($concentrations['crystal'], (int) $planet->crystal_concentration);
        $this->assertSame($concentrations['deuterium'], (int) $planet->deuterium_concentration);
        $this->actingAs($newUser->fresh())->get(route('galaxy.index'))->assertInertia(fn (Assert $page) => $page
            ->where('slots.6.resources', $concentrations));

        $planet->forceFill(['metal_mine_level' => 10, 'crystal_mine_level' => 10, 'deuterium_synthesizer_level' => 10])->save();
        $economy = app(PlanetEconomy::class);
        $reference = $planet->replicate();
        $reference->forceFill(['metal_concentration' => 100, 'crystal_concentration' => 100, 'deuterium_concentration' => 100]);
        foreach (['metal', 'crystal', 'deuterium'] as $resource) {
            $this->assertEqualsWithDelta(
                $economy->buildingProduction($reference, $resource) * $concentrations[$resource] / 100,
                $economy->buildingProduction($planet, $resource),
                1.5,
            );
        }
    }

    private function planet(User $user, int $position, string $name): Planet
    {
        $planet = new Planet(['name' => $name]);
        $planet->forceFill([
            'universe_id' => $user->universe_id, 'user_id' => $user->id,
            'galaxy' => 1, 'system' => 1, 'position' => $position, 'resources_updated_at' => now(),
        ])->save();

        return $planet;
    }
}
