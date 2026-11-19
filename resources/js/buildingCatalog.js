/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Provides application functionality for buildingCatalog.
 */

export const buildingCategories = [
    { key: 'all', icon: 'bi-grid' },
    { key: 'production', icon: 'bi-lightning-charge' },
    { key: 'storage', icon: 'bi-box-seam' },
    { key: 'industry', icon: 'bi-gear' },
    { key: 'research', icon: 'bi-lightbulb' },
    { key: 'defence', icon: 'bi-shield' },
    { key: 'special', icon: 'bi-stars' },
];

export const buildingCatalog = [
    { key: 'metal', category: 'production', level: 'metal_mine_level', activity: 'metal_mine_activity', code: 1, icon: '▰' },
    { key: 'crystal', category: 'production', level: 'crystal_mine_level', activity: 'crystal_mine_activity', code: 2, icon: '◇' },
    { key: 'deuterium', category: 'production', level: 'deuterium_synthesizer_level', activity: 'deuterium_synthesizer_activity', code: 3, icon: '⬡' },
    { key: 'solar', category: 'production', level: 'solar_plant_level', activity: 'solar_plant_activity', code: 4, icon: '☼' },
    { key: 'fusion', category: 'production', level: 'fusion_plant_level', activity: 'fusion_plant_activity', code: 12, icon: '⌬', requirementKey: 'fusionRequirement' },
    { key: 'robot_factory', category: 'industry', level: 'robot_factory_level', activity: null, code: 14, icon: 'R', requirementKey: 'robotEffect', maxLevel: 30 },
    { key: 'nano_factory', category: 'industry', level: 'nano_factory_level', activity: null, code: 15, icon: 'N', requirementKey: 'nanoRequirement', maxLevel: 30 },
    { key: 'hangar', category: 'industry', level: 'hangar_level', activity: null, code: 21, icon: 'H', requirementKey: 'shipyardRequirement' },
    { key: 'metal_storage', category: 'storage', level: 'metal_storage_level', activity: null, code: 22, icon: 'M', resource: 'metal', maxLevel: 30 },
    { key: 'crystal_storage', category: 'storage', level: 'crystal_storage_level', activity: null, code: 23, icon: 'C', resource: 'crystal', maxLevel: 30 },
    { key: 'deuterium_storage', category: 'storage', level: 'deuterium_storage_level', activity: null, code: 24, icon: 'D', resource: 'deuterium', maxLevel: 30 },
    { key: 'laboratory', category: 'research', level: 'laboratory_level', activity: 'laboratory_activity', code: 31, icon: 'L', requirementKey: 'laboratoryRequirement' },
    { key: 'terraformer', category: 'special', level: 'terraformer_level', activity: null, code: 33, icon: 'T', maxLevel: 30, requirementKey: 'terraformerRequirement' },
    { key: 'missile_silo', category: 'defence', level: 'missile_silo_level', activity: null, code: 44, icon: 'X', requirementKey: 'siloRequirement' },
];

/** Return all facilities for the reset option, or only those assigned to a category. */
export function buildingsInCategory(buildings, category) {
    return category === 'all' ? buildings : buildings.filter((building) => building.category === category);
}
