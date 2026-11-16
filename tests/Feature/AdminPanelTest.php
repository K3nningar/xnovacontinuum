<?php

namespace Tests\Feature;

use App\Models\Planet;
use App\Models\Universe;
use App\Models\User;
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
        $this->post(route('register'), [
            'name' => 'Commander',
            'email' => 'commander@example.test',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
            'admin_level' => 4,
        ])->assertRedirect(route('dashboard'));

        $this->assertSame(0, User::query()->where('email', 'commander@example.test')->firstOrFail()->admin_level);
    }

    #[DataProvider('forbiddenPages')]
    public function test_insufficient_levels_cannot_access_administrative_pages(int $level, string $route): void
    {
        $user = User::factory()->create();
        $user->forceFill(['admin_level' => $level])->save();

        $this->actingAs($user)->get(route($route))->assertForbidden();
    }

    public static function forbiddenPages(): array
    {
        return [
            'player overview' => [0, 'admin.index'],
            'operator resources' => [1, 'admin.resources'],
            'operator elements' => [1, 'admin.elements'],
            'moderator elements' => [2, 'admin.elements'],
            'administrator roles' => [3, 'admin.roles'],
            'invalid level' => [5, 'admin.index'],
        ];
    }

    #[DataProvider('allowedPages')]
    public function test_authorized_levels_can_open_administrative_pages(int $level, string $route, string $mode): void
    {
        $user = User::factory()->create();
        $user->forceFill(['admin_level' => $level])->save();

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
        ];
    }

    public function test_overview_displays_totals_and_the_selected_players_planet(): void
    {
        $operator = User::factory()->create();
        $operator->forceFill(['admin_level' => 1])->save();
        $planet = $this->createPlanet();
        DB::table('fleets')->insert([
            'universe_id' => $planet->universe_id, 'user_id' => $planet->user_id,
            'origin_planet_id' => $planet->id, 'mission' => 'transport', 'ships' => '{}',
            'status' => 'outbound', 'arrives_at' => now()->addHour(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($operator)->get(route('admin.index', ['player' => $planet->user_id, 'planet' => $planet->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('overview.players', 2)
            ->where('overview.planets', 1)
            ->where('overview.universes', 1)
            ->where('overview.fleets', 1)
            ->where('activeFleets.0.player', $planet->user->name)
            ->where('activeFleets.0.mission', 'transport')
            ->where('selectedPlayer.id', $planet->user_id)
            ->where('selectedPlanetId', $planet->id));
    }

    #[DataProvider('forbiddenActions')]
    public function test_insufficient_levels_cannot_post_administrative_actions(int $level, string $route): void
    {
        $user = User::factory()->create();
        $user->forceFill(['admin_level' => $level])->save();

        $this->actingAs($user)->post(route($route), [])->assertForbidden();
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public static function forbiddenActions(): array
    {
        return [
            'player resources' => [0, 'admin.resources.store'],
            'operator resources' => [1, 'admin.resources.store'],
            'moderator elements' => [2, 'admin.elements.store'],
            'administrator roles' => [3, 'admin.roles.update'],
        ];
    }

    public function test_founder_can_assign_a_role_and_it_is_audited(): void
    {
        $founder = User::factory()->create();
        $founder->forceFill(['admin_level' => 4])->save();
        $target = User::factory()->create();

        $this->actingAs($founder)->post(route('admin.roles.update'), [
            'player_id' => $target->id,
            'admin_level' => 2,
        ])->assertRedirect(route('admin.roles', ['player' => $target->id]));

        $this->assertSame(2, $target->fresh()->admin_level);
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
        $moderator->forceFill(['admin_level' => 2])->save();
        $planet = $this->createPlanet();

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
        $moderator->forceFill(['admin_level' => 2])->save();
        $planet = $this->createPlanet();
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
        $moderator->forceFill(['admin_level' => 2])->save();
        $planet = $this->createPlanet();
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
        $moderator->forceFill(['admin_level' => 2])->save();
        $planet = $this->createPlanet();

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
        $planet->user->forceFill(['admin_level' => 2])->save();
        $moderator = User::factory()->create();
        $moderator->forceFill(['admin_level' => 2])->save();

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
        $payload = ['player_id' => $planet->user_id, 'planet_id' => $planet->id, 'amount' => 1];

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
        $planet->forceFill(['hangar_level' => 1, 'robot_factory_level' => 1, 'unit_407' => 1])->save();
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
        $planet->forceFill(['hangar_level' => 1, 'robot_factory_level' => 1, 'missile_silo_level' => 4, 'unit_502' => 36])->save();
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
        $payload = ['player_id' => $planet->user_id, 'planet_id' => $planet->id, 'amount' => 1];

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

    public function test_operator_can_suspend_a_player_and_an_existing_session_is_blocked(): void
    {
        $this->freezeTime();
        $operator = User::factory()->create();
        $operator->forceFill(['admin_level' => 1])->save();
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

    public function test_only_moderators_and_above_can_lift_a_suspension(): void
    {
        $this->freezeTime();
        $operator = User::factory()->create();
        $operator->forceFill(['admin_level' => 1])->save();
        $moderator = User::factory()->create();
        $moderator->forceFill(['admin_level' => 2])->save();
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
        $operator->forceFill(['admin_level' => 1])->save();
        $other = User::factory()->create();
        $other->forceFill(['admin_level' => 2])->save();

        $this->actingAs($operator)->post(route('admin.suspend'), [
            'player_id' => $other->id, 'days' => 2, 'reason' => 'Violation',
        ])->assertSessionHasErrors('player_id');

        $this->assertNull($other->fresh()->suspended_until);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_operator_can_publish_a_general_announcement_for_existing_players(): void
    {
        $this->freezeTime();
        $operator = User::factory()->create();
        $operator->forceFill(['admin_level' => 1])->save();
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
        $operator->forceFill(['admin_level' => 1])->save();

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
        $operator->forceFill(['admin_level' => 1])->save();
        $moderator = User::factory()->create();
        $moderator->forceFill(['admin_level' => 2])->save();
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
        $operator->forceFill(['admin_level' => 1])->save();
        $player = User::factory()->create();
        $this->actingAs($operator)->post(route('admin.announcements.store'), ['body' => '<script>alert(1)</script>']);

        $this->actingAs($player)->get(route('announcements.index'))
            ->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_general_announcements_are_rate_limited_per_operator(): void
    {
        $operator = User::factory()->create();
        $operator->forceFill(['admin_level' => 1])->save();
        $this->actingAs($operator);
        for ($number = 1; $number <= 5; $number++) {
            $this->post(route('admin.announcements.store'), ['body' => "Message {$number}"])->assertRedirect();
        }

        $this->post(route('admin.announcements.store'), ['body' => 'Message 6'])->assertTooManyRequests();

        $this->assertDatabaseCount('admin_announcements', 5);
        $this->assertDatabaseCount('admin_audit_logs', 5);
    }

    private function createPlanet(): Planet
    {
        $universe = new Universe;
        $universe->forceFill(['name' => 'Test', 'slug' => 'test'])->save();
        $user = User::factory()->create();
        $user->universe()->associate($universe);
        $user->save();
        $planet = new Planet;
        $planet->forceFill([
            'universe_id' => $universe->id, 'user_id' => $user->id, 'name' => 'Prime',
            'galaxy' => 1, 'system' => 1, 'position' => 1, 'resources_updated_at' => now(),
        ])->save();

        return $planet;
    }
}
