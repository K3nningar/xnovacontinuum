/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Renders the combatForm Inertia page.
 */

const sides = ['attacker', 'defender'];
const technologyKeys = ['weapons', 'shields', 'armour'];
const uniqueUnits = new Set([217, 407, 408, 409]);

/** Create blank inputs for valid attacker/defender unit sets and only applicable custom profiles. */
export function emptyCombatForm(units) {
    const side = (sideUnits) => ({
        fleet: Object.fromEntries(sideUnits.map((unit) => [unit.code, ''])),
        technologies: Object.fromEntries(technologyKeys.map((key) => [key, ''])),
    });

    return {
        attacker: side(units.filter((unit) => unit.type === 'ships')),
        defender: side(units),
        special_profiles: Object.fromEntries(units.filter((unit) => unit.customProfile).map((unit) => [
            unit.code, { attack: '', shield: '', hull: '' },
        ])),
        seed: '',
    };
}

/** Generate validation-bounded sample fleets; an injectable RNG keeps boundary behavior testable. */
export function randomCombatForm(units, maxUnitsPerSide, random = Math.random) {
    const integer = (max) => Math.floor(random() * (max + 1));
    const form = emptyCombatForm(units);

    for (const side of sides) {
        const sideUnits = side === 'attacker' ? units.filter((unit) => unit.type === 'ships') : units;
        // Per-type limits keep each generated fleet within the global cap as counts are consumed.
        const unitLimit = Math.min(10, Math.max(1, Math.floor(maxUnitsPerSide / sideUnits.length)));
        let remaining = maxUnitsPerSide;
        for (const unit of sideUnits) {
            const count = integer(Math.min(remaining, uniqueUnits.has(unit.code) ? 1 : unitLimit));
            form[side].fleet[unit.code] = count;
            remaining -= count;
        }
        if (remaining === maxUnitsPerSide) {
            form[side].fleet[sideUnits[integer(sideUnits.length - 1)].code] = 1;
        }
        for (const key of technologyKeys) {
            form[side].technologies[key] = integer(20);
        }
    }

    for (const unit of units.filter((unit) => unit.customProfile)) {
        const hull = Math.min(1000000000, Math.max(1, Math.round(unit.hull * (0.5 + random()))));
        form.special_profiles[unit.code] = {
            attack: integer(Math.floor(hull / 2)),
            shield: integer(Math.floor(hull / 2)),
            hull,
        };
    }
    form.seed = integer(2147483647);

    return form;
}

/** Convert untouched numeric fields to zero in the payload while leaving editable form state intact. */
export function prepareCombatSubmission(form) {
    const zeroIfEmpty = (value) => value === '' || value === null ? 0 : value;
    const side = (value) => ({
        fleet: Object.fromEntries(Object.entries(value.fleet).map(([code, count]) => [code, zeroIfEmpty(count)])),
        technologies: Object.fromEntries(Object.entries(value.technologies).map(([key, level]) => [key, zeroIfEmpty(level)])),
    });

    return { ...form, attacker: side(form.attacker), defender: side(form.defender) };
}
