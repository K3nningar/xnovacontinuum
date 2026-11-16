<?php

namespace App\Http\Controllers;

use App\Services\GalaxyMap;
use App\Services\ResearchService;
use App\Services\UnitProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class GalaxyController extends Controller
{
    public function __invoke(Request $request, GalaxyMap $map, ResearchService $research, UnitProductionService $production): Response|RedirectResponse
    {
        $user = $request->user();
        $universe = $user->universe;
        if (! $universe) {
            return to_route('dashboard')->with('error', 'Fondez une planète pour accéder à la galaxie.');
        }

        $planets = $user->planets()->where('universe_id', $universe->id)
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

        return inertia('Galaxy/Index', [
            'user' => $user->only('id', 'name'),
            'universe' => $universe->only('id', 'name'),
            'planets' => $planets->map->only('id', 'name', 'coordinates', 'metal', 'crystal', 'deuterium')->values(),
            'selectedPlanetId' => $planet->id,
            'fleetSpeed' => (float) $universe->fleet_speed,
            'combatLimit' => (int) config('combat.max_units_per_side'),
            'availableShips' => collect([202, 203, 204, 205, 206, 207, 208, 209, 210, 211, 213, 214, 215, 216])
                ->map(fn (int $code): array => ['code' => $code, 'count' => (int) $planet->{'unit_'.$code}])->values(),
            'galaxy' => $galaxy,
            'system' => $system,
            'galaxyLimit' => min(9, (int) $universe->galaxies),
            'systemLimit' => min(499, (int) $universe->systems_per_galaxy),
            'spyLevel' => (int) $user->spy_technology_level,
            ...$map->system($universe, $user, $galaxy, $system, $planet->position),
        ]);
    }
}
