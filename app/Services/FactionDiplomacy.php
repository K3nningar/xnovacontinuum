<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class FactionDiplomacy
{
    public function relation(User $first, User $second): string
    {
        if ($first->universe_id === null || (int) $first->universe_id !== (int) $second->universe_id) {
            return 'neutral';
        }

        $firstKey = $first->faction_key ?? '0';
        $secondKey = $second->faction_key ?? '0';
        if ($firstKey === $secondKey) {
            return $firstKey === '0' ? 'legacy' : 'allied';
        }

        [$firstKey, $secondKey] = $this->orderedKeys($firstKey, $secondKey);

        return DB::table('faction_diplomacy')
            ->where('universe_id', $first->universe_id)
            ->where('first_faction', $firstKey)
            ->where('second_faction', $secondKey)
            ->value('status') ?? 'neutral';
    }

    public function orderedKeys(string $first, string $second): array
    {
        return strcmp($first, $second) < 0 ? [$first, $second] : [$second, $first];
    }

    public function canStation(User $first, User $second): bool
    {
        $relation = $this->relation($first, $second);

        return $relation === 'allied' || ($relation === 'legacy' && $first->alliance_id !== null
            && (int) $first->alliance_id === (int) $second->alliance_id);
    }
}
