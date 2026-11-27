<?php

namespace App\Http\Controllers;

use App\Models\Faction;
use App\Services\AdminAccess;
use App\Services\GameModules;
use App\Services\PlanetEconomy;
use App\Services\UnitProductionService;
use Illuminate\Http\Request;
use Inertia\Response;

class FactionController extends Controller
{
    public function index(Request $request, PlanetEconomy $economy, UnitProductionService $production, AdminAccess $admin): Response
    {
        $user = $request->user();
        abort_if($user->universe_id === null, 404);

        $universe = $user->universe;
        if (! app(GameModules::class)->enabled('factions', $user->universe_id)) {
            return inertia('Factions/Index', [
                'universe' => $universe->only('id', 'name'),
                'factionsEnabled' => false,
                'factions' => [],
                'currentFactionKey' => null,
                'canEdit' => false,
            ]);
        }
        $catalog = $this->catalog($economy, $production);
        $members = $universe->users()->selectRaw('faction_key, COUNT(*) as total')->groupBy('faction_key')->pluck('total', 'faction_key');
        $planetCounts = $universe->planets()->whereNull('destroyed_at')->join('users', 'users.id', '=', 'planets.user_id')
            ->selectRaw('users.faction_key, COUNT(planets.id) as total')->groupBy('users.faction_key')->pluck('total', 'faction_key');
        $factions = $universe->factions()->get()->map(fn (Faction $faction): array => $this->presentFaction($faction, $catalog, $members, $planetCounts))->values();
        if (! $factions->contains('key', '0')) {
            $factions->prepend($this->presentFallbackFaction($members));
        }

        return inertia('Factions/Index', [
            'universe' => $universe->only('id', 'name'),
            'factionsEnabled' => true,
            'currentFactionKey' => (string) ($user->faction_key ?? '0'),
            'factions' => $factions->sortBy(fn (array $faction): int => $faction['key'] === '0' ? 0 : 1)->values(),
            'canEdit' => $admin->levelFor($user, $universe->id) >= 4,
        ]);
    }

    private function presentFaction(Faction $faction, array $catalog, object $members, object $planetCounts): array
    {
        $settings = is_array($faction->settings) ? $faction->settings : [];

        return [
            ...$faction->only('id', 'key', 'name', 'description', 'image'),
            'members' => (int) ($members[$faction->key] ?? 0),
            'planets' => (int) ($planetCounts[$faction->key] ?? 0),
            'statistics' => ['attack' => (float) data_get($settings, 'statistics.attack', 1), 'shield' => (float) data_get($settings, 'statistics.shield', 1), 'hull' => (float) data_get($settings, 'statistics.hull', 1)],
            'exclusiveElements' => $this->exclusiveElements($settings, $catalog),
        ];
    }

    private function presentFallbackFaction(object $members): array
    {
        return ['id' => null, 'key' => '0', 'name' => 'Faction 0', 'description' => null, 'image' => null, 'members' => (int) ($members['0'] ?? 0), 'planets' => 0, 'statistics' => ['attack' => 1, 'shield' => 1, 'hull' => 1], 'exclusiveElements' => []];
    }

    private function exclusiveElements(array $settings, array $catalog): array
    {
        $elements = [];
        foreach (['buildings', 'ships', 'defences'] as $category) {
            foreach ($catalog[$category] as $item) {
                $override = $settings[$category][$item['key']] ?? null;
                if (! is_array($override) || (($override['available'] ?? true) === true && empty($override['name']) && empty($override['image']))) {
                    continue;
                }
                $elements[] = [
                    'category' => $category,
                    'name' => ($override['name'] ?? '') ?: $item['name'],
                    'image' => ($override['image'] ?? '') ?: null,
                    'available' => ($override['available'] ?? true) !== false,
                ];
            }
        }

        return $elements;
    }

    private function catalog(PlanetEconomy $economy, UnitProductionService $production): array
    {
        return [
            'buildings' => collect($economy->administrativeBuildings())->map(fn (array $item, string $key): array => ['key' => $key, 'name' => $item['label']])->all(),
            ...collect($production->administrativeUnits())->mapWithKeys(fn (array $items, string $category): array => [$category => collect($items)->map(fn (array $item, int $code): array => ['key' => (string) $code, 'name' => $item['name']])->all()])->all(),
        ];
    }
}
