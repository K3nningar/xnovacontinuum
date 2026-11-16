<?php

namespace App\Services;

use App\Models\Planet;
use App\Models\ResearchQueue;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResearchService
{
    public function administrativeTechnologies(): array
    {
        return self::TECHNOLOGIES;
    }

    public function __construct(private PlanetEconomy $economy) {}

    private const TECHNOLOGIES = [
        'spy' => ['code' => 106, 'column' => 'spy_technology_level', 'rarity' => 'remarkable', 'cost' => [200, 1000, 200], 'requirements' => ['laboratory_level' => 3]],
        'computer' => ['code' => 108, 'column' => 'computer_technology_level', 'rarity' => 'ordinary', 'cost' => [0, 400, 600], 'requirements' => ['laboratory_level' => 1]],
        'military' => ['code' => 109, 'column' => 'military_technology_level', 'rarity' => 'ordinary', 'cost' => [800, 200, 0], 'requirements' => ['laboratory_level' => 4]],
        'defence' => ['code' => 110, 'column' => 'defence_technology_level', 'rarity' => 'exceptional', 'cost' => [200, 600, 0], 'requirements' => ['laboratory_level' => 6, 'energy_technology_level' => 3]],
        'shield' => ['code' => 111, 'column' => 'shield_technology_level', 'rarity' => 'remarkable', 'cost' => [1000, 0, 0], 'requirements' => ['laboratory_level' => 2]],
        'energy' => ['code' => 113, 'column' => 'energy_technology_level', 'rarity' => 'exceptional', 'cost' => [0, 800, 400], 'requirements' => ['laboratory_level' => 1]],
        'hyperspace' => ['code' => 114, 'column' => 'hyperspace_technology_level', 'rarity' => 'epic', 'cost' => [0, 4000, 2000], 'requirements' => ['laboratory_level' => 7, 'energy_technology_level' => 5, 'defence_technology_level' => 5]],
        'combustion' => ['code' => 115, 'column' => 'combustion_technology_level', 'rarity' => 'remarkable', 'cost' => [400, 0, 600], 'requirements' => ['laboratory_level' => 1, 'energy_technology_level' => 1]],
        'impulse' => ['code' => 117, 'column' => 'impulse_technology_level', 'rarity' => 'exceptional', 'cost' => [2000, 4000, 600], 'requirements' => ['laboratory_level' => 2, 'energy_technology_level' => 1]],
        'hyperspace_drive' => ['code' => 118, 'column' => 'hyperspace_drive_level', 'rarity' => 'epic', 'cost' => [10000, 20000, 6000], 'requirements' => ['laboratory_level' => 7, 'hyperspace_technology_level' => 3]],
        'laser' => ['code' => 120, 'column' => 'laser_technology_level', 'rarity' => 'remarkable', 'cost' => [200, 100, 0], 'requirements' => ['laboratory_level' => 1, 'energy_technology_level' => 2]],
        'ion' => ['code' => 121, 'column' => 'ion_technology_level', 'rarity' => 'exceptional', 'cost' => [1000, 300, 100], 'requirements' => ['laboratory_level' => 4, 'laser_technology_level' => 5, 'energy_technology_level' => 4]],
        'plasma' => ['code' => 122, 'column' => 'plasma_technology_level', 'rarity' => 'epic', 'cost' => [2000, 4000, 1000], 'requirements' => ['laboratory_level' => 5, 'energy_technology_level' => 8, 'laser_technology_level' => 10, 'ion_technology_level' => 5]],
        'intergalactic' => ['code' => 123, 'column' => 'intergalactic_research_level', 'rarity' => 'epic', 'cost' => [240000, 400000, 160000], 'requirements' => ['laboratory_level' => 10, 'computer_technology_level' => 8, 'hyperspace_technology_level' => 8]],
        'expedition' => ['code' => 124, 'column' => 'expedition_technology_level', 'rarity' => 'legendary', 'cost' => [4000, 8000, 4000], 'requirements' => ['laboratory_level' => 3, 'computer_technology_level' => 4, 'impulse_technology_level' => 3]],
        'graviton' => ['code' => 199, 'column' => 'graviton_technology_level', 'rarity' => 'mythic', 'cost' => [0, 0, 0], 'energy' => 300000, 'requirements' => ['laboratory_level' => 12]],
    ];

    public function catalog(User $user, Planet $planet): array
    {
        $queue = $user->researchQueues()->with('planet')->first();
        $researches = [];

        foreach (self::TECHNOLOGIES as $key => $technology) {
            $level = (int) $user->{$technology['column']};
            $cost = $this->cost($technology, $level);
            $requirements = [];
            $requirementsMet = true;

            foreach ($technology['requirements'] as $column => $required) {
                $current = (int) ($column === 'laboratory_level' ? $planet->{$column} : $user->{$column});
                $requirements[] = ['key' => $column, 'required' => $required, 'current' => $current];
                $requirementsMet = $requirementsMet && $current >= $required;
            }

            $hasResources = (int) $planet->metal >= $cost['metal']
                && (int) $planet->crystal >= $cost['crystal']
                && (int) $planet->deuterium >= $cost['deuterium']
                && $this->economy->energyBalance($planet)['supply'] >= $cost['energy'];
            $researches[] = ['key' => $key, 'code' => $technology['code'], 'rarity' => $technology['rarity'], 'level' => $level, 'targetLevel' => $level + 1,
                'cost' => $cost, 'duration' => $this->duration($cost, $planet), 'requirements' => $requirements,
                'requirementsMet' => $requirementsMet, 'hasResources' => $hasResources,
                'canResearch' => ! $queue && $requirementsMet && $hasResources];
        }

        return ['researches' => $researches, 'activeResearch' => $queue ? [
            'id' => $queue->id, 'technologyKey' => $queue->technology_key, 'technologyCode' => $queue->technology_code,
            'targetLevel' => $queue->target_level, 'planetName' => $queue->planet->name,
            'completesAt' => $queue->completes_at->toIso8601String(), 'remainingSeconds' => $queue->remaining_seconds,
        ] : null];
    }

    public function start(User $user, Planet $planet, string $technologyKey): ResearchQueue
    {
        $this->complete($user);

        if (! isset(self::TECHNOLOGIES[$technologyKey])) {
            throw ValidationException::withMessages(['technology' => 'Cette technologie est inconnue.']);
        }

        $this->economy->materialize($planet);
        $this->economy->completeBuildingQueue($planet);

        return DB::transaction(function () use ($user, $planet, $technologyKey): ResearchQueue {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedPlanet = Planet::query()->whereKey($planet->id)->where('user_id', $user->id)
                ->where('universe_id', $lockedUser->universe_id)->lockForUpdate()->firstOrFail();
            $technology = self::TECHNOLOGIES[$technologyKey];

            if ($lockedUser->researchQueues()->exists()) {
                throw ValidationException::withMessages(['technology' => 'Une recherche est déjà en cours dans votre empire.']);
            }

            foreach ($technology['requirements'] as $column => $required) {
                $current = (int) ($column === 'laboratory_level' ? $lockedPlanet->{$column} : $lockedUser->{$column});
                if ($current < $required) {
                    throw ValidationException::withMessages(['technology' => 'Les prérequis de cette recherche ne sont pas remplis.']);
                }
            }

            $currentLevel = (int) $lockedUser->{$technology['column']};
            $cost = $this->cost($technology, $currentLevel);
            if ((int) $lockedPlanet->metal < $cost['metal'] || (int) $lockedPlanet->crystal < $cost['crystal']
                || (int) $lockedPlanet->deuterium < $cost['deuterium']
                || $this->economy->energyBalance($lockedPlanet)['supply'] < $cost['energy']) {
                throw ValidationException::withMessages(['resources' => 'Les ressources de cette planète ne suffisent pas pour lancer la recherche.']);
            }

            $lockedPlanet->metal -= $cost['metal'];
            $lockedPlanet->crystal -= $cost['crystal'];
            $lockedPlanet->deuterium -= $cost['deuterium'];
            $lockedPlanet->save();

            return $lockedUser->researchQueues()->create([
                'universe_id' => $lockedUser->universe_id,
                'planet_id' => $lockedPlanet->id,
                'technology_key' => $technologyKey,
                'technology_code' => $technology['code'],
                'target_level' => $currentLevel + 1,
                'cost_metal' => $cost['metal'],
                'cost_crystal' => $cost['crystal'],
                'cost_deuterium' => $cost['deuterium'],
                'completes_at' => now()->addSeconds($this->duration($cost, $lockedPlanet)),
            ]);
        }, 3);
    }

    public function cancel(User $user): void
    {
        $this->complete($user);

        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $queue = $lockedUser->researchQueues()->first();
            if (! $queue) {
                throw ValidationException::withMessages(['research' => 'Aucune recherche n’est en cours.']);
            }

            $planet = Planet::query()->whereKey($queue->planet_id)->where('user_id', $lockedUser->id)
                ->where('universe_id', $lockedUser->universe_id)->lockForUpdate()->firstOrFail();
            $planet->metal += $queue->cost_metal;
            $planet->crystal += $queue->cost_crystal;
            $planet->deuterium += $queue->cost_deuterium;
            $planet->save();
            $queue->delete();
        }, 3);
    }

    public function complete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $queue = $user->researchQueues()->where('completes_at', '<=', now())->first();
            if (! $queue) {
                return;
            }

            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $queue = $lockedUser->researchQueues()->where('completes_at', '<=', now())->first();
            if (! $queue) {
                return;
            }

            $column = $queue->technology_key === 'sensors'
                ? 'sensor_technology_level'
                : self::TECHNOLOGIES[$queue->technology_key]['column'];
            $lockedUser->{$column} = max((int) $lockedUser->{$column}, (int) $queue->target_level);
            $lockedUser->save();
            $queue->delete();
        }, 3);
    }

    private function cost(array $technology, int $level): array
    {
        $multiplier = ($technology['factor'] ?? ($technology['code'] === 199 ? 3 : 2)) ** $level;

        return ['metal' => (int) floor($technology['cost'][0] * $multiplier),
            'crystal' => (int) floor($technology['cost'][1] * $multiplier),
            'deuterium' => (int) floor($technology['cost'][2] * $multiplier),
            'energy' => (int) ($technology['energy'] ?? 0)];
    }

    private function duration(array $cost, Planet $planet): int
    {
        return max(1, (int) floor(($cost['metal'] + $cost['crystal']) / (((int) $planet->laboratory_level + 1) * 2) * 3600));
    }
}
