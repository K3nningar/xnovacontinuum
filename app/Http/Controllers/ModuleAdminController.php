<?php

namespace App\Http\Controllers;

use App\Models\Universe;
use App\Services\AdminAccess;
use App\Services\GameModules;
use App\Services\UnitProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Response;

class ModuleAdminController extends Controller
{
    public function index(Request $request, GameModules $modules, UnitProductionService $production): Response
    {
        $data = $request->validate(['universe_id' => ['sometimes', 'required', 'integer', Rule::exists('universes', 'id')]]);
        $universes = Universe::query()->orderBy('name')->get(['id', 'name']);
        $universeId = isset($data['universe_id']) ? (int) $data['universe_id'] : $universes->first()?->id;
        $officers = collect($production->administrativeOfficers())->map(fn (array $officer, int $code): array => [
            'code' => $code,
            'key' => $officer['key'],
            'enabled' => $modules->officerConfigured($code, $universeId),
        ])->values();

        return inertia('Admin/Modules', [
            'universes' => $universes,
            'selectedUniverseId' => $universeId,
            'factionsEnabled' => $modules->enabled('factions', $universeId),
            'officersEnabled' => $modules->enabled('officers', $universeId),
            'officers' => $officers,
        ]);
    }

    public function update(Request $request, GameModules $modules, UnitProductionService $production): RedirectResponse
    {
        $codes = array_keys($production->administrativeOfficers());
        $data = $request->validate([
            'universe_id' => ['sometimes', 'required', 'integer', Rule::exists('universes', 'id')],
            'factions_enabled' => ['sometimes', 'required', 'boolean'],
            'officers_enabled' => ['required', 'boolean'],
            'enabled_officers' => ['present', 'array', 'list'],
            'enabled_officers.*' => ['required', 'integer', 'distinct', Rule::in($codes)],
        ]);

        $enabledCodes = array_map('intval', $data['enabled_officers']);
        $universeId = isset($data['universe_id']) ? (int) $data['universe_id'] : null;
        $factionsEnabled = (bool) ($data['factions_enabled'] ?? $modules->enabled('factions', $universeId));
        $moduleEnabled = (bool) $data['officers_enabled'];
        DB::transaction(function () use ($request, $codes, $enabledCodes, $moduleEnabled, $factionsEnabled, $universeId): void {
            $now = now();
            $settings = [
                ['key' => 'module.officers', 'enabled' => $moduleEnabled, 'created_at' => $now, 'updated_at' => $now],
                ['key' => 'module.factions', 'enabled' => $factionsEnabled, 'created_at' => $now, 'updated_at' => $now],
            ];
            foreach ($codes as $code) {
                $settings[] = ['key' => "officer.$code", 'enabled' => in_array($code, $enabledCodes, true), 'created_at' => $now, 'updated_at' => $now];
            }
            if ($universeId === null) {
                DB::table('game_module_settings')->upsert($settings, ['key'], ['enabled', 'updated_at']);
            } else {
                $settings = array_map(fn (array $setting): array => [...$setting, 'universe_id' => $universeId], $settings);
                DB::table('universe_module_settings')->upsert($settings, ['universe_id', 'key'], ['enabled', 'updated_at']);
            }
            DB::table('admin_audit_logs')->insert([
                'actor_id' => $request->user()->id,
                'actor_level' => app(AdminAccess::class)->globalLevel($request->user()),
                'action' => 'modules_updated',
                'details' => json_encode(['universe_id' => $universeId, 'officers_enabled' => $moduleEnabled, 'enabled_officers' => $enabledCodes, 'factions_enabled' => $factionsEnabled], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
        $modules->forgetOverrides();

        return to_route('admin.modules', $universeId === null ? [] : ['universe_id' => $universeId])->with('success', 'Configuration des modules enregistrée.');
    }
}
