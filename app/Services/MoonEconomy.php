<?php

namespace App\Services;

use App\Models\Fleet;
use App\Models\MoonBuildingQueue;
use App\Models\Planet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoonEconomy
{
    private const BUILDINGS = [
        'moon_base' => ['code' => 41, 'label' => 'Base lunaire', 'cost' => [20000, 40000, 20000], 'factor' => 2, 'requirements' => []],
        'phalanx' => ['code' => 42, 'label' => 'Phalange sensorielle', 'cost' => [20000, 40000, 20000], 'factor' => 2, 'requirements' => [41 => 1]],
        'jump_gate' => ['code' => 43, 'label' => 'Porte de saut', 'cost' => [2000000, 4000000, 2000000], 'factor' => 2, 'requirements' => [41 => 1, 114 => 7]],
    ];

    public function snapshot(User $user, Planet $planet, array $moon, object $stock): array
    {
        $this->complete($planet, (int) $moon['orbit']);
        $stock = $this->stock($planet, $moon);
        $queue = MoonBuildingQueue::query()->where('planet_id', $planet->id)->where('orbit', $moon['orbit'])->orderBy('position')->get();
        $buildings = [];

        foreach (self::BUILDINGS as $key => $definition) {
            $column = $this->levelColumn($key);
            $level = (int) $stock->{$column};
            $queued = $queue->where('building_key', $key)->count();
            $cost = $this->cost($definition, $level + $queued);
            $requirementsMet = $this->requirementsMet($key, $user, $stock);
            $buildings[] = [
                'key' => $key,
                'code' => $definition['code'],
                'label' => $definition['label'],
                'level' => $level,
                'queued' => $queued,
                'cost' => $cost,
                'canBuild' => $requirementsMet && $queue->count() < 5
                    && (int) $stock->metal >= $cost['metal']
                    && (int) $stock->crystal >= $cost['crystal']
                    && (int) $stock->deuterium >= $cost['deuterium']
                    && ((int) $stock->max_fields - (int) $stock->used_fields > 0),
                'requirementsMet' => $requirementsMet,
            ];
        }

        return [
            'buildings' => $buildings,
            'energy' => 0,
            'queue' => $queue->map(fn (MoonBuildingQueue $entry): array => [
                'id' => $entry->id,
                'key' => $entry->building_key,
                'code' => (int) $entry->building_code,
                'targetLevel' => (int) $entry->target_level,
                'position' => (int) $entry->position,
                'startsAt' => $entry->starts_at->toIso8601String(),
                'completesAt' => $entry->completes_at->toIso8601String(),
                'durationSeconds' => max(1, (int) $entry->starts_at->diffInSeconds($entry->completes_at)),
                'remainingSeconds' => max(0, (int) now()->diffInSeconds($entry->completes_at, false)),
            ])->values(),
            'fields' => ['used' => (int) $stock->used_fields, 'max' => (int) $stock->max_fields],
            'unitStock' => $stock->unit_stock ? $this->decodeShips($stock->unit_stock) : [],
            'jumpGateReadyAt' => $stock->jump_gate_used_at ? Carbon::parse($stock->jump_gate_used_at)->addSeconds(max(1, intdiv(3600, max(1, (int) $stock->jump_gate_level))))->toIso8601String() : null,
        ];
    }

    public function upgrade(User $user, Planet $planet, int $orbit, string $building): void
    {
        if (! isset(self::BUILDINGS[$building])) {
            throw ValidationException::withMessages(['building' => 'Bâtiment lunaire invalide.']);
        }

        DB::transaction(function () use ($user, $planet, $orbit, $building): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedPlanet = Planet::query()->whereKey($planet->id)->where('user_id', $user->id)
                ->where('universe_id', $lockedUser->universe_id)->lockForUpdate()->firstOrFail();
            $moon = $this->ownedMoon($lockedUser, $lockedPlanet, $orbit);
            $stock = DB::table('moon_stocks')->where('planet_id', $lockedPlanet->id)->where('orbit', $orbit)->lockForUpdate()->first();
            if (! $stock) {
                throw ValidationException::withMessages(['moon' => 'Cette lune n’existe plus.']);
            }

            $this->completeLocked($lockedPlanet, $orbit, $stock);
            $stock = DB::table('moon_stocks')->where('planet_id', $lockedPlanet->id)->where('orbit', $orbit)->lockForUpdate()->firstOrFail();
            $queue = MoonBuildingQueue::query()->where('planet_id', $lockedPlanet->id)->where('orbit', $orbit)->orderBy('position')->get();
            $definition = self::BUILDINGS[$building];
            $nextLevel = (int) $stock->{$this->levelColumn($building)} + $queue->where('building_key', $building)->count() + 1;
            $projected = [41 => (int) $stock->moon_base_level, 42 => (int) $stock->phalanx_level, 43 => (int) $stock->jump_gate_level];
            foreach ($queue as $queued) {
                $projected[(int) $queued->building_code] = max($projected[(int) $queued->building_code], (int) $queued->target_level);
            }
            foreach ($definition['requirements'] as $code => $required) {
                $level = match ($code) {
                    41 => $projected[41],
                    114 => (int) $lockedUser->hyperspace_technology_level,
                    default => 0,
                };
                if ($level < $required) {
                    throw ValidationException::withMessages(['building' => 'Les prérequis de ce bâtiment lunaire ne sont pas remplis.']);
                }
            }
            if ($building === 'jump_gate' && DB::table('moon_stocks')->where('planet_id', $lockedPlanet->id)->where('jump_gate_level', '>', 0)->exists()) {
                throw ValidationException::withMessages(['building' => 'Une seule porte de saut peut exister par empire.']);
            }
            if ($queue->count() >= 5 || (int) $stock->used_fields >= (int) $stock->max_fields) {
                throw ValidationException::withMessages(['building' => 'La lune ne dispose plus de place de construction.']);
            }
            $cost = $this->cost($definition, $nextLevel - 1);
            foreach ($cost as $resource => $amount) {
                if ((int) $stock->{$resource} < $amount) {
                    throw ValidationException::withMessages(['resources' => 'Les ressources lunaires sont insuffisantes.']);
                }
            }
            $startsAt = $queue->last()?->completes_at ?? now();
            $duration = max(1, (int) ceil(($cost['metal'] + $cost['crystal']) / 2500 * 3600));
            DB::table('moon_stocks')->where('id', $stock->id)->update([
                'metal' => (int) $stock->metal - $cost['metal'],
                'crystal' => (int) $stock->crystal - $cost['crystal'],
                'deuterium' => (int) $stock->deuterium - $cost['deuterium'],
                'used_fields' => (int) $stock->used_fields + 1,
                'updated_at' => now(),
            ]);
            $completesAt = $startsAt->copy()->addSeconds($duration);
            $order = new Fleet;
            $order->forceFill([
                'universe_id' => $lockedPlanet->universe_id,
                'user_id' => $lockedUser->id,
                'origin_planet_id' => $lockedPlanet->id,
                'target_planet_id' => $lockedPlanet->id,
                'target_galaxy' => $lockedPlanet->galaxy,
                'target_system' => $lockedPlanet->system,
                'target_position' => $lockedPlanet->position,
                'target_type' => 'moon',
                'target_orbit' => $orbit,
                'moon_orbit' => $orbit,
                'moon_order' => ['buildingCode' => $definition['code'], 'targetLevel' => $nextLevel, 'cost' => $cost],
                'mission' => 'moon_building',
                'ships' => [],
                'cargo' => ['metal' => 0, 'crystal' => 0, 'deuterium' => 0],
                'status' => 'outbound',
                'arrives_at' => $completesAt,
                'returns_at' => null,
                'mission_report' => ['moonBuilding' => ['code' => $definition['code'], 'targetLevel' => $nextLevel, 'orbit' => $orbit]],
            ]);
            $order->save();
            MoonBuildingQueue::query()->create([
                'planet_id' => $lockedPlanet->id,
                'orbit' => $orbit,
                'building_key' => $building,
                'building_code' => $definition['code'],
                'target_level' => $nextLevel,
                'position' => $queue->count() + 1,
                'cost_metal' => $cost['metal'],
                'cost_crystal' => $cost['crystal'],
                'cost_deuterium' => $cost['deuterium'],
                'starts_at' => $startsAt,
                'completes_at' => $completesAt,
            ]);
        }, 3);
    }

    public function rename(User $user, Planet $planet, int $orbit, string $name): void
    {
        DB::transaction(function () use ($user, $planet, $orbit, $name): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedPlanet = Planet::query()->whereKey($planet->id)->where('user_id', $lockedUser->id)
                ->where('universe_id', $lockedUser->universe_id)->lockForUpdate()->firstOrFail();
            $this->ownedMoon($lockedUser, $lockedPlanet, $orbit);
            $stock = DB::table('moon_stocks')->where('planet_id', $lockedPlanet->id)->where('orbit', $orbit)->lockForUpdate()->first();

            if (! $stock) {
                throw ValidationException::withMessages(['moon' => 'Cette lune n’existe plus.']);
            }

            DB::table('moon_stocks')->where('id', $stock->id)->update(['name' => $name, 'updated_at' => now()]);
        }, 3);
    }

    public function complete(Planet $planet, int $orbit): void
    {
        DB::transaction(function () use ($planet, $orbit): void {
            $lockedPlanet = Planet::query()->whereKey($planet->id)->lockForUpdate()->firstOrFail();
            $stock = DB::table('moon_stocks')->where('planet_id', $lockedPlanet->id)->where('orbit', $orbit)->lockForUpdate()->first();
            if ($stock) {
                $this->completeLocked($lockedPlanet, $orbit, $stock);
            }
        }, 3);
    }

    public function stock(Planet $planet, array $moon): object
    {
        $coordinates = ['planet_id' => $planet->id, 'orbit' => (int) $moon['orbit']];
        DB::table('moon_stocks')->insertOrIgnore([...$coordinates, 'resources_updated_at' => now(), 'max_fields' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('moon_stocks')->where($coordinates)->firstOrFail();
    }

    public function jumpGateDestinations(User $user, Planet $origin, int $originOrbit): array
    {
        $destinations = [];
        foreach ($user->planets()->where('universe_id', $origin->universe_id)->get() as $planet) {
            $slots = app(GalaxyMap::class)->system($planet->universe, $user, (int) $planet->galaxy, (int) $planet->system, (int) $planet->position)['slots'];
            $moons = $slots[$planet->position - 2]['naturalMoons'] ?? [];
            foreach ($moons as $moon) {
                if ($planet->id === $origin->id && (int) $moon['orbit'] === $originOrbit) {
                    continue;
                }
                $stock = DB::table('moon_stocks')->where('planet_id', $planet->id)->where('orbit', $moon['orbit'])->first();
                if ((int) ($stock?->jump_gate_level ?? 0) < 1) {
                    continue;
                }
                $destinations[] = ['destination' => $planet->id.':'.$moon['orbit'], 'planetId' => $planet->id, 'planetName' => $planet->name, 'coordinates' => $planet->coordinates, ...$moon];
            }
        }

        return $destinations;
    }

    public function ships(Planet $planet, int $orbit): array
    {
        $moon = app(GalaxyMap::class)->ownedMoon($planet->user, $planet, $orbit);

        return $this->decodeShips($this->stock($planet, $moon)->unit_stock);
    }

    public function setShips(Planet $planet, int $orbit, array $ships): void
    {
        $moon = app(GalaxyMap::class)->ownedMoon($planet->user, $planet, $orbit);
        foreach ($ships as $code => $count) {
            if (! isset(config('combat.units')[$code]) || ! is_int($count) || $count < 0) {
                throw ValidationException::withMessages(['ships' => 'Composition de flotte lunaire invalide.']);
            }
        }
        $stock = $this->stock($planet, ['orbit' => $orbit]);
        DB::table('moon_stocks')->where('id', $stock->id)->update(['unit_stock' => json_encode($ships, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
    }

    public function resources(Planet $planet, int $orbit): array
    {
        app(GalaxyMap::class)->ownedMoon($planet->user, $planet, $orbit);
        $stock = DB::table('moon_stocks')->where('planet_id', $planet->id)->where('orbit', $orbit)->first();
        if (! $stock) {
            return ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];
        }

        return ['metal' => (int) $stock->metal, 'crystal' => (int) $stock->crystal, 'deuterium' => (int) $stock->deuterium];
    }

    public function setResources(Planet $planet, int $orbit, array $resources): void
    {
        $moon = app(GalaxyMap::class)->ownedMoon($planet->user, $planet, $orbit);
        $stock = DB::table('moon_stocks')->where('planet_id', $planet->id)->where('orbit', $orbit)->lockForUpdate()->first();
        if (! $stock) {
            throw ValidationException::withMessages(['moon' => 'Les ressources lunaires ne sont pas disponibles.']);
        }
        DB::table('moon_stocks')->where('id', $stock->id)->update([...$resources, 'updated_at' => now()]);
    }

    public function addShips(Planet $planet, int $orbit, array $ships): void
    {
        if ($ships === [] || array_sum($ships) < 1) {
            return;
        }
        $moon = app(GalaxyMap::class)->ownedMoon($planet->user, $planet, $orbit);
        $stock = DB::table('moon_stocks')->where('planet_id', $planet->id)->where('orbit', $orbit)->lockForUpdate()->first();
        if (! $stock) {
            throw ValidationException::withMessages(['ships' => 'Le stock lunaire n’existe plus.']);
        }
        if ($ships !== []) {
            foreach ($ships as $code => $count) {
                if (! isset(config('combat.units')[$code]) || ! is_int($count) || $count < 0) {
                    throw ValidationException::withMessages(['ships' => 'Composition de flotte lunaire invalide.']);
                }
            }
        }
        $fleet = $this->decodeShips($stock->unit_stock);
        foreach ($ships as $code => $count) {
            if ((int) ($fleet[$code] ?? 0) > PHP_INT_MAX - (int) $count) {
                throw new \OverflowException('La flotte lunaire dépasse la limite numérique.');
            }
            $fleet[$code] = ($fleet[$code] ?? 0) + (int) $count;
        }
        DB::table('moon_stocks')->where('id', $stock->id)->update(['unit_stock' => json_encode($fleet, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
    }

    public function moveShips(User $user, Planet $planet, int $originOrbit, int $destinationOrbit, array $ships): void
    {
        if ($originOrbit === $destinationOrbit) {
            throw ValidationException::withMessages(['destination' => 'Choisissez une autre lune.']);
        }
        foreach ($ships as $code => $count) {
            if (! isset(config('combat.units')[$code]) || ! is_int($count) || $count < 0 || $count > 1_000_000) {
                throw ValidationException::withMessages(['ships' => 'Composition de flotte lunaire invalide.']);
            }
        }
        if ($ships === [] || array_sum($ships) < 1) {
            throw ValidationException::withMessages(['ships' => 'Sélectionnez des vaisseaux à transférer.']);
        }
        DB::transaction(function () use ($user, $planet, $originOrbit, $destinationOrbit, $ships): void {
            $sourceMoon = app(GalaxyMap::class)->ownedMoon($user, $planet, $originOrbit);
            $targetMoon = app(GalaxyMap::class)->ownedMoon($user, $planet, $destinationOrbit);
            $source = DB::table('moon_stocks')->where('planet_id', $planet->id)->where('orbit', $sourceMoon['orbit'])->lockForUpdate()->first();
            $target = DB::table('moon_stocks')->where('planet_id', $planet->id)->where('orbit', $targetMoon['orbit'])->lockForUpdate()->first();
            if (! $source || ! $target || (int) $source->jump_gate_level < 1 || (int) $target->jump_gate_level < 1) {
                throw ValidationException::withMessages(['target_planet_id' => 'Les deux lunes doivent posséder une porte de saut.']);
            }
            $cooldown = max(1, intdiv(3600, max(1, (int) $source->jump_gate_level)));
            $sourceUsed = $source->jump_gate_used_at ? Carbon::parse($source->jump_gate_used_at) : null;
            $targetUsed = $target->jump_gate_used_at ? Carbon::parse($target->jump_gate_used_at) : null;
            if (($sourceUsed && now()->lt($sourceUsed->addSeconds($cooldown))) || ($targetUsed && now()->lt($targetUsed->addSeconds(max(1, intdiv(3600, max(1, (int) $target->jump_gate_level))))))) {
                throw ValidationException::withMessages(['target_planet_id' => 'Une des portes de saut est encore en recharge.']);
            }
            $this->removeShips($planet, $originOrbit, $ships);
            $this->addShips($planet, $destinationOrbit, $ships);
            DB::table('moon_stocks')->whereIn('id', [$source->id, $target->id])->update(['jump_gate_used_at' => now(), 'updated_at' => now()]);
        }, 3);
    }

    public function removeShips(Planet $planet, int $orbit, array $ships): void
    {
        if ($ships === [] || array_sum($ships) < 1) {
            throw ValidationException::withMessages(['ships' => 'Sélectionnez les vaisseaux lunaires à envoyer.']);
        }
        $stock = DB::table('moon_stocks')->where('planet_id', $planet->id)->where('orbit', $orbit)->lockForUpdate()->first();
        if (! $stock) {
            throw ValidationException::withMessages(['ships' => 'La lune ne possède pas de stock de flotte.']);
        }
        $fleet = $this->decodeShips($stock->unit_stock);
        foreach ($ships as $code => $count) {
            if (! isset(config('combat.units')[$code]) || ! is_int($count) || $count < 0) {
                throw ValidationException::withMessages(['ships' => 'Composition de flotte lunaire invalide.']);
            }
            if ((int) ($fleet[$code] ?? 0) < (int) $count) {
                throw ValidationException::withMessages(['ships' => 'La flotte lunaire ne dispose pas de ces vaisseaux.']);
            }
            if ((int) $count === 0) {
                continue;
            }
            $fleet[$code] -= (int) $count;
            if ($fleet[$code] === 0) {
                unset($fleet[$code]);
            }
        }
        DB::table('moon_stocks')->where('id', $stock->id)->update(['unit_stock' => json_encode($fleet, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
    }

    private function completeLocked(Planet $planet, int $orbit, object $stock): void
    {
        $stock = DB::table('moon_stocks')->where('planet_id', $planet->id)->where('orbit', $orbit)->lockForUpdate()->firstOrFail();
        $queue = MoonBuildingQueue::query()->where('planet_id', $planet->id)->where('orbit', $orbit)
            ->where('completes_at', '<=', now())->orderBy('position')->lockForUpdate()->get();
        foreach ($queue as $entry) {
            if (isset(self::BUILDINGS[$entry->building_key])) {
                DB::table('moon_stocks')->where('id', $stock->id)->increment($this->levelColumn($entry->building_key));
            } else {
                $column = ['metal_mine' => 'metal_mine_level', 'crystal_mine' => 'crystal_mine_level', 'deuterium_synthesizer' => 'deuterium_synthesizer_level', 'solar_plant' => 'solar_plant_level'][$entry->building_key];
                DB::table('moon_stocks')->where('id', $stock->id)->increment($column);
            }
            if ($entry->building_key === 'moon_base') {
                DB::table('moon_stocks')->where('id', $stock->id)->increment('max_fields', 4);
            }
            $entry->delete();
        }
        $this->repositionQueue($planet, $orbit);
    }

    private function repositionQueue(Planet $planet, int $orbit): void
    {
        MoonBuildingQueue::query()->where('planet_id', $planet->id)->where('orbit', $orbit)->orderBy('position')->get()
            ->each(fn (MoonBuildingQueue $entry, int $index) => $entry->update(['position' => $index + 1]));
    }

    private function requirementsMet(string $building, User $user, object $stock): bool
    {
        return match ($building) {
            'moon_base' => true,
            'phalanx' => (int) $stock->moon_base_level >= 1,
            'jump_gate' => (int) $stock->moon_base_level >= 1 && (int) $user->hyperspace_technology_level >= 7,
        };
    }

    private function levelColumn(string $building): string
    {
        return match ($building) {
            'moon_base' => 'moon_base_level',
            'phalanx' => 'phalanx_level',
            'jump_gate' => 'jump_gate_level',
        };
    }

    private function cost(array $definition, int $level): array
    {
        $multiplier = $definition['factor'] ** $level;

        return ['metal' => (int) ceil($definition['cost'][0] * $multiplier), 'crystal' => (int) ceil($definition['cost'][1] * $multiplier), 'deuterium' => (int) ceil($definition['cost'][2] * $multiplier)];
    }

    private function decodeShips(?string $json): array
    {
        if (! $json) {
            return [];
        }
        $ships = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        foreach ($ships as $code => $count) {
            if (! isset(config('combat.units')[$code]) || ! is_int($count) || $count < 0) {
                throw new \UnexpectedValueException('Invalid moon fleet stock.');
            }
        }

        return $ships;
    }

    private function ownedMoon(User $user, Planet $planet, int $orbit): array
    {
        return app(GalaxyMap::class)->ownedMoon($user, $planet, $orbit);
    }
}
