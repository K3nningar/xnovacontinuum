<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Tests the application's AdminPanelTest behavior.
 */

namespace Tests\Feature;

use App\Models\LicenseKey;
use App\Models\Planet;
use App\Models\Universe;
use App\Models\User;
use App\Services\CelestialNames;
use App\Services\PlanetEconomy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.index'))->assertRedirect(route('login'));
    }

    public function test_registration_cannot_set_an_administrative_level(): void
    {
        $licenseKey = 'XNOVA-11111111-22222222-33333333-44444444';
        LicenseKey::query()->create(['key_hash' => LicenseKey::digest($licenseKey)]);

        $this->post(route('register'), [
            'license_key' => $licenseKey,
            'name' => 'Commander',
            'email' => 'commander@example.test',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
            'admin_level' => 4,
        ])->assertRedirect(route('verification.notice'));

        $this->assertSame(0, User::query()->where('email', 'commander@example.test')->firstOrFail()->admin_level);
    }

    #[DataProvider('forbiddenPages')]
    public function test_insufficient_levels_cannot_access_administrative_pages(int $level, string $route): void
    {
        $user = User::factory()->create();
        $this->assignAccessLevel($user, $level);

        $this->actingAs($user)->get(route($route))->assertForbidden();
    }

    public static function forbiddenPages(): array
    {
        return [
            'player overview' => [0, 'admin.index'],
            'operator resources' => [1, 'admin.resources'],
            'operator elements' => [1, 'admin.elements'],
            'moderator elements' => [2, 'admin.elements'],
            'moderator roles' => [2, 'admin.roles'],
            'administrator universes' => [3, 'admin.universes'],
            'invalid level' => [5, 'admin.index'],
        ];
    }

    #[DataProvider('allowedPages')]
    public function test_authorized_levels_can_open_administrative_pages(int $level, string $route, string $mode): void
    {
        $user = User::factory()->create();
        $this->assignAccessLevel($user, $level);

        $this->actingAs($user)->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Index')
            ->where('mode', $mode)
            ->where('auth.user.admin_level', $level));
    }

    public static function allowedPages(): array
    {
        return [
            'operator overview' => [1, 'admin.index', 'overview'],
            'moderator resources' => [2, 'admin.resources', 'resources'],
            'administrator resources' => [3, 'admin.resources', 'resources'],
            'founder resources' => [4, 'admin.resources', 'resources'],
            'administrator elements' => [3, 'admin.elements', 'elements'],
            'founder roles' => [4, 'admin.roles', 'roles'],
            'founder universes' => [4, 'admin.universes', 'universes'],
        ];
    }

    public function test_overview_displays_totals_and_the_selected_players_planet(): void
    {
        $operator = User::factory()->create();
        $planet = $this->createPlanet();
        $this->assignAccessLevel($operator, 1, (int) $planet->universe_id);
        $playerAccount = User::query()->where('commander_id', $planet->user->commander_id)->whereNull('universe_id')->firstOrFail();
        DB::table('fleets')->insert([
            'universe_id' => $planet->universe_id, 'user_id' => $planet->user_id,
            'origin_planet_id' => $planet->id, 'mission' => 'transport', 'ships' => '{}',
            'status' => 'outbound', 'arrives_at' => now()->addHour(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($operator)->get(route('admin.index', ['player' => $playerAccount->id, 'planet' => $planet->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('overview.players', 2)
            ->where('overview.planets', 1)
            ->where('overview.universes', 1)
            ->where('overview.fleets', 1)
            ->where('activeFleets.0.player', $planet->user->name)
            ->where('activeFleets.0.mission', 'transport')
            ->where('selectedPlayer.id', $playerAccount->id)
            ->where('selectedPlanetId', $planet->id));
    }

    #[DataProvider('forbiddenActions')]
    public function test_insufficient_levels_cannot_post_administrative_actions(int $level, string $route): void
    {
        $user = User::factory()->create();
        $this->assignAccessLevel($user, $level);

        $this->actingAs($user)->post(route($route), [])->assertForbidden();
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public static function forbiddenActions(): array
    {
        return [
            'player resources' => [0, 'admin.resources.store'],
            'operator resources' => [1, 'admin.resources.store'],
            'moderator elements' => [2, 'admin.elements.store'],
            'moderator roles' => [2, 'admin.roles.update'],
            'moderator suspension' => [2, 'admin.suspend'],
            'moderator announcement' => [2, 'admin.announcements.store'],
            'moderator changelog publication' => [2, 'admin.changelog.entries.store'],
            'administrator universe creation' => [3, 'admin.universes.store'],
        ];
    }

    public function test_founder_can_create_an_active_universe_with_custom_settings_and_it_is_audited(): void
    {
        $founder = User::factory()->create();
        $founder->forceFill(['admin_level' => 4])->save();

        $this->actingAs($founder)->post(route('admin.universes.store'), [
            'name' => 'Pegasus',
            'slug' => 'pegasus',
            'galaxies' => 2,
            'systems_per_galaxy' => 3,
            'economy_speed' => 2.5,
            'fleet_speed' => 3,
        ])->assertRedirect(route('admin.universes'));

        $universe = Universe::query()->where('slug', 'pegasus')->firstOrFail();
        $this->assertDatabaseCount('universe_system_names', 6);
        $generatedNames = app(CelestialNames::class)->forSystem($universe, 1, 1);
        $this->assertDatabaseHas('universe_system_names', [
            'universe_id' => $universe->id,
            'galaxy' => 1,
            'system' => 1,
            'star_name' => $generatedNames['starName'],
        ]);
        $this->assertSame($generatedNames['starName'].' VIII', $generatedNames['planetNames'][8]);
        $this->assertSame('active', $universe->status);
        $this->assertSame(2, $universe->galaxies);
        $this->assertSame(3, $universe->systems_per_galaxy);
        $this->assertSame(2.5, (float) $universe->economy_speed);
        $this->assertSame(3.0, (float) $universe->fleet_speed);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $founder->id,
            'action' => 'universe_created',
        ]);
        $this->get(route('admin.universes'))->assertInertia(fn (Assert $page) => $page
            ->where('universes.0.id', $universe->id)
            ->where('universes.0.galaxies', 2)
            ->where('universes.0.systems_per_galaxy', 3)
            ->where('universes.0.named_systems', 6));

        $newPlayer = User::factory()->create();
        $this->actingAs($newPlayer)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('availableUniverses.0.id', $universe->id)
            ->where('availableUniverses.0.name', 'Pegasus'));
    }

    public function test_founder_can_open_a_closed_universe_without_changing_its_existing_players(): void
    {
        $founder = User::factory()->create();
        $founder->forceFill(['admin_level' => 4])->save();
        $universe = Universe::query()->create(['name' => 'Pegasus', 'slug' => 'pegasus', 'status' => 'closed', 'galaxies' => 1, 'systems_per_galaxy' => 2]);
        DB::table('universe_system_names')->insert(['universe_id' => $universe->id, 'galaxy' => 1, 'system' => 1,
            'star_name' => 'Preexisting star', 'planet_names' => '{}', 'moon_names' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $player = User::factory()->create(['universe_id' => $universe->id]);

        $this->actingAs($founder)->post(route('admin.universes.open', $universe))
            ->assertRedirect(route('admin.universes'));

        $this->assertSame('active', $universe->fresh()->status);
        $this->assertSame(2, DB::table('universe_system_names')->where('universe_id', $universe->id)->count());
        $this->assertDatabaseHas('universe_system_names', ['universe_id' => $universe->id, 'system' => 1, 'star_name' => 'Preexisting star']);
        $this->assertSame($universe->id, $player->fresh()->universe_id);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $founder->id,
            'action' => 'universe_opened',
        ]);

        $newPlayer = User::factory()->create();
        $this->actingAs($newPlayer)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('availableUniverses.0.id', $universe->id)
            ->where('availableUniverses.0.name', 'Pegasus'));
    }

    public function test_administrator_cannot_open_a_closed_universe(): void
    {
        $administrator = User::factory()->create();
        $administrator->forceFill(['admin_level' => 3])->save();
        $universe = Universe::query()->create(['name' => 'Pegasus', 'slug' => 'pegasus', 'status' => 'closed']);

        $this->actingAs($administrator)->post(route('admin.universes.open', $universe))->assertForbidden();

        $this->assertSame('closed', $universe->fresh()->status);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_founder_cannot_create_a_universe_with_a_duplicate_slug_or_out_of_range_settings(): void
    {
        $founder = User::factory()->create();
        $founder->forceFill(['admin_level' => 4])->save();
        Universe::query()->create(['name' => 'Pegasus', 'slug' => 'pegasus']);

        $this->actingAs($founder)->from(route('admin.universes'))->post(route('admin.universes.store'), [
            'name' => 'New Pegasus',
            'slug' => 'pegasus',
            'galaxies' => 10,
            'systems_per_galaxy' => 500,
            'economy_speed' => 0,
            'fleet_speed' => 101,
        ])->assertRedirect(route('admin.universes'))
            ->assertSessionHasErrors(['slug', 'galaxies', 'systems_per_galaxy', 'economy_speed', 'fleet_speed']);

        $this->assertDatabaseCount('universes', 1);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_opening_an_already_active_universe_is_rejected_without_an_audit_entry(): void
    {
        $founder = User::factory()->create();
        $founder->forceFill(['admin_level' => 4])->save();
        $universe = Universe::query()->create(['name' => 'Pegasus', 'slug' => 'pegasus']);

        $this->actingAs($founder)->from(route('admin.universes'))->post(route('admin.universes.open', $universe))
            ->assertRedirect(route('admin.universes'))
            ->assertSessionHasErrors('universe');

        $this->assertSame('active', $universe->fresh()->status);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_founder_can_assign_a_role_and_it_is_audited(): void
    {
        $founder = User::factory()->create();
        $founder->forceFill(['admin_level' => 4])->save();
        $target = User::factory()->create();
        $universe = Universe::query()->create(['name' => 'Pegasus', 'slug' => fake()->unique()->slug()]);

        $this->actingAs($founder)->post(route('admin.roles.update'), [
            'player_id' => $target->id,
            'admin_level' => 2,
            'universe_ids' => [$universe->id],
        ])->assertRedirect(route('admin.roles', ['player' => $target->id]));

        $this->assertSame(0, $target->fresh()->admin_level);
        $this->assertDatabaseHas('universe_staff', ['commander_id' => $target->commander_id, 'universe_id' => $universe->id, 'level' => 2]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $founder->id,
            'target_user_id' => $target->id,
            'action' => 'role_changed',
        ]);
        $this->actingAs($founder)->get(route('admin.roles'))->assertInertia(fn (Assert $page) => $page
            ->where('auditLogs.0.action', 'role_changed')
            ->where('auditLogs.0.target_user_id', $target->id));
    }

    public function test_administrator_can_view_the_element_catalog(): void
    {
        $administrator = User::factory()->create();
        $administrator->forceFill(['admin_level' => 3])->save();

        $this->actingAs($administrator)->get(route('admin.elements'))->assertInertia(fn (Assert $page) => $page
            ->where('catalog.0.category', 'buildings')
            ->where('catalog.0.key', 'metal')
            ->has('catalog'));
    }

    public function test_founder_cannot_create_another_founder_or_modify_their_own_role(): void
    {
        $founder = User::factory()->create();
        $founder->forceFill(['admin_level' => 4])->save();
        $target = User::factory()->create();

        $this->actingAs($founder)->post(route('admin.roles.update'), [
            'player_id' => $target->id,
            'admin_level' => 4,
        ])->assertSessionHasErrors('admin_level');
        $this->actingAs($founder)->post(route('admin.roles.update'), [
            'player_id' => $founder->id,
            'admin_level' => 0,
        ])->assertSessionHasErrors('player_id');

        $this->assertSame(4, $founder->fresh()->admin_level);
        $this->assertSame(0, $target->fresh()->admin_level);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_founder_is_bootstrapped_from_the_console_only_once(): void
    {
        $founder = User::factory()->create();
        $other = User::factory()->create();

        $this->artisan('admin:founder', ['userId' => $founder->id])->assertSuccessful();
        $this->artisan('admin:founder', ['userId' => $other->id])->assertFailed();

        $this->assertSame(4, $founder->fresh()->admin_level);
        $this->assertSame(0, $other->fresh()->admin_level);
        $this->assertDatabaseHas('admin_audit_logs', ['target_user_id' => $founder->id, 'action' => 'founder_bootstrap']);
    }

    public function test_moderator_can_add_resources_to_a_selected_planet_and_the_action_is_audited(): void
    {
        $this->freezeTime();
        $moderator = User::factory()->create();
        $planet = $this->createPlanet();
        $this->assignAccessLevel($moderator, 2, (int) $planet->universe_id);

        $this->actingAs($moderator)->post(route('admin.resources.store'), [
            'player_id' => $planet->user_id,
            'planet_id' => $planet->id,
            'metal' => 100,
            'crystal' => 50,
            'deuterium' => 20,
        ])->assertRedirect(route('admin.resources', ['player' => $planet->user_id, 'planet' => $planet->id]));

        $this->assertDatabaseHas('planets', ['id' => $planet->id, 'metal' => 600, 'crystal' => 550, 'deuterium' => 20]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $moderator->id, 'target_user_id' => $planet->user_id,
            'planet_id' => $planet->id, 'action' => 'resources_added',
        ]);
    }

    public function test_resource_addition_rejects_another_players_planet_and_invalid_quantities(): void
    {
        $moderator = User::factory()->create();
        $planet = $this->createPlanet();
        $this->assignAccessLevel($moderator, 2, (int) $planet->universe_id);
        $other = User::factory()->create();

        $this->actingAs($moderator)->post(route('admin.resources.store'), [
            'player_id' => $other->id, 'planet_id' => $planet->id,
            'metal' => 1, 'crystal' => 0, 'deuterium' => 0,
        ])->assertNotFound();
        $this->actingAs($moderator)->post(route('admin.resources.store'), [
            'player_id' => $planet->user_id, 'planet_id' => $planet->id,
            'metal' => -1, 'crystal' => 0, 'deuterium' => 0,
        ])->assertSessionHasErrors('metal');

        $this->assertDatabaseHas('planets', ['id' => $planet->id, 'metal' => 500]);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_resource_grant_materializes_the_existing_production_first(): void
    {
        $this->freezeTime();
        $moderator = User::factory()->create();
        $planet = $this->createPlanet();
        $this->assignAccessLevel($moderator, 2, (int) $planet->universe_id);
        $planet->forceFill(['resources_updated_at' => now()->subHour()])->save();

        $this->actingAs($moderator)->post(route('admin.resources.store'), [
            'player_id' => $planet->user_id, 'planet_id' => $planet->id,
            'metal' => 100, 'crystal' => 0, 'deuterium' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('planets', ['id' => $planet->id, 'metal' => 620, 'crystal' => 510]);
    }

    public function test_resource_grant_cannot_exceed_storage_capacity(): void
    {
        $this->freezeTime();
        $moderator = User::factory()->create();
        $planet = $this->createPlanet();
        $this->assignAccessLevel($moderator, 2, (int) $planet->universe_id);

        $this->actingAs($moderator)->post(route('admin.resources.store'), [
            'player_id' => $planet->user_id, 'planet_id' => $planet->id,
            'metal' => 999501, 'crystal' => 0, 'deuterium' => 0,
        ])->assertSessionHasErrors('metal');

        $this->assertDatabaseHas('planets', ['id' => $planet->id, 'metal' => 500]);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_staff_cannot_grant_resources_to_a_peer_or_themselves(): void
    {
        $planet = $this->createPlanet();
        $this->assignAccessLevel($planet->user, 2, (int) $planet->universe_id);
        $moderator = User::factory()->create();
        $this->assignAccessLevel($moderator, 2, (int) $planet->universe_id);

        $this->actingAs($moderator)->post(route('admin.resources.store'), [
            'player_id' => $planet->user_id, 'planet_id' => $planet->id,
            'metal' => 1, 'crystal' => 0, 'deuterium' => 0,
        ])->assertSessionHasErrors('player_id');
        $this->actingAs($planet->user)->post(route('admin.resources.store'), [
            'player_id' => $planet->user_id, 'planet_id' => $planet->id,
            'metal' => 1, 'crystal' => 0, 'deuterium' => 0,
        ])->assertSessionHasErrors('player_id');

        $this->assertDatabaseHas('planets', ['id' => $planet->id, 'metal' => 500]);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_founder_can_grant_resources_and_elements_to_their_own_empire(): void
    {
        $this->freezeTime();
        $planet = $this->createPlanet();
        $profile = $planet->user;
        $account = User::query()->where('commander_id', $profile->commander_id)->whereNull('universe_id')->firstOrFail();
        $account->forceFill(['admin_level' => 4])->save();
        $planet->forceFill(['hangar_level' => 2, 'robot_factory_level' => 2])->save();
        $profile->forceFill(['combustion_technology_level' => 2])->save();

        $this->actingAs($profile)->get(route('admin.elements', ['player' => $account->id, 'planet' => $planet->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('selectedPlayer.id', $account->id)
            ->where('selectedPlanetId', $planet->id));

        $this->post(route('admin.resources.store'), [
            'player_id' => $account->id, 'planet_id' => $planet->id,
            'metal' => 100, 'crystal' => 50, 'deuterium' => 20,
        ])->assertRedirect()->assertSessionHasNoErrors();

        foreach ([['buildings', 'metal', 1], ['buildings', 'defence_center', 1], ['research', 'computer', 1], ['officers', '601', 1], ['ships', '202', 2], ['defences', '401', 1]] as [$category, $key, $amount]) {
            $this->post(route('admin.elements.store'), [
                'player_id' => $account->id, 'planet_id' => $planet->id,
                'category' => $category, 'key' => $key, 'amount' => $amount,
            ])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->post(route('admin.resources.store'), [
            'player_id' => $account->id, 'planet_id' => $planet->id,
            'metal' => 1000000000, 'crystal' => 0, 'deuterium' => 0,
        ])->assertSessionHasErrors('metal');

        $this->assertDatabaseHas('planets', ['id' => $planet->id, 'metal' => 600, 'crystal' => 550, 'deuterium' => 20, 'metal_mine_level' => 1, 'defence_center_level' => 1, 'unit_202' => 2, 'unit_401' => 1]);
        $this->assertDatabaseHas('users', ['id' => $profile->id, 'computer_technology_level' => 1, 'officer_geologist_level' => 1]);
        $this->assertDatabaseHas('users', ['id' => $account->id, 'computer_technology_level' => 0, 'officer_geologist_level' => 0]);
        $this->assertDatabaseHas('admin_audit_logs', ['actor_id' => $profile->id, 'target_user_id' => $account->id, 'planet_id' => $planet->id, 'action' => 'resources_added']);
        $this->assertDatabaseCount('admin_audit_logs', 7);
    }

    public function test_admin_research_grants_preserve_suspended_officer_bonuses(): void
    {
        config(['modules.officers.enabled' => false]);
        $administrator = User::factory()->create(['admin_level' => 3]);
        $planet = $this->createPlanet();
        $player = $planet->user;
        $player->forceFill([
            'spy_technology_level' => 7, 'computer_technology_level' => 5,
            'officer_spy_level' => 1, 'officer_commander_level' => 1,
            'officer_spy_research_bonus' => 5, 'officer_commander_research_bonus' => 3,
        ])->save();

        foreach (['spy', 'computer'] as $technology) {
            $this->actingAs($administrator)->post(route('admin.elements.store'), [
                'player_id' => $player->id, 'planet_id' => $planet->id,
                'category' => 'research', 'key' => $technology, 'amount' => 1,
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(3, $player->fresh()->spy_technology_level);
        $this->assertSame(3, $player->fresh()->computer_technology_level);
        $this->assertDatabaseHas('users', ['id' => $player->id, 'spy_technology_level' => 8, 'computer_technology_level' => 6]);

        config(['modules.officers.enabled' => true]);
        $this->assertSame(8, $player->fresh()->spy_technology_level);
        $this->assertSame(6, $player->fresh()->computer_technology_level);
    }

    public function test_self_grants_remain_forbidden_for_administrators_and_other_founders_are_protected(): void
    {
        $planet = $this->createPlanet();
        $profile = $planet->user;
        $account = User::query()->where('commander_id', $profile->commander_id)->whereNull('universe_id')->firstOrFail();
        $account->forceFill(['admin_level' => 3])->save();
        $resources = ['player_id' => $account->id, 'planet_id' => $planet->id, 'metal' => 1, 'crystal' => 0, 'deuterium' => 0];
        $element = ['player_id' => $account->id, 'planet_id' => $planet->id, 'category' => 'buildings', 'key' => 'metal', 'amount' => 1];

        $this->actingAs($profile)->post(route('admin.resources.store'), $resources)->assertSessionHasErrors('player_id');
        $this->post(route('admin.elements.store'), $element)->assertSessionHasErrors('player_id');

        $account->forceFill(['admin_level' => 4])->save();
        $this->actingAs($profile)->post(route('admin.suspend'), [
            'player_id' => $account->id, 'days' => 1, 'reason' => 'Test',
        ])->assertSessionHasErrors('player_id');

        $otherFounder = User::factory()->create(['admin_level' => 4]);
        $this->actingAs($otherFounder)->post(route('admin.resources.store'), $resources)->assertSessionHasErrors('player_id');
        $this->post(route('admin.elements.store'), $element)->assertSessionHasErrors('player_id');
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_founder_can_grant_elements_to_an_unmapped_legacy_profile_in_another_universe(): void
    {
        $planet = $this->createPlanet();
        $profile = $planet->user;
        $account = User::query()->where('commander_id', $profile->commander_id)->whereNull('universe_id')->firstOrFail();
        $account->forceFill(['admin_level' => 4])->save();
        $universe = Universe::query()->create(['name' => 'Second', 'slug' => 'second']);
        $otherProfile = User::factory()->create(['commander_id' => $account->commander_id, 'universe_id' => $universe->id]);
        $otherPlanet = new Planet;
        $otherPlanet->forceFill(['universe_id' => $universe->id, 'user_id' => $otherProfile->id, 'name' => 'Second',
            'galaxy' => 1, 'system' => 1, 'position' => 8, 'resources_updated_at' => now()])->save();

        $this->actingAs($account)->get(route('admin.elements', ['player' => $account->id, 'planet' => $otherPlanet->id]))
            ->assertInertia(fn (Assert $page) => $page->where('selectedPlanetId', $otherPlanet->id));
        $this->post(route('admin.elements.store'), [
            'player_id' => $account->id, 'planet_id' => $otherPlanet->id,
            'category' => 'research', 'key' => 'computer', 'amount' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('admin.resources.store'), [
            'player_id' => $account->id, 'planet_id' => $otherPlanet->id,
            'metal' => 10, 'crystal' => 0, 'deuterium' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $otherProfile->id, 'computer_technology_level' => 1]);
        $this->assertDatabaseHas('planets', ['id' => $otherPlanet->id, 'metal' => 510]);
        $this->assertDatabaseHas('users', ['id' => $profile->id, 'computer_technology_level' => 0]);
        $this->assertDatabaseHas('users', ['id' => $account->id, 'computer_technology_level' => 0]);
    }

    public function test_administrator_can_add_a_building_and_terraformer_fields(): void
    {
        $this->freezeTime();
        $administrator = User::factory()->create();
        $administrator->forceFill(['admin_level' => 3])->save();
        $planet = $this->createPlanet();

        $this->actingAs($administrator)->post(route('admin.elements.store'), [
            'player_id' => $planet->user_id, 'planet_id' => $planet->id,
            'category' => 'buildings', 'key' => 'terraformer', 'amount' => 2,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('planets', ['id' => $planet->id, 'terraformer_level' => 2, 'fields' => 173]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $administrator->id, 'target_user_id' => $planet->user_id,
            'planet_id' => $planet->id, 'action' => 'element_added',
        ]);
    }

    public function test_administrator_can_add_research_and_officers_to_the_player(): void
    {
        $administrator = User::factory()->create();
        $administrator->forceFill(['admin_level' => 3])->save();
        $planet = $this->createPlanet();
        $account = User::query()->where('commander_id', $planet->user->commander_id)->whereNull('universe_id')->firstOrFail();
        $payload = ['player_id' => $account->id, 'planet_id' => $planet->id, 'amount' => 1];

        $this->actingAs($administrator)->post(route('admin.elements.store'), [
            ...$payload, 'category' => 'research', 'key' => 'computer',
        ])->assertSessionHasNoErrors();
        $this->actingAs($administrator)->post(route('admin.elements.store'), [
            ...$payload, 'category' => 'officers', 'key' => '601',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $planet->user_id, 'computer_technology_level' => 1, 'officer_geologist_level' => 1]);
        $this->assertDatabaseCount('admin_audit_logs', 2);
    }

    public function test_administrator_can_add_a_ship_when_its_requirements_are_met(): void
    {
        $administrator = User::factory()->create();
        $administrator->forceFill(['admin_level' => 3])->save();
        $planet = $this->createPlanet();
        $planet->forceFill(['hangar_level' => 2, 'robot_factory_level' => 2])->save();
        $planet->user->forceFill(['combustion_technology_level' => 2])->save();

        $this->actingAs($administrator)->post(route('admin.elements.store'), [
            'player_id' => $planet->user_id, 'planet_id' => $planet->id,
            'category' => 'ships', 'key' => '202', 'amount' => 10,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('planets', ['id' => $planet->id, 'unit_202' => 10]);
        $this->assertDatabaseHas('admin_audit_logs', ['planet_id' => $planet->id, 'action' => 'element_added']);
    }

    public function test_granted_solar_satellites_do_not_power_mines_before_the_grant(): void
    {
        $this->freezeSecond();
        $administrator = User::factory()->create();
        $administrator->forceFill(['admin_level' => 3])->save();
        $planet = $this->createPlanet();
        $planet->forceFill([
            'hangar_level' => 1,
            'robot_factory_level' => 1,
            'metal' => 0,
            'metal_mine_level' => 2,
            'metal_mine_activity' => 10,
            'max_temperature' => 40,
            'resources_updated_at' => now()->subHour(),
        ])->save();

        $this->actingAs($administrator)->post(route('admin.elements.store'), [
            'player_id' => $planet->user_id, 'planet_id' => $planet->id,
            'category' => 'ships', 'key' => '212', 'amount' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame(20, $planet->fresh()->metal);
        $this->assertSame(1, (int) $planet->fresh()->unit_212);

        $this->travel(1)->hour();
        app(PlanetEconomy::class)->materialize($planet->fresh());
        $this->assertSame(112, $planet->fresh()->metal);
    }

    public function test_unique_defence_cannot_be_granted_more_than_once(): void
    {
        $administrator = User::factory()->create();
        $administrator->forceFill(['admin_level' => 3])->save();
        $planet = $this->createPlanet();
        $planet->forceFill(['defence_center_level' => 1, 'robot_factory_level' => 1, 'unit_407' => 1])->save();
        $planet->user->forceFill(['defence_technology_level' => 2])->save();

        $this->actingAs($administrator)->post(route('admin.elements.store'), [
            'player_id' => $planet->user_id, 'planet_id' => $planet->id,
            'category' => 'defences', 'key' => '407', 'amount' => 1,
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseHas('planets', ['id' => $planet->id, 'unit_407' => 1]);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_missile_grants_respect_shared_silo_capacity_including_the_queue(): void
    {
        $administrator = User::factory()->create();
        $administrator->forceFill(['admin_level' => 3])->save();
        $planet = $this->createPlanet();
        $planet->forceFill(['defence_center_level' => 1, 'robot_factory_level' => 1, 'missile_silo_level' => 4, 'unit_502' => 36])->save();
        DB::table('planet_unit_queues')->insert([
            'universe_id' => $planet->universe_id, 'planet_id' => $planet->id,
            'unit_code' => 503, 'unit_type' => 'defences', 'quantity' => 1, 'quantity_completed' => 0, 'position' => 1,
            'cost_metal' => 0, 'cost_crystal' => 0, 'cost_deuterium' => 0,
            'starts_at' => now(), 'completes_at' => now()->addHour(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($administrator)->post(route('admin.elements.store'), [
            'player_id' => $planet->user_id, 'planet_id' => $planet->id,
            'category' => 'defences', 'key' => '502', 'amount' => 3,
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseHas('planets', ['id' => $planet->id, 'unit_502' => 36]);
        $this->assertDatabaseCount('admin_audit_logs', 0);

        $this->actingAs($administrator)->post(route('admin.elements.store'), [
            'player_id' => $planet->user_id, 'planet_id' => $planet->id,
            'category' => 'defences', 'key' => '502', 'amount' => 2,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('planets', ['id' => $planet->id, 'unit_502' => 38]);
        $this->assertDatabaseCount('admin_audit_logs', 1);
    }

    public function test_element_grant_rejects_another_players_planet(): void
    {
        $administrator = User::factory()->create();
        $administrator->forceFill(['admin_level' => 3])->save();
        $planet = $this->createPlanet();
        $other = User::factory()->create();

        $this->actingAs($administrator)->post(route('admin.elements.store'), [
            'player_id' => $other->id, 'planet_id' => $planet->id,
            'category' => 'buildings', 'key' => 'metal', 'amount' => 1,
        ])->assertNotFound();

        $this->assertDatabaseHas('planets', ['id' => $planet->id, 'metal_mine_level' => 0]);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_invalid_element_and_pending_buildings_cannot_be_modified(): void
    {
        $administrator = User::factory()->create();
        $administrator->forceFill(['admin_level' => 3])->save();
        $planet = $this->createPlanet();
        DB::table('planet_building_queues')->insert([
            'universe_id' => $planet->universe_id, 'planet_id' => $planet->id,
            'building_key' => 'metal', 'building_code' => 1, 'target_level' => 1, 'position' => 1,
            'cost_metal' => 60, 'cost_crystal' => 15, 'cost_deuterium' => 0,
            'starts_at' => now(), 'completes_at' => now()->addHour(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $payload = ['player_id' => $planet->user_id, 'planet_id' => $planet->id, 'category' => 'buildings', 'amount' => 1];

        $this->actingAs($administrator)->post(route('admin.elements.store'), [...$payload, 'key' => 'metal'])->assertSessionHasErrors('key');
        $this->actingAs($administrator)->post(route('admin.elements.store'), [...$payload, 'key' => 'admin_level'])->assertSessionHasErrors('key');

        $this->assertDatabaseHas('planets', ['id' => $planet->id, 'metal_mine_level' => 0]);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_pending_research_and_officer_recruitment_block_conflicting_grants(): void
    {
        $administrator = User::factory()->create();
        $administrator->forceFill(['admin_level' => 3])->save();
        $planet = $this->createPlanet();
        DB::table('research_queues')->insert([
            'universe_id' => $planet->universe_id, 'user_id' => $planet->user_id, 'planet_id' => $planet->id,
            'technology_key' => 'computer', 'technology_code' => 108, 'target_level' => 1,
            'cost_metal' => 0, 'cost_crystal' => 0, 'cost_deuterium' => 0,
            'completes_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('planet_unit_queues')->insert([
            'universe_id' => $planet->universe_id, 'planet_id' => $planet->id,
            'unit_code' => 601, 'unit_type' => 'officers', 'quantity' => 1, 'quantity_completed' => 0, 'position' => 1,
            'cost_metal' => 0, 'cost_crystal' => 0, 'cost_deuterium' => 0,
            'starts_at' => now(), 'completes_at' => now()->addHour(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $account = User::query()->where('commander_id', $planet->user->commander_id)->whereNull('universe_id')->firstOrFail();
        $payload = ['player_id' => $account->id, 'planet_id' => $planet->id, 'amount' => 1];

        $this->actingAs($administrator)->post(route('admin.elements.store'), [...$payload, 'category' => 'research', 'key' => 'computer'])
            ->assertSessionHasErrors('key');
        $this->actingAs($administrator)->post(route('admin.elements.store'), [...$payload, 'category' => 'officers', 'key' => '601'])
            ->assertSessionHasErrors('key');

        $this->assertDatabaseHas('users', ['id' => $planet->user_id, 'computer_technology_level' => 0, 'officer_geologist_level' => 0]);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_building_grant_cannot_exceed_planet_fields(): void
    {
        $administrator = User::factory()->create();
        $administrator->forceFill(['admin_level' => 3])->save();
        $planet = $this->createPlanet();
        $planet->forceFill(['fields' => 1, 'metal_mine_level' => 1])->save();

        $this->actingAs($administrator)->post(route('admin.elements.store'), [
            'player_id' => $planet->user_id, 'planet_id' => $planet->id,
            'category' => 'buildings', 'key' => 'crystal', 'amount' => 1,
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseHas('planets', ['id' => $planet->id, 'crystal_mine_level' => 0]);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_administrator_can_suspend_a_player_and_an_existing_session_is_blocked(): void
    {
        $this->freezeTime();
        $operator = User::factory()->create();
        $this->assignAccessLevel($operator, 3);
        $player = User::factory()->create();

        $this->actingAs($operator)->post(route('admin.suspend'), [
            'player_id' => $player->id, 'days' => 2, 'reason' => 'Violation',
        ])->assertRedirect(route('admin.index', ['player' => $player->id]));

        $this->assertTrue($player->fresh()->isSuspended());
        $this->assertDatabaseHas('admin_audit_logs', ['actor_id' => $operator->id, 'target_user_id' => $player->id, 'action' => 'account_suspended']);
        $this->actingAs($player->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login'), ['email' => $player->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_only_administrators_and_above_can_lift_a_suspension(): void
    {
        $this->freezeTime();
        $operator = User::factory()->create();
        $this->assignAccessLevel($operator, 1);
        $moderator = User::factory()->create();
        $this->assignAccessLevel($moderator, 3);
        $player = User::factory()->create();
        $player->forceFill(['suspended_until' => now()->addDay(), 'suspension_reason' => 'Violation'])->save();

        $this->actingAs($operator)->delete(route('admin.unsuspend'), ['player_id' => $player->id])->assertForbidden();
        $this->actingAs($moderator)->delete(route('admin.unsuspend'), ['player_id' => $player->id])
            ->assertRedirect(route('admin.index', ['player' => $player->id]));

        $this->assertFalse($player->fresh()->isSuspended());
        $this->assertDatabaseHas('admin_audit_logs', ['actor_id' => $moderator->id, 'target_user_id' => $player->id, 'action' => 'account_unsuspended']);
    }

    public function test_staff_cannot_suspend_an_equal_or_higher_level(): void
    {
        $operator = User::factory()->create();
        $this->assignAccessLevel($operator, 3);
        $other = User::factory()->create();
        $this->assignAccessLevel($other, 3);

        $this->actingAs($operator)->post(route('admin.suspend'), [
            'player_id' => $other->id, 'days' => 2, 'reason' => 'Violation',
        ])->assertSessionHasErrors('player_id');

        $this->assertNull($other->fresh()->suspended_until);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_administrator_can_publish_a_general_announcement_for_existing_players(): void
    {
        $this->freezeTime();
        $operator = User::factory()->create();
        $this->assignAccessLevel($operator, 3);
        $player = User::factory()->create();

        $this->actingAs($operator)->post(route('admin.announcements.store'), [
            'body' => 'Une maintenance est prévue.',
        ])->assertRedirect(route('admin.index'));

        $this->assertDatabaseHas('admin_announcements', ['actor_id' => $operator->id, 'body' => 'Une maintenance est prévue.']);
        $this->assertDatabaseHas('admin_audit_logs', ['actor_id' => $operator->id, 'action' => 'announcement_created']);
        $this->actingAs($player)->get(route('announcements.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Announcements/Index')
            ->has('announcements.data', 1)
            ->where('announcements.data.0.body', 'Une maintenance est prévue.'));

        $newPlayer = User::factory()->create();
        $this->actingAs($newPlayer)->get(route('announcements.index'))->assertInertia(fn (Assert $page) => $page
            ->has('announcements.data', 0));
    }

    public function test_players_cannot_publish_or_overflow_an_announcement(): void
    {
        $player = User::factory()->create();
        $operator = User::factory()->create();
        $this->assignAccessLevel($operator, 3);

        $this->actingAs($player)->post(route('admin.announcements.store'), ['body' => 'Message'])
            ->assertForbidden();
        $this->actingAs($operator)->post(route('admin.announcements.store'), ['body' => str_repeat('x', 2001)])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('admin_announcements', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_only_administrators_can_revoke_announcements_and_the_action_is_audited(): void
    {
        $this->freezeTime();
        $operator = User::factory()->create();
        $this->assignAccessLevel($operator, 3);
        $moderator = User::factory()->create();
        $this->assignAccessLevel($moderator, 2);
        $administrator = User::factory()->create();
        $administrator->forceFill(['admin_level' => 3])->save();
        $player = User::factory()->create();
        $this->actingAs($operator)->post(route('admin.announcements.store'), ['body' => 'Message']);
        $announcement = DB::table('admin_announcements')->first();

        $this->actingAs($moderator)->delete(route('admin.announcements.revoke', $announcement->id))->assertForbidden();
        $this->actingAs($administrator)->delete(route('admin.announcements.revoke', $announcement->id))
            ->assertRedirect(route('admin.index'));

        $this->assertNotNull(DB::table('admin_announcements')->where('id', $announcement->id)->value('revoked_at'));
        $this->assertDatabaseHas('admin_audit_logs', ['actor_id' => $administrator->id, 'action' => 'announcement_revoked']);
        $this->actingAs($player)->get(route('announcements.index'))->assertInertia(fn (Assert $page) => $page
            ->has('announcements.data', 0));
    }

    public function test_administrators_can_find_older_announcements_for_revocation(): void
    {
        $administrator = User::factory()->create();
        $administrator->forceFill(['admin_level' => 3])->save();
        for ($number = 1; $number <= 11; $number++) {
            DB::table('admin_announcements')->insert([
                'actor_id' => $administrator->id,
                'body' => "Message {$number}", 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($administrator)->get(route('admin.index', ['announcements_page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('adminAnnouncements.data.0.body', 'Message 1'));
    }

    public function test_announcement_content_is_not_rendered_as_html(): void
    {
        $operator = User::factory()->create();
        $this->assignAccessLevel($operator, 3);
        $player = User::factory()->create();
        $this->actingAs($operator)->post(route('admin.announcements.store'), ['body' => '<script>alert(1)</script>'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($player)->get(route('announcements.index'))
            ->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_general_announcements_are_rate_limited_per_administrator(): void
    {
        $operator = User::factory()->create();
        $this->assignAccessLevel($operator, 3);
        $this->actingAs($operator);
        for ($number = 1; $number <= 5; $number++) {
            $this->post(route('admin.announcements.store'), ['body' => "Message {$number}"])->assertRedirect();
        }

        $this->post(route('admin.announcements.store'), ['body' => 'Message 6'])->assertTooManyRequests();

        $this->assertDatabaseCount('admin_announcements', 5);
        $this->assertDatabaseCount('admin_audit_logs', 5);
    }

    /**
     * Assigns global levels to the account row and local staff levels to universe_staff.
     */
    private function assignAccessLevel(User $user, int $level, ?int $universeId = null): void
    {
        if ($level >= 3) {
            $user->forceFill(['admin_level' => $level])->save();

            return;
        }

        if ($level <= 0 || $level > 2) {
            $user->forceFill(['admin_level' => $level])->save();

            return;
        }

        if ($universeId === null) {
            $universe = Universe::query()->create([
                'name' => 'Admin test universe',
                'slug' => fake()->unique()->slug(),
            ]);
            $universeId = (int) $universe->id;
        }

        DB::table('universe_staff')->insert([
            'commander_id' => $user->commander_id,
            'universe_id' => $universeId,
            'level' => $level,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPlanet(): Planet
    {
        $universe = new Universe;
        $universe->forceFill(['name' => 'Test', 'slug' => fake()->unique()->slug()])->save();
        $commander = User::factory()->create();
        $profile = new User;
        $profile->name = $commander->name;
        $profile->email = 'profile-'.fake()->unique()->uuid().'@profiles.xnova.invalid';
        $profile->password = 'random-profile-password';
        $profile->forceFill(['commander_id' => $commander->commander_id, 'universe_id' => $universe->id])->save();
        DB::table('commander_profiles')->insert([
            'commander_id' => $commander->commander_id,
            'profile_user_id' => $profile->id,
            'universe_id' => $universe->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $planet = new Planet;
        $planet->forceFill([
            'universe_id' => $universe->id, 'user_id' => $profile->id, 'name' => 'Prime',
            'galaxy' => 1, 'system' => 1, 'position' => 1, 'resources_updated_at' => now(),
        ])->save();

        return $planet;
    }
}
