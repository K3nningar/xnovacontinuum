<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GameModules;
use App\Services\UnitProductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ModuleAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_founders_can_view_or_update_module_settings(): void
    {
        $payload = ['officers_enabled' => false, 'enabled_officers' => []];
        $this->get(route('admin.modules'))->assertRedirect(route('login'));
        foreach ([0, 3] as $level) {
            $user = User::factory()->create(['admin_level' => $level]);
            $this->actingAs($user)->get(route('admin.modules'))->assertForbidden();
            $this->patch(route('admin.modules.update'), $payload)->assertForbidden();
        }
        $this->assertDatabaseCount('game_module_settings', 0);
    }

    public function test_initial_values_follow_configuration_and_are_exposed_individually(): void
    {
        config(['modules.officers.enabled' => false, 'modules.officers.disabled' => ['601']]);
        $founder = User::factory()->create(['admin_level' => 4]);

        $this->actingAs($founder)->get(route('admin.modules'))->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Modules')->where('officersEnabled', false)->has('officers', 15)
            ->where('officers.0.code', 601)->where('officers.0.enabled', false)
            ->where('officers.1.code', 602)->where('officers.1.enabled', true));
    }

    public function test_saving_persists_global_and_individual_states_and_audits_changes(): void
    {
        $founder = User::factory()->create(['admin_level' => 4]);
        $this->actingAs($founder)->patch(route('admin.modules.update'), [
            'officers_enabled' => false, 'enabled_officers' => [602, 603],
        ])->assertRedirect(route('admin.modules'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('game_module_settings', ['key' => 'module.officers', 'enabled' => false]);
        $this->assertDatabaseHas('game_module_settings', ['key' => 'officer.601', 'enabled' => false]);
        $this->assertDatabaseHas('game_module_settings', ['key' => 'officer.602', 'enabled' => true]);
        $this->assertDatabaseHas('admin_audit_logs', ['actor_id' => $founder->id, 'action' => 'modules_updated']);
        config(['modules.officers.enabled' => true, 'modules.officers.disabled' => []]);
        $this->get(route('admin.modules'))->assertInertia(fn (Assert $page) => $page
            ->where('officersEnabled', false)->where('officers.0.enabled', false)->where('officers.1.enabled', true));
        $this->assertFalse(app(GameModules::class)->officerEnabled(602));

        $this->patch(route('admin.modules.update'), ['officers_enabled' => true, 'enabled_officers' => [602, 603]])
            ->assertSessionHasNoErrors();
        $this->get(route('admin.modules'))->assertInertia(fn (Assert $page) => $page
            ->where('officersEnabled', true)->where('officers.0.enabled', false)->where('officers.1.enabled', true));
        $this->assertFalse(app(GameModules::class)->officerEnabled(601));
        $this->assertTrue(app(GameModules::class)->officerEnabled(602));
        $catalog = app(UnitProductionService::class)->officerCatalog(User::factory()->create());
        $this->assertSame([602, 603], array_column($catalog['officers'], 'code'));
    }

    public function test_invalid_codes_duplicates_and_missing_settings_do_not_change_configuration(): void
    {
        $founder = User::factory()->create(['admin_level' => 4]);
        $this->actingAs($founder)->from(route('admin.modules'));
        $this->patch(route('admin.modules.update'), ['officers_enabled' => true, 'enabled_officers' => [999]])
            ->assertSessionHasErrors('enabled_officers.0');
        $this->patch(route('admin.modules.update'), ['officers_enabled' => true, 'enabled_officers' => [601, 601]])
            ->assertSessionHasErrors('enabled_officers.0');
        $this->patch(route('admin.modules.update'), ['officers_enabled' => true])
            ->assertSessionHasErrors('enabled_officers');
        $this->assertDatabaseCount('game_module_settings', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_administration_changes_suspend_and_restore_existing_research_bonuses(): void
    {
        $founder = User::factory()->create(['admin_level' => 4]);
        $player = User::factory()->create();
        $player->forceFill([
            'spy_technology_level' => 7, 'computer_technology_level' => 5,
            'officer_spy_level' => 1, 'officer_commander_level' => 1,
            'officer_spy_research_bonus' => 5, 'officer_commander_research_bonus' => 3,
        ])->save();

        $this->actingAs($founder)->patch(route('admin.modules.update'), [
            'officers_enabled' => true, 'enabled_officers' => [611],
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $player->fresh()->spy_technology_level);
        $this->assertSame(5, $player->fresh()->computer_technology_level);

        $this->patch(route('admin.modules.update'), ['officers_enabled' => false, 'enabled_officers' => [610, 611]])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $player->fresh()->spy_technology_level);
        $this->assertSame(2, $player->fresh()->computer_technology_level);

        $this->patch(route('admin.modules.update'), ['officers_enabled' => true, 'enabled_officers' => [610, 611]])
            ->assertSessionHasNoErrors();
        $this->assertSame(7, $player->fresh()->spy_technology_level);
        $this->assertSame(5, $player->fresh()->computer_technology_level);
        $this->assertDatabaseHas('users', ['id' => $player->id, 'spy_technology_level' => 7, 'computer_technology_level' => 5]);
    }

    public function test_founder_can_toggle_factions_without_changing_officer_settings(): void
    {
        $founder = User::factory()->create(['admin_level' => 4]);
        $this->actingAs($founder)->get(route('admin.modules'))->assertInertia(fn (Assert $page) => $page
            ->where('factionsEnabled', true));

        $this->patch(route('admin.modules.update'), [
            'factions_enabled' => false, 'officers_enabled' => true, 'enabled_officers' => [610],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('game_module_settings', ['key' => 'module.factions', 'enabled' => false]);
        $this->get(route('admin.modules'))->assertInertia(fn (Assert $page) => $page
            ->where('factionsEnabled', false)->where('officersEnabled', true));
        $this->assertFalse(app(GameModules::class)->enabled('factions'));
        $this->assertTrue(app(GameModules::class)->officerEnabled(610));

        $this->patch(route('admin.modules.update'), [
            'factions_enabled' => true, 'officers_enabled' => true, 'enabled_officers' => [610],
        ])->assertSessionHasNoErrors();
        $this->assertTrue(app(GameModules::class)->enabled('factions'));
        $this->assertDatabaseHas('game_module_settings', ['key' => 'officer.610', 'enabled' => true]);
    }
}
