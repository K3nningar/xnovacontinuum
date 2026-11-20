<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Tests the application's ExampleTest behavior.
 */

namespace Tests\Feature;

use App\Models\Planet;
use App\Models\Universe;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_is_rendered_by_inertia(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('releases')
                ->where('ziggy.url', config('app.url'))
            );
    }

    public function test_guests_can_open_the_changelog(): void
    {
        $this->get(route('changelog'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Changelog'));
    }

    public function test_game_navigation_routes_generate_english_urls(): void
    {
        $paths = [
            'login' => '/login',
            'register' => '/register',
            'dashboard' => '/commandcenter',
            'galaxy.index' => '/galaxy',
            'buildings.index' => '/buildings',
            'research.index' => '/research',
            'research.tree' => '/research/tree',
            'shipyard.index' => '/shipyard',
            'defences.index' => '/defences',
            'officers.index' => '/officers',
            'fleet.index' => '/fleet',
            'combat-simulator.index' => '/combat-simulator',
            'announcements.index' => '/announcements',
            'multi-accounts.index' => '/multi-account-declarations',
            'admin.index' => '/admin',
        ];

        foreach ($paths as $name => $path) {
            $this->assertSame($path, route($name, [], false));
        }

        $this->assertSame('/planets/42/moons/3/transfer', route('moon.transfer', ['planet' => 42, 'orbit' => 3], false));
        $this->assertSame('/planets/42/shipyard/ships', route('planet.ships.store', ['planet' => 42], false));
    }

    public function test_the_landing_page_defaults_to_english_even_for_a_french_browser(): void
    {
        config(['app.locale' => 'en']);

        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
            ->get('/')
            ->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    }

    public function test_a_selected_language_is_used_for_html_and_inertia_responses(): void
    {
        config(['app.locale' => 'en']);

        $this->withUnencryptedCookie('xnova.locale', 'fr')
            ->get('/')
            ->assertOk()
            ->assertSee('<html lang="fr"', false)
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'fr'));
    }

    public function test_an_invalid_language_cookie_uses_the_configured_default(): void
    {
        config(['app.locale' => 'en']);

        $this->withUnencryptedCookie('xnova.locale', 'invalid')
            ->get('/')
            ->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    }

    public function test_guests_are_redirected_to_login_from_the_command_center(): void
    {
        $this->get('/commandcenter')->assertRedirect('/login');
    }

    public function test_guests_can_open_login_and_registration_pages(): void
    {
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
        $this->get('/register')->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
    }

    public function test_a_user_can_register_and_reaches_the_command_center(): void
    {
        $universe = Universe::create(['name' => 'Andromède', 'slug' => 'andromeda']);

        $this->post('/register', [
            'name' => 'Nova Pilot',
            'email' => 'nova@example.test',
            'password' => 'secure-password-123',
            'password_confirmation' => 'secure-password-123',
        ])->assertRedirect(route('account.universes'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'nova@example.test']);
        $this->get(route('account.universes'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Universes')
            ->where('universes.0.hasEmpire', false));
        $accountId = $this->app['auth']->user()->id;
        $this->post(route('account.universes.select'), ['universe_id' => $universe->id, 'account_id' => $accountId])
            ->assertRedirect(route('dashboard'));
        $this->withSession(['onboardingUniverseId' => $universe->id,
            'onboardingCommanderUserId' => $accountId])
            ->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('hasUniverse', false)
            ->where('onboardingUniverseId', $universe->id));

        $this->post('/universe/join', ['universe_id' => $universe->id, 'name' => 'Nova Prime'])
            ->assertRedirect('/commandcenter');

        $this->assertDatabaseHas('planets', [
            'name' => 'Nova Prime',
            'universe_id' => $universe->id,
            'position' => 8,
        ]);
        $this->assertDatabaseHas('commander_profiles', [
            'commander_id' => $this->app['auth']->user()->commander_id,
            'profile_user_id' => $this->app['auth']->user()->id,
            'universe_id' => $universe->id,
        ]);
    }

    public function test_repeated_universe_selection_does_not_hit_the_empire_creation_limit(): void
    {
        $account = User::factory()->create();
        $existingUniverse = Universe::create(['name' => 'Existing universe', 'slug' => 'existing-universe']);
        $existingProfile = new User;
        $existingProfile->name = $account->name;
        $existingProfile->email = null;
        $existingProfile->password = 'universe-profile-password';
        $existingProfile->forceFill(['commander_id' => $account->commander_id, 'universe_id' => $existingUniverse->id])->save();
        DB::table('commander_profiles')->insert([
            'commander_id' => $account->commander_id,
            'profile_user_id' => $existingProfile->id,
            'universe_id' => $existingUniverse->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $newUniverse = Universe::create(['name' => 'New universe', 'slug' => 'new-universe']);

        $this->actingAs($account);
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('account.universes.select'), [
                'universe_id' => $existingUniverse->id,
                'account_id' => $account->id,
            ])->assertRedirect(route('dashboard'));
            $this->assertAuthenticatedAs($existingProfile);
            auth()->login($account);
        }

        $this->post(route('account.universes.select'), [
            'universe_id' => $newUniverse->id,
            'account_id' => $account->id,
        ])->assertRedirect(route('dashboard'));
        $this->withSession([
            'onboardingUniverseId' => $newUniverse->id,
            'onboardingCommanderUserId' => $account->id,
            'activeCommanderUserId' => $account->id,
        ])->post(route('universe.join'), [
            'universe_id' => $newUniverse->id,
            'name' => 'New homeworld',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('commander_profiles', [
            'commander_id' => $account->commander_id,
            'universe_id' => $newUniverse->id,
        ]);
    }

    public function test_a_commander_can_create_separate_empires_and_resume_either_universe(): void
    {
        $firstUniverse = Universe::create(['name' => 'Andromède', 'slug' => 'andromeda']);
        $secondUniverse = Universe::create(['name' => 'Pégase', 'slug' => 'pegase']);
        $account = User::factory()->create([
            'name' => 'Nova Pilot',
            'email' => 'multi-universe@example.test',
            'password' => 'secure-password-123',
        ]);
        $accountId = $account->commander_id;

        $this->actingAs($account)->post(route('universe.join'), [
            'universe_id' => $firstUniverse->id,
            'name' => 'Andromeda Prime',
        ])->assertRedirect(route('dashboard'));
        $firstProfile = auth()->user();
        $firstProfile->forceFill(['energy_technology_level' => 4])->save();
        $firstPlanet = $firstProfile->planets()->firstOrFail();
        $firstPlanet->update(['metal' => 4321]);

        $this->actingAs($firstProfile)->post('/logout')->assertRedirect('/');
        $this->post('/login', [
            'email' => 'multi-universe@example.test',
            'password' => 'secure-password-123',
        ])->assertRedirect(route('account.universes'));
        $this->get(route('account.universes'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Universes')
            ->where('universes.0.hasEmpire', true)
            ->where('universes.1.hasEmpire', false));

        $this->post(route('account.universes.select'), ['universe_id' => $secondUniverse->id, 'account_id' => $account->id])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('hasUniverse', false)
            ->where('onboardingUniverseId', $secondUniverse->id));
        $this->post(route('universe.join'), [
            'universe_id' => $secondUniverse->id,
            'name' => 'Pegasus Prime',
        ])->assertRedirect(route('dashboard'));

        $secondProfile = auth()->user();
        $secondProfile->forceFill(['energy_technology_level' => 1])->save();
        $secondPlanet = $secondProfile->planets()->firstOrFail();
        $secondPlanet->update(['metal' => 9876]);
        $this->assertSame($accountId, $secondProfile->commander_id);
        $this->assertNotSame($firstProfile->id, $secondProfile->id);
        $this->assertSame('multi-universe@example.test', User::query()->where('id', $account->id)->value('email'));
        $this->assertNull($secondProfile->fresh()->email);

        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', [
            'email' => 'multi-universe@example.test',
            'password' => 'secure-password-123',
        ])->assertRedirect(route('account.universes'));
        $this->post(route('account.universes.select'), ['universe_id' => $firstUniverse->id, 'account_id' => $account->id])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('universe.name', 'Andromède')
            ->where('planets.0.metal', 4321));

        $this->assertSame(4, $firstProfile->fresh()->energy_technology_level);
        $this->assertSame(1, $secondProfile->fresh()->energy_technology_level);
        $this->assertSame(9876, $secondPlanet->fresh()->metal);
        $this->assertDatabaseCount('commander_profiles', 2);
    }

    public function test_registration_rejects_short_passwords(): void
    {
        Universe::create(['name' => 'Andromède', 'slug' => 'andromeda']);

        $this->from('/register')->post('/register', [
            'name' => 'Nova Pilot',
            'email' => 'nova@example.test',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertRedirect('/register')->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'nova@example.test']);
    }

    public function test_a_commander_can_join_an_active_universe_and_found_a_homeworld(): void
    {
        $universe = Universe::create(['name' => 'Andromède', 'slug' => 'andromeda']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/universe/join', ['universe_id' => $universe->id, 'name' => 'Nova Prime'])
            ->assertRedirect('/commandcenter');

        $profile = User::query()->where('commander_id', $user->commander_id)->where('universe_id', $universe->id)->firstOrFail();
        $this->assertDatabaseHas('users', [
            'id' => $profile->id,
            'universe_id' => $universe->id,
        ]);
        $this->assertDatabaseHas('planets', [
            'user_id' => $profile->id,
            'universe_id' => $universe->id,
            'name' => 'Nova Prime',
            'position' => 8,
            'metal' => 500,
            'crystal' => 500,
        ]);
        $this->assertDatabaseCount('planets', 1);

        $profile = $profile->fresh();

        $this->actingAs($profile)->get('/commandcenter')->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('universe.name', 'Andromède')
            ->has('planets', 1)
        );
    }

    public function test_new_colony_is_placed_in_an_unoccupied_system(): void
    {
        $universe = Universe::create(['name' => 'Andromède', 'slug' => 'andromeda']);
        $existingCommander = User::factory()->create(['universe_id' => $universe->id]);
        $existingPlanet = new Planet(['name' => 'Old Prime']);
        $existingPlanet->universe()->associate($universe);
        $existingPlanet->galaxy = 1;
        $existingPlanet->system = 1;
        $existingPlanet->position = 8;
        $existingPlanet->user()->associate($existingCommander);
        $existingPlanet->save();

        $newCommander = User::factory()->create();
        $this->actingAs($newCommander)
            ->post('/universe/join', ['universe_id' => $universe->id, 'name' => 'New Prime'])
            ->assertRedirect('/commandcenter');

        $newProfile = User::query()->where('commander_id', $newCommander->commander_id)->where('universe_id', $universe->id)->firstOrFail();
        $this->assertDatabaseHas('planets', [
            'user_id' => $newProfile->id,
            'universe_id' => $universe->id,
            'position' => 8,
        ]);
        $this->assertDatabaseMissing('planets', [
            'user_id' => $newProfile->id,
            'galaxy' => 1,
            'system' => 1,
        ]);
    }

    public function test_joining_twice_does_not_create_another_homeworld(): void
    {
        $universe = Universe::create(['name' => 'Andromède', 'slug' => 'andromeda']);
        $user = User::factory()->create(['universe_id' => $universe->id]);
        $planet = new Planet(['name' => 'Nova Prime']);
        $planet->universe()->associate($universe);
        $planet->galaxy = 1;
        $planet->system = 1;
        $planet->position = 8;
        $planet->user()->associate($user);
        $planet->save();

        $this->actingAs($user)
            ->from('/commandcenter')
            ->post('/universe/join', ['universe_id' => $universe->id, 'name' => 'Nova II'])
            ->assertRedirect('/commandcenter')
            ->assertSessionHasErrors('universe');

        $this->assertDatabaseCount('planets', 1);
    }

    public function test_joining_rejects_an_untrusted_universe_or_planet_name(): void
    {
        $user = User::factory()->create();
        $activeUniverse = Universe::create(['name' => 'Andromède', 'slug' => 'andromeda']);
        $closedUniverse = Universe::create(['name' => 'Pégase', 'slug' => 'pegase', 'status' => 'closed']);

        $this->actingAs($user)
            ->from('/commandcenter')
            ->post('/universe/join', ['universe_id' => $activeUniverse->id, 'name' => '<script>alert(1)</script>'])
            ->assertSessionHasErrors('name');
        $this->assertDatabaseCount('planets', 0);

        $this->actingAs($user)
            ->from('/commandcenter')
            ->post('/universe/join', ['universe_id' => $closedUniverse->id, 'name' => 'Nova Prime'])
            ->assertSessionHasErrors('universe_id');

        $this->actingAs($user)
            ->from('/commandcenter')
            ->post('/universe/join', ['universe_id' => $activeUniverse->id, 'name' => 'Nova Prime'])
            ->assertRedirect('/commandcenter');

        $this->assertNull($user->fresh()->universe_id);
        $this->assertDatabaseHas('users', [
            'commander_id' => $user->commander_id,
            'universe_id' => $activeUniverse->id,
        ]);
        $this->assertDatabaseCount('planets', 1);
    }

    public function test_planets_cannot_be_attached_to_a_commander_in_another_universe(): void
    {
        $firstUniverse = Universe::create(['name' => 'Andromède', 'slug' => 'andromeda']);
        $secondUniverse = Universe::create(['name' => 'Pégase', 'slug' => 'pegase']);
        $commander = User::factory()->create(['universe_id' => $firstUniverse->id]);

        $planet = new Planet(['name' => 'Foreign Prime']);
        $planet->universe()->associate($secondUniverse);
        $planet->galaxy = 1;
        $planet->system = 1;
        $planet->position = 8;
        $planet->user()->associate($commander);

        $this->expectException(QueryException::class);
        $planet->save();
    }

    public function test_a_commander_can_log_in_and_log_out(): void
    {
        $universe = Universe::create(['name' => 'Andromède', 'slug' => 'andromeda']);
        $user = User::factory()->create([
            'email' => 'nova@example.test',
            'password' => 'secure-password-123',
        ]);
        $profile = new User;
        $profile->name = $user->name;
        $profile->email = null;
        $profile->password = 'random-profile-password';
        $profile->forceFill(['commander_id' => $user->commander_id, 'universe_id' => $universe->id])->save();
        DB::table('commander_profiles')->insert([
            'commander_id' => $user->commander_id,
            'profile_user_id' => $profile->id,
            'universe_id' => $universe->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/login', [
            'email' => 'nova@example.test',
            'password' => 'secure-password-123',
        ])->assertRedirect(route('account.universes'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('account.universes'))->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Universes')
            ->where('universes.0.hasEmpire', true));
        $this->post(route('account.universes.select'), ['universe_id' => $universe->id, 'account_id' => $user->id])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($profile);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
