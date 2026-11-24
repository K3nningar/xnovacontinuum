<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Planet;
use App\Models\Universe;
use App\Models\User;
use App\Services\AdminAccess;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UniverseAdminAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_staff_access_is_limited_to_its_assigned_universe(): void
    {
        $pegasus = Universe::query()->create(['name' => 'Pégase', 'slug' => 'pegase']);
        Universe::query()->create(['name' => 'Andromède', 'slug' => 'andromede']);
        $staff = User::factory()->create();
        DB::table('universe_staff')->insert(['commander_id' => $staff->commander_id, 'universe_id' => $pegasus->id,
            'level' => 2, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($staff)->get(route('admin.index'))->assertOk();
        $this->actingAs($staff)->get(route('admin.resources'))->assertOk();
        $this->actingAs($staff)->get(route('admin.universes'))->assertForbidden();
    }

    public function test_global_administrator_role_on_account_applies_even_when_profile_has_no_role(): void
    {
        $universe = Universe::query()->create(['name' => 'Test', 'slug' => 'test']);
        $global = User::factory()->create(['admin_level' => 3]);
        $profile = User::factory()->create(['commander_id' => $global->commander_id, 'universe_id' => $universe->id]);

        $this->actingAs($profile)->get(route('admin.roles'))->assertOk();
    }

    public function test_role_assignment_requires_a_universe_for_local_staff_levels(): void
    {
        $founder = User::factory()->create(['admin_level' => 4]);
        $target = User::factory()->create();

        $this->actingAs($founder)->post(route('admin.roles.update'), [
            'player_id' => $target->id, 'admin_level' => 1,
        ])->assertSessionHasErrors('universe_ids');
        $this->assertDatabaseCount('universe_staff', 0);
    }

    public function test_roles_are_independent_per_universe_and_do_not_create_profiles(): void
    {
        $founder = User::factory()->create(['admin_level' => 4]);
        $player = User::factory()->create();
        $andromeda = Universe::query()->create(['name' => 'Andromède', 'slug' => 'andromede']);
        $pegasus = Universe::query()->create(['name' => 'Pégase', 'slug' => 'pegase']);
        $access = app(AdminAccess::class);

        $this->actingAs($founder)->post(route('admin.roles.update'), [
            'player_id' => $player->id, 'admin_level' => 2, 'universe_ids' => [$andromeda->id],
        ])->assertSessionHasNoErrors();
        $this->actingAs($founder)->post(route('admin.roles.update'), [
            'player_id' => $player->id, 'admin_level' => 1, 'universe_ids' => [$pegasus->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $access->levelFor($player, $andromeda->id));
        $this->assertSame(1, $access->levelFor($player, $pegasus->id));
        $this->assertDatabaseCount('users', 2);
        $this->assertSame(0, $player->fresh()->admin_level);

        $this->actingAs($founder)->post(route('admin.roles.update'), [
            'player_id' => $player->id, 'admin_level' => 0, 'universe_ids' => [$andromeda->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $access->levelFor($player, $andromeda->id));
        $this->assertSame(1, $access->levelFor($player, $pegasus->id));
        $this->assertDatabaseHas('admin_audit_logs', ['target_user_id' => $player->id, 'universe_id' => $andromeda->id, 'action' => 'role_changed']);
    }

    public function test_global_roles_apply_to_existing_and_future_universes_without_extra_users(): void
    {
        $founder = User::factory()->create(['admin_level' => 4]);
        $player = User::factory()->create();
        $existing = Universe::query()->create(['name' => 'Pégase', 'slug' => 'pegase']);

        $this->actingAs($founder)->post(route('admin.roles.update'), [
            'player_id' => $player->id, 'admin_level' => 3,
        ])->assertSessionHasNoErrors();
        $new = Universe::query()->create(['name' => 'Andromède', 'slug' => 'andromede']);

        $this->assertSame(3, app(AdminAccess::class)->levelFor($player->fresh(), $existing->id));
        $this->assertSame(3, app(AdminAccess::class)->levelFor($player->fresh(), $new->id));
        $this->assertSame(4, app(AdminAccess::class)->levelFor($founder, $new->id));
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('universe_staff', 0);
    }

    public function test_normalization_recovers_profile_roles_without_overwriting_existing_staff_assignments(): void
    {
        $account = User::factory()->create();
        $andromeda = Universe::query()->create(['name' => 'Andromède', 'slug' => 'andromede']);
        $pegasus = Universe::query()->create(['name' => 'Pégase', 'slug' => 'pegase']);
        $profile = User::factory()->create(['commander_id' => $account->commander_id, 'universe_id' => $andromeda->id, 'admin_level' => 2]);
        $otherProfile = User::factory()->create(['commander_id' => $account->commander_id, 'universe_id' => $pegasus->id, 'admin_level' => 3]);
        DB::table('universe_staff')->insert(['commander_id' => $account->commander_id, 'universe_id' => $andromeda->id, 'level' => 1]);

        $migration = require database_path('migrations/2026_11_24_100000_normalize_administrative_roles.php');
        $migration->up();

        $this->assertSame(3, $account->fresh()->admin_level);
        $this->assertSame(0, $profile->fresh()->admin_level);
        $this->assertSame(0, $otherProfile->fresh()->admin_level);
        $this->assertDatabaseHas('universe_staff', ['commander_id' => $account->commander_id, 'universe_id' => $andromeda->id, 'level' => 1]);
    }

    public function test_moderator_cannot_change_resources_outside_the_assigned_universe(): void
    {
        $andromeda = Universe::query()->create(['name' => 'Andromède', 'slug' => 'andromede']);
        $pegasus = Universe::query()->create(['name' => 'Pégase', 'slug' => 'pegase']);
        $moderator = User::factory()->create();
        $account = User::factory()->create();
        $profile = User::factory()->create(['commander_id' => $account->commander_id, 'universe_id' => $pegasus->id]);
        DB::table('commander_profiles')->insert(['commander_id' => $account->commander_id, 'universe_id' => $pegasus->id, 'profile_user_id' => $profile->id]);
        DB::table('universe_staff')->insert(['commander_id' => $moderator->commander_id, 'universe_id' => $andromeda->id, 'level' => 2]);
        $planet = new Planet;
        $planet->forceFill(['universe_id' => $pegasus->id, 'user_id' => $profile->id, 'name' => 'Prime',
            'galaxy' => 1, 'system' => 1, 'position' => 1])->save();

        $this->actingAs($moderator)->post(route('admin.resources.store'), [
            'player_id' => $account->id, 'planet_id' => $planet->id, 'metal' => 1, 'crystal' => 0, 'deuterium' => 0,
        ])->assertForbidden();
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_a_profile_can_be_promoted_globally_without_copying_the_role_to_profiles(): void
    {
        $founder = User::factory()->create(['admin_level' => 4]);
        $account = User::factory()->create();
        $universe = Universe::query()->create(['name' => 'Andromède', 'slug' => 'andromede']);
        $profile = User::factory()->create(['commander_id' => $account->commander_id, 'universe_id' => $universe->id]);

        $this->actingAs($founder)->post(route('admin.roles.update'), [
            'player_id' => $profile->id, 'admin_level' => 3,
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, $account->fresh()->admin_level);
        $this->assertSame(0, $profile->fresh()->admin_level);
        $this->assertSame(3, app(AdminAccess::class)->levelFor($profile, $universe->id));
        $this->assertDatabaseCount('users', 3);
    }

    public function test_normalization_recovers_a_legacy_account_role_when_there_is_one_universe(): void
    {
        $account = User::factory()->create(['admin_level' => 2]);
        $universe = Universe::query()->create(['name' => 'Andromède', 'slug' => 'andromede']);
        User::factory()->create(['commander_id' => $account->commander_id, 'universe_id' => $universe->id]);

        $migration = require database_path('migrations/2026_11_24_100000_normalize_administrative_roles.php');
        $migration->up();

        $this->assertSame(0, $account->fresh()->admin_level);
        $this->assertDatabaseHas('universe_staff', ['commander_id' => $account->commander_id, 'universe_id' => $universe->id, 'level' => 2]);
    }
}
