<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Handles HTTP requests for DefenceController operations.
 */

namespace App\Http\Controllers;

use App\Models\Planet;
use App\Services\PlanetEconomy;
use App\Services\ResearchService;
use App\Services\UnitProductionService;
use Illuminate\Http\Request;
use Inertia\Response;

class DefenceController extends Controller
{
    /**
     * Completes due research and planet work before presenting the selected owned planet's defence catalog/queue.
     *
     * The selector is constrained to the authenticated user's current universe and defaults to the first planet.
     */
    public function index(Request $request, PlanetEconomy $economy, ResearchService $research, UnitProductionService $production): Response
    {
        $user = $request->user();
        $research->complete($user);
        $planets = $user->planets()->where('universe_id', $user->universe_id)
            ->orderBy('galaxy')->orderBy('system')->orderBy('position')->get()
            ->map(function (Planet $planet) use ($economy, $production) {
                $planet = $economy->materialize($planet);
                $economy->completeBuildingQueue($planet);
                $production->complete($planet);

                return $planet->fresh();
            });
        $selectedPlanet = $planets->firstWhere('id', $request->integer('planet')) ?? $planets->first();

        if (! $selectedPlanet) {
            return to_route('dashboard');
        }

        $defences = $production->defenceCatalog($selectedPlanet, $user->fresh());

        return inertia('Defences/Index', [
            'user' => $user->only('id', 'name'),
            'universe' => $user->universe?->only('id', 'name'),
            'planets' => $planets->map(fn (Planet $planet) => [...$planet->only('id', 'name', 'coordinates', 'metal', 'crystal', 'deuterium'), 'hangarLevel' => $planet->hangar_level])->values(),
            'selectedPlanetId' => $selectedPlanet->id,
            'units' => $defences['units'],
            'queue' => $production->defencesQueueSnapshot($selectedPlanet),
        ]);
    }
}
