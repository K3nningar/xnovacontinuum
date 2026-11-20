/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Renders the combatForm.test Inertia page.
 */

import assert from 'node:assert/strict';
import test from 'node:test';
import { emptyCombatForm, prepareCombatSubmission, randomCombatForm } from './combatForm.js';

const units = [
    { code: 202, type: 'ships' },
    { code: 204, type: 'ships' },
    { code: 216, type: 'ships', customProfile: true, hull: 15000 },
    { code: 217, type: 'ships', customProfile: true, hull: 200000 },
    { code: 407, type: 'defences' },
    { code: 408, type: 'defences' },
    { code: 409, type: 'defences', customProfile: true, hull: 35000 },
];

test('clearing empties both armies, technologies, special profiles and seed', () => {
    const form = emptyCombatForm(units);

    for (const side of ['attacker', 'defender']) {
        assert.ok(Object.values(form[side].fleet).every((count) => count === ''));
        assert.ok(Object.values(form[side].technologies).every((level) => level === ''));
    }
    for (const profile of Object.values(form.special_profiles)) {
        assert.deepEqual(profile, { attack: '', shield: '', hull: '' });
    }
    assert.ok(!Object.hasOwn(form.attacker.fleet, 409));
    assert.ok(Object.hasOwn(form.defender.fleet, 409));
    assert.equal(form.seed, '');
});

test('empty army and technology fields are submitted as zero without changing the form', () => {
    const form = emptyCombatForm(units);
    form.attacker.fleet[202] = 3;
    form.defender.fleet[204] = 1;
    form.attacker.technologies.weapons = 2;
    const submitted = prepareCombatSubmission(form);

    assert.equal(submitted.attacker.fleet[202], 3);
    assert.equal(submitted.attacker.fleet[204], 0);
    assert.equal(submitted.defender.fleet[204], 1);
    assert.equal(submitted.attacker.technologies.weapons, 2);
    assert.equal(submitted.defender.technologies.shields, 0);
    assert.equal(form.attacker.fleet[204], '');
    assert.equal(submitted.special_profiles[216].attack, '');
});

// Exercise RNG extremes and army-size boundaries to lock down server-validation-compatible generated inputs.
test('random data stays within validation limits even at random and army boundaries', () => {
    for (const random of [() => 0, () => 0.999999999]) {
        for (const maxUnits of [1, 500]) {
            const form = randomCombatForm(units, maxUnits, random);

            for (const code of [407, 408, 409]) {
                assert.ok(!Object.hasOwn(form.attacker.fleet, code));
                assert.ok(Object.hasOwn(form.defender.fleet, code));
            }
            for (const side of ['attacker', 'defender']) {
                const fleet = form[side].fleet;
                const total = Object.values(fleet).reduce((sum, count) => sum + count, 0);
                assert.ok(total >= 1 && total <= maxUnits);
                assert.ok(Object.values(fleet).every((count) => Number.isInteger(count) && count >= 0));
                assert.ok([217, 407, 408, 409].every((code) => !(code in fleet) || fleet[code] <= 1));
                assert.ok(Object.values(form[side].technologies).every((level) => Number.isInteger(level) && level >= 0 && level <= 100));
            }
            for (const profile of Object.values(form.special_profiles)) {
                assert.ok(Number.isInteger(profile.attack) && profile.attack >= 0 && profile.attack <= 1000000000);
                assert.ok(Number.isInteger(profile.shield) && profile.shield >= 0 && profile.shield <= 1000000000);
                assert.ok(Number.isInteger(profile.hull) && profile.hull >= 1 && profile.hull <= 1000000000);
            }
            assert.ok(Number.isInteger(form.seed) && form.seed >= 0 && form.seed <= 2147483647);
        }
    }
});
