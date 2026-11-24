<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Universe;
use App\Models\User;
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
}
