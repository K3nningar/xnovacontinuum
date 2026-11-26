<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class GameModules
{
    public function enabled(string $module): bool
    {
        if (! is_bool(config("modules.$module.enabled"))) {
            return false;
        }

        return $this->overrides()["module.$module"] ?? config("modules.$module.enabled");
    }

    public function officerEnabled(int $code): bool
    {
        return $this->enabled('officers') && $this->officerConfigured($code);
    }

    public function officerConfigured(int $code): bool
    {
        return $this->overrides()["officer.$code"]
            ?? ! in_array((string) $code, array_map('strval', config('modules.officers.disabled', [])), true);
    }

    public function officerRequirementMet(int $code, int $level, int $required = 1): bool
    {
        return ! $this->officerEnabled($code) || $level >= $required;
    }

    public function forgetOverrides(): void
    {
        request()->attributes->remove('game_module_overrides');
    }

    private function overrides(): array
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return $this->loadOverrides();
        }

        $request = request();
        if (! $request->attributes->has('game_module_overrides')) {
            $request->attributes->set('game_module_overrides', $this->loadOverrides());
        }

        return $request->attributes->get('game_module_overrides');
    }

    private function loadOverrides(): array
    {
        return DB::table('game_module_settings')->pluck('enabled', 'key')
            ->map(fn ($enabled): bool => (bool) $enabled)->all();
    }
}
