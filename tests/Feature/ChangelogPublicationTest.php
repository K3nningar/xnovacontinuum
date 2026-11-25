<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Tests the application's ChangelogPublicationTest behavior.
 */

namespace Tests\Feature;

use App\Models\Universe;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChangelogPublicationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_game_shares_the_current_version_even_when_the_changelog_is_unpublished(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player)->get(route('dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('gameVersion', '0.2.0-alpha'));
        $this->get(route('changelog'))->assertInertia(fn (Assert $page) => $page->has('releases', 0));
    }

    public function test_alpha_six_changelog_covers_recent_game_features_and_fixes(): void
    {
        DB::table('changelog_release_states')->insert([
            'version' => '0.1.1-alpha',
            'published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $operator = User::factory()->create(['admin_level' => 3]);

        $expectedKeys = [
            'versionBumpAlpha6', 'celestialNameCatalog', 'planetMoonResourceIsolation',
            'lunarBuildingManagement', 'moonRenaming', 'colonizationNames', 'lunarConstructionQueue',
            'legacyUniverseNameCatalog', 'moonStockReads', 'serverMessages', 'multiUniverseAccounts', 'playerProfileSecurity',
            'commanderAvatar', 'adminUniverseManagement', 'welcomeGamePreview', 'totpLoginChallenge',
            'avatarPublicUrls', 'universeSelectionRateLimit',
        ];

        $this->actingAs($operator)->get(route('admin.changelog'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('releases.0.version', '0.2.0-alpha')
            ->where('releases.1.version', '0.1.1-alpha')
            ->where('releases.1.entries', function (Collection $entries) use ($expectedKeys): bool {
                return $entries->pluck('key')->sort()->values()->all() === collect($expectedKeys)->sort()->values()->all();
            })
            ->where('releases.2.version', '0.1.0-alpha.5')
            ->where('releases.2.entries.0.key', 'moonLogistics'));
    }

    public function test_current_release_can_be_published_without_changing_the_previous_release(): void
    {
        $operator = User::factory()->create(['admin_level' => 3]);

        $this->actingAs($operator)->patch(route('admin.changelog.update'), [
            'version' => '0.2.0-alpha', 'published' => true,
        ])->assertRedirect(route('admin.changelog'));

        $this->get(route('changelog'))->assertInertia(fn (Assert $page) => $page
            ->has('releases', 1)
            ->where('releases.0.version', '0.2.0-alpha')
            ->where('releases.0.entries.0.key', 'factionsPerUniverse'));
        $this->assertDatabaseMissing('changelog_release_states', ['version' => '0.1.1-alpha']);
    }

    public function test_unpublished_versions_are_hidden_from_public_pages_but_visible_to_administrators(): void
    {
        $operator = User::factory()->create(['admin_level' => 3]);

        $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('releases', 0));
        $this->get(route('changelog'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('administration', false)->has('releases', 0));
        $this->actingAs($operator)->get(route('admin.changelog'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('administration', true)
                ->where('releases.0.version', '0.2.0-alpha')
                ->where('releases.0.published', false)
                ->where('releases.1.version', '0.1.1-alpha')
                ->where('releases.1.published', false)
                ->has('releases.0.entries'));
    }

    public function test_an_unpublished_newer_version_does_not_hide_the_previous_public_version(): void
    {
        DB::table('changelog_release_states')->insert([
            'version' => '0.1.0-alpha.1',
            'published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $operator = User::factory()->create(['admin_level' => 3]);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->has('releases', 1)->where('releases.0.version', '0.1.0-alpha.1'));
        $this->get(route('changelog'))->assertInertia(fn (Assert $page) => $page
            ->has('releases', 1)->where('releases.0.version', '0.1.0-alpha.1'));
        $this->actingAs($operator)->get(route('admin.changelog'))->assertInertia(fn (Assert $page) => $page
            ->where('releases.0.version', '0.2.0-alpha')
            ->where('releases.0.published', false)
            ->where('releases.1.version', '0.1.1-alpha')
            ->where('releases.1.published', false)
            ->where('releases.2.version', '0.1.0-alpha.5')
            ->where('releases.2.published', false)
            ->where('releases.3.version', '0.1.0-alpha.4')
            ->where('releases.3.published', false)
            ->where('releases.4.version', '0.1.0-alpha.3')
            ->where('releases.4.published', false)
            ->where('releases.5.version', '0.1.0-alpha.2')
            ->where('releases.5.published', false)
            ->where('releases.6.version', '0.1.0-alpha.1')
            ->where('releases.6.published', true));
    }

    public function test_an_administrator_can_publish_and_hide_a_version(): void
    {
        $operator = User::factory()->create(['admin_level' => 3]);
        $version = '0.1.0-alpha.2';

        $this->actingAs($operator)->patch(route('admin.changelog.update'), [
            'version' => $version, 'published' => true,
        ])->assertRedirect(route('admin.changelog'));

        $this->assertDatabaseHas('changelog_release_states', ['version' => $version, 'published' => true]);
        $this->assertDatabaseHas('admin_audit_logs', ['actor_id' => $operator->id, 'action' => 'changelog_publication_changed']);
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('releases.0.version', $version));
        $this->get(route('changelog'))->assertInertia(fn (Assert $page) => $page->where('releases.0.version', $version));

        $this->actingAs($operator)->patch(route('admin.changelog.update'), [
            'version' => $version, 'published' => false,
        ])->assertRedirect(route('admin.changelog'));

        $this->assertDatabaseHas('changelog_release_states', ['version' => $version, 'published' => false]);
        $this->assertSame(2, DB::table('admin_audit_logs')->where('action', 'changelog_publication_changed')->count());
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('releases', 0));
        $this->get(route('changelog'))->assertInertia(fn (Assert $page) => $page->has('releases', 0));
    }

    public function test_guests_and_players_cannot_manage_the_changelog(): void
    {
        $payload = ['version' => '0.1.0-alpha.2', 'published' => true];

        $this->get(route('admin.changelog'))->assertRedirect(route('login'));
        $this->patch(route('admin.changelog.update'), $payload)->assertRedirect(route('login'));
        $player = User::factory()->create(['admin_level' => 0]);
        $this->actingAs($player)->get(route('admin.changelog'))->assertForbidden();
        $this->patch(route('admin.changelog.update'), $payload)->assertForbidden();
        $this->assertDatabaseCount('changelog_release_states', 0);
    }

    public function test_invalid_publication_requests_do_not_change_visibility(): void
    {
        $operator = User::factory()->create(['admin_level' => 3]);

        $this->actingAs($operator)->from(route('admin.changelog'))->patch(route('admin.changelog.update'), [
            'version' => 'unknown', 'published' => 'invalid',
        ])->assertRedirect(route('admin.changelog'))->assertSessionHasErrors(['version', 'published']);

        $this->assertDatabaseCount('changelog_release_states', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    #[DataProvider('manual_entry_levels')]
    public function test_administrators_and_higher_can_add_a_manual_entry(int $level): void
    {
        $moderator = User::factory()->create(['admin_level' => $level]);
        $entry = [
            'version' => '0.1.0-alpha.2',
            'date' => '2026-11-17',
            'type' => 'HOTFIX',
            'description' => 'A manually recorded fix',
        ];

        $this->actingAs($moderator)->post(route('admin.changelog.entries.store'), $entry + [
            'actor_id' => 999,
        ])->assertRedirect(route('admin.changelog'));

        $this->assertDatabaseHas('changelog_entries', $entry);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $moderator->id,
            'actor_level' => $level,
            'action' => 'changelog_entry_added',
        ]);
        $this->get(route('admin.changelog'))->assertInertia(fn (Assert $page) => $page
            ->where('releases.5.version', '0.1.0-alpha.2')
            ->where('releases.5.entries.0.text', 'A manually recorded fix')
            ->where('releases.5.entries.0.date', '2026-11-17')
            ->where('releases.5.entries.0.type', 'HOTFIX'));
    }

    public static function manual_entry_levels(): array
    {
        return ['administrator' => [3], 'founder' => [4]];
    }

    public function test_manual_entries_follow_their_versions_publication_state(): void
    {
        $moderator = User::factory()->create(['admin_level' => 3]);
        $entry = [
            'version' => '0.1.0-alpha.2',
            'date' => '2026-11-17',
            'type' => 'NEW',
            'description' => '<script>alert("not HTML")</script>',
        ];
        $this->actingAs($moderator)->post(route('admin.changelog.entries.store'), $entry)
            ->assertRedirect(route('admin.changelog'));
        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('releases', 0));
        $this->get(route('changelog'))->assertInertia(fn (Assert $page) => $page->has('releases', 0));

        $this->actingAs($moderator)->patch(route('admin.changelog.update'), [
            'version' => $entry['version'], 'published' => true,
        ])->assertRedirect(route('admin.changelog'));
        $this->post(route('logout'))->assertRedirect(route('home'));

        foreach (['home', 'changelog'] as $route) {
            $this->get(route($route))->assertInertia(fn (Assert $page) => $page
                ->where('releases.0.entries.0.text', $entry['description'])
                ->where('releases.0.entries.0.date', $entry['date']));
        }

        $this->actingAs($moderator)->patch(route('admin.changelog.update'), [
            'version' => $entry['version'], 'published' => false,
        ]);
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->get(route('changelog'))->assertInertia(fn (Assert $page) => $page->has('releases', 0));
    }

    public function test_guests_players_and_local_staff_cannot_add_manual_entries(): void
    {
        $entry = ['version' => '0.1.0-alpha.2', 'date' => '2026-11-17', 'type' => 'CHANGE', 'description' => 'Example'];

        $this->post(route('admin.changelog.entries.store'), $entry)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post(route('admin.changelog.entries.store'), $entry)->assertForbidden();
        $universe = Universe::query()->create(['name' => 'Test', 'slug' => 'test']);
        foreach ([1, 2] as $level) {
            $staff = User::factory()->create();
            DB::table('universe_staff')->insert(['commander_id' => $staff->commander_id, 'universe_id' => $universe->id, 'level' => $level, 'created_at' => now(), 'updated_at' => now()]);
            $this->actingAs($staff)
                ->post(route('admin.changelog.entries.store'), $entry)->assertForbidden();
        }

        $this->assertDatabaseCount('changelog_entries', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_entries_added_to_a_published_version_are_visible_and_ordered_by_date(): void
    {
        $moderator = User::factory()->create(['admin_level' => 3]);
        $this->actingAs($moderator)->patch(route('admin.changelog.update'), [
            'version' => '0.1.0-alpha.2', 'published' => true,
        ]);
        foreach ([
            ['date' => '2026-11-15', 'description' => 'Earlier entry'],
            ['date' => '2026-11-17', 'description' => 'Latest entry'],
        ] as $entry) {
            $this->post(route('admin.changelog.entries.store'), $entry + [
                'version' => '0.1.0-alpha.2', 'type' => 'CHANGE',
            ])->assertRedirect(route('admin.changelog'));
        }
        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->get(route('changelog'))->assertInertia(fn (Assert $page) => $page
            ->where('releases.0.entries.0.text', 'Latest entry')
            ->where('releases.0.entries.1.key', 'manualChangelog')
            ->where('releases.0.entries.6.text', 'Earlier entry'));
    }

    public function test_invalid_manual_entries_are_rejected_without_writing(): void
    {
        $moderator = User::factory()->create(['admin_level' => 3]);

        $this->actingAs($moderator)->from(route('admin.changelog'))
            ->post(route('admin.changelog.entries.store'), [
                'version' => 'unknown',
                'date' => '2026-02-30',
                'type' => 'INVALID',
                'description' => str_repeat('a', 2001),
            ])->assertRedirect(route('admin.changelog'))
            ->assertSessionHasErrors(['version', 'date', 'type', 'description']);
        $this->post(route('admin.changelog.entries.store'), [])
            ->assertSessionHasErrors(['version', 'date', 'type', 'description']);

        $this->assertDatabaseCount('changelog_entries', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }
}
