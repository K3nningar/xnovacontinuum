<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Handles HTTP requests for MultiAccountDeclarationController operations.
 */

namespace App\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class MultiAccountDeclarationController extends Controller
{
    /**
     * Shows the authenticated user's declarations and whether their account has joined a universe.
     */
    public function index(Request $request): Response
    {
        return inertia('MultiAccounts/Index', [
            'canDeclare' => $request->user()->universe_id !== null,
            'declarations' => $this->declarations($request->user()->id),
        ]);
    }

    /**
     * Presents the paginated declaration list to the admin route's authorized caller.
     */
    public function administration(): Response
    {
        return inertia('Admin/Declarations', ['declarations' => $this->declarations()]);
    }

    /**
     * Creates a declaration for one to three distinct other players in the declarant's current universe.
     *
     * Requires the declarant to have joined a universe; the declaration and all player links are inserted
     * together in a retryable transaction.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->universe_id === null) {
            throw ValidationException::withMessages(['player_ids' => 'Vous devez rejoindre un univers avant de faire une déclaration.']);
        }

        $data = $request->validate([
            'player_ids' => ['required', 'array', 'list', 'min:1', 'max:3'],
            'player_ids.*' => [
                'required', 'integer', 'min:1', 'distinct',
                Rule::notIn([$user->id]),
                Rule::exists('users', 'id')->where('universe_id', $user->universe_id),
            ],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($user, $data): void {
            $id = DB::table('multi_account_declarations')->insertGetId([
                'universe_id' => $user->universe_id,
                'declarant_id' => $user->id,
                'reason' => $data['reason'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('multi_account_declaration_players')->insert(array_map(fn ($playerId) => [
                'declaration_id' => $id,
                'player_id' => (int) $playerId,
                'universe_id' => $user->universe_id,
            ], $data['player_ids']));
        }, 3);

        return redirect()->route('multi-accounts.index')->with('success', 'Déclaration enregistrée.');
    }

    /**
     * Paginates declarations and attaches their linked players without issuing a query per declaration.
     *
     * A null declarant id returns all declarations for administration; a supplied id restricts results to its owner.
     */
    private function declarations(?int $declarantId = null): LengthAwarePaginator
    {
        $declarations = DB::table('multi_account_declarations as declarations')
            ->join('users as declarants', 'declarants.id', '=', 'declarations.declarant_id')
            ->when($declarantId !== null, fn ($query) => $query->where('declarations.declarant_id', $declarantId))
            ->orderByDesc('declarations.id')
            ->paginate(20, ['declarations.id', 'declarations.universe_id', 'declarations.declarant_id',
                'declarations.reason', 'declarations.created_at', 'declarants.name as declarant_name']);

        $players = DB::table('multi_account_declaration_players as links')
            ->join('users', 'users.id', '=', 'links.player_id')
            ->whereIn('links.declaration_id', $declarations->getCollection()->pluck('id'))
            ->orderBy('users.id')->get(['links.declaration_id', 'users.id', 'users.name'])
            ->groupBy('declaration_id');
        $declarations->getCollection()->transform(function ($declaration) use ($players) {
            $declaration->players = $players->get($declaration->id, collect())->map(
                fn ($player) => ['id' => $player->id, 'name' => $player->name],
            )->values();

            return $declaration;
        });

        return $declarations;
    }
}
