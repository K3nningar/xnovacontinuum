<?php

namespace App\Http\Controllers;

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
    public function index(GameModules $modules, UnitProductionService $production): Response
    {
        $officers = collect($production->administrativeOfficers())->map(fn (array $officer, int $code): array => [
            'code' => $code,
            'key' => $officer['key'],
            'enabled' => $modules->officerConfigured($code),
        ])->values();

        return inertia('Admin/Modules', [
            'factionsEnabled' => $modules->enabled('factions'),
            'officersEnabled' => $modules->enabled('officers'),
            'officers' => $officers,
        ]);
    }

    public function update(Request $request, GameModules $modules, UnitProductionService $production): RedirectResponse
    {
        $codes = array_keys($production->administrativeOfficers());
        $data = $request->validate([
            'factions_enabled' => ['sometimes', 'required', 'boolean'],
            'officers_enabled' => ['required', 'boolean'],
            'enabled_officers' => ['present', 'array', 'list'],
            'enabled_officers.*' => ['required', 'integer', 'distinct', Rule::in($codes)],
        ]);

        $enabledCodes = array_map('intval', $data['enabled_officers']);
        $factionsEnabled = (bool) ($data['factions_enabled'] ?? $modules->enabled('factions'));
        $moduleEnabled = (bool) $data['officers_enabled'];
        DB::transaction(function () use ($request, $codes, $enabledCodes, $moduleEnabled, $factionsEnabled): void {
            $now = now();
            $settings = [
                ['key' => 'module.officers', 'enabled' => $moduleEnabled, 'created_at' => $now, 'updated_at' => $now],
                ['key' => 'module.factions', 'enabled' => $factionsEnabled, 'created_at' => $now, 'updated_at' => $now],
            ];
            foreach ($codes as $code) {
                $settings[] = ['key' => "officer.$code", 'enabled' => in_array($code, $enabledCodes, true), 'created_at' => $now, 'updated_at' => $now];
            }
            DB::table('game_module_settings')->upsert($settings, ['key'], ['enabled', 'updated_at']);
            DB::table('admin_audit_logs')->insert([
                'actor_id' => $request->user()->id,
                'actor_level' => app(AdminAccess::class)->globalLevel($request->user()),
                'action' => 'modules_updated',
                'details' => json_encode(['officers_enabled' => $moduleEnabled, 'enabled_officers' => $enabledCodes, 'factions_enabled' => $factionsEnabled], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
        $modules->forgetOverrides();

        return to_route('admin.modules')->with('success', 'Configuration des modules enregistrée.');
    }
}
