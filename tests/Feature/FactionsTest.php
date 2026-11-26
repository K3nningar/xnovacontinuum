<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Fleet;
use App\Models\Planet;
use App\Models\Universe;
use App\Models\User;
use App\Services\CombatSimulatorService;
use App\Services\ExplorationMissions;
use App\Services\FactionCatalog;
use App\Services\FactionDiplomacy;
use App\Services\GalaxyMap;
use App\Services\PlanetEconomy;
use App\Services\UnitProductionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FactionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_founder_configures_factions_and_symmetric_universe_scoped_diplomacy(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $founder = User::factory()->create(['admin_level' => 4]);
        $ordinary = User::factory()->create();
        $payload = ['key' => 'red', 'name' => 'Rouges'];
        $this->actingAs($ordinary)->post(route('admin.factions.store', $universe), $payload)->assertForbidden();
        $this->actingAs($founder)->post(route('admin.factions.store', $universe), $payload)->assertSessionHasNoErrors();
        $this->get(route('admin.factions.index', $universe))->assertInertia(fn (Assert $page) => $page->component('Admin/Factions')->has('factions', 2));
        $this->post(route('admin.factions.store', $universe), ['key' => 'blue', 'name' => 'Bleus'])->assertSessionHasNoErrors();
        $this->post(route('admin.factions.diplomacy', $universe), ['first' => 'red', 'second' => 'blue', 'status' => 'allied'])->assertSessionHasNoErrors();
        $red = User::factory()->create(['universe_id' => $universe->id, 'faction_key' => 'red']);
        $blue = User::factory()->create(['universe_id' => $universe->id, 'faction_key' => 'blue']);
        $this->assertSame('allied', app(FactionDiplomacy::class)->relation($blue, $red));
        $this->post(route('admin.factions.diplomacy', $universe), ['first' => 'blue', 'second' => 'red', 'status' => 'war'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('faction_diplomacy', 1);
        $this->assertSame('war', app(FactionDiplomacy::class)->relation($red, $blue));
        $this->post(route('admin.factions.diplomacy', $universe), ['first' => 'red', 'second' => 'unknown', 'status' => 'war'])->assertSessionHasErrors('first');
    }

    public function test_players_can_open_the_dynamic_faction_directory(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        Faction::create(['universe_id' => $universe->id, 'key' => 'red', 'name' => 'Rouges', 'description' => 'Faction offensive.', 'settings' => [
            'statistics' => ['attack' => 1.5],
            'ships' => ['202' => ['name' => 'Transport rouge', 'image' => '/images/factions/red.webp']],
        ]]);
        $player = User::factory()->create(['universe_id' => $universe->id, 'faction_key' => 'red']);

        $this->actingAs($player)->get(route('factions.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Factions/Index')->has('factions', 2)->where('factions.1.name', 'Rouges')
            ->where('factions.1.statistics.attack', 1.5)->where('factions.1.exclusiveElements.0.name', 'Transport rouge'));
    }

    public function test_founder_can_upload_a_faction_logo(): void
    {
        Storage::fake('public');
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $founder = User::factory()->create(['admin_level' => 4]);

        $this->actingAs($founder)->post(route('admin.factions.store', $universe), [
            'key' => 'red', 'name' => 'Rouges', 'image_file' => UploadedFile::fake()->image('banner.png'),
        ])->assertSessionHasNoErrors();

        $faction = Faction::query()->where('universe_id', $universe->id)->where('key', 'red')->firstOrFail();
        $this->assertStringStartsWith('/storage/factions/'.$universe->id.'/red-', $faction->image);
        $this->assertStringEndsWith('.webp', $faction->image);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', parse_url($faction->image, PHP_URL_PATH)));
    }

    public function test_existing_absolute_faction_image_url_can_be_saved(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $faction = Faction::create(['universe_id' => $universe->id, 'key' => 'red', 'name' => 'Rouges', 'image' => 'http://localhost:8000/storage/factions/1/red.webp']);
        $founder = User::factory()->create(['admin_level' => 4]);

        $this->actingAs($founder)->patch(route('admin.factions.update', [$universe, $faction]), [
            'name' => 'Rouges', 'description' => '', 'image' => $faction->image,
            'settings' => json_encode(['statistics' => ['attack' => 1, 'shield' => 1, 'hull' => 1]]),
        ])->assertSessionHasNoErrors();
    }

    public function test_faction_administration_exposes_the_complete_game_catalog(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $founder = User::factory()->create(['admin_level' => 4]);
        $buildings = app(PlanetEconomy::class)->administrativeBuildings();
        $units = app(UnitProductionService::class)->administrativeUnits();

        $this->actingAs($founder)->get(route('admin.factions.index', $universe))->assertInertia(fn (Assert $page) => $page
            ->has('catalog.buildings', count($buildings))->has('catalog.ships', count($units['ships']))
            ->has('catalog.defences', count($units['defences']))
            ->where('catalog.buildings.0.key', 'metal')->where('catalog.ships.0.key', '202')
            ->where('catalog.defences.0.key', '401'));
    }

    public function test_join_requires_a_faction_from_the_selected_universe(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $other = Universe::create(['name' => 'Other', 'slug' => 'other']);
        Faction::create(['universe_id' => $other->id, 'key' => 'foreign', 'name' => 'Foreign']);
        Faction::create(['universe_id' => $universe->id, 'key' => 'red', 'name' => 'Red']);
        $account = User::factory()->create();
        $this->actingAs($account)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('availableUniverses.0.factions', 2));
        $this->actingAs($account)->post(route('universe.join'), ['universe_id' => $universe->id, 'name' => 'Home', 'faction_key' => 'foreign'])->assertSessionHasErrors('faction_key');
        $this->post(route('universe.join'), ['universe_id' => $universe->id, 'name' => 'Home', 'faction_key' => 'red'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['commander_id' => $account->commander_id, 'universe_id' => $universe->id, 'faction_key' => 'red']);
    }

    public function test_hostile_orders_are_blocked_for_allies_but_allowed_for_neutral_factions(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        Faction::create(['universe_id' => $universe->id, 'key' => 'blue', 'name' => 'Bleus']);
        $attacker = User::factory()->create(['universe_id' => $universe->id, 'faction_key' => 'red']);
        $recipient = User::factory()->create(['universe_id' => $universe->id, 'faction_key' => 'blue']);
        $home = $this->planet($attacker, 8);
        $target = $this->planet($recipient, 9);
        $home->forceFill(['unit_202' => 3, 'deuterium' => 100000])->save();
        $attacker->forceFill(['spy_technology_level' => 1])->save();
        $target->forceFill(['alliance_depot_level' => 1])->save();
        DB::table('faction_diplomacy')->insert(['universe_id' => $universe->id, 'first_faction' => 'blue', 'second_faction' => 'red', 'status' => 'allied']);
        $slot = app(GalaxyMap::class)->system($universe, $attacker, 1, 1, 8)['slots'][7]['planet'];
        $this->assertFalse($slot['hostileAllowed']);
        $this->assertTrue($slot['alliedDepot']);
        $this->assertSame('Bleus', $slot['factionName']);
        $payload = ['mission' => 'attack', 'galaxy' => 1, 'system' => 1, 'position' => 9, 'ships' => [202 => 1]];
        $this->actingAs($attacker)->post(route('planet.missions.store', $home), $payload)->assertSessionHasErrors('target');
        $this->assertDatabaseCount('fleets', 0);
        $this->post(route('planet.missions.store', $home), [...$payload, 'mission' => 'station'])->assertSessionHasNoErrors();
        DB::table('faction_diplomacy')->update(['status' => 'neutral']);
        $this->post(route('planet.missions.store', $home), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('fleets', 2);
        DB::table('faction_diplomacy')->update(['status' => 'allied']);
        $fleet = Fleet::query()->where('mission', 'attack')->firstOrFail();
        $this->travelTo($fleet->arrives_at->addSecond());
        app(ExplorationMissions::class)->processDue();
        $this->assertNotNull($fleet->fresh()->combat_report);
    }

    public function test_unavailable_buildings_and_units_are_rejected_server_side(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        Faction::create(['universe_id' => $universe->id, 'key' => 'red', 'name' => 'Red', 'settings' => [
            'buildings' => ['metal' => ['available' => false]], 'ships' => ['202' => ['available' => false]], 'defences' => ['401' => ['available' => false]],
        ]]);
        $player = User::factory()->create(['universe_id' => $universe->id, 'faction_key' => 'red']);
        $planet = $this->planet($player, 8);
        $this->actingAs($player)->post(route('planet.buildings.upgrade', $planet), ['building' => 'metal'])->assertSessionHasErrors('building');
        $this->post(route('planet.ships.store', $planet), ['code' => 202, 'quantity' => 1])->assertSessionHasErrors('unit');
        $this->post(route('planet.defences.store', $planet), ['code' => 401, 'quantity' => 1])->assertSessionHasErrors('unit');
        $this->assertDatabaseCount('planet_building_queues', 0);
        $this->assertDatabaseCount('planet_unit_queues', 0);
    }

    public function test_founder_can_update_validated_faction_settings(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $other = Universe::create(['name' => 'Other', 'slug' => 'other']);
        $faction = Faction::create(['universe_id' => $universe->id, 'key' => 'red', 'name' => 'Red']);
        $founder = User::factory()->create(['admin_level' => 4]);
        $payload = ['name' => 'Red', 'settings' => json_encode(['ships' => ['202' => ['available' => false]], 'statistics' => ['attack' => 1.5]])];
        $this->actingAs($founder)->patch(route('admin.factions.update', [$other, $faction]), $payload)->assertNotFound();
        $this->patch(route('admin.factions.update', [$universe, $faction]), $payload)->assertSessionHasNoErrors();
        $this->assertFalse($faction->fresh()->settings['ships']['202']['available']);
        $this->assertSame(1.5, $faction->fresh()->settings['statistics']['attack']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'faction_updated', 'universe_id' => $universe->id]);
        $this->patch(route('admin.factions.update', [$universe, $faction]), ['name' => 'Red', 'settings' => '{"statistics":{"attack":100}}'])->assertSessionHasErrors();
        $this->patch(route('admin.factions.update', [$universe, $faction]), ['name' => 'Red', 'settings' => '{}'])->assertSessionHasNoErrors();
    }

    public function test_template_faction_preserves_the_original_alliance_rules(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        $first = User::factory()->create(['universe_id' => $universe->id]);
        $second = User::factory()->create(['universe_id' => $universe->id]);
        $diplomacy = app(FactionDiplomacy::class);
        $this->assertSame('legacy', $diplomacy->relation($first, $second));
        $this->assertFalse($diplomacy->canStation($first, $second));
        $this->assertSame('0', $first->fresh()->faction_key);
    }

    public function test_founder_can_configure_template_without_creating_duplicates_or_affecting_other_universes(): void
    {
        $universe = Universe::create(['name' => 'First', 'slug' => 'first']);
        $other = Universe::create(['name' => 'Second', 'slug' => 'second']);
        Faction::create(['universe_id' => $universe->id, 'key' => 'red', 'name' => 'Rouges']);
        $founder = User::factory()->create(['admin_level' => 4]);
        $ordinary = User::factory()->create();
        $route = route('admin.factions.template.update', $universe);
        $payload = ['name' => 'Terriens', 'description' => 'La Terre', 'image' => '/images/earth.webp', 'settings' => json_encode([
            'buildings' => ['metal' => ['available' => false]],
            'ships' => ['202' => ['name' => 'Transport terrien', 'available' => false]],
            'defences' => ['401' => ['available' => false]],
            'statistics' => ['attack' => 1.5],
        ])];

        $this->actingAs($ordinary)->patch($route, $payload)->assertForbidden();
        $this->actingAs($founder)->patch($route, [...$payload, 'settings' => '{"statistics":{"attack":100}}'])->assertSessionHasErrors();
        $this->assertDatabaseMissing('factions', ['universe_id' => $universe->id, 'key' => '0']);
        $this->patch($route, $payload)->assertSessionHasNoErrors();
        $this->patch($route, $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, Faction::query()->where('universe_id', $universe->id)->where('key', '0')->count());
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'faction_updated', 'universe_id' => $universe->id]);
        $this->get(route('admin.factions.index', $universe))->assertInertia(fn (Assert $page) => $page
            ->has('factions', 2)->where('factions.0.key', '0')->where('factions.0.name', 'Terriens')
            ->where('factions.0.settings.ships.202.name', 'Transport terrien'));

        $player = User::factory()->create(['universe_id' => $universe->id]);
        $otherPlayer = User::factory()->create(['universe_id' => $other->id]);
        $planet = $this->planet($player, 8);
        $this->assertSame([], app(FactionCatalog::class)->settings($otherPlayer));
        $this->assertSame(1.5, app(FactionCatalog::class)->settings($player)['statistics']['attack']);
        $this->actingAs($player)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.faction.name', 'Terriens')->where('faction.name', 'Terriens')
            ->where('factionSettings.ships.202.name', 'Transport terrien'));
        $this->post(route('planet.buildings.upgrade', $planet), ['building' => 'metal'])->assertSessionHasErrors('building');
        $this->post(route('planet.ships.store', $planet), ['code' => 202, 'quantity' => 1])->assertSessionHasErrors('unit');
        $this->post(route('planet.defences.store', $planet), ['code' => 401, 'quantity' => 1])->assertSessionHasErrors('unit');

        $account = User::factory()->create(['universe_id' => null]);
        $this->actingAs($account)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->has('availableUniverses.0.factions', 2)->where('availableUniverses.0.factions.0.name', 'Terriens')
            ->where('availableUniverses.1.factions.0.name', 'Faction 0'));
    }

    public function test_template_diplomacy_controls_attacks_and_stationing_with_other_factions(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        Faction::create(['universe_id' => $universe->id, 'key' => 'red', 'name' => 'Rouges']);
        $founder = User::factory()->create(['admin_level' => 4]);
        $template = User::factory()->create(['universe_id' => $universe->id]);
        $red = User::factory()->create(['universe_id' => $universe->id, 'faction_key' => 'red']);
        $home = $this->planet($template, 8);
        $target = $this->planet($red, 9);
        $home->forceFill(['unit_202' => 3, 'deuterium' => 100000, 'metal' => 100000, 'crystal' => 100000])->save();
        $target->forceFill(['alliance_depot_level' => 1])->save();
        $payload = ['mission' => 'attack', 'galaxy' => 1, 'system' => 1, 'position' => 9, 'ships' => [202 => 1]];

        $this->actingAs($founder)->post(route('admin.factions.diplomacy', $universe), ['first' => '0', 'second' => 'red', 'status' => 'allied'])->assertSessionHasNoErrors();
        $this->assertSame('allied', app(FactionDiplomacy::class)->relation($template, $red));
        $this->actingAs($template)->post(route('planet.missions.store', $home), $payload)->assertSessionHasErrors('target');
        $this->post(route('planet.missions.store', $home), [...$payload, 'mission' => 'station'])->assertSessionHasNoErrors();
        $this->post(route('planet.buildings.upgrade', $home), ['building' => 'alliance_depot'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('planet_building_queues', ['planet_id' => $home->id, 'building_key' => 'alliance_depot']);
        $this->actingAs($founder)->post(route('admin.factions.diplomacy', $universe), ['first' => '0', 'second' => 'red', 'status' => 'neutral'])->assertSessionHasNoErrors();
        $this->actingAs($template)->post(route('planet.missions.store', $home), $payload)->assertSessionHasNoErrors();
    }

    public function test_active_faction_is_shared_on_game_pages_for_each_universe_profile(): void
    {
        $universe = Universe::create(['name' => 'First', 'slug' => 'first']);
        $other = Universe::create(['name' => 'Second', 'slug' => 'second']);
        Faction::create(['universe_id' => $universe->id, 'key' => 'red', 'name' => 'Rouges']);
        Faction::create(['universe_id' => $other->id, 'key' => 'red', 'name' => 'Autres']);
        $account = User::factory()->create(['universe_id' => null]);
        $player = User::factory()->create(['universe_id' => $universe->id, 'faction_key' => 'red', 'commander_id' => $account->commander_id]);
        $template = User::factory()->create(['universe_id' => $other->id, 'faction_key' => '0']);

        $this->actingAs($player)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.faction.key', 'red')->where('auth.user.faction.name', 'Rouges'));
        $this->get(route('profile.show'))->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.faction.name', 'Rouges'));
        $this->actingAs($template)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.faction.key', '0')->where('auth.user.faction.name', 'Faction 0'));
        $this->actingAs($account)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.faction', null));
    }

    public function test_combat_uses_configured_faction_statistics(): void
    {
        $base = ['fleet' => [204 => 1], 'technologies' => ['weapons' => 0, 'shields' => 0, 'armour' => 0]];
        $result = app(CombatSimulatorService::class)->simulate([...$base, 'factionStats' => ['attack' => 10, 'hull' => 10]], $base, 123);
        $this->assertSame('attacker', $result['winner']);
    }

    public function test_faction_statistics_apply_to_real_fleet_combat(): void
    {
        $universe = Universe::create(['name' => 'Test', 'slug' => 'test']);
        Faction::create(['universe_id' => $universe->id, 'key' => 'red', 'name' => 'Red', 'settings' => ['statistics' => ['attack' => 10, 'hull' => 10]]]);
        $attacker = User::factory()->create(['universe_id' => $universe->id, 'faction_key' => 'red']);
        $defender = User::factory()->create(['universe_id' => $universe->id, 'faction_key' => 'blue']);
        $home = $this->planet($attacker, 8);
        $target = $this->planet($defender, 9);
        $home->forceFill(['unit_204' => 1, 'deuterium' => 100000])->save();
        $target->forceFill(['unit_204' => 1])->save();
        $this->actingAs($attacker)->post(route('planet.missions.store', $home), ['mission' => 'attack', 'galaxy' => 1, 'system' => 1, 'position' => 9, 'ships' => [204 => 1]])->assertSessionHasNoErrors();
        $fleet = Fleet::firstOrFail();
        $this->travelTo($fleet->arrives_at->addSecond());
        app(ExplorationMissions::class)->processDue();
        $this->assertSame('attacker', $fleet->fresh()->combat_report['winner']);
    }

    public function test_founder_can_configure_a_closed_universe_before_opening_it(): void
    {
        $founder = User::factory()->create(['admin_level' => 4]);
        $this->actingAs($founder)->post(route('admin.universes.store'), [
            'name' => 'Closed', 'slug' => 'closed', 'galaxies' => 1, 'systems_per_galaxy' => 1,
            'economy_speed' => 1, 'fleet_speed' => 1, 'status' => 'closed',
        ])->assertSessionHasNoErrors();
        $universe = Universe::query()->where('slug', 'closed')->firstOrFail();
        $this->assertSame('closed', $universe->status);
        $this->post(route('admin.factions.store', $universe), ['key' => 'red', 'name' => 'Red'])->assertSessionHasNoErrors();
        $this->post(route('admin.universes.open', $universe))->assertSessionHasNoErrors();
        $this->assertSame('active', $universe->fresh()->status);
    }

    private function planet(User $user, int $position): Planet
    {
        $planet = new Planet(['name' => 'Home']);
        $planet->forceFill(['user_id' => $user->id, 'universe_id' => $user->universe_id, 'galaxy' => 1, 'system' => 1,
            'position' => $position, 'resources_updated_at' => now()])->save();

        return $planet;
    }
}
