<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Handles HTTP requests for BuildingController operations.
 */

namespace App\Http\Controllers;

use App\Models\Planet;
use App\Services\PlanetEconomy;
use App\Services\ResearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class BuildingController extends Controller
{
    /**
     * Settles due research and each owned planet's economy/building queue before rendering building snapshots.
     *
     * A requested planet is honored only within the authenticated user's current universe; without an owned
     * planet the visitor is redirected to the dashboard.
     */
    public function index(Request $request, PlanetEconomy $economy, ResearchService $research): Response|RedirectResponse
    {
        $user = $request->user();
        $research->complete($user);
        $planetId = $request->integer('planet');
        $selectedPlanet = $user->planets()
            ->where('universe_id', $user->universe_id)
            ->when($planetId > 0, fn ($query) => $query->whereKey($planetId))
            ->orderBy('galaxy')
            ->orderBy('system')
            ->orderBy('position')
            ->first();

        if (! $selectedPlanet) {
            return to_route('dashboard')->with('error', 'Fondez d’abord votre planète pour accéder aux bâtiments.');
        }

        $planets = $user->planets()
            ->where('universe_id', $user->universe_id)
            ->orderBy('galaxy')
            ->orderBy('system')
            ->orderBy('position')
            ->get()
            ->map(function (Planet $planet) use ($economy) {
                $planet = $economy->materialize($planet);
                $economy->completeBuildingQueue($planet);

                return $planet->fresh();
            });

        $selectedPlanet = $planets->firstWhere('id', $selectedPlanet->id);

        return inertia('Buildings/Index', [
            'user' => $user->only('id', 'name'),
            'universe' => $user->universe?->only('id', 'name'),
            'technologyEnergyLevel' => (int) $user->energy_technology_level,
            'technologyComputerLevel' => (int) $user->computer_technology_level,
            'buildingRarities' => config('technology-tree.building_rarities'),
            'planets' => $planets->map(fn (Planet $planet) => [
                ...$planet->only('id', 'name', 'coordinates', 'fields'),
                ...$economy->snapshot($planet, (int) $user->energy_technology_level, (int) $user->computer_technology_level),
            ])->values(),
            'selectedPlanetId' => $selectedPlanet->id,
        ]);
    }
}
