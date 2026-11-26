<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Handles HTTP requests for DashboardController operations.
 */

namespace App\Http\Controllers;

use App\Models\Faction;
use App\Models\Fleet;
use App\Models\Planet;
use App\Models\Universe;
use App\Models\User;
use App\Services\GalaxyMap;
use App\Services\GameModules;
use App\Services\MoonEconomy;
use App\Services\PlanetEconomy;
use App\Services\ResearchService;
use App\Services\UnitProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Settles due empire research and owned-planet work, then assembles dashboard state for the current universe.
     *
     * A requested planet must belong to the user's loaded universe; fleet visibility includes own active fleets
     * and outbound fleets targeting owned planets, while only active universes are offered to unjoined users.
     * Natural moons are resolved once per owned star system and included with each planet's dashboard data.
     */
    public function __invoke(Request $request, PlanetEconomy $economy, MoonEconomy $moonEconomy, ResearchService $research, UnitProductionService $production, GalaxyMap $map): Response
    {
        $user = $request->user();
        $research->complete($user);
        $onboardingUniverseId = (int) $request->session()->get('onboardingUniverseId', 0);
        $activeAccount = User::query()->find($request->session()->get('activeCommanderUserId'));
        $onboardingForAccount = $onboardingUniverseId > 0
            && (int) $request->session()->get('onboardingCommanderUserId') === (int) $activeAccount?->id;
        $universe = $onboardingForAccount && $onboardingUniverseId !== (int) $user->universe_id
            ? null
            : $user->universe;
        $planets = $universe
            ? $user->planets()->where('universe_id', $user->universe_id)->orderBy('galaxy')->orderBy('system')->orderBy('position')->get()
                ->map(function ($planet) use ($economy, $production) {
                    $planet = $economy->materialize($planet);
                    $economy->completeBuildingQueue($planet);
                    $production->complete($planet);

                    return $planet->fresh();
                })
            : collect();
        $moonsByPlanet = [];
        $generatedPlanetNames = [];
        $moonSystems = [];
        $reservedSystems = $planets
            ->filter(fn (Planet $planet): bool => $planet->position < 2 || $planet->position > 15)
            ->mapWithKeys(fn (Planet $planet): array => ["{$planet->galaxy}:{$planet->system}" => true]);

        foreach ($planets as $planet) {
            $systemKey = "{$planet->galaxy}:{$planet->system}";

            if (isset($reservedSystems[$systemKey]) || $planet->position < 2 || $planet->position > 15) {
                $moonsByPlanet[$planet->id] = [];

                continue;
            }

            $moonSystems[$systemKey] ??= $map->system(
                $universe,
                $user,
                (int) $planet->galaxy,
                (int) $planet->system,
                (int) $planet->position,
            )['slots'];
            $slot = $moonSystems[$systemKey][$planet->position - 2] ?? [];
            $generatedPlanetNames[$planet->id] = $slot['generatedName'] ?? null;
            $moonsByPlanet[$planet->id] = $slot['naturalMoons'] ?? [];
        }

        $moonStocks = DB::table('moon_stocks')->whereIn('planet_id', $planets->pluck('id'))
            ->get()->keyBy(fn ($stock): string => "{$stock->planet_id}:{$stock->orbit}");
        $planetId = $request->integer('planet');
        $activePlanet = $planetId > 0 ? $planets->firstWhere('id', $planetId) : $planets->first();
        if ($planetId > 0 && ! $activePlanet) {
            abort(404);
        }

        $fleets = $universe ? Fleet::query()->where('universe_id', $universe->id)
            ->where(function ($query) use ($user, $planets): void {
                $query->where(function ($own) use ($user): void {
                    $own->where('user_id', $user->id)->whereIn('status', ['outbound', 'returning']);
                })->orWhere(function ($incoming) use ($user, $planets): void {
                    $incoming->where('user_id', '!=', $user->id)->where('status', 'outbound')
                        ->whereIn('target_planet_id', $planets->pluck('id'));
                });
            })
            ->with(['originPlanet:id,name,galaxy,system,position', 'targetPlanet:id,name,galaxy,system,position'])
            ->orderByRaw('case when status = ? then coalesce(returns_at, arrives_at) else arrives_at end', ['returning'])
            ->limit(25)->get()->map(fn ($fleet) => [
                'id' => $fleet->id,
                'mission' => $fleet->mission,
                'status' => $fleet->status,
                'incoming' => $fleet->user_id !== $user->id,
                'origin' => $fleet->originPlanet?->coordinates,
                'target' => $fleet->target_position ? "{$fleet->target_galaxy}:{$fleet->target_system}:{$fleet->target_position}" : $fleet->targetPlanet?->coordinates,
                'eventAt' => ($fleet->status === 'returning' ? $fleet->returns_at : $fleet->arrives_at)?->toIso8601String(),
            ])->values() : collect();

        $faction = $universe && app(GameModules::class)->enabled('factions')
            ? Faction::query()->where('universe_id', $universe->id)->where('key', $user->faction_key ?? '0')->first()
            : null;
        $researchQueue = $universe ? $user->researchQueues()->first() : null;

        return inertia('Dashboard', [
            'user' => $user->only('id', 'name'),
            'hasUniverse' => $universe !== null,
            'selectedPlanetId' => null,
            'activePlanetId' => $activePlanet?->id,
            'serverTime' => now()->toIso8601String(),
            'serverTimezone' => config('app.timezone'),
            'universe' => $universe ? $universe->only('id', 'name', 'galaxies', 'systems_per_galaxy') : [],
            'availableUniverses' => $universe ? [] : Universe::query()
                ->where('status', 'active')
                ->orderBy('id')
                ->with('factions:id,universe_id,key,name,description,image')
                ->get(['id', 'name', 'galaxies', 'systems_per_galaxy'])
                ->map(fn (Universe $available): array => [
                    ...$available->only('id', 'name', 'galaxies', 'systems_per_galaxy'),
                    'factions' => ! app(GameModules::class)->enabled('factions') ? [] : $available->factions->where('key', '!=', '0')->prepend(
                        $available->factions->firstWhere('key', '0') ?? ['key' => '0', 'name' => 'Faction 0', 'description' => null, 'image' => null],
                    )->values(),
                ])->toArray(),
            'onboardingUniverseId' => $onboardingForAccount && $onboardingUniverseId > 0
                && Universe::query()->where('status', 'active')->whereKey($onboardingUniverseId)->exists()
                ? $onboardingUniverseId
                : null,
            'planets' => $planets->map(fn ($planet) => [
                ...$planet->only('id', 'name', 'galaxy', 'system', 'position', 'coordinates', 'fields', 'max_temperature'),
                'generatedName' => $generatedPlanetNames[$planet->id] ?? null,
                'moons' => collect($moonsByPlanet[$planet->id] ?? [])->map(function (array $moon) use ($planet, $moonStocks, $moonEconomy): array {
                    $stock = $moonStocks->get("{$planet->id}:{$moon['orbit']}");

                    return [...$moon,
                        'name' => $stock?->name ?? $moon['name'],
                        'generatedName' => $moon['generatedName'],
                        'metal' => (int) ($stock?->metal ?? 0),
                        'crystal' => (int) ($stock?->crystal ?? 0),
                        'deuterium' => (int) ($stock?->deuterium ?? 0),
                        'capacity' => $moonEconomy->storageCapacities(),
                    ];
                })->values(),
                'shields' => [
                    'small' => (int) $planet->unit_407 > 0,
                    'large' => (int) $planet->unit_408 > 0,
                    'planetary' => (int) $planet->unit_409 > 0,
                ],
                'usedFields' => collect($economy->administrativeBuildings())
                    ->sum(fn (array $building): int => (int) $planet->{$building['level']}),
                'capacity' => $economy->storageCapacities($planet),
                ...$economy->snapshot($planet, (int) $user->energy_technology_level, (int) $user->computer_technology_level),
                'liveResources' => $economy->liveResourceSnapshot($planet),
            ])->values(),
            'alliance' => $user->alliance?->only('id', 'name', 'tag', 'description'),
            'faction' => $faction ? [
                ...$faction->only('name', 'description', 'image'),
                'members' => User::query()->where('universe_id', $universe->id)->where('faction_key', $faction->key)->count(),
                'planets' => Planet::query()->where('universe_id', $universe->id)->whereNull('destroyed_at')->whereHas('user', fn ($query) => $query->where('faction_key', $faction->key))->count(),
            ] : null,
            'fleetCount' => $universe ? $user->fleets()->where('status', 'outbound')->count() : 0,
            'researchQueue' => $researchQueue ? [
                ...$researchQueue->only('id', 'technology_key', 'technology_code', 'completes_at'),
                'target_level' => $research->effectiveQueueTarget($user, $researchQueue),
            ] : null,
            'activeFleets' => $fleets,
        ]);
    }

    /**
     * Renames a planet only after resolving it through the authenticated user's current-universe ownership relation.
     *
     * The accepted name must begin with a letter/number and may then contain letters, numbers, spaces, periods,
     * hyphens, or apostrophes; the response returns to that planet's dashboard view.
     */
    public function rename(Request $request, Planet $planet): RedirectResponse
    {
        $ownedPlanet = $request->user()->planets()
            ->where('universe_id', $request->user()->universe_id)
            ->whereKey($planet->getKey())->firstOrFail();
        $attributes = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[\pL\pN][\pL\pN .\-\']*$/u'],
        ]);
        $ownedPlanet->update(['name' => $attributes['name']]);

        return to_route('dashboard', ['planet' => $ownedPlanet->id])->with('success', 'Planète renommée.');
    }
}
