<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Handles HTTP requests for OfficerController operations.
 */

namespace App\Http\Controllers;

use App\Services\UnitProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

class OfficerController extends Controller
{
    /**
     * Applies due officer/unit production on an available planet and renders empire officer progress and queue.
     */
    public function index(Request $request, UnitProductionService $production): Response
    {
        $user = $request->user();
        $planet = $user->planets()->where('universe_id', $user->universe_id)->first();
        if ($planet) {
            $production->complete($planet);
        }
        $queue = $planet ? $production->officersQueueSnapshot($planet) : [];

        return inertia('Officers/Index', [
            'user' => $user->only('id', 'name'),
            'queue' => $queue,
            ...$production->officerCatalog($user),
        ]);
    }

    /**
     * Validates an officer code and queues recruitment on the user's first planet in the current universe.
     */
    public function store(Request $request, UnitProductionService $production): RedirectResponse
    {
        $attributes = $request->validate([
            'code' => ['required', 'integer', Rule::in(range(601, 615))],
        ]);
        $planet = $request->user()->planets()->where('universe_id', $request->user()->universe_id)->firstOrFail();
        $production->recruit($request->user(), $planet, (int) $attributes['code']);

        return to_route('officers.index')->with('success', 'Officier recruté avec succès.');
    }
}
