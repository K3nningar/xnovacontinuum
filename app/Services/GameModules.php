<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class GameModules
{
    public function enabled(string $module, ?int $universeId = null): bool
    {
        if (! is_bool(config("modules.$module.enabled"))) {
            return false;
        }

        return $this->overrides($universeId)["module.$module"] ?? config("modules.$module.enabled");
    }

    public function officerEnabled(int $code, ?int $universeId = null): bool
    {
        return $this->enabled('officers', $universeId) && $this->officerConfigured($code, $universeId);
    }

    public function officerConfigured(int $code, ?int $universeId = null): bool
    {
        return $this->overrides($universeId)["officer.$code"]
            ?? ! in_array((string) $code, array_map('strval', config('modules.officers.disabled', [])), true);
    }

    public function officerRequirementMet(int $code, int $level, int $required = 1, ?int $universeId = null): bool
    {
        return ! $this->officerEnabled($code, $universeId) || $level >= $required;
    }

    public function forgetOverrides(): void
    {
        request()->attributes->remove('game_module_overrides');
    }

    private function overrides(?int $universeId): array
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return $this->loadOverrides($universeId);
        }

        $request = request();
        $cache = $request->attributes->get('game_module_overrides', []);
        $key = $universeId ?? 'global';
        if (! array_key_exists($key, $cache)) {
            $cache[$key] = $this->loadOverrides($universeId);
            $request->attributes->set('game_module_overrides', $cache);
        }

        return $cache[$key];
    }

    private function loadOverrides(?int $universeId): array
    {
        $global = DB::table('game_module_settings')->pluck('enabled', 'key')
            ->map(fn ($enabled): bool => (bool) $enabled)->all();

        if ($universeId === null) {
            return $global;
        }

        $universe = DB::table('universe_module_settings')->where('universe_id', $universeId)->pluck('enabled', 'key')
            ->map(fn ($enabled): bool => (bool) $enabled)->all();

        return array_replace($global, $universe);
    }
}
