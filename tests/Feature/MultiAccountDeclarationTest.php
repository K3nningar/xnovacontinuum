<?php

namespace Tests\Feature;

use App\Models\Universe;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MultiAccountDeclarationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_access_requires_authentication_and_admin_listing_requires_operator_level(): void
    {
        $this->get(route('multi-accounts.index'))->assertRedirect(route('login'));
        $this->post(route('multi-accounts.store'))->assertRedirect(route('login'));
        $this->get(route('admin.declarations'))->assertRedirect(route('login'));

        $player = User::factory()->create();
        $this->actingAs($player)->get(route('admin.declarations'))->assertForbidden();

        $operator = User::factory()->create();
        $operator->forceFill(['admin_level' => 1])->save();
        $this->actingAs($operator)->get(route('admin.declarations'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Declarations'));
    }

    public function test_player_can_declare_up_to_three_others_and_cannot_forge_the_declarant(): void
    {
        $universe = Universe::query()->create(['name' => 'Test', 'slug' => 'test']);
        $declarant = $this->playerIn($universe);
        $players = User::factory()->count(3)->create(['universe_id' => $universe->id]);
        $reason = '<script>alert(1)</script>';

        $this->actingAs($declarant)->post(route('multi-accounts.store'), [
            'player_ids' => $players->modelKeys(),
            'reason' => $reason,
            'declarant_id' => $players[0]->id,
            'universe_id' => 999,
        ])->assertRedirect(route('multi-accounts.index'))->assertSessionHasNoErrors();

        $declaration = DB::table('multi_account_declarations')->first();
        $this->assertSame($declarant->id, $declaration->declarant_id);
        $this->assertSame($universe->id, $declaration->universe_id);
        $this->assertSame($reason, $declaration->reason);
        $this->assertDatabaseCount('multi_account_declaration_players', 3);
        foreach ($players as $player) {
            $this->assertDatabaseHas('multi_account_declaration_players', [
                'declaration_id' => $declaration->id, 'player_id' => $player->id, 'universe_id' => $universe->id,
            ]);
        }

        $this->actingAs($declarant)->get(route('multi-accounts.index'))
            ->assertOk()->assertDontSee($reason, false)
            ->assertInertia(fn (Assert $page) => $page->component('MultiAccounts/Index')
                ->where('canDeclare', true)->has('declarations.data', 1)
                ->where('declarations.data.0.reason', $reason)
                ->has('declarations.data.0.players', 3));
        $this->actingAs($players[0])->get(route('multi-accounts.index'))
            ->assertInertia(fn (Assert $page) => $page->has('declarations.data', 0));
        $operator = User::factory()->create();
        $operator->forceFill(['admin_level' => 1])->save();
        $this->actingAs($operator)->get(route('admin.declarations'))
            ->assertInertia(fn (Assert $page) => $page->has('declarations.data', 1)
                ->where('declarations.data.0.declarant_id', $declarant->id));
    }

    #[DataProvider('invalidDeclarations')]
    public function test_invalid_declarations_are_rejected(string $case, string $error): void
    {
        $universe = Universe::query()->create(['name' => 'Test', 'slug' => 'test']);
        $otherUniverse = Universe::query()->create(['name' => 'Other', 'slug' => 'other']);
        $user = $this->playerIn($universe);
        $other = $this->playerIn($universe);
        $outsider = $this->playerIn($otherUniverse);
        $data = ['player_ids' => [$other->id], 'reason' => 'Shared connection'];
        switch ($case) {
            case 'self': $data['player_ids'] = [$user->id];
                break;
            case 'duplicate': $data['player_ids'] = [$other->id, (string) $other->id];
                break;
            case 'missing player': $data['player_ids'] = [999999];
                break;
            case 'other universe': $data['player_ids'] = [$outsider->id];
                break;
            case 'too many': $data['player_ids'] = [1, 2, 3, 4];
                break;
            case 'no players': $data['player_ids'] = [];
                break;
            case 'associative': $data['player_ids'] = ['arbitrary' => $other->id];
                break;
            case 'long reason': $data['reason'] = str_repeat('x', 501);
                break;
            case 'empty reason': $data['reason'] = '';
                break;
        }
        $this->actingAs($user)->post(route('multi-accounts.store'), $data)->assertSessionHasErrors($error);
        $this->assertDatabaseCount('multi_account_declarations', 0);
    }

    public static function invalidDeclarations(): array
    {
        return [
            ['self', 'player_ids.0'], ['duplicate', 'player_ids.0'],
            ['missing player', 'player_ids.0'], ['other universe', 'player_ids.0'],
            ['too many', 'player_ids'], ['no players', 'player_ids'],
            ['associative', 'player_ids'], ['long reason', 'reason'], ['empty reason', 'reason'],
        ];
    }

    public function test_player_without_a_universe_cannot_declare_accounts(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->get(route('multi-accounts.index'))
            ->assertInertia(fn (Assert $page) => $page->where('canDeclare', false));
        $this->post(route('multi-accounts.store'), ['player_ids' => [$other->id], 'reason' => 'Reason'])
            ->assertSessionHasErrors('player_ids');
        $this->assertDatabaseCount('multi_account_declarations', 0);
    }

    public function test_declaration_list_is_paginated_and_scoped_to_the_current_player(): void
    {
        $universe = Universe::query()->create(['name' => 'Test', 'slug' => 'test']);
        $user = $this->playerIn($universe);
        $other = $this->playerIn($universe);
        for ($i = 1; $i <= 21; $i++) {
            DB::table('multi_account_declarations')->insert([
                'universe_id' => $universe->id, 'declarant_id' => $user->id,
                'reason' => "Declaration {$i}", 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('multi_account_declarations')->insert([
            'universe_id' => $universe->id, 'declarant_id' => $other->id,
            'reason' => 'Private', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($user)->get(route('multi-accounts.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->where('declarations.total', 21)
                ->has('declarations.data', 1)->where('declarations.data.0.reason', 'Declaration 1'));
    }

    public function test_declaration_submissions_are_rate_limited_per_player(): void
    {
        $universe = Universe::query()->create(['name' => 'Test', 'slug' => 'test']);
        $user = $this->playerIn($universe);
        $other = $this->playerIn($universe);
        $this->actingAs($user);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('multi-accounts.store'), ['player_ids' => [$other->id], 'reason' => 'Reason'])->assertRedirect();
        }
        $this->post(route('multi-accounts.store'), ['player_ids' => [$other->id], 'reason' => 'Reason'])->assertTooManyRequests();
        $this->assertDatabaseCount('multi_account_declarations', 5);
    }

    public function test_database_rejects_participants_from_another_universe(): void
    {
        $universe = Universe::query()->create(['name' => 'Test', 'slug' => 'test']);
        $otherUniverse = Universe::query()->create(['name' => 'Other', 'slug' => 'other']);
        $user = $this->playerIn($universe);
        $outsider = $this->playerIn($otherUniverse);
        $declaration = DB::table('multi_account_declarations')->insertGetId([
            'universe_id' => $universe->id, 'declarant_id' => $user->id,
            'reason' => 'Reason', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('multi_account_declaration_players')->insert([
            'declaration_id' => $declaration, 'player_id' => $outsider->id, 'universe_id' => $universe->id,
        ]);
    }

    private function playerIn(Universe $universe): User
    {
        return User::factory()->create(['universe_id' => $universe->id]);
    }
}
