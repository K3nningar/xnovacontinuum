<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
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
                ->where('gameVersion', '0.1.0-alpha.1'));
        $this->get(route('changelog'))->assertInertia(fn (Assert $page) => $page->has('releases', 0));
    }

    public function test_unpublished_versions_are_hidden_from_public_pages_but_visible_to_operators(): void
    {
        $operator = User::factory()->create(['admin_level' => 1]);

        $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('releases', 0));
        $this->get(route('changelog'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('administration', false)->has('releases', 0));
        $this->actingAs($operator)->get(route('admin.changelog'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('administration', true)
                ->where('releases.0.version', '0.1.0-alpha.1')
                ->where('releases.0.published', false)
                ->has('releases.0.entries'));
    }

    public function test_an_operator_can_publish_and_hide_a_version(): void
    {
        $operator = User::factory()->create(['admin_level' => 1]);
        $version = '0.1.0-alpha.1';

        $this->actingAs($operator)->patch(route('admin.changelog.update'), [
            'version' => $version, 'published' => true,
        ])->assertRedirect(route('admin.changelog'));

        $this->assertDatabaseHas('changelog_release_states', ['version' => $version, 'published' => true]);
        $this->assertDatabaseHas('admin_audit_logs', ['actor_id' => $operator->id, 'action' => 'changelog_publication_changed']);
        auth()->logout();
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('releases.0.version', $version));
        $this->get(route('changelog'))->assertInertia(fn (Assert $page) => $page->where('releases.0.version', $version));

        $this->actingAs($operator)->patch(route('admin.changelog.update'), [
            'version' => $version, 'published' => false,
        ])->assertRedirect(route('admin.changelog'));

        $this->assertDatabaseHas('changelog_release_states', ['version' => $version, 'published' => false]);
        $this->assertSame(2, DB::table('admin_audit_logs')->where('action', 'changelog_publication_changed')->count());
        auth()->logout();
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('releases', 0));
        $this->get(route('changelog'))->assertInertia(fn (Assert $page) => $page->has('releases', 0));
    }

    public function test_guests_and_players_cannot_manage_the_changelog(): void
    {
        $payload = ['version' => '0.1.0-alpha.1', 'published' => true];

        $this->get(route('admin.changelog'))->assertRedirect(route('login'));
        $this->patch(route('admin.changelog.update'), $payload)->assertRedirect(route('login'));
        $player = User::factory()->create(['admin_level' => 0]);
        $this->actingAs($player)->get(route('admin.changelog'))->assertForbidden();
        $this->patch(route('admin.changelog.update'), $payload)->assertForbidden();
        $this->assertDatabaseCount('changelog_release_states', 0);
    }

    public function test_invalid_publication_requests_do_not_change_visibility(): void
    {
        $operator = User::factory()->create(['admin_level' => 1]);

        $this->actingAs($operator)->from(route('admin.changelog'))->patch(route('admin.changelog.update'), [
            'version' => 'unknown', 'published' => 'invalid',
        ])->assertRedirect(route('admin.changelog'))->assertSessionHasErrors(['version', 'published']);

        $this->assertDatabaseCount('changelog_release_states', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }
}
