/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Tests the catalogFilters.test JavaScript module.
 */

import assert from 'node:assert/strict';
import test from 'node:test';
import { catalogCategories, defenceFilters, matchesCatalogCategory, officerFilters, researchFilters, shipFilters } from './catalogFilters.js';

// Group definitions must partition the known catalogs exactly once, with no dropped or duplicated codes.
test('catalog filters classify each real research, ship, defence and officer once', () => {
    for (const [groups, codes] of [
        [researchFilters, [106, 108, 109, 110, 111, 113, 114, 115, 117, 118, 120, 121, 122, 123, 124, 199]],
        [shipFilters, Array.from({ length: 16 }, (_, index) => 202 + index)],
        [defenceFilters, [401, 402, 403, 404, 405, 406, 407, 408, 409, 502, 503]],
        [officerFilters, Array.from({ length: 15 }, (_, index) => 601 + index)],
    ]) {
        const items = codes.map((code) => ({ code }));
        const categories = catalogCategories(items, groups);
        assert.equal(categories[0].count, items.length);
        assert.equal(categories.slice(1).reduce((sum, group) => sum + group.count, 0), items.length);
        assert.ok(items.every((item) => categories.slice(1).filter((group) => matchesCatalogCategory(item, group.key, categories)).length === 1));
        assert.ok(items.every((item) => matchesCatalogCategory(item, 'all', categories)));
    }
});

test('officer filters show the selected specialty and keep unknown officers accessible', () => {
    const items = [601, 604, 603, 602, 999].map((code) => ({ code }));
    const categories = catalogCategories(items, officerFilters);

    for (const [category, code] of [['economy', 601], ['science', 604], ['defence', 603], ['command', 602], ['other', 999]]) {
        assert.deepEqual(items.filter((item) => matchesCatalogCategory(item, category, categories)).map((item) => item.code), [code]);
    }
});

test('unknown items remain accessible in an other category and empty categories are hidden', () => {
    const items = [{ code: 202 }, { code: 999 }];
    const categories = catalogCategories(items, shipFilters);
    assert.deepEqual(categories.map(({ key, count }) => [key, count]), [['all', 2], ['civilian', 1], ['other', 1]]);
    assert.equal(matchesCatalogCategory(items[1], 'other', categories), true);
    assert.equal(matchesCatalogCategory(items[0], 'other', categories), false);
    assert.equal(matchesCatalogCategory(items[0], 'unknown', categories), false);
});
