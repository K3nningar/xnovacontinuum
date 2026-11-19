import assert from 'node:assert/strict';
import test from 'node:test';
import { normalizeFleetMission } from './fleetMissionForm.js';

test('normalizes ship quantities and omits an unused moon orbit for colonization', () => {
    const result = normalizeFleetMission({
        mission: 'colonize',
        ships: { 208: 1, 202: '0' },
        moon_orbit: null,
        cargo: { metal: 0, crystal: 0, deuterium: 0 },
    });

    assert.deepEqual(result.ships, { 208: 1, 202: 0 });
    assert.equal(Object.hasOwn(result, 'moon_orbit'), false);
});

test('converts target and origin moon selections to orbit numbers', () => {
    const result = normalizeFleetMission({ mission: 'attack', target_type: 'moon', moon_orbit: '2', origin_target_type: 'moon', origin_moon_orbit: '1', ships: { 214: 1 } });

    assert.equal(result.moon_orbit, 2);
    assert.equal(result.origin_moon_orbit, 1);
});
