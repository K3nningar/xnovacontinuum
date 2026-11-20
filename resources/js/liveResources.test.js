/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Tests the liveResources.test JavaScript module.
 */

import assert from 'node:assert/strict';
import test from 'node:test';
import { projectPlanetResources } from './liveResources.js';

const updatedAt = '2026-01-01T00:00:00Z';
const start = Date.parse(updatedAt);

function planet(overrides = {}, liveOverrides = {}) {
    return {
        metal: 100,
        crystal: 200,
        deuterium: 50,
        ...overrides,
        liveResources: {
            updatedAt,
            rates: { metal: 3600, crystal: 1800, deuterium: 0 },
            remainders: { metal: 0, crystal: 1800, deuterium: 0 },
            capacities: { metal: 1000, crystal: 1000, deuterium: 1000 },
            fusionUsage: 0,
            fusionRemainder: 0,
            nextChangeAt: null,
            ...liveOverrides,
        },
    };
}

test('projects whole resources each second including stored fractions', () => {
    const result = projectPlanetResources(planet(), start + 3000);
    assert.deepEqual([result.metal, result.crystal, result.deuterium], [103, 202, 50]);
});

test('respects storage limits without removing existing excess', () => {
    const result = projectPlanetResources(planet({ metal: 1200, crystal: 1099 }), start + 10_000);
    assert.equal(result.metal, 1200);
    assert.equal(result.crystal, 1100);
});

test('subtracts fusion consumption without allowing negative resources', () => {
    const result = projectPlanetResources(planet({ deuterium: 2 }, {
        fusionUsage: 3600,
        fusionRemainder: 1800,
        rates: { metal: 0, crystal: 0, deuterium: 1800 },
    }), start + 5000);
    assert.equal(result.deuterium, 0);
});

// Projection must stop at state-changing events, expire stale snapshots, and never run backward in time.
test('does not extrapolate past a queued change or the next synchronization', () => {
    const queued = planet({}, { nextChangeAt: '2026-01-01T00:00:03Z' });
    assert.equal(projectPlanetResources(queued, start + 30_000).metal, 103);
    assert.equal(projectPlanetResources(planet(), start + 120_000).metal, 160);
    assert.equal(projectPlanetResources(planet(), start - 1000).metal, 100);
});
