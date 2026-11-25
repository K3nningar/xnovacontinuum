<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Implements the ExplorationMissions application service.
 */

namespace App\Services;

use App\Models\Fleet;
use App\Models\Planet;
use App\Models\Universe;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExplorationMissions
{
    private const CAPACITY = [
        202 => 5000, 203 => 25000, 204 => 50, 205 => 100, 206 => 800,
        207 => 1500, 208 => 7500, 209 => 20000, 210 => 5, 211 => 500,
        213 => 2000, 214 => 1000000, 215 => 750, 216 => 10000, 217 => 0,
    ];

    /**
     * Injects map/economy, production/research, and combat collaborators used by mission dispatch and resolution.
     */
    public function __construct(
        private GalaxyMap $map,
        private PlanetEconomy $economy,
        private UnitProductionService $production,
        private ResearchService $research,
        private CombatSimulatorService $combat,
        private MoonEconomy $moonEconomy,
    ) {}

    /**
     * Validates and dispatches an expedition or recycling fleet from an owned planet.
     *
     * Due missions, research, planet resources, and unit queues are settled before locking the user and planet.
     * The transaction then verifies coordinates, mission-specific technology/visibility and ship stock, deducts
     * fuel and ships, and records outbound/return times. Invalid orders do not retain partial deductions.
     *
     * @param  array<int|string, int>  $ships  Unit code to requested quantity.
     */
    public function send(User $user, Planet $planet, string $mission, int $galaxy, int $system, int $position, array $ships, array $options = []): Fleet
    {
        foreach ($ships as $code => $count) {
            if (! isset(self::CAPACITY[$code]) || ! is_int($count) || $count < 0 || $count > 1_000_000) {
                throw ValidationException::withMessages(['ships' => 'Composition de flotte invalide.']);
            }
        }
        if (! in_array($mission, ['transport', 'attack', 'station', 'spy', 'colonize', 'recycle', 'destroy_moon', 'destroy_planet', 'expedition'], true)) {
            throw ValidationException::withMessages(['mission' => 'Mission inconnue.']);
        }
        $this->processDue();
        $this->research->complete($user);

        return DB::transaction(function () use ($user, $planet, $mission, $galaxy, $system, $position, $ships, $options): Fleet {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $targetId = Planet::query()->where('universe_id', $user->universe_id)
                ->where('galaxy', $galaxy)->where('system', $system)->where('position', $position)
                ->whereNull('destroyed_at')->value('id');
            $planetIds = array_unique(array_filter([(int) $planet->id, $targetId ? (int) $targetId : null]));
            sort($planetIds);
            foreach ($planetIds as $planetId) {
                Planet::query()->whereKey($planetId)->lockForUpdate()->first();
            }
            $planet = Planet::query()->whereKey($planet->id)->where('user_id', $user->id)
                ->where('universe_id', $user->universe_id)->lockForUpdate()->firstOrFail();
            $this->economy->materialize($planet);
            $planet->refresh();
            $this->production->complete($planet);
            $planet->refresh();
            $universe = $user->universe;
            if (! $universe || $galaxy < 1 || $galaxy > min(9, $universe->galaxies)
                || $system < 1 || $system > min(499, $universe->systems_per_galaxy)) {
                throw ValidationException::withMessages(['target' => 'Coordonnées invalides.']);
            }
            if ($mission === 'expedition' && (int) $user->expedition_technology_level > 0 && $user->fleets()->where('mission', 'expedition')->whereIn('status', ['outbound', 'returning'])->count() >= (int) $user->expedition_technology_level) {
                throw ValidationException::withMessages(['mission' => 'Toutes vos places d’expédition sont occupées.']);
            }
            $target = $this->validateDestination($user, $planet, $universe, $galaxy, $system, $position, $mission, $ships, $options);
            if ($mission === 'recycle' && ($options['target_type'] ?? $this->inferRecycleType($universe, $user, $galaxy, $system, $position, $planet->position)) === 'asteroid') {
                $slot = $this->map->system($universe, $user, $galaxy, $system, $planet->position)['slots'][$position - 2];
                $coordinates = ['universe_id' => $universe->id, 'galaxy' => $galaxy, 'system' => $system, 'position' => $position];
                if (($slot['kind'] ?? null) === 'asteroid') {
                    DB::table('asteroid_fields')->insertOrIgnore([...$coordinates, ...($slot['remaining'] ?? ['metal' => 0, 'crystal' => 0, 'deuterium' => 0]), 'created_at' => now(), 'updated_at' => now()]);
                }
                $target = null;
            }
            $selected = [];
            if (($options['origin_target_type'] ?? 'planet') === 'moon') {
                $selected = array_filter($ships, fn (int $count): bool => $count > 0);
                $this->moonEconomy()->removeShips($planet, (int) $options['origin_moon_orbit'], $selected);
            } else {
                foreach ($ships as $code => $count) {
                    if ($count > 0) {
                        $column = 'unit_'.$code;
                        if ((int) $planet->{$column} < $count) {
                            throw ValidationException::withMessages(['ships' => 'Vous ne disposez pas de ces vaisseaux.']);
                        }
                        $selected[(int) $code] = $count;
                        $planet->{$column} -= $count;
                    }
                }
            }
            if ($selected === []) {
                throw ValidationException::withMessages(['ships' => 'Sélectionnez au moins un vaisseau.']);
            }
            $distance = $galaxy !== (int) $planet->galaxy ? 20000 * abs($galaxy - $planet->galaxy)
                : ($system !== (int) $planet->system ? 2700 + 95 * abs($system - $planet->system) : 1000 + 5 * abs($position - $planet->position));
            if ($distance > intdiv(PHP_INT_MAX, max(1, array_sum($selected)))) {
                throw ValidationException::withMessages(['target' => 'La distance dépasse les limites numériques du voyage.']);
            }
            $fleetSpeed = (float) $universe->fleet_speed;
            if ($fleetSpeed <= 0) {
                throw ValidationException::withMessages(['mission' => 'La vitesse des flottes de cet univers est invalide.']);
            }
            $flightSeconds = max(60, (int) ceil((600 + $distance) / $fleetSpeed));
            $fuel = (int) ceil(array_sum($selected) * $distance / 1000);
            if (($options['origin_target_type'] ?? 'planet') !== 'moon' && (int) $planet->deuterium < $fuel) {
                throw ValidationException::withMessages(['ships' => 'Deutérium insuffisant pour le voyage.']);
            }
            if (($options['origin_target_type'] ?? 'planet') !== 'moon') {
                $planet->deuterium -= $fuel;
            }
            $cargo = ['metal' => 0, 'crystal' => 0, 'deuterium' => 0, ...($options['cargo'] ?? [])];
            $moonStock = null;
            if (($options['origin_target_type'] ?? 'planet') === 'moon') {
                $moon = $this->map->ownedMoon($user, $planet, (int) $options['origin_moon_orbit']);
                $moonStock = $this->moonEconomy()->stock($planet, $moon);
                if ($mission !== 'transport' && (int) $moonStock->deuterium < $fuel) {
                    throw ValidationException::withMessages(['ships' => 'Deutérium lunaire insuffisant pour le voyage.']);
                }
                if ($mission !== 'transport') {
                    DB::table('moon_stocks')->where('id', $moonStock->id)->decrement('deuterium', $fuel);
                }
            }
            foreach ($cargo as $resource => $amount) {
                if ($mission === 'transport' && ($options['origin_target_type'] ?? 'planet') !== 'moon' && (int) $planet->{$resource} < $amount) {
                    throw ValidationException::withMessages(['cargo' => 'Les ressources de départ sont insuffisantes.']);
                }
                if ($mission === 'transport') {
                    if (($options['origin_target_type'] ?? 'planet') === 'moon') {
                        if ((int) $moonStock->{$resource} < $amount) {
                            throw ValidationException::withMessages(['cargo' => 'Les ressources lunaires de départ sont insuffisantes.']);
                        }
                        DB::table('moon_stocks')->where('id', $moonStock->id)->decrement($resource, $amount);
                    } else {
                        $planet->{$resource} -= $amount;
                    }
                }
            }
            if (($options['origin_target_type'] ?? 'planet') !== 'moon') {
                $planet->save();
            }
            $fleet = new Fleet;
            $fleet->forceFill([
                'universe_id' => $universe->id, 'user_id' => $user->id, 'origin_planet_id' => $planet->id,
                'mission' => $mission, 'ships' => $selected, 'status' => 'outbound',
                'target_planet_id' => $target?->id, 'target_galaxy' => $galaxy, 'target_system' => $system, 'target_position' => $position,
                'target_type' => $options['target_type'] ?? match ($mission) {
                    'destroy_moon' => 'moon', 'recycle' => $this->inferRecycleType($universe, $user, $galaxy, $system, $position, $planet->position), default => 'planet'
                },
                'moon_orbit' => $mission === 'destroy_moon' ? ($options['moon_orbit'] ?? null) : null,
                'target_orbit' => ($options['target_type'] ?? 'planet') === 'moon' ? ($options['moon_orbit'] ?? null) : null,
                'origin_orbit' => ($options['origin_target_type'] ?? 'planet') === 'moon' ? ($options['origin_moon_orbit'] ?? null) : null,
                'cargo' => $mission === 'transport' ? $cargo : ['metal' => 0, 'crystal' => 0, 'deuterium' => 0],
                'arrives_at' => now()->addSeconds($flightSeconds),
                'returns_at' => now()->addSeconds(2 * $flightSeconds + ($mission === 'expedition' ? 3600 : 0)),
                'mission_report' => ['target' => ['galaxy' => $galaxy, 'system' => $system, 'position' => $position, 'orbit' => ($options['target_type'] ?? 'planet') === 'moon' ? (int) ($options['moon_orbit'] ?? 0) : null, 'diameter' => ($options['target_type'] ?? 'planet') === 'moon' ? collect($this->map->system($universe, $user, $galaxy, $system, $planet->position)['slots'][$position - 2]['naturalMoons'] ?? [])->firstWhere('orbit', (int) ($options['moon_orbit'] ?? 0))['diameter'] ?? null : null, 'type' => $options['target_type'] ?? match ($mission) {
                    'destroy_moon' => 'moon', 'recycle' => $this->inferRecycleType($universe, $user, $galaxy, $system, $position, $planet->position), default => 'planet'
                }]],
            ])->save();

            return $fleet;
        }, 3);
    }

    /**
     * Determines whether a coordinate resolves to an asteroid or an orbital-debris field.
     */
    private function inferRecycleType(Universe $universe, User $user, int $galaxy, int $system, int $position, int $originPosition): string
    {
        $slot = $this->map->system($universe, $user, $galaxy, $system, $originPosition)['slots'][$position - 2];

        return ($slot['kind'] ?? null) === 'asteroid' ? 'asteroid' : 'debris';
    }

    /**
     * Validates mission-specific target rules and returns the matching destination planet when applicable.
     *
     * @param  array<int|string, int>  $ships
     * @param  array<string, mixed>  $options
     */
    private function validateDestination(User $user, Planet $origin, Universe $universe, int $galaxy, int $system, int $position, string $mission, array $ships, array $options): ?Planet
    {
        $targetType = $options['target_type'] ?? match ($mission) {
            'destroy_moon' => 'moon',
            'recycle' => 'asteroid',
            default => 'planet',
        };
        $expectedType = match ($mission) {
            'destroy_moon' => 'moon',
            'recycle' => in_array($targetType, ['asteroid', 'debris'], true) ? $targetType : null,
            'station' => in_array($targetType, ['depot', 'planet', 'moon'], true) ? $targetType : null,
            'transport', 'attack', 'spy' => in_array($targetType, ['planet', 'moon'], true) ? $targetType : null,
            default => 'planet',
        };
        if ($expectedType === null || $targetType !== $expectedType) {
            throw ValidationException::withMessages(['target_type' => 'Le type de cible ne correspond pas à la mission.']);
        }
        if (in_array($mission, ['transport', 'attack', 'station', 'spy', 'colonize', 'destroy_moon', 'destroy_planet'], true) && $position > 15) {
            throw ValidationException::withMessages(['target' => 'Cette mission nécessite une planète cible valide.']);
        }
        if (($options['origin_target_type'] ?? 'planet') === 'moon') {
            $this->map->ownedMoon($user, $origin, (int) ($options['origin_moon_orbit'] ?? 0));
        }
        if ($mission === 'destroy_planet' && ! config('combat.experimental_planetary_destroyer', false)) {
            throw ValidationException::withMessages(['mission' => 'La destruction planétaire expérimentale est désactivée.']);
        }
        if ($mission === 'destroy_planet' && (($ships[217] ?? 0) !== 1 || array_sum($ships) !== 1)) {
            throw ValidationException::withMessages(['ships' => 'Cette mission nécessite uniquement un destructeur planétaire.']);
        }
        if ($mission === 'destroy_planet' && (int) $user->officer_emperor_level < 1) {
            throw ValidationException::withMessages(['mission' => 'Cette mission nécessite l’officier empereur.']);
        }
        if ($mission === 'spy' && (array_sum($ships) !== (int) ($ships[210] ?? 0))) {
            throw ValidationException::withMessages(['ships' => 'Une mission d’espionnage ne peut contenir que des sondes.']);
        }
        if ($mission === 'colonize' && (($ships[208] ?? 0) < 1)) {
            throw ValidationException::withMessages(['ships' => 'Un vaisseau de colonisation est nécessaire.']);
        }
        if ($mission === 'recycle' && (($ships[209] ?? 0) < 1)) {
            throw ValidationException::withMessages(['ships' => 'Un recycleur est nécessaire.']);
        }
        if ($mission === 'expedition' && ($position !== 16 || (int) $user->expedition_technology_level < 1)) {
            throw ValidationException::withMessages(['target' => 'La technologie expédition est nécessaire pour explorer la zone obscure.']);
        }
        if ($mission === 'recycle' && $targetType === 'asteroid' && $position >= 2 && $position <= 15 && abs($position - (int) $origin->position) > (int) $user->spy_technology_level) {
            throw ValidationException::withMessages(['target' => 'Ce champ est hors de portée de la technologie espionnage.']);
        }

        $coordinates = ['universe_id' => $universe->id, 'galaxy' => $galaxy, 'system' => $system, 'position' => $position];
        $target = Planet::query()->where($coordinates)->whereNull('destroyed_at')->lockForUpdate()->first();
        $cargo = array_filter($options['cargo'] ?? [], fn ($amount): bool => $amount > 0);
        $capacity = 0;
        foreach ($ships as $code => $count) {
            if (isset(self::CAPACITY[$code]) && $count > 0 && ! in_array((int) $code, [208, 210, 217], true)) {
                $capacity += self::CAPACITY[$code] * $count;
            }
        }
        if ($mission === 'transport') {
            if ($cargo !== []) {
                $this->assertCargo($cargo, $capacity);
            }
            $sameOriginPlanet = $target && (int) $target->id === (int) $origin->id && $targetType === 'planet';
            $sameOriginMoon = $target && (int) $target->id === (int) $origin->id
                && $targetType === 'moon' && ($options['origin_target_type'] ?? 'planet') === 'moon'
                && (int) ($options['moon_orbit'] ?? 0) === (int) ($options['origin_moon_orbit'] ?? 0);
            if (! $target || (int) $target->user_id !== (int) $user->id || $sameOriginPlanet || $sameOriginMoon || $cargo === []) {
                throw ValidationException::withMessages(['target' => 'Le transport nécessite une autre planète ou lune de votre empire et une cargaison.']);
            }
            foreach ($cargo as $resource => $amount) {
                if ($targetType === 'planet' && (int) $target->{$resource} > PHP_INT_MAX - $amount) {
                    throw ValidationException::withMessages(['cargo' => 'La cargaison dépasse la capacité entière de stockage.']);
                }
            }
        } elseif ($mission !== 'transport' && $cargo !== []) {
            throw ValidationException::withMessages(['cargo' => 'Cette mission ne peut pas transporter de cargaison.']);
        }
        if (in_array($mission, ['attack', 'spy', 'destroy_planet'], true) && $targetType === 'moon'
            && (! $target || (int) $target->user_id === (int) $user->id || $target->destroyed_at)) {
            throw ValidationException::withMessages(['target' => 'La lune ciblée doit appartenir à un joueur ennemi.']);
        }
        if (in_array($mission, ['attack', 'spy', 'destroy_planet'], true) && $targetType !== 'moon'
            && (! $target || (int) $target->user_id === (int) $user->id || $target->destroyed_at)) {
            throw ValidationException::withMessages(['target' => 'La mission nécessite une planète appartenant à un autre commandant.']);
        }
        if ($target && (int) $target->user_id !== (int) $user->id
            && in_array($mission, ['attack', 'spy', 'destroy_moon', 'destroy_planet'], true)
            && app(FactionDiplomacy::class)->relation($user, $target->user) === 'allied') {
            throw ValidationException::withMessages(['target' => 'Une mission hostile ne peut pas viser une faction alliée.']);
        }
        if (in_array($mission, ['attack', 'spy', 'destroy_moon', 'destroy_planet'], true) && $targetType === 'moon'
            && ! collect($this->map->system($universe, $user, $galaxy, $system, $origin->position)['slots'][$position - 2]['naturalMoons'] ?? [])->contains(fn (array $moon): bool => (int) $moon['orbit'] === (int) ($options['moon_orbit'] ?? 0))) {
            throw ValidationException::withMessages(['moon_orbit' => 'Cette orbite ne possède pas de lune ciblable.']);
        }
        if ($mission === 'destroy_moon' && ! in_array($targetType, ['moon', 'planet'], true)) {
            throw ValidationException::withMessages(['target_type' => 'La destruction lunaire doit viser une lune ou une planète hôte.']);
        }
        if ($mission === 'destroy_planet' && $targetType !== 'planet') {
            throw ValidationException::withMessages(['target_type' => 'La destruction planétaire doit viser une planète.']);
        }
        if ($mission === 'station' && $targetType !== 'moon') {
            $recipient = $target?->user;
            if (! $target || (int) $target->user_id === (int) $user->id || ! $recipient || ! app(FactionDiplomacy::class)->canStation($user, $recipient)
                || (int) $recipient->universe_id !== (int) $user->universe_id || (int) $target->alliance_depot_level < 1) {
                throw ValidationException::withMessages(['target' => 'Le stationnement exige un dépôt actif sur une planète alliée.']);
            }
        }
        if ($mission === 'station' && $targetType === 'moon' && $target
            && (int) $target->user_id !== (int) $user->id
            && (($user->faction_key ?? '0') !== '0' || ($target->user->faction_key ?? '0') !== '0')
            && (! app(FactionDiplomacy::class)->canStation($user, $target->user) || (int) $target->alliance_depot_level < 1)) {
            throw ValidationException::withMessages(['target' => 'Le stationnement exige un dépôt actif sur une planète alliée.']);
        }
        if ($mission === 'colonize') {
            $slot = $this->map->system($universe, $user, $galaxy, $system, $position)['slots'][$position - 2];
            if (in_array($position, [2, 15], true) && $galaxy === (int) $origin->galaxy && $system === (int) $origin->system) {
                throw ValidationException::withMessages(['target' => 'Le système ne peut pas recevoir de colonie sur ce poste réservé.']);
            }
            if ($target || $slot['kind'] !== 'planet' || $user->planets()->where('universe_id', $universe->id)->count() >= 21) {
                throw ValidationException::withMessages(['target' => 'Cet emplacement ne peut pas être colonisé ou la limite de colonies est atteinte.']);
            }
        }
        if ($mission === 'recycle') {
            if ($targetType === 'asteroid') {
                $slot = $this->map->system($universe, $user, $galaxy, $system, $position)['slots'][$position - 2];
                if ($target || $slot['kind'] !== 'asteroid') {
                    throw ValidationException::withMessages(['target' => 'La cible n’est pas un champ d’astéroïdes disponible.']);
                }
            } elseif (! DB::table('orbital_debris')->where($coordinates)->where(fn ($query) => $query->where('metal', '>', 0)->orWhere('crystal', '>', 0))->exists()) {
                throw ValidationException::withMessages(['target' => 'Aucun débris orbital n’est présent à cette position.']);
            }
        }
        if ($targetType === 'moon') {
            if (! in_array($mission, ['transport', 'attack', 'spy', 'station', 'destroy_moon'], true)) {
                throw ValidationException::withMessages(['target_type' => 'Cette mission ne peut pas cibler une lune.']);
            }
            $orbit = (int) ($options['moon_orbit'] ?? 0);
            $slot = $target ? $this->map->system($universe, $user, $galaxy, $system, $position)['slots'][$position - 2] : null;
            if ($orbit < 1 || ! $slot || ! collect($slot['naturalMoons'])->contains(fn (array $moon): bool => (int) $moon['orbit'] === $orbit)) {
                throw ValidationException::withMessages(['moon_orbit' => 'Cette orbite ne possède pas de lune ciblable.']);
            }
            if ($mission === 'transport' && (int) $target->user_id !== (int) $user->id) {
                throw ValidationException::withMessages(['target' => 'Le transport lunaire exige une lune de votre empire.']);
            }
            if ($mission === 'spy' && array_sum($ships) !== (int) ($ships[210] ?? 0)) {
                throw ValidationException::withMessages(['ships' => 'Une mission d’espionnage ne peut contenir que des sondes.']);
            }
        }

        return $target;
    }

    private function assertCargo(array $cargo, int $capacity): void
    {
        $total = 0;
        foreach (['metal', 'crystal', 'deuterium'] as $resource) {
            $amount = (int) ($cargo[$resource] ?? 0);
            if ($amount < 0 || $amount > 1_000_000_000 || $total > PHP_INT_MAX - $amount) {
                throw ValidationException::withMessages(['cargo' => 'Cargaison invalide.']);
            }
            $total += $amount;
        }
        if ($total < 1 || $total > $capacity) {
            throw ValidationException::withMessages(['cargo' => 'La cargaison dépasse la capacité de transport sélectionnée.']);
        }
    }

    /**
     * Repeatedly claims and processes the earliest due outbound arrival or returning fleet.
     *
     * Each fleet is selected under a row lock and processed in a retryable transaction, so concurrent
     * requests cannot apply the same arrival or return twice. Processing stops once no eligible fleet remains.
     */
    public function processDue(): void
    {
        while (true) {
            $processed = DB::transaction(function (): bool {
                $fleet = Fleet::query()->where(function ($query): void {
                    $query->where(fn ($query) => $query->whereNotNull('target_position')->where('status', 'outbound')->where('arrives_at', '<=', now()))
                        ->orWhere(fn ($query) => $query->where('mission', 'moon_building')->where('status', 'outbound')->where('arrives_at', '<=', now()))
                        ->orWhere(fn ($query) => $query->where('status', 'returning')->where('returns_at', '<=', now()));
                })
                    ->orderByRaw('case when status = ? then returns_at else arrives_at end', ['returning'])
                    ->orderBy('id')->lockForUpdate()->first();
                if (! $fleet) {
                    return false;
                }
                if ($fleet->mission === 'moon_building' && $fleet->status === 'outbound' && $fleet->arrives_at <= now()) {
                    $this->moonEconomy()->complete($fleet->targetPlanet, (int) $fleet->moon_orbit);
                    $fleet->status = 'returning';
                    $fleet->returns_at = now();
                    $fleet->save();
                } elseif ($fleet->status === 'outbound') {
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

    /**
     * Queues a ship-and-resource transfer to or from an owned moon orbit.
     *
     * The locked transaction verifies orbit ownership, transfer bounds, allowed transport ships, capacity and
     * stocks; it deducts the ships, travel fuel and source resources before creating the outbound fleet record.
     *
     * @param  'deposit'|'withdraw'  $direction
     * @param  'metal'|'crystal'|'deuterium'  $resource
     * @param  array<int|string, int>  $ships
     */
    public function sendMoon(User $user, Planet $planet, int $orbit, string $direction, string $resource, int $amount, array $ships): Fleet
    {
        $this->processDue();

        return DB::transaction(function () use ($user, $planet, $orbit, $direction, $resource, $amount, $ships): Fleet {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $planet = Planet::query()->whereKey($planet->id)->where('user_id', $user->id)
                ->where('universe_id', $user->universe_id)->lockForUpdate()->firstOrFail();
            $this->economy->materialize($planet);
            $planet->refresh();
            $this->production->complete($planet);
            $planet->refresh();
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

    /**
     * Resolves an arrived fleet's mission, records its outcome/cargo, and changes its state to returning.
     *
     * Recycling locks and decrements the shared asteroid stock; expeditions roll an outcome and may run combat.
     * The caller must process the fleet within the due-fleet transaction to make the state change atomic.
     */
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
        if ($fleet->mission === 'transport') {
            $target = Planet::query()->whereKey($fleet->target_planet_id)->where('universe_id', $fleet->universe_id)->whereNull('destroyed_at')->lockForUpdate()->firstOrFail();
            if ((int) $target->user_id !== (int) $fleet->user_id) {
                throw ValidationException::withMessages(['target' => 'Le transport ne peut livrer que sur une planète alliée.']);
            }
            $moonStock = null;
            if ($fleet->target_type === 'moon' && (int) $fleet->target_orbit > 0) {
                $moon = collect($this->map->system($fleet->universe, $fleet->user, $fleet->target_galaxy, $fleet->target_system, $fleet->originPlanet->position)['slots'][$fleet->target_position - 2]['naturalMoons'] ?? [])
                    ->firstWhere('orbit', (int) $fleet->target_orbit);
                if (! $moon) {
                    throw ValidationException::withMessages(['target' => 'Cette lune a été détruite ou n’existe plus.']);
                }
                $moonStock = $this->moonEconomy()->stock($target, ['orbit' => (int) $fleet->target_orbit]);
            }
            if ($moonStock && $fleet->origin_orbit) {
                $originStock = $this->moonEconomy()->stock($fleet->originPlanet, ['orbit' => (int) $fleet->origin_orbit]);
                if ((int) $originStock->metal > PHP_INT_MAX - (int) $fleet->cargo['metal']
                    || (int) $originStock->crystal > PHP_INT_MAX - (int) $fleet->cargo['crystal']
                    || (int) $originStock->deuterium > PHP_INT_MAX - (int) $fleet->cargo['deuterium']) {
                    throw new \OverflowException('La cargaison dépasse la capacité lunaire de destination.');
                }
            }
            foreach ($fleet->cargo as $resource => $amount) {
                if ($moonStock) {
                    if ((int) $moonStock->{$resource} > PHP_INT_MAX - (int) $amount) {
                        throw new \OverflowException('La livraison dépasse la limite des ressources lunaires.');
                    }
                    DB::table('moon_stocks')->where('id', $moonStock->id)->increment($resource, (int) $amount);
                } else {
                    if ((int) $target->{$resource} > PHP_INT_MAX - (int) $amount) {
                        throw new \OverflowException('La livraison dépasse la limite des ressources.');
                    }
                    $target->{$resource} = (int) $target->{$resource} + (int) $amount;
                }
            }
            if (! $moonStock) {
                $target->save();
            }
            $fleet->cargo = ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];
            $fleet->outcome = 'transport_delivered';
        } elseif ($fleet->mission === 'recycle') {
            $coordinates = ['universe_id' => $fleet->universe_id, 'galaxy' => $fleet->target_galaxy, 'system' => $fleet->target_system, 'position' => $fleet->target_position];
            $field = null;
            if ($fleet->target_type === 'debris') {
                $field = DB::table('orbital_debris')->where($coordinates)->lockForUpdate()->first();
            } else {
                $coordinates['position'] = $fleet->target_position;
                $field = DB::table('asteroid_fields')->where($coordinates)->lockForUpdate()->first();
                if (! $field) {
                    $universe = $fleet->universe;
                    $viewer = $fleet->user;
                    $slot = $this->map->system($universe, $viewer, $fleet->target_galaxy, $fleet->target_system, $fleet->originPlanet->position)['slots'][$fleet->target_position - 2];
                    if (($slot['kind'] ?? null) === 'asteroid') {
                        DB::table('asteroid_fields')->insertOrIgnore([...$coordinates, ...($slot['remaining'] ?? ['metal' => 0, 'crystal' => 0, 'deuterium' => 0]), 'created_at' => now(), 'updated_at' => now()]);
                        $field = DB::table('asteroid_fields')->where($coordinates)->lockForUpdate()->first();
                    }
                }
            }
            if (! $field) {
                $fleet->outcome = 'field_unavailable';
            } else {
                $capacity = min($capacity, (int) ($fleet->ships[209] ?? 0) * self::CAPACITY[209]);
                foreach (['metal', 'crystal'] as $resource) {
                    $cargo[$resource] = min($capacity, (int) $field->{$resource});
                    $capacity -= $cargo[$resource];
                }
                $table = $fleet->target_type === 'debris' ? 'orbital_debris' : 'asteroid_fields';
                DB::table($table)->where('id', $field->id)->update([
                    'metal' => (int) $field->metal - $cargo['metal'], 'crystal' => (int) $field->crystal - $cargo['crystal'], 'updated_at' => now(),
                ]);
                $fleet->outcome = 'recycled';
            }
        } elseif ($fleet->mission === 'station') {
            if ($fleet->target_type === 'moon' && $fleet->target_planet_id && $fleet->target_orbit
                && ((int) $fleet->targetPlanet->user_id === (int) $fleet->user_id
                    || (($fleet->user->faction_key ?? '0') === '0' && ($fleet->targetPlanet->user->faction_key ?? '0') === '0'))) {
                $this->moonEconomy()->addShips($fleet->targetPlanet, (int) $fleet->target_orbit, $fleet->ships);
                $fleet->ships = [];
                $fleet->mission_report = ['stationedOnMoon' => true, 'orbit' => (int) $fleet->target_orbit];
                $fleet->status = 'completed';
                $fleet->returns_at = null;
                $fleet->outcome = 'stationed';
            } else {
                $fleet->status = 'stationed';
                $fleet->returns_at = null;
                $fleet->outcome = 'stationed';
            }
        } elseif ($fleet->mission === 'attack') {
            $this->resolvePlanetaryCombat($fleet);
        } elseif ($fleet->mission === 'moon_building') {
            $fleet->status = 'completed';
            $fleet->outcome = 'moon_building_completed';
            $fleet->mission_report = ['moonBuilding' => ['code' => (int) $fleet->moon_order['buildingCode'], 'orbit' => (int) $fleet->moon_orbit]];
        } elseif ($fleet->mission === 'spy') {
            $this->resolveEspionage($fleet);
        } elseif ($fleet->mission === 'colonize') {
            $this->resolveColonization($fleet);
        } elseif ($fleet->mission === 'destroy_moon') {
            $this->resolveMoonDestruction($fleet);
        } elseif ($fleet->mission === 'destroy_planet') {
            $this->resolvePlanetDestruction($fleet);
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
        if ($fleet->status !== 'stationed' && $fleet->status !== 'completed') {
            if (! in_array($fleet->mission, ['attack', 'transport'], true)) {
                $fleet->cargo = $cargo;
            }
            $fleet->status = 'returning';
        }
        $fleet->save();
    }

    private function resolvePlanetaryCombat(Fleet $fleet): void
    {
        $target = Planet::query()->whereKey($fleet->target_planet_id)->where('universe_id', $fleet->universe_id)->whereNull('destroyed_at')->lockForUpdate()->firstOrFail();
        $targetOrbit = $fleet->target_type === 'moon' ? (int) $fleet->target_orbit : null;
        $moonTarget = $targetOrbit !== null && $targetOrbit > 0;
        $moonEconomy = $this->moonEconomy();
        if ($targetOrbit) {
            $moon = collect($this->map->system($fleet->universe, $fleet->user, $fleet->target_galaxy, $fleet->target_system, $fleet->originPlanet->position)['slots'][$fleet->target_position - 2]['naturalMoons'] ?? [])
                ->firstWhere('orbit', $targetOrbit);
            if (! $moon) {
                throw ValidationException::withMessages(['target' => 'Cette lune a été détruite ou n’existe plus.']);
            }
        }
        if ((int) $target->user_id === (int) $fleet->user_id && ! ($fleet->target_type === 'moon' && $fleet->mission === 'transport')) {
            throw ValidationException::withMessages(['target' => 'La cible appartient à votre empire.']);
        }
        $attacker = $fleet->ships;
        if (array_sum($attacker) > (int) config('combat.max_units_per_side')) {
            throw ValidationException::withMessages(['ships' => 'La flotte dépasse la limite de combat de cet univers.']);
        }
        if ($moonTarget) {
            $defender = $moonEconomy->ships($target, $targetOrbit);
        } else {
            $defender = [];
            foreach (config('combat.units') as $code => $definition) {
                if ($definition['type'] === 'defences' || $definition['type'] === 'ships') {
                    $count = (int) $target->{'unit_'.$code};
                    if ($count > 0) {
                        $defender[$code] = $count;
                    }
                }
            }
        }
        $technology = $this->technologies($fleet->user);
        $defenderTechnology = $this->technologies($target->user);
        $simulatorDefender = $this->limitFleet($defender, (int) config('combat.max_units_per_side'));
        $profiles = config('combat.special_profiles', []);
        $result = $this->combat->simulate(
            ['fleet' => $attacker, 'technologies' => $technology, 'factionStats' => app(FactionCatalog::class)->settings($fleet->user)['statistics'] ?? []],
            ['fleet' => $simulatorDefender, 'technologies' => $defenderTechnology, 'factionStats' => app(FactionCatalog::class)->settings($target->user)['statistics'] ?? []],
            null,
            $profiles,
        );
        $survivingAttacker = $this->survivors($result['attacker']);
        $survivingDefender = $this->survivors($result['defender']);
        foreach ($defender as $code => $count) {
            $survivingDefender[$code] = ($survivingDefender[$code] ?? 0) + max(0, $count - ($simulatorDefender[$code] ?? 0));
        }
        if ($moonTarget) {
            $moonEconomy->setShips($target, $targetOrbit, $survivingDefender);
        } else {
            foreach ($survivingDefender as $code => $count) {
                $target->{'unit_'.$code} = $count;
            }
            $target->save();
        }
        $fleet->ships = $survivingAttacker;
        if ($fleet->origin_orbit) {
            $originFleet = $moonEconomy->ships($fleet->originPlanet, (int) $fleet->origin_orbit);
            foreach ($fleet->ships as $code => $count) {
                $originFleet[$code] = ($originFleet[$code] ?? 0) + $count;
            }
            $moonEconomy->setShips($fleet->originPlanet, (int) $fleet->origin_orbit, $originFleet);
            $fleet->ships = [];
        }
        $fleet->combat_report = $result;

        $fleet->outcome = $result['winner'] === 'attacker' ? 'attack_won' : ($result['winner'] === 'defender' ? 'attack_lost' : 'attack_draw');
        if (! $moonTarget) {
            $this->addDebris($fleet, $result['attacker']['totalLosses'] + $result['defender']['totalLosses']);
        }
        if ($moonTarget && $result['attacker']['totalLosses'] + $result['defender']['totalLosses'] > 0) {
            $this->moonDebris($fleet, $result['attacker']['totalLosses'] + $result['defender']['totalLosses']);
        }
        $pillage = ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];
        if ($result['winner'] === 'attacker' && ! $fleet->origin_orbit) {
            $remainingCapacity = array_sum(array_map(fn (int $code, int $count): int => self::CAPACITY[$code] * $count, array_keys($survivingAttacker), $survivingAttacker));
            $targetResources = $moonTarget ? $moonEconomy->resources($target, (int) $fleet->target_orbit) : ['metal' => (int) $target->metal, 'crystal' => (int) $target->crystal, 'deuterium' => (int) $target->deuterium];
            foreach (['metal', 'crystal', 'deuterium'] as $resource) {
                $amount = min(intdiv($targetResources[$resource], 2), $remainingCapacity);
                $targetResources[$resource] -= $amount;
                $remainingCapacity -= $amount;
                $pillage[$resource] = $amount;
            }
            if ($moonTarget) {
                $moonEconomy->setResources($target, (int) $fleet->target_orbit, $targetResources);
            } else {
                $target->forceFill($targetResources)->save();
            }
        }
        if ($fleet->origin_orbit && $result['winner'] === 'attacker') {
            $moonStock = $moonEconomy->stock($target, ['orbit' => (int) $fleet->target_orbit]);
            foreach (['metal', 'crystal', 'deuterium'] as $resource) {
                if ((int) $moonStock->{$resource} > PHP_INT_MAX - $pillage[$resource]) {
                    throw new \OverflowException('La cargaison dépasse la limite du stock lunaire de départ.');
                }
            }
            foreach ($pillage as $resource => $amount) {
                DB::table('moon_stocks')->where('id', $moonStock->id)->increment($resource, $amount);
            }
        }
        $fleet->mission_report = ['combat' => ['winner' => $result['winner'], 'pillage' => $pillage]];
        $fleet->cargo = $fleet->origin_orbit ? ['metal' => 0, 'crystal' => 0, 'deuterium' => 0] : $pillage;
    }

    private function resolveEspionage(Fleet $fleet): void
    {
        $target = Planet::query()->whereKey($fleet->target_planet_id)->where('universe_id', $fleet->universe_id)->lockForUpdate()->firstOrFail();
        if ((int) $target->user_id === (int) $fleet->user_id && $fleet->target_type !== 'moon') {
            throw ValidationException::withMessages(['target' => 'La cible appartient à votre empire.']);
        }
        $moonTarget = $fleet->target_type === 'moon' && (int) $fleet->target_orbit > 0;
        $targetUser = $target->user;
        $probeCount = (int) ($fleet->ships[210] ?? 0);
        $difference = (int) $fleet->user->spy_technology_level - (int) $targetUser->spy_technology_level;
        $level = max(0, $probeCount + $difference);
        $moonResources = $moonTarget ? $this->moonEconomy()->resources($target, (int) $fleet->target_orbit) : null;
        $moonShips = $moonTarget && $level >= 2 ? $this->moonEconomy()->ships($target, (int) $fleet->target_orbit) : [];
        $details = ['resources' => $moonResources ?? ['metal' => (int) $target->metal, 'crystal' => (int) $target->crystal, 'deuterium' => (int) $target->deuterium]];
        if ($level >= 2) {
            $details['fleet'] = $moonTarget ? $moonShips : collect(config('combat.units'))->filter(fn (array $unit): bool => $unit['type'] === 'ships')
                ->mapWithKeys(fn (array $unit, int $code): array => [$code => (int) $target->{'unit_'.$code}])->all();
        }
        if ($level >= 3) {
            $details['defences'] = $moonTarget ? [] : collect(config('combat.units'))->filter(fn (array $unit): bool => $unit['type'] === 'defences')
                ->mapWithKeys(fn (array $unit, int $code): array => [$code => (int) $target->{'unit_'.$code}])->all();
        }
        if ($level >= 4) {
            $details['buildings'] = $moonTarget
                ? ['moon_base_level' => (int) $this->moonEconomy()->stock($target, ['orbit' => (int) $fleet->target_orbit])->moon_base_level, 'phalanx_level' => (int) $this->moonEconomy()->stock($target, ['orbit' => (int) $fleet->target_orbit])->phalanx_level, 'jump_gate_level' => (int) $this->moonEconomy()->stock($target, ['orbit' => (int) $fleet->target_orbit])->jump_gate_level]
                : $target->only('metal_mine_level', 'crystal_mine_level', 'deuterium_synthesizer_level', 'solar_plant_level', 'fusion_plant_level', 'laboratory_level', 'robot_factory_level', 'nano_factory_level', 'hangar_level', 'defence_center_level', 'missile_silo_level', 'alliance_depot_level');
        }
        if ($level >= 5) {
            $details['research'] = $targetUser->only('spy_technology_level', 'military_technology_level', 'defence_technology_level', 'shield_technology_level', 'combustion_technology_level', 'impulse_technology_level', 'hyperspace_drive_level');
        }
        $detected = $difference < 0 || $probeCount <= max(1, -(2 * $difference));
        $fleet->mission_report = ['espionage' => ['probeCount' => $probeCount, 'visibilityLevel' => $level, 'detected' => $detected, 'intelligence' => $details]];
        $fleet->ships = $detected ? [] : $fleet->ships;
        if ($fleet->origin_orbit) {
            if ($detected) {
                $this->moonEconomy()->removeShips($fleet->originPlanet, (int) $fleet->origin_orbit, [210 => $probeCount]);
            }
            $fleet->ships = [];
        }
        $fleet->outcome = $detected ? 'spy_detected' : 'spy_success';
        $fleet->cargo = ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];
        if ($detected) {
            $fleet->returns_at = Carbon::parse(now()->addSeconds(max(60, (int) $fleet->arrives_at->diffInSeconds($fleet->returns_at)))->toDateTimeString());
        }
        if ($fleet->origin_orbit) {
            $fleet->mission_report['originOrbit'] = (int) $fleet->origin_orbit;
        }
        if ($fleet->target_type === 'moon' && (int) $fleet->target_orbit > 0) {
            $moon = collect($this->map->system($fleet->universe, $fleet->user, $fleet->target_galaxy, $fleet->target_system, $fleet->originPlanet->position)['slots'][$fleet->target_position - 2]['naturalMoons'] ?? [])
                ->firstWhere('orbit', (int) $fleet->target_orbit);
            if (! $moon) {
                throw ValidationException::withMessages(['target' => 'Cette lune n’existe plus.']);
            }
            $fleet->mission_report['target'] = ['type' => 'moon', 'orbit' => (int) $fleet->target_orbit, 'diameter' => (int) $moon['diameter']];
        }
    }

    private function resolveColonization(Fleet $fleet): void
    {
        $slot = $this->map->system($fleet->universe, $fleet->user, $fleet->target_galaxy, $fleet->target_system, $fleet->originPlanet->position)['slots'][$fleet->target_position - 2];
        abort_if($slot['kind'] !== 'planet' || Planet::query()->where('universe_id', $fleet->universe_id)->where('galaxy', $fleet->target_galaxy)->where('system', $fleet->target_system)->where('position', $fleet->target_position)->exists(), 409);
        $user = User::query()->whereKey($fleet->user_id)->lockForUpdate()->firstOrFail();
        abort_if($user->planets()->where('universe_id', $fleet->universe_id)->count() >= 21, 409);
        $concentrations = $this->map->planetConcentrations($fleet->universe, $fleet->target_galaxy, $fleet->target_system, $fleet->target_position);
        $planet = new Planet(['name' => $slot['generatedName']]);
        $planet->forceFill([
            'universe_id' => $fleet->universe_id, 'user_id' => $fleet->user_id, 'galaxy' => $fleet->target_galaxy,
            'system' => $fleet->target_system, 'position' => $fleet->target_position, 'metal' => 500, 'crystal' => 500,
            'deuterium' => 0, 'fields' => 163, 'max_temperature' => 40,
            'metal_mine_activity' => 10, 'crystal_mine_activity' => 10, 'deuterium_synthesizer_activity' => 10,
            'solar_plant_activity' => 10, 'fusion_plant_activity' => 10,
            'metal_concentration' => $concentrations['metal'], 'crystal_concentration' => $concentrations['crystal'],
            'deuterium_concentration' => $concentrations['deuterium'], 'resources_updated_at' => now(),
        ])->save();
        $ships = $fleet->ships;
        $ships[208]--;
        unset($ships[208]);
        $fleet->ships = $ships;
        $fleet->outcome = 'colonized';
        $fleet->mission_report = ['colonizedPlanetId' => $planet->id, 'colonizedPlanetName' => $planet->name];
    }

    private function resolveMoonDestruction(Fleet $fleet): void
    {
        $this->resolveTargetBattle($fleet);
        $result = $fleet->combat_report;
        $moonSlot = $this->map->system($fleet->universe, $fleet->user, $fleet->target_galaxy, $fleet->target_system, $fleet->originPlanet->position)['slots'][$fleet->target_position - 2];
        $moonOrbit = (int) $fleet->moon_orbit;
        $moon = collect($moonSlot['naturalMoons'] ?? [])->firstWhere('orbit', $moonOrbit);
        $deathStars = (int) ($fleet->ships[214] ?? 0);
        $chance = max(1, min(20, (int) floor($deathStars * 100 / 1000)));
        $destroyed = $result['winner'] === 'attacker' && $this->random(1, 100) <= $chance;
        if ($destroyed) {
            $coordinates = ['universe_id' => $fleet->universe_id, 'galaxy' => $fleet->target_galaxy, 'system' => $fleet->target_system, 'position' => $fleet->target_position, 'orbit' => $moonOrbit];
            if ($moon) {
                DB::table('destroyed_moons')->insertOrIgnore([...$coordinates, 'diameter' => $moon['diameter'], 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('moon_stocks')->where('planet_id', $fleet->target_planet_id)->where('orbit', $coordinates['orbit'])->delete();
        }
        $orbit = (int) $fleet->moon_orbit;
        $fleet->mission_report = ['moonDestruction' => ['destroyed' => $destroyed, 'chancePercent' => $chance, 'orbit' => $orbit, 'type' => 'moon']];
        if ($fleet->origin_orbit && $deathStars > 0) {
            $this->moonEconomy()->removeShips($fleet->originPlanet, (int) $fleet->origin_orbit, [214 => $deathStars]);
        }
        $fleet->ships = [];
        $fleet->outcome = $destroyed ? 'moon_destroyed' : 'moon_survived';
    }

    private function resolvePlanetDestruction(Fleet $fleet): void
    {
        $this->resolveTargetBattle($fleet);
        $destroyed = $fleet->combat_report['winner'] === 'attacker';
        if ($destroyed) {
            $target = Planet::query()->whereKey($fleet->target_planet_id)->where('universe_id', $fleet->universe_id)->whereNull('destroyed_at')->lockForUpdate()->firstOrFail();
            $target->forceFill(['destroyed_at' => now(), 'metal' => 0, 'crystal' => 0, 'deuterium' => 0]);
            foreach (config('combat.units') as $code => $definition) {
                if (isset($target->{'unit_'.$code})) {
                    $target->{'unit_'.$code} = 0;
                }
            }
            $target->save();
        }
        $fleet->mission_report = ['planetDestruction' => ['destroyed' => $destroyed, 'experimental' => true]];
        $fleet->outcome = $destroyed ? 'planet_destroyed' : 'planet_survived';
    }

    private function resolveTargetBattle(Fleet $fleet): void
    {
        $target = Planet::query()->whereKey($fleet->target_planet_id)->where('universe_id', $fleet->universe_id)->whereNull('destroyed_at')->lockForUpdate()->firstOrFail();
        if ((int) $target->user_id === (int) $fleet->user_id && ! ($fleet->mission === 'transport' && $fleet->target_type === 'moon')) {
            throw ValidationException::withMessages(['target' => 'La cible appartient à votre empire.']);
        }
        $moonTarget = $fleet->target_type === 'moon' && (int) $fleet->target_orbit > 0;
        $moonEconomy = $this->moonEconomy;
        if ($moonTarget) {
            $moon = collect($this->map->system($fleet->universe, $fleet->user, $fleet->target_galaxy, $fleet->target_system, $fleet->originPlanet->position)['slots'][$fleet->target_position - 2]['naturalMoons'] ?? [])
                ->firstWhere('orbit', (int) $fleet->target_orbit);
            if (! $moon) {
                throw ValidationException::withMessages(['target' => 'Cette lune a été détruite ou n’existe plus.']);
            }
            $defender = $moonEconomy->ships($target, (int) $fleet->target_orbit);
        } else {
            $defender = [];
            foreach (config('combat.units') as $code => $definition) {
                if ($definition['type'] === 'ships' || $definition['type'] === 'defences') {
                    $count = (int) $target->{'unit_'.$code};
                    if ($count > 0) {
                        $defender[$code] = $count;
                    }
                }
            }
        }
        $limited = $this->limitFleet($defender, (int) config('combat.max_units_per_side'));
        if (array_sum($fleet->ships) > (int) config('combat.max_units_per_side')) {
            throw ValidationException::withMessages(['ships' => 'La flotte dépasse la limite de combat de cet univers.']);
        }
        $result = $this->combat->simulate(
            ['fleet' => $fleet->ships, 'technologies' => $this->technologies($fleet->user), 'factionStats' => app(FactionCatalog::class)->settings($fleet->user)['statistics'] ?? []],
            ['fleet' => $limited, 'technologies' => $this->technologies($target->user), 'factionStats' => app(FactionCatalog::class)->settings($target->user)['statistics'] ?? []],
            null,
            config('combat.special_profiles', []),
        );
        $survivors = $this->survivors($result['defender']);
        foreach ($defender as $code => $count) {
            $survivors[$code] = ($survivors[$code] ?? 0) + max(0, $count - ($limited[$code] ?? 0));
        }
        if ($moonTarget) {
            $moonEconomy->setShips($target, (int) $fleet->target_orbit, $survivors);
        } else {
            foreach ($survivors as $code => $count) {
                $target->{'unit_'.$code} = $count;
            }
            $target->save();
        }
        $fleet->ships = $this->survivors($result['attacker']);
        $fleet->combat_report = $result;
        if (! $moonTarget) {
            $this->addDebris($fleet, $result['attacker']['totalLosses'] + $result['defender']['totalLosses']);
        }
    }

    private function technologies(User $user): array
    {
        return ['weapons' => (int) $user->military_technology_level, 'shields' => (int) $user->defence_technology_level, 'armour' => (int) $user->shield_technology_level];
    }

    private function moonEconomy(): MoonEconomy
    {
        return $this->moonEconomy;
    }

    private function survivors(array $summary): array
    {
        $ships = [];
        foreach ($summary['units'] as $unit) {
            if ($unit['remaining'] > 0) {
                $ships[$unit['code']] = $unit['remaining'];
            }
        }

        return $ships;
    }

    private function limitFleet(array $fleet, int $limit): array
    {
        $fleet = array_filter($fleet, fn (int $count): bool => $count > 0);
        $total = array_sum($fleet);
        if ($total <= $limit) {
            return $fleet;
        }

        $limited = array_fill_keys(array_keys($fleet), 1);
        $remainingFleet = array_map(fn (int $count): int => $count - 1, $fleet);
        $remainingFleet = array_filter($remainingFleet, fn (int $count): bool => $count > 0);
        $remainingSlots = $limit - count($limited);
        $remainingTotal = array_sum($remainingFleet);
        $remainders = [];
        $allocated = 0;

        foreach ($remainingFleet as $code => $count) {
            $scaled = $count * $remainingSlots;
            $additional = intdiv($scaled, $remainingTotal);
            $limited[$code] += $additional;
            $remainders[$code] = $scaled % $remainingTotal;
            $allocated += $additional;
        }

        uksort($remainders, fn (int|string $left, int|string $right) => ($remainders[$right] <=> $remainders[$left]) ?: ((int) $left <=> (int) $right));

        foreach (array_keys($remainders) as $code) {
            if ($allocated >= $remainingSlots) {
                break;
            }
            if ($limited[$code] < $fleet[$code]) {
                $limited[$code]++;
                $allocated++;
            }
        }

        return $limited;
    }

    private function moonDebris(Fleet $fleet, int $losses): void
    {
        $economy = $this->moonEconomy;
        $stock = $economy->stock($fleet->targetPlanet, ['orbit' => (int) $fleet->target_orbit]);
        $metal = $losses * 1000;
        $crystal = $losses * 500;
        if ((int) $stock->metal > PHP_INT_MAX - $metal || (int) $stock->crystal > PHP_INT_MAX - $crystal) {
            throw new \OverflowException('Le stock lunaire dépasse la limite des ressources.');
        }
        $economy->setResources($fleet->targetPlanet, (int) $fleet->target_orbit, [
            'metal' => (int) $stock->metal + $metal,
            'crystal' => (int) $stock->crystal + $crystal,
            'deuterium' => (int) $stock->deuterium,
        ]);
    }

    private function addDebris(Fleet $fleet, int $losses): void
    {
        if ($losses < 1) {
            return;
        }
        $coordinates = ['universe_id' => $fleet->universe_id, 'galaxy' => $fleet->target_galaxy, 'system' => $fleet->target_system, 'position' => $fleet->target_position];
        DB::table('orbital_debris')->insertOrIgnore([...$coordinates, 'created_at' => now(), 'updated_at' => now()]);
        $debris = DB::table('orbital_debris')->where($coordinates)->lockForUpdate()->first();
        $metal = $losses * 1000;
        $crystal = $losses * 500;
        if ((int) $debris->metal > PHP_INT_MAX - $metal || (int) $debris->crystal > PHP_INT_MAX - $crystal) {
            throw new \OverflowException('Le champ de débris dépasse la limite des ressources.');
        }
        DB::table('orbital_debris')->where('id', $debris->id)->update(['metal' => $debris->metal + $metal, 'crystal' => $debris->crystal + $crystal, 'updated_at' => now()]);
    }

    /**
     * Applies a moon deposit to locked moon stock or marks a withdrawal as ready to return.
     *
     * Deposits create the stock row if needed, reject integer overflow, and clear delivered fleet cargo.
     */
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

    /**
     * Resolves a pirate/alien encounter using current player technologies and stores survivors plus the report.
     *
     * Research due by the fleet's arrival timestamp is applied before attacker technology is read; opponent
     * levels are adjusted by the encounter type, and only the attacker's surviving units remain in the fleet.
     *
     * @param  'pirates'|'aliens'  $enemy
     */
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
            ['fleet' => $fleet->ships, 'technologies' => $technology, 'factionStats' => app(FactionCatalog::class)->settings($user)['statistics'] ?? []],
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

    /**
     * Returns an inclusive cryptographically secure random integer; overridable to make mission outcomes testable.
     */
    protected function random(int $minimum, int $maximum): int
    {
        return random_int($minimum, $maximum);
    }

    /**
     * Restores surviving ships and cargo to the origin planet, then marks the fleet completed.
     *
     * The caller holds the fleet-processing transaction; the origin planet is locked and resource/unit integer
     * overflows abort the whole update. Production is materialized at the fleet's recorded return time first.
     */
    private function returnFleet(Fleet $fleet): void
    {
        if ($fleet->status === 'stationed') {
            return;
        }
        if ($fleet->mission === 'moon_building' && $fleet->status === 'returning') {
            $fleet->status = 'completed';
            $fleet->save();

            return;
        }
        if ($fleet->origin_orbit && in_array($fleet->mission, ['transport', 'attack', 'spy', 'station', 'destroy_moon', 'destroy_planet'], true)) {
            $origin = Planet::query()->whereKey($fleet->origin_planet_id)->where('user_id', $fleet->user_id)->where('universe_id', $fleet->universe_id)->lockForUpdate()->firstOrFail();
            if ($fleet->mission === 'spy') {
                $fleet->status = 'completed';
                $fleet->ships = [];
                $fleet->save();

                return;
            }
            $moon = collect($this->map->system($fleet->universe, $fleet->user, $origin->galaxy, $origin->system, $origin->position)['slots'][$origin->position - 2]['naturalMoons'] ?? [])->firstWhere('orbit', (int) $fleet->origin_orbit);
            if ($moon && $fleet->ships !== []) {
                $this->moonEconomy()->addShips($origin, (int) $fleet->origin_orbit, $fleet->ships);
            }
            if ($fleet->cargo !== [] && $fleet->target_type === 'planet') {
                foreach ($fleet->cargo as $resource => $amount) {
                    $origin->{$resource} += (int) $amount;
                }
                $origin->save();
            }
            $fleet->ships = [];
            $fleet->cargo = ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];
            $fleet->status = 'completed';
            $fleet->save();

            return;
        }
        $planet = Planet::query()->whereKey($fleet->origin_planet_id)->where('user_id', $fleet->user_id)->where('universe_id', $fleet->universe_id)->lockForUpdate()->firstOrFail();
        $this->economy->materialize($planet, Carbon::instance($fleet->returns_at));
        $planet->refresh();
        foreach ($fleet->ships as $code => $count) {
            $column = 'unit_'.$code;
            if (! in_array((int) $code, array_keys(config('combat.units')), true)) {
                continue;
            }
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
