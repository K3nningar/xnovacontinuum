<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Handles HTTP requests for MoonController operations.
 */

namespace App\Http\Controllers;

use App\Models\Planet;
use App\Services\ExplorationMissions;
use App\Services\GalaxyMap;
use App\Services\MoonEconomy;
use App\Services\PlanetEconomy;
use App\Services\UnitProductionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class MoonController extends Controller
{
    /**
     * Shows an owned planet's moon orbit after validating ownership and settling due resource/unit production.
     *
     * An absent moon-stock row is presented as zero stock; moon existence is checked by the galaxy-map service.
     */
    public function show(Request $request, Planet $planet, int $orbit, GalaxyMap $map, CelestialNames $celestialNames, MoonEconomy $moonEconomy, PlanetEconomy $economy, UnitProductionService $production): Response
    {
        $planet = $this->ownedPlanet($request, $planet);
        $moon = $map->ownedMoon($request->user(), $planet, $orbit);
        $planet = $economy->materialize($planet);
        $production->complete($planet);
        $planet->refresh();
        $moonEconomy->complete($planet, $orbit);
        $stock = $moonEconomy->stock($planet, $moon);
        $names = $celestialNames->forSystem($planet->universe, (int) $planet->galaxy, (int) $planet->system);
        $moon['generatedName'] = $names['moonNames'][$planet->position][$orbit];
        $moon['planetGeneratedName'] = $names['planetNames'][$planet->position];

        return inertia('Moon/Show', [
            'user' => $request->user()->only('id', 'name'),
            'universe' => $planet->universe->only('id', 'name'),
            'planets' => $request->user()->planets()->where('universe_id', $request->user()->universe_id)
                ->orderBy('galaxy')->orderBy('system')->orderBy('position')->get()
                ->map->only('id', 'name', 'coordinates', 'metal', 'crystal', 'deuterium')->values(),
            'selectedPlanetId' => $planet->id,
            'planet' => $planet->only('id', 'name', 'coordinates', 'galaxy', 'system', 'position'),
            'moon' => [...$moon,
                'name' => $stock?->name ?? $moon['generatedName'],
                'generatedName' => $moon['generatedName'],
                'planetGeneratedName' => $moon['planetGeneratedName'],
                'metal' => (int) ($stock?->metal ?? 0),
                'crystal' => (int) ($stock?->crystal ?? 0),
                'deuterium' => (int) ($stock?->deuterium ?? 0),
            ],
            'moonEconomy' => $moonEconomy->snapshot($request->user(), $planet, $moon, $stock),
            'moonFleet' => $moonEconomy->ships($planet, $orbit),
            'jumpGateDestinations' => $moonEconomy->jumpGateDestinations($request->user(), $planet, $orbit),
            'ships' => collect([202, 203, 209])->map(fn (int $code): array => ['code' => $code, 'count' => (int) $planet->{'unit_'.$code}])->values(),
            'fleetSpeed' => (float) $planet->universe->fleet_speed,
        ]);
    }

    /**
     * Lists only the authenticated user's natural moons and presents the selected moon's independent building economy.
     */
    public function buildings(Request $request, GalaxyMap $map, MoonEconomy $moonEconomy): Response|RedirectResponse
    {
        $user = $request->user();
        $planets = $user->planets()->where('universe_id', $user->universe_id)
            ->orderBy('galaxy')->orderBy('system')->orderBy('position')->get();
        $universe = $user->universe;
        $moonNames = DB::table('moon_stocks')->whereIn('planet_id', $planets->pluck('id'))
            ->get()->keyBy(fn ($stock): string => "{$stock->planet_id}:{$stock->orbit}");
        $systems = [];
        $moons = [];

        foreach ($planets as $planet) {
            if ($planet->position < 2 || $planet->position > 15) {
                continue;
            }

            $systemKey = "{$planet->galaxy}:{$planet->system}";
            $systems[$systemKey] ??= $map->system($universe, $user, (int) $planet->galaxy, (int) $planet->system, (int) $planet->position)['slots'];
            $naturalMoons = $systems[$systemKey][$planet->position - 2]['naturalMoons'] ?? [];

            $generatedPlanetName = $systems[$systemKey][$planet->position - 2]['generatedName'];

            foreach ($naturalMoons as $moon) {
                $moons[] = [
                    'planet' => [...$planet->only('id', 'name', 'coordinates'), 'generatedName' => $generatedPlanetName],
                    ...$moon,
                    'planetGeneratedName' => $generatedPlanetName,
                ];
            }
        }

        if ($moons === []) {
            return to_route('dashboard')->with('error', 'Aucune lune ne peut encore être aménagée.');
        }

        $planetId = $request->integer('planet');
        $orbit = $request->integer('orbit');
        $selected = $planetId > 0 || $orbit > 0
            ? collect($moons)->first(fn (array $moon): bool => (int) $moon['planet']['id'] === $planetId && (int) $moon['orbit'] === $orbit
            )
            : $moons[0];

        abort_if($selected === null, 404);
        $planet = $planets->firstWhere('id', $selected['planet']['id']);
        $stock = $moonEconomy->stock($planet, $selected);
        $selected = [...$selected,
            'name' => $stock->name ?? $selected['name'],
            'generatedName' => $selected['generatedName'],
            'planetGeneratedName' => $selected['planetGeneratedName'],
            'metal' => (int) $stock->metal,
            'crystal' => (int) $stock->crystal,
            'deuterium' => (int) $stock->deuterium,
        ];
        $economy = $moonEconomy->snapshot($user, $planet, $selected, $stock);

        return inertia('Moon/Buildings', [
            'user' => $user->only('id', 'name'),
            'moons' => $moons,
            'planet' => [...$planet->only('id', 'name', 'coordinates'), 'generatedName' => $selected['planet']['generatedName']],
            'moon' => $selected,
            'moonEconomy' => $economy,
            'selectedPlanetId' => $planet->id,
        ]);
    }

    public function rename(Request $request, Planet $planet, int $orbit, GalaxyMap $map, MoonEconomy $moonEconomy): RedirectResponse
    {
        $planet = $this->ownedPlanet($request, $planet);
        $map->ownedMoon($request->user(), $planet, $orbit);
        $attributes = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[\pL\pN][\pL\pN .\-\']*$/u'],
        ]);
        $moonEconomy->rename($request->user(), $planet, $orbit, $attributes['name']);

        return to_route('moon.buildings', ['planet' => $planet->id, 'orbit' => $orbit])->with('success', 'Lune renommée.');
    }

    public function upgrade(Request $request, Planet $planet, int $orbit, GalaxyMap $map, MoonEconomy $moonEconomy): RedirectResponse
    {
        $planet = $this->ownedPlanet($request, $planet);
        $map->ownedMoon($request->user(), $planet, $orbit);
        $attributes = $request->validate(['building' => ['required', Rule::in(['moon_base', 'phalanx', 'jump_gate'])]]);
        $moonEconomy->upgrade($request->user(), $planet, $orbit, $attributes['building']);

        return to_route('moon.buildings', ['planet' => $planet->id, 'orbit' => $orbit]);
    }

    public function phalanx(Request $request, Planet $planet, int $orbit, GalaxyMap $map, MoonEconomy $moonEconomy, CelestialNames $celestialNames): Response
    {
        $planet = $this->ownedPlanet($request, $planet);
        $moon = $map->ownedMoon($request->user(), $planet, $orbit);
        $stock = $moonEconomy->stock($planet, $moon);
        $names = $celestialNames->forSystem($planet->universe, (int) $planet->galaxy, (int) $planet->system);
        $moon['generatedName'] = $names['moonNames'][$planet->position][$orbit];
        $moon['planetGeneratedName'] = $names['planetNames'][$planet->position];
        $moon['name'] = $stock->name ?? $moon['generatedName'];
        abort_if((int) $stock->phalanx_level < 1, 403);

        return inertia('Moon/Phalanx', [
            'planet' => $planet->only('id', 'name', 'coordinates', 'galaxy', 'system', 'position'),
            'moon' => $moon,
            'fleets' => $request->user()->fleets()->where('universe_id', $planet->universe_id)
                ->whereIn('status', ['outbound', 'returning'])
                ->where(fn ($query) => $query->where(function ($query) use ($planet): void {
                    $query->where('target_galaxy', $planet->galaxy)->where('target_system', $planet->system)->where('target_position', $planet->position);
                })->orWhereHas('originPlanet', fn ($query) => $query->where('galaxy', $planet->galaxy)->where('system', $planet->system)->where('position', $planet->position)))
                ->get()->map(fn ($fleet): array => ['id' => $fleet->id, 'mission' => $fleet->mission, 'status' => $fleet->status,
                    'ships' => $fleet->ships, 'origin' => $fleet->originPlanet?->coordinates,             'target' => $fleet->target_position ? "{$fleet->target_galaxy}:{$fleet->target_system}:{$fleet->target_position}" : null,
                    'targetOrbit' => $fleet->target_orbit,
                    'originOrbit' => $fleet->origin_orbit,
                    'arrivesAt' => $fleet->arrives_at->toIso8601String()])->values(),
        ]);
    }

    public function jump(Request $request, Planet $planet, int $orbit, GalaxyMap $map, MoonEconomy $moonEconomy): RedirectResponse
    {
        $planet = $this->ownedPlanet($request, $planet);
        $map->ownedMoon($request->user(), $planet, $orbit);
        $attributes = $request->validate([
            'destination' => ['required', 'string'],
            'ships' => ['required', 'array'],
            'ships.*' => ['integer', 'min:0'],
        ]);
        $destination = collect($moonEconomy->jumpGateDestinations($request->user(), $planet, $orbit))
            ->firstWhere('destination', $attributes['destination']);
        if (! $destination) {
            throw ValidationException::withMessages(['destination' => 'Cette destination ne possède pas de porte de saut active.']);
        }
        if ($attributes['ships'] === [] || array_sum(array_map('intval', $attributes['ships'])) < 1) {
            throw ValidationException::withMessages(['ships' => 'Sélectionnez des vaisseaux à transférer.']);
        }
        $destinationPlanet = $request->user()->planets()->where('universe_id', $planet->universe_id)->whereKey($destination['planetId'])->firstOrFail();
        $units = array_map('intval', $attributes['ships']);
        $moonEconomy->moveShips($request->user(), $planet, $orbit, (int) $destination['orbit'], $units);

        return to_route('moon.show', ['planet' => $destinationPlanet->id, 'orbit' => $destination['orbit']]);
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
        $moon = $map->ownedMoon($request->user(), $planet, $orbit);
        $moonStock = app(MoonEconomy::class)->stock($planet, $moon);
        if ($attributes['direction'] === 'deposit' && ((int) $moonStock->{$attributes['resource']} > PHP_INT_MAX - (int) $attributes['amount'])) {
            throw ValidationException::withMessages(['amount' => 'Le stock lunaire dépasse la limite des ressources.']);
        }
        $attributes = array_intersect_key($attributes, array_flip(['direction', 'resource', 'amount', 'ships']));
        $attributes['ships'] = array_map('intval', $attributes['ships']);
        $missions->sendMoon($request->user(), $planet, $orbit, $attributes['direction'], $attributes['resource'], (int) $attributes['amount'], $attributes['ships']);

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
