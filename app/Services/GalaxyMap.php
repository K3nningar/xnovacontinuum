<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Implements the GalaxyMap application service.
 */

namespace App\Services;

use App\Models\Planet;
use App\Models\Universe;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GalaxyMap
{
    public function __construct(private CelestialNames $names) {}

    private const PLANET_RESOURCES = [
        'silicate' => ['metal' => 100, 'crystal' => 100, 'deuterium' => 100],
        'carbon' => ['metal' => 90, 'crystal' => 120, 'deuterium' => 100],
        'metallic' => ['metal' => 135, 'crystal' => 85, 'deuterium' => 80],
        'magmatic' => ['metal' => 125, 'crystal' => 110, 'deuterium' => 65],
        'gaseous' => ['metal' => 70, 'crystal' => 85, 'deuterium' => 140],
        'desert' => ['metal' => 110, 'crystal' => 105, 'deuterium' => 75],
        'oceanic' => ['metal' => 80, 'crystal' => 105, 'deuterium' => 130],
    ];

    /**
     * Builds deterministic star/body data and viewer-filtered slots for one system.
     *
     * Planet and asteroid depletion records override generated asteroid stock; otherwise the same coordinates
     * always yield the same star, body types, concentrations, and natural moons. Positions 1 and 16 are reserved.
     *
     * @return array{star: string, starName: string, slots: array<int, array<string, mixed>>}
     */
    public function system(Universe $universe, User $viewer, int $galaxy, int $system, int $originPosition): array
    {
        $planets = Planet::query()
            ->whereNull('destroyed_at')
            ->where('universe_id', $universe->id)
            ->where('galaxy', $galaxy)
            ->where('system', $system)
            ->with('user:id,name,alliance_id')
            ->get()
            ->keyBy('position');

        abort_if($planets->has(1) || $planets->has(16), 409, 'Ce système contient une planète sur un emplacement réservé.');

        $asteroidFields = DB::table('asteroid_fields')
            ->where('universe_id', $universe->id)->where('galaxy', $galaxy)->where('system', $system)
            ->get()->keyBy('position');
        $destroyedMoons = DB::table('destroyed_moons')
            ->where('universe_id', $universe->id)->where('galaxy', $galaxy)->where('system', $system)
            ->get()->keyBy(fn ($moon): string => $moon->position.':'.$moon->orbit);
        $moonStocks = DB::table('moon_stocks')->whereIn('planet_id', $planets->pluck('id'))
            ->get()->keyBy(fn ($moon): string => "{$moon->planet_id}:{$moon->orbit}");
        $systemNames = $this->names->forSystem($universe, $galaxy, $system);

        $starRoll = $this->roll($universe, $galaxy, $system, 1, 'star', 100);
        $star = match (true) {
            $starRoll < 2 => 'black_hole',
            $starRoll < 5 => 'neutron_star',
            $starRoll < 13 => 'white_dwarf',
            $starRoll < 25 => 'blue_giant',
            $starRoll < 40 => 'red_giant',
            $starRoll < 70 => 'red_dwarf',
            default => 'yellow_dwarf',
        };

        $slots = [];
        for ($position = 2; $position <= 15; $position++) {
            $planet = $planets->get($position);
            $requiredSpyLevel = abs($position - $originPosition);
            if ((int) $viewer->spy_technology_level < $requiredSpyLevel && (! $planet || (int) $planet->user_id !== (int) $viewer->id)) {
                $slots[] = ['position' => $position, 'visible' => false, 'requiredSpyLevel' => $requiredSpyLevel];

                continue;
            }

            $asteroid = $position !== 8 && ! $planet && $this->roll($universe, $galaxy, $system, $position, 'body', 100) < 22;
            if ($asteroid) {
                $type = $position >= 10 ? 'ice' : 'rock';
                $concentration = 50 + $this->roll($universe, $galaxy, $system, $position, 'concentration', 101);
                $amount = $concentration * 10000;
                $field = $asteroidFields->get($position);
                $slots[] = [
                    'position' => $position,
                    'visible' => true,
                    'kind' => 'asteroid',
                    'type' => $type,
                    'concentration' => $concentration,
                    'remaining' => $field ? ['metal' => (int) $field->metal, 'crystal' => (int) $field->crystal, 'deuterium' => (int) $field->deuterium] : [
                        'metal' => $type === 'rock' ? $amount : 0,
                        'crystal' => intdiv($amount, 2),
                        'deuterium' => $type === 'ice' ? $amount : 0,
                    ],
                ];

                continue;
            }

            $type = $this->planetType($universe, $galaxy, $system, $position);
            $maximumMoons = $type === 'gaseous' ? 4 : ($position <= 4 ? 1 : 2);
            $generatedMoonCount = $this->roll($universe, $galaxy, $system, $position, 'moons', $maximumMoons + 1);
            $moons = [];
            for ($orbit = 1; $orbit <= $generatedMoonCount; $orbit++) {
                if ($destroyedMoons->has($position.':'.$orbit)) {
                    continue;
                }
                $moonStock = $planet ? $moonStocks->get("{$planet->id}:{$orbit}") : null;
                $generatedMoonName = $systemNames['moonNames'][$position][$orbit];
                $moons[] = [
                    'orbit' => $orbit,
                    'diameter' => 900 + $this->roll($universe, $galaxy, $system, $position, 'moon-diameter-'.$orbit, 5101),
                    'generatedName' => $generatedMoonName,
                    'planetGeneratedName' => $systemNames['planetNames'][$position],
                    'name' => $moonStock?->name ?? $generatedMoonName,
                ];
            }
            $moonCount = count($moons);
            $slots[] = [
                'position' => $position,
                'visible' => true,
                'kind' => 'planet',
                'type' => $type,
                'generatedName' => $systemNames['planetNames'][$position],
                'resources' => $planet ? [
                    'metal' => (int) $planet->metal_concentration,
                    'crystal' => (int) $planet->crystal_concentration,
                    'deuterium' => (int) $planet->deuterium_concentration,
                ] : self::PLANET_RESOURCES[$type],
                'moons' => $moonCount,
                'naturalMoons' => $moons,
                'planet' => $planet ? [
                    'name' => $planet->name,
                    'owner' => $planet->user->name,
                    'owned' => (int) $planet->user_id === (int) $viewer->id,
                    'alliedDepot' => (int) $planet->user_id !== (int) $viewer->id && $viewer->alliance_id !== null && (int) $planet->user->alliance_id === (int) $viewer->alliance_id && (int) $planet->alliance_depot_level > 0,
                    ...((int) $planet->user_id === (int) $viewer->id ? ['id' => $planet->id] : []),
                ] : null,
            ];
        }

        return ['star' => $star, 'starName' => $systemNames['starName'], 'slots' => $slots];
    }

    /**
     * Returns deterministic resource concentrations for a planet at the supplied universe coordinates.
     *
     * @return array{metal: int, crystal: int, deuterium: int}
     */
    public function planetConcentrations(Universe $universe, int $galaxy, int $system, int $position): array
    {
        return self::PLANET_RESOURCES[$this->planetType($universe, $galaxy, $system, $position)];
    }

    /**
     * Resolves a natural moon only when the planet belongs to the user's current universe and the orbit exists.
     *
     * Ownership mismatches and nonexistent moons return 404; reserved planet positions return 409.
     *
     * @return array{orbit: int, diameter: int}
     */
    public function ownedMoon(User $user, Planet $planet, int $orbit): array
    {
        abort_if((int) $planet->user_id !== (int) $user->id || (int) $planet->universe_id !== (int) $user->universe_id, 404);
        abort_if($planet->position < 2 || $planet->position > 15, 409);
        $slot = $this->system($planet->universe, $user, $planet->galaxy, $planet->system, $planet->position)['slots'][$planet->position - 2];
        $moon = collect($slot['naturalMoons'])->firstWhere('orbit', $orbit);
        abort_if(! $moon, 404);

        return $moon;
    }

    /**
     * Chooses a coordinate-stable planet type from a position-dependent candidate ordering.
     */
    private function planetType(Universe $universe, int $galaxy, int $system, int $position): string
    {
        $types = $position <= 5
            ? ['magmatic', 'metallic', 'desert', 'silicate', 'carbon', 'oceanic', 'gaseous']
            : ($position >= 11
                ? ['gaseous', 'oceanic', 'carbon', 'silicate', 'metallic', 'desert', 'magmatic']
                : array_keys(self::PLANET_RESOURCES));

        return $types[$this->roll($universe, $galaxy, $system, $position, 'planet-type', count($types))];
    }

    /**
     * Produces a deterministic integer in [0, $maximum) from a versioned coordinate-and-purpose hash.
     *
     * Separate purpose strings keep star, body, and moon calculations stable and independent; $maximum must be positive.
     */
    private function roll(Universe $universe, int $galaxy, int $system, int $position, string $purpose, int $maximum): int
    {
        return (int) (hexdec(substr(hash('sha256', "galaxy-v1:{$universe->id}:{$galaxy}:{$system}:{$position}:{$purpose}"), 0, 7)) % $maximum);
    }
}
