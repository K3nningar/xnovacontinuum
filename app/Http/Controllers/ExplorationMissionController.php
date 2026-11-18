<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Handles HTTP requests for ExplorationMissionController operations.
 */

namespace App\Http\Controllers;

use App\Models\Planet;
use App\Services\ExplorationMissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExplorationMissionController extends Controller
{
    /**
     * Validates mission coordinates and ship quantities, scopes the route planet to its owner/universe, then dispatches it.
     *
     * Service-level checks repeat mission eligibility, visibility, inventory, and fuel rules under transaction locks.
     */
    public function store(Request $request, Planet $planet, ExplorationMissions $missions): RedirectResponse
    {
        $attributes = $request->validate([
            'mission' => ['required', Rule::in(['recycle', 'expedition'])],
            'galaxy' => ['required', 'integer', 'between:1,9'],
            'system' => ['required', 'integer', 'between:1,499'],
            'position' => ['required', 'integer', 'between:2,16'],
            'ships' => ['required', 'array:202,203,204,205,206,207,208,209,210,211,213,214,215,216'],
            'ships.*' => ['required', 'integer', 'between:0,1000000'],
        ]);
        $planet = $request->user()->planets()->where('universe_id', $request->user()->universe_id)
            ->whereKey($planet->id)->firstOrFail();
        $ships = array_map('intval', $attributes['ships']);
        $missions->send($request->user(), $planet, $attributes['mission'], (int) $attributes['galaxy'], (int) $attributes['system'], (int) $attributes['position'], $ships);

        return to_route('fleet.index', ['planet' => $planet->id]);
    }
}
