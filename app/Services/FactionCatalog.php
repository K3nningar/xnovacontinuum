<?php

namespace App\Services;

use App\Models\Faction;
use App\Models\User;

class FactionCatalog
{
    public function settings(User $user): array
    {
        if ($user->universe_id === null) {
            return [];
        }

        return Faction::query()->where('universe_id', $user->universe_id)
            ->where('key', $user->faction_key ?? '0')->first()?->settings ?? [];
    }

    public function available(User $user, string $category, string|int $key): bool
    {
        return data_get($this->settings($user), "$category.$key.available", true) !== false;
    }
}
