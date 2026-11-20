<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Defines the combat application configuration.
 */

return [
    'max_units_per_side' => 500,
    'max_rounds' => 6,
    'experimental_planetary_destroyer' => env('COMBAT_EXPERIMENTAL_PLANETARY_DESTROYER', false),
    'special_profiles' => [
        216 => ['attack' => 1000, 'shield' => 0, 'hull' => 15000],
        217 => ['attack' => 300000, 'shield' => 100000, 'hull' => 200000],
        409 => ['attack' => 1000, 'shield' => 0, 'hull' => 35000],
    ],
    'units' => [
        202 => ['key' => 'small_cargo', 'type' => 'ships', 'attack' => 5, 'shield' => 10, 'hull' => 400, 'rapid_fire' => [210 => 5, 212 => 5]],
        203 => ['key' => 'large_cargo', 'type' => 'ships', 'attack' => 5, 'shield' => 25, 'hull' => 1200, 'rapid_fire' => [210 => 5, 212 => 5]],
        204 => ['key' => 'light_fighter', 'type' => 'ships', 'attack' => 50, 'shield' => 10, 'hull' => 400, 'rapid_fire' => [202 => 2, 210 => 5, 212 => 5]],
        205 => ['key' => 'heavy_fighter', 'type' => 'ships', 'attack' => 150, 'shield' => 25, 'hull' => 1000, 'rapid_fire' => [202 => 3, 210 => 5, 212 => 5]],
        206 => ['key' => 'cruiser', 'type' => 'ships', 'attack' => 400, 'shield' => 50, 'hull' => 2700, 'rapid_fire' => [204 => 6, 210 => 5, 212 => 5, 401 => 10]],
        207 => ['key' => 'battleship', 'type' => 'ships', 'attack' => 1000, 'shield' => 200, 'hull' => 6000, 'rapid_fire' => [210 => 5, 212 => 5, 401 => 8]],
        208 => ['key' => 'colony_ship', 'type' => 'ships', 'attack' => 50, 'shield' => 100, 'hull' => 3000, 'rapid_fire' => [210 => 5, 212 => 5]],
        209 => ['key' => 'recycler', 'type' => 'ships', 'attack' => 1, 'shield' => 10, 'hull' => 1600, 'rapid_fire' => [210 => 5, 212 => 5]],
        210 => ['key' => 'espionage_probe', 'type' => 'ships', 'attack' => 0, 'shield' => 0, 'hull' => 100, 'rapid_fire' => []],
        211 => ['key' => 'bomber', 'type' => 'ships', 'attack' => 1000, 'shield' => 500, 'hull' => 7500, 'rapid_fire' => [210 => 5, 212 => 5, 401 => 20, 402 => 20, 403 => 10, 405 => 10]],
        212 => ['key' => 'solar_satellite', 'type' => 'ships', 'attack' => 1, 'shield' => 10, 'hull' => 200, 'rapid_fire' => []],
        213 => ['key' => 'destroyer', 'type' => 'ships', 'attack' => 2000, 'shield' => 500, 'hull' => 11000, 'rapid_fire' => [210 => 5, 212 => 5, 215 => 2, 402 => 10]],
        214 => ['key' => 'deathstar', 'type' => 'ships', 'attack' => 200000, 'shield' => 50000, 'hull' => 900000, 'rapid_fire' => [202 => 250, 203 => 250, 204 => 200, 205 => 100, 206 => 33, 207 => 30, 208 => 250, 209 => 250, 210 => 1250, 211 => 25, 212 => 1250, 213 => 5, 215 => 15, 401 => 200, 402 => 200, 403 => 100, 404 => 50, 405 => 100]],
        215 => ['key' => 'battlecruiser', 'type' => 'ships', 'attack' => 700, 'shield' => 400, 'hull' => 7000, 'rapid_fire' => [202 => 3, 203 => 3, 205 => 4, 206 => 4, 207 => 7, 210 => 5, 212 => 5]],
        216 => ['key' => 'supernova', 'type' => 'ships', 'custom_profile' => true, 'hull' => 15000, 'rapid_fire' => []],
        217 => ['key' => 'planetary_destroyer', 'type' => 'ships', 'custom_profile' => true, 'hull' => 200000, 'rapid_fire' => []],
        401 => ['key' => 'rocket_launcher', 'type' => 'defences', 'attack' => 80, 'shield' => 20, 'hull' => 200, 'rapid_fire' => [210 => 5]],
        402 => ['key' => 'light_laser', 'type' => 'defences', 'attack' => 100, 'shield' => 25, 'hull' => 200, 'rapid_fire' => [210 => 5]],
        403 => ['key' => 'heavy_laser', 'type' => 'defences', 'attack' => 250, 'shield' => 100, 'hull' => 800, 'rapid_fire' => [210 => 5]],
        404 => ['key' => 'gauss_cannon', 'type' => 'defences', 'attack' => 1100, 'shield' => 200, 'hull' => 3500, 'rapid_fire' => [210 => 5]],
        405 => ['key' => 'ion_cannon', 'type' => 'defences', 'attack' => 150, 'shield' => 500, 'hull' => 800, 'rapid_fire' => [210 => 5]],
        406 => ['key' => 'plasma_turret', 'type' => 'defences', 'attack' => 3000, 'shield' => 300, 'hull' => 10000, 'rapid_fire' => [210 => 5]],
        407 => ['key' => 'small_shield_dome', 'type' => 'defences', 'attack' => 1, 'shield' => 2000, 'hull' => 2000, 'rapid_fire' => [210 => 5]],
        408 => ['key' => 'large_shield_dome', 'type' => 'defences', 'attack' => 1, 'shield' => 2000, 'hull' => 10000, 'rapid_fire' => [210 => 5]],
        409 => ['key' => 'planetary_protector', 'type' => 'defences', 'custom_profile' => true, 'hull' => 35000, 'rapid_fire' => []],
    ],
];
