<?php

/*
 * Generates and resolves stable, related names for stars, planets, and moons in each universe.
 */

namespace App\Services;

use App\Models\Universe;
use Illuminate\Support\Facades\DB;

class CelestialNames
{
    private const PREFIXES = [
        'Ael', 'Astra', 'Cael', 'Cyr', 'Drae', 'Ely', 'Ery', 'Fey', 'Gael', 'Hesper',
        'Ilyr', 'Jor', 'Kael', 'Kyr', 'Lyr', 'Mira', 'Ner', 'Nyx', 'Or', 'Phae',
        'Quor', 'Rhea', 'Syr', 'Tala', 'Thal', 'Umbra', 'Vey', 'Xan', 'Yl', 'Zor',
    ];

    private const SUFFIXES = [
        'a', 'ara', 'aris', 'eon', 'eth', 'ia', 'ion', 'ios', 'is', 'ius',
        'ora', 'or', 'os', 'une', 'ura', 'yne', 'yra', 'yr', 'el', 'en',
    ];

    private const ROMAN_NUMERALS = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII',
        8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII', 13 => 'XIII', 14 => 'XIV', 15 => 'XV',
    ];

    private const MOON_SUFFIXES = [1 => 'a', 2 => 'b', 3 => 'c', 4 => 'd'];

    /**
     * Pre-generates the naming catalog for every system in a newly created universe.
     *
     * @return int The number of generated systems.
     */
    public function preGenerate(Universe $universe): int
    {
        $batch = [];
        $total = 0;
        $now = now();
        $batchSize = 250;

        for ($galaxy = 1; $galaxy <= (int) $universe->galaxies; $galaxy++) {
            for ($system = 1; $system <= (int) $universe->systems_per_galaxy; $system++) {
                $names = $this->generateSystem($universe, $galaxy, $system);
                $batch[] = [
                    'universe_id' => $universe->id,
                    'galaxy' => $galaxy,
                    'system' => $system,
                    'star_name' => $names['starName'],
                    'planet_names' => json_encode($names['planetNames'], JSON_THROW_ON_ERROR),
                    'moon_names' => json_encode($names['moonNames'], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) >= $batchSize) {
                    $total += DB::table('universe_system_names')->insertOrIgnore($batch);
                    $batch = [];
                }
            }
        }

        if ($batch !== []) {
            $total += DB::table('universe_system_names')->insertOrIgnore($batch);
        }

        return $total;
    }

    public function backfillMissing(int $systemsPerChunk = 250): int
    {
        if ($systemsPerChunk < 1) {
            throw new \InvalidArgumentException('La taille de lot doit être positive.');
        }

        $total = 0;

        foreach (Universe::query()->orderBy('id')->get() as $universe) {
            $known = DB::table('universe_system_names')->where('universe_id', $universe->id)
                ->get(['galaxy', 'system'])
                ->mapWithKeys(fn (object $system): array => ["{$system->galaxy}:{$system->system}" => true]);
            $batch = [];

            for ($galaxy = 1; $galaxy <= (int) $universe->galaxies; $galaxy++) {
                for ($system = 1; $system <= (int) $universe->systems_per_galaxy; $system++) {
                    if ($known->has("{$galaxy}:{$system}")) {
                        continue;
                    }

                    $names = $this->generateSystem($universe, $galaxy, $system);
                    $batch[] = [
                        'universe_id' => $universe->id, 'galaxy' => $galaxy, 'system' => $system,
                        'star_name' => $names['starName'],
                        'planet_names' => json_encode($names['planetNames'], JSON_THROW_ON_ERROR),
                        'moon_names' => json_encode($names['moonNames'], JSON_THROW_ON_ERROR),
                        'created_at' => now(), 'updated_at' => now(),
                    ];

                    if (count($batch) >= $systemsPerChunk) {
                        $total += DB::table('universe_system_names')->insertOrIgnore($batch);
                        $batch = [];
                    }
                }
            }

            if ($batch !== []) {
                $total += DB::table('universe_system_names')->insertOrIgnore($batch);
            }
        }

        return $total;
    }

    /**
     * Returns the persisted catalog for a system, or a deterministic legacy fallback for older universes.
     *
     * @return array{starName: string, planetNames: array<int, string>, moonNames: array<int, array<int, string>>}
     */
    public function forSystem(Universe $universe, int $galaxy, int $system): array
    {
        $catalog = DB::table('universe_system_names')->where('universe_id', $universe->id)
            ->where('galaxy', $galaxy)->where('system', $system)->first();

        if (! $catalog) {
            return $this->generateSystem($universe, $galaxy, $system);
        }

        return [
            'starName' => $catalog->star_name,
            'planetNames' => json_decode($catalog->planet_names, true, 512, JSON_THROW_ON_ERROR),
            'moonNames' => json_decode($catalog->moon_names, true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @return array{starName: string, planetNames: array<int, string>, moonNames: array<int, array<int, string>>}
     */
    private function generateSystem(Universe $universe, int $galaxy, int $system): array
    {
        $seed = "xnova-celestial-v1:{$universe->id}:{$universe->slug}:{$galaxy}:{$system}";
        $prefix = self::PREFIXES[$this->index($seed.':prefix', count(self::PREFIXES))];
        $suffix = self::SUFFIXES[$this->index($seed.':suffix', count(self::SUFFIXES))];
        $starName = sprintf('%s%s G%d-S%03d', $prefix, $suffix, $galaxy, $system);
        $planetNames = [];
        $moonNames = [];

        for ($position = 2; $position <= 15; $position++) {
            $planetName = $starName.' '.self::ROMAN_NUMERALS[$position];
            $planetNames[$position] = $planetName;

            foreach (self::MOON_SUFFIXES as $orbit => $moonSuffix) {
                $moonNames[$position][$orbit] = $planetName.'-'.$moonSuffix;
            }
        }

        return ['starName' => $starName, 'planetNames' => $planetNames, 'moonNames' => $moonNames];
    }

    private function index(string $seed, int $maximum): int
    {
        return (int) (hexdec(substr(hash('sha256', $seed), 0, 7)) % $maximum);
    }
}
