<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Handles HTTP requests for PlanetBuildingController operations.
 */

namespace App\Http\Controllers;

use App\Models\Planet;
use App\Services\PlanetEconomy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanetBuildingController extends Controller
{
    /**
     * Validates a building action and scopes the route planet to the authenticated user's current universe.
     *
     * Supplying activity changes the production rate; otherwise the service queues a level using the player's
     * current energy and computer technology levels.
     */
    public function upgrade(Request $request, Planet $planet, PlanetEconomy $economy): RedirectResponse
    {
        $attributes = $request->validate([
            'building' => ['required', 'string', Rule::in(['metal', 'crystal', 'deuterium', 'solar', 'fusion', 'laboratory', 'robot_factory', 'nano_factory', 'hangar', 'defence_center', 'missile_silo', 'metal_storage', 'crystal_storage', 'deuterium_storage', 'terraformer', 'alliance_depot'])],
            'activity' => ['sometimes', 'integer', 'min:0', 'max:10'],
        ]);

        $ownedPlanet = $request->user()->planets()
            ->where('universe_id', $request->user()->universe_id)
            ->whereKey($planet->getKey())
            ->firstOrFail();

        if (isset($attributes['activity'])) {
            $economy->setActivity($ownedPlanet, $attributes['building'], (int) $attributes['activity']);

            return to_route('buildings.index', ['planet' => $ownedPlanet->id])->with('success', 'Niveau de production modifié.');
        }

        $economy->upgrade(
            $ownedPlanet,
            $attributes['building'],
            (int) $request->user()->energy_technology_level,
            (int) $request->user()->computer_technology_level,
        );

        return to_route('buildings.index', ['planet' => $ownedPlanet->id])->with('success', 'Amélioration ajoutée à la file de construction.');
    }
}
