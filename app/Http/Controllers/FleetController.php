<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Handles HTTP requests for FleetController operations.
 */

namespace App\Http\Controllers;

use App\Models\Planet;
use App\Services\PlanetEconomy;
use Illuminate\Http\Request;
use Inertia\Response;

class FleetController extends Controller
{
    /**
     * Materializes owned planets and returns their ship stocks with the user's current-universe fleet list.
     *
     * A requested planet is honored only if it occurs in the owned collection; otherwise the first ordered
     * planet is selected. With no planets, the user is redirected to the dashboard.
     */
    public function index(Request $request, PlanetEconomy $economy): Response
    {
        $user = $request->user();
        $planets = $user->planets()->where('universe_id', $user->universe_id)
            ->orderBy('galaxy')->orderBy('system')->orderBy('position')->get()
            ->map(function (Planet $planet) use ($economy): Planet {
                $planet = $economy->materialize($planet);
                $economy->completeBuildingQueue($planet);

                return $planet->fresh();
            });
        $selectedPlanet = $planets->firstWhere('id', $request->integer('planet')) ?? $planets->first();

        if (! $selectedPlanet) {
            return to_route('dashboard');
        }

        return inertia('Fleet/Index', [
            'user' => $user->only('id', 'name'),
            'universe' => $user->universe?->only('id', 'name'),
            'planets' => $planets->map(fn (Planet $planet) => [
                ...$planet->only('id', 'name', 'coordinates'),
                'hangarLevel' => $planet->hangar_level,
                'unit_202' => $planet->unit_202, 'unit_203' => $planet->unit_203,
                'unit_204' => $planet->unit_204, 'unit_205' => $planet->unit_205,
                'unit_206' => $planet->unit_206, 'unit_207' => $planet->unit_207,
                'unit_208' => $planet->unit_208, 'unit_209' => $planet->unit_209,
                'unit_210' => $planet->unit_210, 'unit_211' => $planet->unit_211,
                'unit_212' => $planet->unit_212, 'unit_213' => $planet->unit_213,
                'unit_214' => $planet->unit_214, 'unit_215' => $planet->unit_215,
            ])->values(),

            'selectedPlanetId' => $selectedPlanet->id,
            'fleets' => $user->fleets()->where('universe_id', $user->universe_id)
                ->with(['originPlanet:id,name,galaxy,system,position', 'targetPlanet:id,name,galaxy,system,position'])
                ->orderBy('arrives_at')->get()->map(fn ($fleet) => [
                    'id' => $fleet->id,
                    'mission' => $fleet->mission,
                    'ships' => $fleet->ships,
                    'status' => $fleet->status,
                    'arrivesAt' => $fleet->arrives_at->toIso8601String(),
                    'returnsAt' => $fleet->returns_at?->toIso8601String(),
                    'origin' => $fleet->originPlanet?->coordinates,
                    'originName' => $fleet->originPlanet?->name,
                    'target' => $fleet->targetPlanet?->coordinates,
                    'targetName' => $fleet->targetPlanet?->name,
                ])->values(),
        ]);
    }
}
