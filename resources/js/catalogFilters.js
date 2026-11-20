/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Provides application functionality for catalogFilters.
 */

export const researchFilters = [
    { key: 'information', icon: 'bi-radar', codes: [106, 108, 123, 124] },
    { key: 'combat', icon: 'bi-crosshair', codes: [109, 110, 111, 120, 121, 122] },
    { key: 'propulsion', icon: 'bi-rocket-takeoff', codes: [115, 117, 118] },
    { key: 'energy', icon: 'bi-lightning-charge', codes: [113, 114, 199] },
];

export const shipFilters = [
    { key: 'civilian', icon: 'bi-box-seam', codes: [202, 203, 208, 209, 210, 212] },
    { key: 'combat', icon: 'bi-crosshair', codes: [204, 205, 206, 207, 211, 213, 214, 215, 216, 217] },
];

export const defenceFilters = [
    { key: 'artillery', icon: 'bi-crosshair', codes: [401, 402, 403, 404, 405, 406] },
    { key: 'shields', icon: 'bi-shield', codes: [407, 408, 409] },
    { key: 'missiles', icon: 'bi-rocket-takeoff', codes: [502, 503] },
];

export const officerFilters = [
    { key: 'economy', icon: 'bi-bar-chart', codes: [601, 605, 607] },
    { key: 'science', icon: 'bi-cpu', codes: [604, 606, 610] },
    { key: 'defence', icon: 'bi-shield', codes: [603, 608, 609] },
    { key: 'command', icon: 'bi-crosshair', codes: [602, 611, 612, 613, 614, 615] },
];

/** Count nonempty configured groups and expose unclassified items in an explicit fallback group. */
export function catalogCategories(items, groups) {
    const assignedCodes = new Set(groups.flatMap((group) => group.codes));
    const categories = groups.map((group) => ({
        ...group,
        count: items.filter((item) => group.codes.includes(item.code)).length,
    })).filter((group) => group.count > 0);
    const otherCodes = items.filter((item) => !assignedCodes.has(item.code)).map((item) => item.code);

    if (otherCodes.length) {
        categories.push({ key: 'other', icon: 'bi-grid', codes: otherCodes, count: otherCodes.length });
    }

    return [{ key: 'all', icon: 'bi-grid', count: items.length }, ...categories];
}

/** Match the inclusive all view or an item's code against a currently available category. */
export function matchesCatalogCategory(item, category, categories) {
    return category === 'all' || categories.find((group) => group.key === category)?.codes?.includes(item.code) === true;
}
