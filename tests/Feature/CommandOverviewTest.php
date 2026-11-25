<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Tests the application's CommandOverviewTest behavior.
 */

namespace Tests\Feature;

use App\Models\Fleet;
use App\Models\Planet;
use App\Models\PlanetBuildingQueue;
use App\Models\ResearchQueue;
use App\Models\Universe;
use App\Models\User;
use App\Services\GalaxyMap;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommandOverviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_overview_selects_owned_planets_and_shows_their_state(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $user = User::factory()->create(['universe_id' => $universe->id]);
        $first = $this->planet($user, 1);
        $second = $this->planet($user, 2);
        $second->forceFill(['metal_mine_level' => 3, 'solar_plant_level' => 2, 'max_temperature' => 33])->save();
        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('activePlanetId', $first->id));
        $this->get(route('dashboard', ['planet' => $second->id]))->assertInertia(fn (Assert $page) => $page
            ->where('activePlanetId', $second->id)
            ->where('planets.1.usedFields', 5)
            ->where('planets.1.max_temperature', 33)
            ->where('planets.1.moons', []));
        $other = User::factory()->create(['universe_id' => $universe->id]);
        $foreign = $this->planet($other, 3);
        $this->get(route('dashboard', ['planet' => $foreign->id]))->assertNotFound();
    }

    public function test_overview_lists_the_owned_planets_natural_moons(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $user = User::factory()->create(['universe_id' => $universe->id]);
        $map = app(GalaxyMap::class);
        $position = null;
        $moons = [];

        foreach (range(2, 15) as $candidatePosition) {
            $moons = $map->system($universe, $user, 1, 1, $candidatePosition)['slots'][$candidatePosition - 2]['naturalMoons'] ?? [];

            if ($moons !== []) {
                $position = $candidatePosition;
                break;
            }
        }

        $this->assertNotNull($position);
        $planet = $this->planet($user, (int) $position);

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('planets.0.id', $planet->id)
            ->has('planets.0.moons', count($moons))
            ->where('planets.0.moons.0.orbit', $moons[0]['orbit'])
            ->where('planets.0.moons.0.diameter', $moons[0]['diameter']));
    }

    public function test_overview_lists_moons_in_a_separate_collection_with_each_moons_own_resources(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $user = User::factory()->create(['universe_id' => $universe->id, 'spy_technology_level' => 13]);
        $moons = [];
        $position = null;

        for ($candidate = 2; $candidate <= 15; $candidate++) {
            $moons = app(GalaxyMap::class)->system($universe, $user, 1, 2, $candidate)['slots'][$candidate - 2]['naturalMoons'] ?? [];
            if (count($moons) >= 2) {
                $position = $candidate;
                break;
            }
        }

        $this->assertNotNull($position);
        $planet = new Planet(['name' => 'Lunar home']);
        $planet->forceFill(['universe_id' => $universe->id, 'user_id' => $user->id, 'galaxy' => 1, 'system' => 2,
            'position' => $position, 'metal' => 9000, 'crystal' => 8000, 'deuterium' => 7000, 'resources_updated_at' => now()])->save();
        DB::table('moon_stocks')->insert([
            ['planet_id' => $planet->id, 'orbit' => $moons[0]['orbit'], 'name' => 'Asteria', 'metal' => 100, 'crystal' => 200, 'deuterium' => 300, 'created_at' => now(), 'updated_at' => now()],
            ['planet_id' => $planet->id, 'orbit' => $moons[1]['orbit'], 'name' => null, 'metal' => 400, 'crystal' => 500, 'deuterium' => 600, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('planets.0.metal', 9000)
            ->where('planets.0.moons.0.name', 'Asteria')
            ->where('planets.0.moons.1.name', $moons[1]['name'])
            ->where('planets.0.crystal', 8000)
            ->where('planets.0.deuterium', 7000)
            ->where('planets.0.moons.0.metal', 100)
            ->where('planets.0.moons.0.crystal', 200)
            ->where('planets.0.moons.1.metal', 400)
            ->where('planets.0.moons.1.deuterium', 600));
    }

    public function test_overview_shows_installed_and_completed_shields_for_each_planet(): void
    {
        $this->freezeSecond();
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $user = User::factory()->create(['universe_id' => $universe->id]);
        $first = $this->planet($user, 1);
        $second = $this->planet($user, 2);
        $first->forceFill(['unit_407' => 1])->save();
        $second->forceFill(['unit_409' => 1])->save();
        foreach ([408 => now()->subSecond(), 409 => now()->addHour()] as $code => $completion) {
            $first->unitQueue()->create([
                'universe_id' => $universe->id, 'unit_code' => $code, 'unit_type' => 'defences',
                'quantity' => 1, 'quantity_completed' => 0, 'position' => $code - 407,
                'cost_metal' => 0, 'cost_crystal' => 0, 'cost_deuterium' => 0,
                'starts_at' => now()->subHour(), 'completes_at' => $completion,
            ]);
        }

        $this->actingAs($user)->get(route('dashboard', ['planet' => $second->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('activePlanetId', $second->id)
                ->where('planets.0.shields.small', true)
                ->where('planets.0.shields.large', true)
                ->where('planets.0.shields.planetary', false)
                ->where('planets.1.shields.small', false)
                ->where('planets.1.shields.large', false)
                ->where('planets.1.shields.planetary', true));

        $this->assertSame(1, (int) $first->fresh()->unit_408);
        $this->assertDatabaseHas('planet_unit_queues', ['planet_id' => $first->id, 'unit_code' => 409]);
    }

    public function test_overview_shows_own_and_incoming_fleets_but_not_unrelated_missions(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $user = User::factory()->create(['universe_id' => $universe->id]);
        $other = User::factory()->create(['universe_id' => $universe->id]);
        $third = User::factory()->create(['universe_id' => $universe->id]);
        $planet = $this->planet($user, 1);
        $otherPlanet = $this->planet($other, 2);
        $thirdPlanet = $this->planet($third, 3);
        Fleet::create(['universe_id' => $universe->id, 'user_id' => $user->id, 'origin_planet_id' => $planet->id,
            'target_planet_id' => $otherPlanet->id, 'mission' => 'transport', 'ships' => [], 'status' => 'outbound', 'arrives_at' => now()->addMinutes(30)]);
        Fleet::create(['universe_id' => $universe->id, 'user_id' => $other->id, 'origin_planet_id' => $otherPlanet->id,
            'target_planet_id' => $planet->id, 'mission' => 'attack', 'ships' => [], 'status' => 'outbound', 'arrives_at' => now()->addHour()]);
        Fleet::create(['universe_id' => $universe->id, 'user_id' => $other->id, 'origin_planet_id' => $otherPlanet->id,
            'target_planet_id' => $thirdPlanet->id, 'mission' => 'attack', 'ships' => [], 'status' => 'outbound', 'arrives_at' => now()->addMinutes(45)]);

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->has('activeFleets', 2)->where('activeFleets.0.mission', 'transport')
            ->where('activeFleets.0.incoming', false)
            ->where('activeFleets.0.origin', $planet->coordinates)
            ->where('activeFleets.1.incoming', true)
            ->where('activeFleets.1.target', $planet->coordinates));
    }

    public function test_overview_exposes_the_active_construction_and_research(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $user = User::factory()->create(['universe_id' => $universe->id]);
        $planet = $this->planet($user, 1);
        PlanetBuildingQueue::create(['universe_id' => $universe->id, 'planet_id' => $planet->id,
            'building_key' => 'metal', 'building_code' => 1, 'target_level' => 1, 'position' => 1,
            'cost_metal' => 60, 'cost_crystal' => 15, 'cost_deuterium' => 0,
            'starts_at' => now(), 'completes_at' => now()->addHour()]);
        ResearchQueue::create(['universe_id' => $universe->id, 'user_id' => $user->id, 'planet_id' => $planet->id,
            'technology_key' => 'energy', 'technology_code' => 113, 'target_level' => 1,
            'cost_metal' => 0, 'cost_crystal' => 800, 'cost_deuterium' => 400, 'completes_at' => now()->addHour()]);
        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('planets.0.queue.0.building_key', 'metal')
            ->has('planets.0.queue.0.completes_at')
            ->where('researchQueue.technology_key', 'energy')
            ->has('researchQueue.completes_at'));
    }

    public function test_owner_can_rename_a_planet_but_not_others_or_use_invalid_names(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $user = User::factory()->create(['universe_id' => $universe->id]);
        $other = User::factory()->create(['universe_id' => $universe->id]);
        $planet = $this->planet($user, 1);
        $foreign = $this->planet($other, 2);
        $this->patch(route('planet.rename', $planet), ['name' => 'New World'])->assertRedirect(route('login'));
        $this->actingAs($user)->patch(route('planet.rename', $foreign), ['name' => 'New World'])->assertNotFound();
        $this->patch(route('planet.rename', $planet), ['name' => '<script>'])->assertSessionHasErrors('name');
        $this->assertSame('World 1', $planet->fresh()->name);
        $this->patch(route('planet.rename', $planet), ['name' => 'New World'])
            ->assertRedirect(route('dashboard', ['planet' => $planet->id]))->assertSessionHasNoErrors();
        $this->assertSame('New World', $planet->fresh()->name);
        $this->assertSame('World 2', $foreign->fresh()->name);
    }

    private function planet(User $user, int $position): Planet
    {
        $planet = new Planet(['name' => "World {$position}"]);
        $planet->forceFill(['universe_id' => $user->universe_id, 'user_id' => $user->id,
            'galaxy' => 1, 'system' => 1, 'position' => $position, 'resources_updated_at' => now()])->save();

        return $planet;
    }
}
