<?php

namespace App\Services;

use App\Models\Fleet;
use App\Models\Planet;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExplorationMissions
{
    private const CAPACITY = [
        202 => 5000, 203 => 25000, 204 => 50, 205 => 100, 206 => 800,
        207 => 1500, 208 => 7500, 209 => 20000, 210 => 5, 211 => 500,
        213 => 2000, 214 => 1000000, 215 => 750, 216 => 10000,
    ];

    public function __construct(
        private GalaxyMap $map,
        private PlanetEconomy $economy,
        private UnitProductionService $production,
        private ResearchService $research,
        private CombatSimulatorService $combat,
    ) {}

    public function send(User $user, Planet $planet, string $mission, int $galaxy, int $system, int $position, array $ships): Fleet
    {
        $this->processDue();
        $this->research->complete($user);
        $this->economy->materialize($planet);
        $this->production->complete($planet);

        return DB::transaction(function () use ($user, $planet, $mission, $galaxy, $system, $position, $ships): Fleet {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $planet = Planet::query()->whereKey($planet->id)->where('user_id', $user->id)
                ->where('universe_id', $user->universe_id)->lockForUpdate()->firstOrFail();
            $universe = $user->universe;
            if (! $universe || $galaxy < 1 || $galaxy > min(9, $universe->galaxies)
                || $system < 1 || $system > min(499, $universe->systems_per_galaxy)) {
                throw ValidationException::withMessages(['target' => 'Coordonnées invalides.']);
            }
            if ($mission === 'expedition') {
                if ($position !== 16 || (int) $user->expedition_technology_level < 1) {
                    throw ValidationException::withMessages(['target' => 'La technologie expédition est nécessaire pour explorer la zone obscure.']);
                }
                if ($user->fleets()->where('mission', 'expedition')->whereIn('status', ['outbound', 'returning'])->count() >= (int) $user->expedition_technology_level) {
                    throw ValidationException::withMessages(['mission' => 'Toutes vos places d’expédition sont occupées.']);
                }
            } elseif ($mission === 'recycle') {
                if ($position < 2 || $position > 15) {
                    throw ValidationException::withMessages(['target' => 'Ce champ est hors de portée de la technologie espionnage.']);
                }
                $slot = $this->map->system($universe, $user, $galaxy, $system, $planet->position)['slots'][$position - 2];
                if (! $slot['visible']) {
                    throw ValidationException::withMessages(['target' => 'Ce champ est hors de portée de la technologie espionnage.']);
                }
                if ($slot['kind'] !== 'asteroid' || ($ships[209] ?? 0) < 1) {
                    throw ValidationException::withMessages(['target' => 'Un champ d’astéroïdes et au moins un recycleur sont nécessaires.']);
                }
            } else {
                throw ValidationException::withMessages(['mission' => 'Mission inconnue.']);
            }
            $selected = [];
            foreach ($ships as $code => $count) {
                if (! isset(self::CAPACITY[$code]) || ! is_int($count) || $count < 0 || $count > 1000000) {
                    throw ValidationException::withMessages(['ships' => 'Composition de flotte invalide.']);
                }
                if ($count > 0) {
                    $column = 'unit_'.$code;
                    if ((int) $planet->{$column} < $count) {
                        throw ValidationException::withMessages(['ships' => 'Vous ne disposez pas de ces vaisseaux.']);
                    }
                    $selected[(int) $code] = $count;
                    $planet->{$column} -= $count;
                }
            }
            if ($selected === []) {
                throw ValidationException::withMessages(['ships' => 'Sélectionnez au moins un vaisseau.']);
            }
            $distance = $galaxy !== (int) $planet->galaxy ? 20000 * abs($galaxy - $planet->galaxy)
                : ($system !== (int) $planet->system ? 2700 + 95 * abs($system - $planet->system) : 1000 + 5 * abs($position - $planet->position));
            $fleetSpeed = (float) $universe->fleet_speed;
            if ($fleetSpeed <= 0) {
                throw ValidationException::withMessages(['mission' => 'La vitesse des flottes de cet univers est invalide.']);
            }
            $flightSeconds = max(60, (int) ceil((600 + $distance) / $fleetSpeed));
            $fuel = (int) ceil(array_sum($selected) * $distance / 1000);
            if ((int) $planet->deuterium < $fuel) {
                throw ValidationException::withMessages(['ships' => 'Deutérium insuffisant pour le voyage.']);
            }
            $planet->deuterium -= $fuel;
            $planet->save();
            $fleet = new Fleet;
            $fleet->forceFill([
                'universe_id' => $universe->id, 'user_id' => $user->id, 'origin_planet_id' => $planet->id,
                'mission' => $mission, 'ships' => $selected, 'status' => 'outbound',
                'target_galaxy' => $galaxy, 'target_system' => $system, 'target_position' => $position,
                'arrives_at' => now()->addSeconds($flightSeconds),
                'returns_at' => now()->addSeconds(2 * $flightSeconds + ($mission === 'expedition' ? 3600 : 0)),
            ])->save();

            return $fleet;
        }, 3);
    }

    public function processDue(): void
    {
        while (true) {
            $processed = DB::transaction(function (): bool {
                $fleet = Fleet::query()->whereNotNull('target_position')
                    ->where(function ($query): void {
                        $query->where(fn ($query) => $query->where('status', 'outbound')->where('arrives_at', '<=', now()))
                            ->orWhere(fn ($query) => $query->where('status', 'returning')->where('returns_at', '<=', now()));
                    })
                    ->orderByRaw('case when status = ? then returns_at else arrives_at end', ['returning'])
                    ->orderBy('id')->lockForUpdate()->first();
                if (! $fleet) {
                    return false;
                }
                if ($fleet->status === 'outbound') {
                    $this->arrive($fleet);
                } else {
                    $this->returnFleet($fleet);
                }

                return true;
            }, 3);
            if (! $processed) {
                return;
            }
        }
    }

    public function sendMoon(User $user, Planet $planet, int $orbit, string $direction, string $resource, int $amount, array $ships): Fleet
    {
        $this->processDue();
        $this->economy->materialize($planet);
        $this->production->complete($planet);

        return DB::transaction(function () use ($user, $planet, $orbit, $direction, $resource, $amount, $ships): Fleet {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $planet = Planet::query()->whereKey($planet->id)->where('user_id', $user->id)
                ->where('universe_id', $user->universe_id)->lockForUpdate()->firstOrFail();
            $this->map->ownedMoon($user, $planet, $orbit);
            if (! in_array($direction, ['deposit', 'withdraw'], true) || ! in_array($resource, ['metal', 'crystal', 'deuterium'], true) || $amount < 1 || $amount > 1000000000) {
                throw ValidationException::withMessages(['amount' => 'Transfert invalide.']);
            }
            $selected = [];
            $capacity = 0;
            foreach ($ships as $code => $count) {
                if (! in_array((int) $code, [202, 203, 209], true) || ! is_int($count) || $count < 0 || $count > 1000000) {
                    throw ValidationException::withMessages(['ships' => 'Composition de flotte invalide.']);
                }
                if ($count > 0) {
                    $column = 'unit_'.$code;
                    if ((int) $planet->{$column} < $count) {
                        throw ValidationException::withMessages(['ships' => 'Vous ne disposez pas de ces vaisseaux.']);
                    }
                    $selected[(int) $code] = $count;
                    $capacity += self::CAPACITY[$code] * $count;
                    $planet->{$column} -= $count;
                }
            }
            if ($selected === [] || $capacity < $amount) {
                throw ValidationException::withMessages(['ships' => 'La capacité de transport est insuffisante.']);
            }
            $fuel = array_sum($selected);
            if ((int) $planet->deuterium < $fuel + ($direction === 'deposit' && $resource === 'deuterium' ? $amount : 0)) {
                throw ValidationException::withMessages(['ships' => 'Deutérium insuffisant pour le voyage.']);
            }
            $planet->deuterium -= $fuel;
            if ($direction === 'deposit') {
                if ((int) $planet->{$resource} < $amount) {
                    throw ValidationException::withMessages(['amount' => 'Les ressources de la planète sont insuffisantes.']);
                }
                $planet->{$resource} -= $amount;
            } else {
                $stock = DB::table('moon_stocks')->where('planet_id', $planet->id)->where('orbit', $orbit)->lockForUpdate()->first();
                if (! $stock || (int) $stock->{$resource} < $amount) {
                    throw ValidationException::withMessages(['amount' => 'Les ressources de la lune sont insuffisantes.']);
                }
                DB::table('moon_stocks')->where('id', $stock->id)->update([$resource => (int) $stock->{$resource} - $amount, 'updated_at' => now()]);
            }
            $fleetSpeed = (float) $planet->universe->fleet_speed;
            if ($fleetSpeed <= 0) {
                throw ValidationException::withMessages(['direction' => 'La vitesse des flottes de cet univers est invalide.']);
            }
            $flightSeconds = max(60, (int) ceil(300 / $fleetSpeed));
            $planet->save();
            $cargo = ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];
            $cargo[$resource] = $amount;
            $fleet = new Fleet;
            $fleet->forceFill([
                'universe_id' => $planet->universe_id, 'user_id' => $user->id,
                'origin_planet_id' => $planet->id, 'target_planet_id' => $planet->id,
                'mission' => 'moon_'.$direction, 'ships' => $selected, 'status' => 'outbound',
                'target_galaxy' => $planet->galaxy, 'target_system' => $planet->system, 'target_position' => $planet->position,
                'moon_orbit' => $orbit, 'moon_order' => ['resource' => $resource, 'amount' => $amount], 'cargo' => $cargo,
                'arrives_at' => now()->addSeconds($flightSeconds), 'returns_at' => now()->addSeconds(2 * $flightSeconds),
            ])->save();

            return $fleet;
        }, 3);
    }

    private function arrive(Fleet $fleet): void
    {
        if (in_array($fleet->mission, ['moon_deposit', 'moon_withdraw'], true)) {
            $this->arriveMoon($fleet);

            return;
        }
        $capacity = 0;
        foreach ($fleet->ships as $code => $count) {
            $capacity += self::CAPACITY[$code] * $count;
        }
        $cargo = ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];
        if ($fleet->mission === 'recycle') {
            $universe = $fleet->universe;
            $viewer = $fleet->user;
            $slot = $this->map->system($universe, $viewer, $fleet->target_galaxy, $fleet->target_system, $fleet->target_position)['slots'][$fleet->target_position - 2];
            if (! $slot['visible'] || $slot['kind'] !== 'asteroid') {
                $fleet->outcome = 'field_unavailable';
            } else {
                $coordinates = ['universe_id' => $fleet->universe_id, 'galaxy' => $fleet->target_galaxy, 'system' => $fleet->target_system, 'position' => $fleet->target_position];
                DB::table('asteroid_fields')->insertOrIgnore([...$coordinates,
                    ...$slot['remaining'],
                    'created_at' => now(), 'updated_at' => now()]);
                $field = DB::table('asteroid_fields')->where($coordinates)->lockForUpdate()->first();
                $capacity = min($capacity, (int) ($fleet->ships[209] ?? 0) * self::CAPACITY[209]);
                foreach ($cargo as $resource => $value) {
                    $cargo[$resource] = min($capacity, (int) $field->{$resource});
                    $capacity -= $cargo[$resource];
                }
                DB::table('asteroid_fields')->where('id', $field->id)->update([
                    'metal' => $field->metal - $cargo['metal'], 'crystal' => $field->crystal - $cargo['crystal'],
                    'deuterium' => $field->deuterium - $cargo['deuterium'], 'updated_at' => now(),
                ]);
                $fleet->outcome = 'recycled';
            }
        } else {
            $roll = $this->random(1, 100);
            if ($roll <= 40) {
                $resource = ['metal', 'crystal', 'deuterium'][$this->random(0, 2)];
                $cargo[$resource] = min($capacity, $this->random(1000, 10000));
                $fleet->outcome = 'resources';
            } elseif ($roll <= 60) {
                $fleet->outcome = 'nothing';
            } elseif ($roll <= 70) {
                $ships = $fleet->ships;
                $ships[202] = ($ships[202] ?? 0) + $this->random(1, 3);
                $fleet->ships = $ships;
                $fleet->outcome = 'ship_found';
            } elseif ($roll <= 80) {
                $fleet->returns_at = $fleet->returns_at->addHour();
                $fleet->outcome = 'delay';
            } elseif ($roll <= 90) {
                if (array_sum($fleet->ships) <= (int) config('combat.max_units_per_side') && ! ($fleet->ships[216] ?? 0)) {
                    $this->encounter($fleet, $roll <= 85 ? 'pirates' : 'aliens');
                } else {
                    $ships = $fleet->ships;
                    foreach ($ships as $code => $count) {
                        $ships[$code] = max(0, $count - (int) ceil($count * 0.1));
                    }
                    $fleet->ships = $ships;
                    $fleet->outcome = 'losses';
                }
            } elseif ($roll <= 95) {
                $returnFlightSeconds = max(0, (int) $fleet->arrives_at->diffInSeconds($fleet->returns_at) - 3600);
                $fleet->returns_at = $fleet->returns_at->subSeconds(intdiv($returnFlightSeconds, 2));
                $fleet->outcome = 'early';
            } else {
                $fleet->ships = [];
                $fleet->outcome = 'lost';
            }
        }
        $fleet->cargo = $cargo;
        $fleet->status = 'returning';
        $fleet->save();
    }

    private function arriveMoon(Fleet $fleet): void
    {
        if ($fleet->mission === 'moon_deposit') {
            $coordinates = ['planet_id' => $fleet->target_planet_id, 'orbit' => $fleet->moon_orbit];
            DB::table('moon_stocks')->insertOrIgnore([...$coordinates, 'created_at' => now(), 'updated_at' => now()]);
            $stock = DB::table('moon_stocks')->where($coordinates)->lockForUpdate()->first();
            $updated = ['updated_at' => now()];
            foreach ($fleet->cargo as $resource => $amount) {
                if ((int) $stock->{$resource} > PHP_INT_MAX - $amount) {
                    throw new \OverflowException('Le stock lunaire dépasse la limite des ressources.');
                }
                $updated[$resource] = (int) $stock->{$resource} + $amount;
            }
            DB::table('moon_stocks')->where('id', $stock->id)->update($updated);
            $fleet->cargo = ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];
            $fleet->outcome = 'moon_deposit';
        } else {
            $fleet->outcome = 'moon_withdraw';
        }
        $fleet->status = 'returning';
        $fleet->save();
    }

    private function encounter(Fleet $fleet, string $enemy): void
    {
        if ($fleet->user->researchQueues()->where('completes_at', '<=', $fleet->arrives_at)->exists()) {
            $this->research->complete($fleet->user);
        }
        $user = $fleet->user->fresh();
        $technology = ['weapons' => (int) $user->military_technology_level, 'shields' => (int) $user->defence_technology_level, 'armour' => (int) $user->shield_technology_level];
        $modifier = $enemy === 'pirates' ? -3 : 3;
        $opponentTechnology = array_map(fn (int $level): int => max(0, $level + $modifier), $technology);
        $opponentCount = max(1, (int) ceil(array_sum($fleet->ships) * ($enemy === 'pirates' ? 0.3 : 0.5)));
        $result = $this->combat->simulate(
            ['fleet' => $fleet->ships, 'technologies' => $technology],
            ['fleet' => [$enemy === 'pirates' ? 204 : 206 => $opponentCount], 'technologies' => $opponentTechnology],
        );
        $ships = [];
        foreach ($result['attacker']['units'] as $unit) {
            if ($unit['remaining'] > 0) {
                $ships[$unit['code']] = $unit['remaining'];
            }
        }
        $fleet->ships = $ships;
        $fleet->outcome = $enemy;
        $fleet->combat_report = $result;
    }

    protected function random(int $minimum, int $maximum): int
    {
        return random_int($minimum, $maximum);
    }

    private function returnFleet(Fleet $fleet): void
    {
        $planet = Planet::query()->whereKey($fleet->origin_planet_id)->where('user_id', $fleet->user_id)->lockForUpdate()->firstOrFail();
        $this->economy->materialize($planet, Carbon::instance($fleet->returns_at));
        $planet->refresh();
        foreach ($fleet->ships as $code => $count) {
            $column = 'unit_'.$code;
            if ((int) $planet->{$column} > PHP_INT_MAX - $count) {
                throw new \OverflowException('La flotte de retour dépasse la limite de stockage des vaisseaux.');
            }
            $planet->{$column} += $count;
        }
        foreach ($fleet->cargo as $resource => $amount) {
            if ((int) $planet->{$resource} > PHP_INT_MAX - $amount) {
                throw new \OverflowException('Le chargement dépasse la limite de stockage des ressources.');
            }
            $planet->{$resource} += $amount;
        }
        $planet->save();
        $fleet->status = 'completed';
        $fleet->save();
    }
}
