<?php

namespace App\Http\Controllers;

use App\Models\Planet;
use App\Services\UnitProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UnitProductionController extends Controller
{
    public function storeShip(Request $request, Planet $planet, UnitProductionService $production): RedirectResponse
    {
        return $this->store($request, $planet, 'ships', $production);
    }

    public function storeDefence(Request $request, Planet $planet, UnitProductionService $production): RedirectResponse
    {
        return $this->store($request, $planet, 'defences', $production);
    }

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
