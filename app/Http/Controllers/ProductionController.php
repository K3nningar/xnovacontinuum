<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Handles HTTP requests for ProductionController operations.
 */

namespace App\Http\Controllers;

use App\Models\Planet;
use App\Services\PlanetEconomy;
use App\Services\UnitProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ProductionController extends Controller
{
    /**
     * Renders the shipyard using the shared production-page preparation flow.
     */
    public function shipyard(Request $request, PlanetEconomy $economy, UnitProductionService $production): Response|RedirectResponse
    {
        return $this->render($request, 'ships', 'Shipyard/Index', $economy, $production);
    }

    /**
     * Renders the defences catalog using the shared production-page preparation flow.
     */
    public function defences(Request $request, PlanetEconomy $economy, UnitProductionService $production): Response|RedirectResponse
    {
        return $this->render($request, 'defences', 'Defences/Index', $economy, $production);
    }

    /**
     * Displays empire fleets and recent completed mission reports after settling each planet's due work.
     *
     * Fleet and report queries are scoped to the authenticated user's selected universe; an optional planet
     * selector falls back to the first ordered owned planet.
     */
    public function fleet(Request $request, PlanetEconomy $economy, UnitProductionService $production): Response|RedirectResponse
    {
        $user = $request->user();
        $planets = $user->planets()->where('universe_id', $user->universe_id)
            ->orderBy('galaxy')->orderBy('system')->orderBy('position')->get()
            ->map(function (Planet $planet) use ($economy, $production): Planet {
                $planet = $economy->materialize($planet);
                $economy->completeBuildingQueue($planet);
                $production->complete($planet);

                return $planet->fresh();
            });
        $selectedPlanet = $planets->firstWhere('id', $request->integer('planet')) ?? $planets->first();

        if (! $selectedPlanet) {
            return to_route('dashboard');
        }

        $shipColumns = [202 => 'unit_202', 203 => 'unit_203', 204 => 'unit_204', 205 => 'unit_205', 206 => 'unit_206', 207 => 'unit_207', 208 => 'unit_208', 209 => 'unit_209', 210 => 'unit_210', 211 => 'unit_211', 212 => 'unit_212', 213 => 'unit_213', 214 => 'unit_214', 215 => 'unit_215', 216 => 'unit_216', 217 => 'unit_217'];
        $ships = [];
        foreach ($shipColumns as $code => $column) {
            $ships[] = ['code' => $code, 'count' => (int) $selectedPlanet->{$column}, 'image' => '/legacy-assets/skins/xnova/gebaeude/'.$code.'.gif'];
        }

        return inertia('Fleet/Index', [
            'user' => $user->only('id', 'name'), 'universe' => $user->universe?->only('id', 'name'),
            'planets' => $planets->map(fn (Planet $planet) => [...$planet->only('id', 'name', 'coordinates', 'unit_216', 'unit_217'), ...$economy->snapshot($planet, (int) $user->energy_technology_level, (int) $user->computer_technology_level)])->values(),
            'selectedPlanetId' => $selectedPlanet->id, 'ships' => $ships,
            'fleets' => $user->fleets()->where('universe_id', $user->universe_id)
                ->whereIn('status', ['outbound', 'returning'])
                ->with(['originPlanet:id,name,galaxy,system,position', 'targetPlanet:id,name,galaxy,system,position'])
                ->orderBy('arrives_at')->get()->map(fn ($fleet) => [
                    'id' => $fleet->id, 'mission' => $fleet->mission, 'ships' => $fleet->ships, 'status' => $fleet->status,
                    'moonOrbit' => $fleet->moon_orbit,
                    'arrivesAt' => $fleet->arrives_at->toIso8601String(), 'returnsAt' => $fleet->returns_at?->toIso8601String(),
                    'origin' => $fleet->originPlanet?->coordinates, 'originName' => $fleet->originPlanet?->name,
                    'target' => $fleet->target_position ? "{$fleet->target_galaxy}:{$fleet->target_system}:{$fleet->target_position}" : $fleet->targetPlanet?->coordinates, 'targetName' => $fleet->targetPlanet?->name,
                ])->values(),
            'reports' => $user->fleets()->where('universe_id', $user->universe_id)->where('status', 'completed')
                ->whereNotNull('target_position')->latest('updated_at')->limit(20)->get()
                ->map(fn ($fleet) => ['id' => $fleet->id, 'outcome' => $fleet->outcome, 'cargo' => $fleet->cargo, 'ships' => $fleet->ships, 'combat' => $fleet->combat_report, 'moonOrder' => $fleet->moon_order, 'moonOrbit' => $fleet->moon_orbit])->values(),
        ]);
    }

    /**
     * Prepares the selected owned planet, settles due economy/buildings/units, then renders its catalog and queue.
     *
     * Redirects to the dashboard if the user has no planet in their current universe.
     *
     * @param  'ships'|'defences'  $type
     */
    private function render(Request $request, string $type, string $component, PlanetEconomy $economy, UnitProductionService $production): Response|RedirectResponse
    {
        $user = $request->user();
        $planetId = $request->integer('planet');
        $planet = $user->planets()->where('universe_id', $user->universe_id)
            ->when($planetId > 0, fn ($query) => $query->whereKey($planetId))
            ->orderBy('galaxy')->orderBy('system')->orderBy('position')->first();

        if (! $planet) {
            return to_route('dashboard')->with('error', 'Fondez d’abord une planète avant de produire des unités.');
        }

        $planet = $economy->materialize($planet);
        $economy->completeBuildingQueue($planet);
        $production->complete($planet);
        $planet->refresh();
        $user = $user->fresh();
        $catalog = $production->catalog($planet, $user, $type);
        $unitQueue = $production->queueSnapshot($planet, $type);

        return inertia($component, [
            'user' => $user->only('id', 'name'),
            'universe' => $user->universe?->only('id', 'name'),
            'planets' => $user->planets()->where('universe_id', $user->universe_id)
                ->orderBy('galaxy')->orderBy('system')->orderBy('position')->get()
                ->map(function (Planet $ownedPlanet) use ($economy): array {
                    $ownedPlanet = $economy->materialize($ownedPlanet);
                    $economy->completeBuildingQueue($ownedPlanet);
                    $ownedPlanet->refresh();

                    return [
                        ...$ownedPlanet->only('id', 'name', 'coordinates', 'metal', 'crystal', 'deuterium'),
                        'hangarLevel' => $ownedPlanet->hangar_level,
                    ];
                })->values(),
            'selectedPlanetId' => $planet->id,
            'units' => $catalog['units'],
            'queue' => $unitQueue,
        ]);
    }
}
