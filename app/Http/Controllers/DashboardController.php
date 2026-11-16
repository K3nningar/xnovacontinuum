<?php

namespace App\Http\Controllers;

use App\Models\Fleet;
use App\Models\Planet;
use App\Models\Universe;
use App\Services\PlanetEconomy;
use App\Services\ResearchService;
use App\Services\UnitProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PlanetEconomy $economy, ResearchService $research, UnitProductionService $production): Response
    {
        $user = $request->user();
        $research->complete($user);
        $universe = $user->universe;
        $planets = $universe
            ? $user->planets()->where('universe_id', $user->universe_id)->orderBy('galaxy')->orderBy('system')->orderBy('position')->get()
                ->map(function ($planet) use ($economy, $production) {
                    $planet = $economy->materialize($planet);
                    $economy->completeBuildingQueue($planet);
                    $production->complete($planet);

                    return $planet->fresh();
                })
            : collect();
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
            ->limit(25)->get()->map(fn (Fleet $fleet) => [
                'id' => $fleet->id,
                'mission' => $fleet->mission,
                'status' => $fleet->status,
                'incoming' => $fleet->user_id !== $user->id,
                'origin' => $fleet->originPlanet?->coordinates,
                'target' => $fleet->target_position ? "{$fleet->target_galaxy}:{$fleet->target_system}:{$fleet->target_position}" : $fleet->targetPlanet?->coordinates,
                'eventAt' => ($fleet->status === 'returning' ? $fleet->returns_at : $fleet->arrives_at)?->toIso8601String(),
            ])->values() : collect();

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
                ->get(['id', 'name', 'galaxies', 'systems_per_galaxy'])
                ->toArray(),
            'planets' => $planets->map(fn ($planet) => [
                ...$planet->only('id', 'name', 'galaxy', 'system', 'position', 'coordinates', 'fields', 'max_temperature'),
                'shields' => [
                    'small' => (int) $planet->unit_407 > 0,
                    'large' => (int) $planet->unit_408 > 0,
                    'planetary' => (int) $planet->unit_409 > 0,
                ],
                'usedFields' => collect($economy->administrativeBuildings())
                    ->sum(fn (array $building): int => (int) $planet->{$building['level']}),
                ...$economy->snapshot($planet, (int) $user->energy_technology_level, (int) $user->computer_technology_level),
                'liveResources' => $economy->liveResourceSnapshot($planet),
            ])->values(),
            'alliance' => $user->alliance?->only('id', 'name', 'tag', 'description'),
            'fleetCount' => $universe ? $user->fleets()->where('status', 'outbound')->count() : 0,
            'researchQueue' => $universe ? $user->researchQueues()->first()?->only('id', 'technology_key', 'technology_code', 'target_level', 'completes_at') : null,
            'activeFleets' => $fleets,
        ]);
    }

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
