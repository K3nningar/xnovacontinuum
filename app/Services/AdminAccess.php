<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Centralise les droits administratifs globaux et par univers. */
class AdminAccess
{
    public function globalLevel(User $user): int
    {
        $account = $user->universe_id === null ? $user : User::query()
            ->where('commander_id', $user->commander_id)->whereNull('universe_id')->first();

        return (int) ($account?->admin_level ?? 0);
    }

    public function isGlobal(User $user, int $minimumLevel = 3): bool
    {
        return $this->globalLevel($user) >= $minimumLevel && $this->globalLevel($user) <= 4;
    }

    /** @return Collection<int, int> */
    public function staffUniverses(User $user, int $minimumLevel = 1): Collection
    {
        return DB::table('universe_staff')->where('commander_id', $user->commander_id)
            ->where('level', '>=', $minimumLevel)->where('level', '<=', 2)
            ->orderBy('universe_id')->pluck('universe_id')->map(fn ($id): int => (int) $id);
    }

    public function levelFor(User $user, int $universeId): int
    {
        $global = $this->globalLevel($user);
        if ($global >= 3) {
            return min($global, 4);
        }

        return (int) DB::table('universe_staff')->where('commander_id', $user->commander_id)
            ->where('universe_id', $universeId)->value('level');
    }

    public function highestLevel(User $user): int
    {
        return max($this->globalLevel($user), (int) DB::table('universe_staff')
            ->where('commander_id', $user->commander_id)->max('level'));
    }

    /** @return Collection<int, int>|null Null signifie que tous les univers sont accessibles. */
    public function manageableUniverseIds(User $user, int $minimumLevel = 1): ?Collection
    {
        if ($this->isGlobal($user, 3)) {
            return null;
        }

        return $this->staffUniverses($user, $minimumLevel);
    }
}
