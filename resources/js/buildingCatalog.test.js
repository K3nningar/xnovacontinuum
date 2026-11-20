/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Tests the buildingCatalog.test JavaScript module.
 */

import assert from 'node:assert/strict';
import test from 'node:test';
import { buildingCatalog, buildingCategories, buildingsInCategory } from './buildingCatalog.js';

test('filters the catalog into its real facility categories', () => {
    assert.deepEqual(buildingsInCategory(buildingCatalog, 'all'), buildingCatalog);
    assert.deepEqual(buildingsInCategory(buildingCatalog, 'production').map((building) => building.key), ['metal', 'crystal', 'deuterium', 'solar', 'fusion']);
    assert.deepEqual(buildingsInCategory(buildingCatalog, 'storage').map((building) => building.key), ['metal_storage', 'crystal_storage', 'deuterium_storage']);
    assert.deepEqual(buildingsInCategory(buildingCatalog, 'industry').map((building) => building.key), ['robot_factory', 'nano_factory', 'hangar']);
    assert.deepEqual(buildingsInCategory(buildingCatalog, 'research').map((building) => building.key), ['laboratory']);
    assert.deepEqual(buildingsInCategory(buildingCatalog, 'defence').map((building) => building.key), ['missile_silo']);
    assert.deepEqual(buildingsInCategory(buildingCatalog, 'special').map((building) => building.key), ['terraformer']);
    assert.deepEqual(buildingsInCategory(buildingCatalog, 'unknown'), []);
});

// Categories form a complete, non-overlapping partition of the catalog and every filter has content.
test('every facility belongs to one available nonempty category', () => {
    const categories = buildingCategories.filter((category) => category.key !== 'all');
    assert.equal(new Set(buildingCatalog.map((building) => building.key)).size, buildingCatalog.length);
    assert.equal(categories.reduce((count, category) => count + buildingsInCategory(buildingCatalog, category.key).length, 0), buildingCatalog.length);
    assert.ok(categories.every((category) => buildingsInCategory(buildingCatalog, category.key).length > 0));
});
