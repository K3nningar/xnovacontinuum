<?php

namespace App\Http\Controllers;

use App\Models\Planet;
use App\Services\PlanetEconomy;
use App\Services\ResearchService;
use App\Services\TechnologyTreeService;
use App\Services\UnitProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

class ResearchController extends Controller
{
    public function index(Request $request, PlanetEconomy $economy, ResearchService $research): Response|RedirectResponse
    {
        return $this->renderResearchPage($request, $economy, $research, 'Research/Index');
    }

    public function tree(Request $request, PlanetEconomy $economy, ResearchService $research, UnitProductionService $production, TechnologyTreeService $technologyTree): Response|RedirectResponse
    {
        return $this->renderResearchPage($request, $economy, $research, 'Research/TechnologyGraph', $production, $technologyTree);
    }

    private function renderResearchPage(Request $request, PlanetEconomy $economy, ResearchService $research, string $component, ?UnitProductionService $production = null, ?TechnologyTreeService $technologyTree = null): Response|RedirectResponse
    {
        $user = $request->user();
        $planetId = $request->integer('planet');
        $planet = $user->planets()->where('universe_id', $user->universe_id)
            ->when($planetId > 0, fn ($query) => $query->whereKey($planetId))
            ->orderBy('galaxy')->orderBy('system')->orderBy('position')->first();

        if (! $planet) {
            return to_route('dashboard')->with('error', 'Fondez d’abord votre planète pour accéder aux recherches.');
        }

        $research->complete($user);
        $planet = $economy->materialize($planet);
        $economy->completeBuildingQueue($planet);
        $planet->refresh();
        $catalog = $research->catalog($user->fresh(), $planet);

        return inertia($component, [
            'user' => $user->only('id', 'name'),
            'universe' => $user->universe?->only('id', 'name'),
            'planets' => $user->planets()->where('universe_id', $user->universe_id)
                ->orderBy('galaxy')->orderBy('system')->orderBy('position')->get()
                ->map(fn (Planet $ownedPlanet) => [...$ownedPlanet->only('id', 'name', 'coordinates', 'metal', 'crystal', 'deuterium', 'laboratory_level'), 'energy' => $economy->energyBalance($ownedPlanet)['supply']])->values(),
            'selectedPlanetId' => $planet->id,
            ...$catalog,
            ...($component === 'Research/TechnologyGraph' && $production && $technologyTree ? [
                'treeElements' => $technologyTree->catalog($user->fresh(), $planet, $catalog['researches'],
                    $economy->snapshot($planet, (int) $user->energy_technology_level, (int) $user->computer_technology_level), $production),
            ] : []),
        ]);
    }

    public function store(Request $request, Planet $planet, ResearchService $research): RedirectResponse
    {
        $attributes = $request->validate([
            'technology' => ['required', 'string', Rule::in(['spy', 'computer', 'military', 'defence', 'shield', 'energy', 'hyperspace', 'combustion', 'impulse', 'hyperspace_drive', 'laser', 'ion', 'plasma', 'intergalactic', 'expedition', 'graviton'])],
        ]);
        $ownedPlanet = $request->user()->planets()->where('universe_id', $request->user()->universe_id)
            ->whereKey($planet->getKey())->firstOrFail();
        $research->start($request->user(), $ownedPlanet, $attributes['technology']);

        return to_route('research.index', ['planet' => $ownedPlanet->id])->with('success', 'Recherche lancée avec succès.');
    }

    public function destroy(Request $request, ResearchService $research): RedirectResponse
    {
        $research->cancel($request->user());

        return to_route('research.index')->with('success', 'Recherche annulée, ressources remboursées.');
    }
}
