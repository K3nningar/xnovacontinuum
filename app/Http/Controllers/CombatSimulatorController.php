<?php

namespace App\Http\Controllers;

use App\Http\Requests\SimulateCombatRequest;
use App\Models\User;
use App\Services\CombatSimulatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class CombatSimulatorController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $planet = $user->planets()
            ->where('universe_id', $user->universe_id)
            ->orderBy('galaxy')
            ->orderBy('system')
            ->orderBy('position')
            ->first();

        $unitColumns = collect(config('combat.units'))
            ->filter(fn (array $unit): bool => $unit['type'] === 'ships')
            ->mapWithKeys(fn (array $unit, int $code): array => [$code => 'unit_'.$code]);
        $units = collect(config('combat.units'))
            ->map(fn (array $unit, int $code): array => [
                'code' => $code,
                'key' => $unit['key'],
                'type' => $unit['type'],
                'attack' => $unit['attack'] ?? null,
                'shield' => $unit['shield'] ?? null,
                'hull' => $unit['hull'],
                'customProfile' => $unit['custom_profile'] ?? false,
                'image' => '/legacy-assets/skins/xnova/gebaeude/'.$code.'.gif',
            ])->values();
        $presetFleet = $unitColumns->map(fn (string $column): int => (int) ($planet?->{$column} ?? 0))->all();

        return inertia('Combat/Index', [
            'units' => $units,
            'maxUnitsPerSide' => (int) config('combat.max_units_per_side'),
            'presetFleet' => $presetFleet,
            'planet' => $planet?->only('id', 'name', 'coordinates'),
            'technologies' => [
                'weapons' => (int) $user->military_technology_level,
                'shields' => (int) $user->defence_technology_level,
                'armour' => (int) $user->shield_technology_level,
            ],
            'result' => session()->get('combatResult'),
        ]);
    }

    public function simulate(SimulateCombatRequest $request, CombatSimulatorService $simulator): RedirectResponse
    {
        $validated = $request->validated();
        $result = $simulator->simulate(
            $validated['attacker'],
            $validated['defender'],
            isset($validated['seed']) ? (int) $validated['seed'] : null,
            $validated['special_profiles'] ?? [],
        );

        return to_route('combat-simulator.index')->with('combatResult', $result);
    }
}
