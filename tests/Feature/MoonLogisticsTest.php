<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Tests the application's MoonLogisticsTest behavior.
 */

namespace Tests\Feature;

use App\Models\Fleet;
use App\Models\Planet;
use App\Models\Universe;
use App\Models\User;
use App\Services\ExplorationMissions;
use App\Services\GalaxyMap;
use App\Services\MoonEconomy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MoonLogisticsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_natural_moons_are_individually_accessible_only_to_the_planet_owner(): void
    {
        [$user, $planet] = $this->playerWithMoons();
        $other = User::factory()->create(['universe_id' => $user->universe_id]);
        $url = route('moon.show', ['planet' => $planet->id, 'orbit' => 1]);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($other)->get($url)->assertNotFound();
        $this->actingAs($user)->get($url)->assertInertia(fn (Assert $page) => $page
            ->component('Moon/Show')->where('moon.orbit', 1)->where('moon.deuterium', 0));
        $this->get(route('moon.show', ['planet' => $planet->id, 'orbit' => 2]))->assertOk();
        $this->get(route('moon.show', ['planet' => $planet->id, 'orbit' => 5]))->assertNotFound();
    }

    public function test_fleets_deliver_and_retrieve_resources_from_a_single_moon_without_duplication(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$user, $planet] = $this->playerWithMoons();
        $url = route('moon.transfer', ['planet' => $planet->id, 'orbit' => 1]);
        $show = route('moon.show', ['planet' => $planet->id, 'orbit' => 1]);
        $payload = ['direction' => 'deposit', 'resource' => 'deuterium', 'amount' => 1000, 'ships' => [202 => 1]];
        $this->actingAs($user)->post($url, $payload)->assertRedirect($show);
        $this->assertSame(8999, (int) $planet->fresh()->deuterium);
        $this->assertSame(1, (int) $planet->fresh()->unit_202);
        $fleet = Fleet::firstOrFail();
        $this->travelTo($fleet->arrives_at->addSecond());
        $this->get($show)->assertInertia(fn (Assert $page) => $page->where('moon.deuterium', 1000));
        $this->get(route('moon.show', ['planet' => $planet->id, 'orbit' => 2]))->assertInertia(fn (Assert $page) => $page->where('moon.deuterium', 0));
        $this->travelTo($fleet->returns_at->addSecond());
        $this->get($show)->assertOk();
        $this->assertSame(2, (int) $planet->fresh()->unit_202);

        $payload['direction'] = 'withdraw';
        $payload['amount'] = 400;
        $this->post($url, $payload)->assertRedirect($show);
        $this->assertDatabaseHas('moon_stocks', ['planet_id' => $planet->id, 'orbit' => 1, 'deuterium' => 600]);
        $this->assertSame(8998, (int) $planet->fresh()->deuterium);
        $return = Fleet::where('mission', 'moon_withdraw')->firstOrFail();
        $this->travelTo($return->returns_at->addSecond());
        $this->get($show)->assertOk();
        $this->assertSame(9398, (int) $planet->fresh()->deuterium);
        $this->assertSame(2, (int) $planet->fresh()->unit_202);
        $this->get($show)->assertOk();
        $this->assertSame(9398, (int) $planet->fresh()->deuterium);
    }

    public function test_invalid_transfers_do_not_reserve_resources_or_ships(): void
    {
        [$user, $planet] = $this->playerWithMoons();
        $url = route('moon.transfer', ['planet' => $planet->id, 'orbit' => 1]);
        $show = route('moon.show', ['planet' => $planet->id, 'orbit' => 1]);
        $this->actingAs($user)->from($show)->post($url, ['direction' => 'deposit', 'resource' => 'metal', 'amount' => 5001, 'ships' => [202 => 1]])->assertSessionHasErrors('ships');
        $this->from($show)->post($url, ['direction' => 'withdraw', 'resource' => 'metal', 'amount' => 1, 'ships' => [202 => 1]])->assertSessionHasErrors('amount');
        $this->from($show)->post($url, ['direction' => 'deposit', 'resource' => 'energy', 'amount' => 1, 'ships' => [202 => 1]])->assertSessionHasErrors('resource');
        $this->assertSame(2, (int) $planet->fresh()->unit_202);
        $this->assertSame(10000, (int) $planet->fresh()->deuterium);
        $this->assertDatabaseCount('fleets', 0);
    }

    public function test_lunar_buildings_restore_base_fields_and_enforce_phalanx_requirements(): void
    {
        [$user, $planet] = $this->playerWithMoons();
        $stock = app(MoonEconomy::class)->stock($planet, ['orbit' => 1]);
        DB::table('moon_stocks')->where('id', $stock->id)->update(['metal' => 100000, 'crystal' => 100000, 'deuterium' => 100000]);
        $url = route('moon.buildings.upgrade', ['planet' => $planet->id, 'orbit' => 1]);

        $this->actingAs($user)->post($url, ['building' => 'phalanx'])->assertSessionHasErrors('building');
        $response = $this->post($url, ['building' => 'moon_base']);
        $response->assertRedirect();
        $this->assertDatabaseHas('fleets', ['origin_planet_id' => $planet->id, 'mission' => 'moon_building']);

        $order = Fleet::where('origin_planet_id', $planet->id)->where('mission', 'moon_building')->firstOrFail();
        DB::table('moon_stocks')->where('planet_id', $planet->id)->where('orbit', 1)->update(['metal' => 100000, 'crystal' => 100000, 'deuterium' => 100000]);
        $this->travelTo($order->arrives_at->addSecond());
        app(ExplorationMissions::class)->processDue();
        $this->get(route('moon.show', ['planet' => $planet->id, 'orbit' => 1]))->assertInertia(fn (Assert $page) => $page
            ->where('moonEconomy.buildings.0.level', 1)->where('moonEconomy.fields.max', 5));

        $this->post($url, ['building' => 'phalanx'])->assertRedirect();
        $this->assertDatabaseHas('fleets', ['origin_planet_id' => $planet->id, 'mission' => 'moon_building', 'status' => 'outbound']);
    }

    public function test_moon_resources_cannot_be_reserved_twice_or_by_another_player(): void
    {
        [$user, $planet] = $this->playerWithMoons();
        $other = User::factory()->create(['universe_id' => $user->universe_id]);
        DB::table('moon_stocks')->insert(['planet_id' => $planet->id, 'orbit' => 1, 'metal' => 1000, 'crystal' => 0, 'deuterium' => 0]);
        $url = route('moon.transfer', ['planet' => $planet->id, 'orbit' => 1]);
        $payload = ['direction' => 'withdraw', 'resource' => 'metal', 'amount' => 700, 'ships' => [202 => 1]];
        $this->actingAs($other)->post($url, $payload)->assertNotFound();
        $this->actingAs($user)->post($url, $payload)->assertRedirect();
        $payload['amount'] = 400;
        $this->from(route('moon.show', ['planet' => $planet->id, 'orbit' => 1]))->post($url, $payload)->assertSessionHasErrors('amount');
        $this->assertDatabaseHas('moon_stocks', ['planet_id' => $planet->id, 'orbit' => 1, 'metal' => 300]);
        $this->assertDatabaseCount('fleets', 1);
        $this->assertSame(1, (int) $planet->fresh()->unit_202);
    }


    private function playerWithMoons(): array
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => fake()->unique()->slug()]);
        $user = User::factory()->create(['universe_id' => $universe->id]);
        $user->forceFill(['spy_technology_level' => 13])->save();
        $system = null;
        for ($candidate = 1; $candidate <= 499; $candidate++) {
            $slot = app(GalaxyMap::class)->system($universe, $user, 1, $candidate, 8)['slots'][6];
            if ($slot['moons'] >= 2) {
                $system = $candidate;
                break;
            }
        }
        $user->forceFill(['spy_technology_level' => 0])->save();
        $this->assertNotNull($system);
        $planet = new Planet(['name' => 'Home']);
        $planet->forceFill(['universe_id' => $universe->id, 'user_id' => $user->id, 'galaxy' => 1, 'system' => $system,
            'position' => 8, 'unit_202' => 2, 'deuterium' => 10000, 'resources_updated_at' => now()])->save();

        return [$user, $planet];
    }
}
