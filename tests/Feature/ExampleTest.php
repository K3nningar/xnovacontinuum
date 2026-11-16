<?php

namespace Tests\Feature;

use App\Models\Planet;
use App\Models\Universe;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                ->where('ziggy.url', config('app.url'))
            );
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
        $this->get('/commandement')->assertRedirect('/connexion');
    }

    public function test_guests_can_open_login_and_registration_pages(): void
    {
        $this->get('/connexion')->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
        $this->get('/inscription')->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
    }

    public function test_a_user_can_register_and_reaches_the_command_center(): void
    {
        $universe = Universe::create(['name' => 'Andromède', 'slug' => 'andromeda']);

        $this->post('/inscription', [
            'name' => 'Nova Pilot',
            'email' => 'nova@example.test',
            'password' => 'secure-password-123',
            'password_confirmation' => 'secure-password-123',
        ])->assertRedirect('/commandement');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'nova@example.test']);
        $this->get('/commandement')->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('user.name', 'Nova Pilot')
            ->where('hasUniverse', false)
            ->where('universe', [])
            ->has('availableUniverses', 1)
        );

        $this->actingAs($this->app['auth']->user())
            ->post('/univers/rejoindre', ['universe_id' => $universe->id, 'name' => 'Nova Prime'])
            ->assertRedirect('/commandement');

        $this->assertDatabaseHas('planets', [
            'name' => 'Nova Prime',
            'universe_id' => $universe->id,
            'position' => 8,
        ]);
    }

    public function test_registration_rejects_short_passwords(): void
    {
        Universe::create(['name' => 'Andromède', 'slug' => 'andromeda']);

        $this->from('/inscription')->post('/inscription', [
            'name' => 'Nova Pilot',
            'email' => 'nova@example.test',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertRedirect('/inscription')->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'nova@example.test']);
    }

    public function test_a_commander_can_join_an_active_universe_and_found_a_homeworld(): void
    {
        $universe = Universe::create(['name' => 'Andromède', 'slug' => 'andromeda']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/univers/rejoindre', ['universe_id' => $universe->id, 'name' => 'Nova Prime'])
            ->assertRedirect('/commandement');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'universe_id' => $universe->id,
        ]);
        $this->assertDatabaseHas('planets', [
            'user_id' => $user->id,
            'universe_id' => $universe->id,
            'name' => 'Nova Prime',
            'position' => 8,
            'metal' => 500,
            'crystal' => 500,
        ]);
        $this->assertDatabaseCount('planets', 1);

        $user->refresh();

        $this->actingAs($user)->get('/commandement')->assertInertia(fn (Assert $page) => $page
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
            ->post('/univers/rejoindre', ['universe_id' => $universe->id, 'name' => 'New Prime'])
            ->assertRedirect('/commandement');

        $this->assertDatabaseHas('planets', [
            'user_id' => $newCommander->id,
            'universe_id' => $universe->id,
            'position' => 8,
        ]);
        $this->assertDatabaseMissing('planets', [
            'user_id' => $newCommander->id,
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
            ->from('/commandement')
            ->post('/univers/rejoindre', ['universe_id' => $universe->id, 'name' => 'Nova II'])
            ->assertRedirect('/commandement')
            ->assertSessionHasErrors('universe');

        $this->assertDatabaseCount('planets', 1);
    }

    public function test_joining_rejects_an_untrusted_universe_or_planet_name(): void
    {
        $user = User::factory()->create();
        $activeUniverse = Universe::create(['name' => 'Andromède', 'slug' => 'andromeda']);
        $closedUniverse = Universe::create(['name' => 'Pégase', 'slug' => 'pegase', 'status' => 'closed']);

        $this->actingAs($user)
            ->from('/commandement')
            ->post('/univers/rejoindre', ['universe_id' => $activeUniverse->id, 'name' => '<script>alert(1)</script>'])
            ->assertSessionHasErrors('name');
        $this->assertDatabaseCount('planets', 0);

        $this->actingAs($user)
            ->from('/commandement')
            ->post('/univers/rejoindre', ['universe_id' => $closedUniverse->id, 'name' => 'Nova Prime'])
            ->assertSessionHasErrors('universe_id');

        $this->actingAs($user)
            ->from('/commandement')
            ->post('/univers/rejoindre', ['universe_id' => $activeUniverse->id, 'name' => 'Nova Prime'])
            ->assertRedirect('/commandement');

        $this->assertSame($activeUniverse->id, $user->fresh()->universe_id);
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
        $user = User::factory()->create([
            'email' => 'nova@example.test',
            'password' => 'secure-password-123',
        ]);

        $this->post('/connexion', [
            'email' => 'nova@example.test',
            'password' => 'secure-password-123',
        ])->assertRedirect('/commandement');

        $this->assertAuthenticatedAs($user);

        $this->post('/deconnexion')->assertRedirect('/');
        $this->assertGuest();
    }
}
