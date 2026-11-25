<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
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
            'mission' => ['required', Rule::in(['transport', 'attack', 'station', 'spy', 'colonize', 'recycle', 'destroy_moon', 'destroy_planet', 'expedition'])],
            'galaxy' => ['required', 'integer', 'between:1,9'],
            'system' => ['required', 'integer', 'between:1,499'],
            'position' => ['required', 'integer', 'between:2,16'],
            'ships' => ['required', 'array:202,203,204,205,206,207,208,209,210,211,213,214,215,216,217'],
            'ships.*' => ['required', 'integer', 'between:0,1000000'],
            'target_type' => ['sometimes', Rule::in(['planet', 'moon', 'asteroid', 'debris', 'depot'])],
            'moon_orbit' => ['sometimes', 'nullable', 'integer', 'between:1,10'],
            'origin_moon_orbit' => ['sometimes', 'nullable', 'integer', 'between:1,10'],
            'origin_target_type' => ['sometimes', Rule::in(['planet', 'moon'])],
            'cargo' => ['sometimes', 'array:metal,crystal,deuterium'],
            'cargo.metal' => ['sometimes', 'integer', 'min:0', 'max:1000000000'],
            'cargo.crystal' => ['sometimes', 'integer', 'min:0', 'max:1000000000'],
            'cargo.deuterium' => ['sometimes', 'integer', 'min:0', 'max:1000000000'],
        ]);
        $planet = $request->user()->planets()->where('universe_id', $request->user()->universe_id)
            ->whereKey($planet->id)->firstOrFail();
        $ships = array_map('intval', $attributes['ships']);
        $missions->send(
            $request->user(),
            $planet,
            $attributes['mission'],
            (int) $attributes['galaxy'],
            (int) $attributes['system'],
            (int) $attributes['position'],
            $ships,
            [
                'target_type' => $attributes['target_type'] ?? null,
                'moon_orbit' => isset($attributes['moon_orbit']) ? (int) $attributes['moon_orbit'] : null,
                'origin_moon_orbit' => isset($attributes['origin_moon_orbit']) ? (int) $attributes['origin_moon_orbit'] : null,
                'origin_target_type' => $attributes['origin_target_type'] ?? 'planet',
                'cargo' => array_map('intval', $attributes['cargo'] ?? []),
                'origin_planet_id' => (int) $planet->id,
            ],
        );

        return to_route('fleet.index', ['planet' => $planet->id]);
    }
}
