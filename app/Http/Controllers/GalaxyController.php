<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Handles HTTP requests for GalaxyController operations.
 */

namespace App\Http\Controllers;

use App\Services\GalaxyMap;
use App\Services\GameModules;
use App\Services\ResearchService;
use App\Services\UnitProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class GalaxyController extends Controller
{
    /**
     * Renders a validated galaxy/system view using an owned planet as the visibility and fleet-stock context.
     *
     * Settles due research and unit queues before computing the map; coordinates remain bounded by both
     * configured universe dimensions and the game's hard display limits.
     */
    public function __invoke(Request $request, GalaxyMap $map, ResearchService $research, UnitProductionService $production): Response|RedirectResponse
    {
        $user = $request->user();
        $universe = $user->universe;
        if (! $universe) {
            return to_route('dashboard')->with('error', 'Fondez une planète pour accéder à la galaxie.');
        }

        $planets = $user->planets()->where('universe_id', $universe->id)->whereNull('destroyed_at')
            ->orderBy('galaxy')->orderBy('system')->orderBy('position')->get();
        if ($planets->isEmpty()) {
            return to_route('dashboard')->with('error', 'Fondez une planète pour accéder à la galaxie.');
        }

        $attributes = $request->validate([
            'planet' => ['sometimes', 'required', 'integer', 'min:1'],
            'galaxy' => ['sometimes', 'required', 'integer', 'between:1,'.min(9, (int) $universe->galaxies)],
            'system' => ['sometimes', 'required', 'integer', 'between:1,'.min(499, (int) $universe->systems_per_galaxy)],
        ]);
        $planet = isset($attributes['planet']) ? $planets->firstWhere('id', (int) $attributes['planet']) : $planets->first();
        abort_if(! $planet, 404);
        $galaxy = (int) ($attributes['galaxy'] ?? $planet->galaxy);
        $system = (int) ($attributes['system'] ?? $planet->system);
        abort_if($galaxy < 1 || $galaxy > min(9, (int) $universe->galaxies)
            || $system < 1 || $system > min(499, (int) $universe->systems_per_galaxy), 409);

        $research->complete($user);
        $user->refresh();
        $production->complete($planet);
        $planet->refresh();
        $sensorPositions = $planets->filter(fn ($ownedPlanet): bool => (int) $ownedPlanet->galaxy === $galaxy && (int) $ownedPlanet->system === $system)
            ->map(fn ($ownedPlanet): int => (int) $ownedPlanet->position)->all();
        $systemVisible = $sensorPositions !== [];

        return inertia('Galaxy/Index', [
            'user' => $user->only('id', 'name'),
            'universe' => $universe->only('id', 'name'),
            'planets' => $planets->map->only('id', 'name', 'coordinates', 'metal', 'crystal', 'deuterium')->values(),
            'selectedPlanetId' => $planet->id,
            'fleetSpeed' => (float) $universe->fleet_speed,
            'combatLimit' => (int) config('combat.max_units_per_side'),
            'availableShips' => collect([202, 203, 204, 205, 206, 207, 208, 209, 210, 211, 213, 214, 215, 216, 217])
                ->map(fn (int $code): array => ['code' => $code, 'count' => (int) $planet->{'unit_'.$code}])->values(),
            'galaxy' => $galaxy,
            'system' => $system,
            'galaxyLimit' => min(9, (int) $universe->galaxies),
            'systemLimit' => min(499, (int) $universe->systems_per_galaxy),
            'spyLevel' => (int) $user->spy_technology_level,
            'missionConfig' => [
                'missions' => ['transport', 'attack', 'station', 'spy', 'colonize', 'recycle', 'destroy_moon', 'destroy_planet', 'expedition'],
                'targetTypes' => ['planet', 'moon', 'asteroid', 'debris', 'depot'],
                'experimentalPlanetaryDestroyerEnabled' => (bool) config('combat.experimental_planetary_destroyer', false),
                'planetaryDestroyerAvailable' => (bool) config('combat.experimental_planetary_destroyer', false) && app(GameModules::class)->officerRequirementMet(615, (int) $user->officer_emperor_level, universeId: $user->universe_id) && (int) $planet->unit_217 > 0,
                'hasAlliance' => $user->alliance_id !== null || (app(GameModules::class)->enabled('factions', $user->universe_id) && $user->faction_key !== '0'),
            ],
            'systemVisible' => $systemVisible,
            ...($systemVisible
                ? $map->system($universe, $user, $galaxy, $system, (int) $planet->position, $sensorPositions)
                : ['star' => null, 'starName' => null, 'slots' => []]),
        ]);
    }
}
