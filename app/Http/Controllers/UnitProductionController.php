<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Handles HTTP requests for UnitProductionController operations.
 */

namespace App\Http\Controllers;

use App\Models\Planet;
use App\Services\UnitProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UnitProductionController extends Controller
{
    /**
     * Dispatches a validated ship-production request to the shared queueing workflow.
     */
    public function storeShip(Request $request, Planet $planet, UnitProductionService $production): RedirectResponse
    {
        return $this->store($request, $planet, 'ships', $production);
    }

    /**
     * Dispatches a validated defence-production request to the shared queueing workflow.
     */
    public function storeDefence(Request $request, Planet $planet, UnitProductionService $production): RedirectResponse
    {
        return $this->store($request, $planet, 'defences', $production);
    }

    /**
     * Validates unit code/quantity, scopes the route planet to its owner and universe, then queues production.
     *
     * The production service repeats eligibility, stock, requirement, and queue checks under locks.
     *
     * @param  'ships'|'defences'  $type
     */
    private function store(Request $request, Planet $planet, string $type, UnitProductionService $production): RedirectResponse
    {
        $attributes = $request->validate([
            'code' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);
        $ownedPlanet = $request->user()->planets()->where('universe_id', $request->user()->universe_id)
            ->whereKey($planet->getKey())->firstOrFail();
        $production->build($request->user(), $ownedPlanet, $type, (int) $attributes['code'], (int) $attributes['quantity']);

        return to_route($type === 'ships' ? 'shipyard.index' : 'defences.index', ['planet' => $ownedPlanet->id])
            ->with('success', $type === 'ships' ? 'Production de vaisseaux ajoutée à la file.' : 'Défenses ajoutées à la file de production.');
    }
}
