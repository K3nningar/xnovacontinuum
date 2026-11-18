<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Handles HTTP requests for MoonController operations.
 */

namespace App\Http\Controllers;

use App\Models\Planet;
use App\Services\ExplorationMissions;
use App\Services\GalaxyMap;
use App\Services\PlanetEconomy;
use App\Services\UnitProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Response;

class MoonController extends Controller
{
    /**
     * Shows an owned planet's moon orbit after validating ownership and settling due resource/unit production.
     *
     * An absent moon-stock row is presented as zero stock; moon existence is checked by the galaxy-map service.
     */
    public function show(Request $request, Planet $planet, int $orbit, GalaxyMap $map, PlanetEconomy $economy, UnitProductionService $production): Response
    {
        $planet = $this->ownedPlanet($request, $planet);
        $moon = $map->ownedMoon($request->user(), $planet, $orbit);
        $planet = $economy->materialize($planet);
        $production->complete($planet);
        $planet->refresh();
        $stock = DB::table('moon_stocks')->where('planet_id', $planet->id)->where('orbit', $orbit)->first();

        return inertia('Moon/Show', [
            'user' => $request->user()->only('id', 'name'),
            'universe' => $planet->universe->only('id', 'name'),
            'planets' => $request->user()->planets()->where('universe_id', $request->user()->universe_id)
                ->orderBy('galaxy')->orderBy('system')->orderBy('position')->get()
                ->map->only('id', 'name', 'coordinates', 'metal', 'crystal', 'deuterium')->values(),
            'selectedPlanetId' => $planet->id,
            'planet' => $planet->only('id', 'name', 'coordinates', 'galaxy', 'system', 'position'),
            'moon' => [...$moon, 'metal' => (int) ($stock?->metal ?? 0), 'crystal' => (int) ($stock?->crystal ?? 0),
                'deuterium' => (int) ($stock?->deuterium ?? 0)],
            'ships' => collect([202, 203, 209])->map(fn (int $code): array => ['code' => $code, 'count' => (int) $planet->{'unit_'.$code}])->values(),
            'fleetSpeed' => (float) $planet->universe->fleet_speed,
        ]);
    }

    /**
     * Validates and dispatches a moon resource transfer for an orbit belonging to the authenticated user's planet.
     *
     * The mission service performs transactional stock, ship, cargo-capacity, and fuel checks before enqueueing.
     */
    public function store(Request $request, Planet $planet, int $orbit, GalaxyMap $map, ExplorationMissions $missions): RedirectResponse
    {
        $planet = $this->ownedPlanet($request, $planet);
        $map->ownedMoon($request->user(), $planet, $orbit);
        $attributes = $request->validate([
            'direction' => ['required', Rule::in(['deposit', 'withdraw'])],
            'resource' => ['required', Rule::in(['metal', 'crystal', 'deuterium'])],
            'amount' => ['required', 'integer', 'between:1,1000000000'],
            'ships' => ['required', 'array:202,203,209'],
            'ships.*' => ['required', 'integer', 'between:0,1000000'],
        ]);
        $missions->sendMoon($request->user(), $planet, $orbit, $attributes['direction'], $attributes['resource'], (int) $attributes['amount'], array_map('intval', $attributes['ships']));

        return to_route('moon.show', ['planet' => $planet->id, 'orbit' => $orbit])->with('success', 'Transport orbital lancé.');
    }

    /**
     * Resolves a route-bound planet only when it belongs to the authenticated user in the selected universe.
     */
    private function ownedPlanet(Request $request, Planet $planet): Planet
    {
        return $request->user()->planets()->where('universe_id', $request->user()->universe_id)->whereKey($planet->id)->firstOrFail();
    }
}
