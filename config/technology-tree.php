<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Defines the technology-tree application configuration.
 */

return [
    'building_rarities' => [
        1 => 'ordinary',
        2 => 'ordinary',
        3 => 'ordinary',
        4 => 'ordinary',
        12 => 'remarkable',
        14 => 'remarkable',
        15 => 'epic',
        20 => 'exceptional',
        21 => 'exceptional',
        22 => 'remarkable',
        23 => 'remarkable',
        24 => 'remarkable',
        31 => 'exceptional',
        33 => 'epic',
        34 => 'exceptional',
        44 => 'exceptional',
    ],
    'buildings' => [
        ['metal', 1, 'metal_mine_level'], ['crystal', 2, 'crystal_mine_level'],
        ['deuterium', 3, 'deuterium_synthesizer_level'], ['solar', 4, 'solar_plant_level'],
        ['fusion', 12, 'fusion_plant_level', [3 => 5, 113 => 3]],
        ['robot_factory', 14, 'robot_factory_level'], ['nano_factory', 15, 'nano_factory_level', [14 => 10, 108 => 10]],
        ['defence_center', 20, 'defence_center_level'],
        ['hangar', 21, 'hangar_level', [14 => 2]],
        ['metal_storage', 22, 'metal_storage_level'], ['crystal_storage', 23, 'crystal_storage_level'],
        ['deuterium_storage', 24, 'deuterium_storage_level'], ['laboratory', 31, 'laboratory_level'],
        ['terraformer', 33, 'terraformer_level', [15 => 1, 113 => 12]], ['alliance_depot', 34, 'alliance_depot_level'],
        ['missile_silo', 44, 'missile_silo_level'],
    ],
];
