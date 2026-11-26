/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Provides application localization support for pageMessages.test.
 */

import assert from 'node:assert/strict';
import test from 'node:test';
import de from './locales/de.js';
import en from './locales/en.js';
import es from './locales/es.js';
import fr from './locales/fr.js';
import it from './locales/it.js';
import frBuildings from './locales/fr/buildings.js';
import frOverview from './locales/fr/overview.js';
import frResearch from './locales/fr/research.js';
import { getPageTranslations, pageTranslationPaths } from './pageMessages.js';

const locales = { en, fr, de, es, it };

test('locale dictionaries are physically split by page type', () => {
    assert.equal(frOverview.dashboard.title, fr.dashboard.title);
    assert.equal(Object.hasOwn(frOverview, 'buildings'), false);
    assert.equal(frBuildings.buildings.title, fr.buildings.title);
    assert.equal(Object.hasOwn(frBuildings, 'research'), false);
    assert.equal(frResearch.research.title, fr.research.title);
    assert.equal(Object.hasOwn(frResearch, 'dashboard'), false);
});

test('page translation output contains only the namespaces assigned to that page', () => {
    const messages = getPageTranslations(fr, 'galaxy');

    assert.deepEqual(Object.keys(messages), ['galaxyLegacySensors', 'galaxyMap', 'galaxyCombatLimit', 'galaxyEncounter', 'galaxyEarlyReturn', 'galaxyMoon', 'galaxyShipsFound', 'galaxyMissions', 'galaxyConcentrationNote', 'galaxy', 'unitNames', 'fleet', 'common', 'factions']);
    assert.equal(messages.galaxy.title, fr.galaxy.title);
    assert.equal(Object.hasOwn(messages, 'auth'), false);
});

test('every page scope is available in every supported locale', () => {
    for (const [locale, translations] of Object.entries(locales)) {
        for (const page of Object.keys(pageTranslationPaths)) {
            assert.doesNotThrow(
                () => getPageTranslations(translations, page),
                `${locale} is missing translations for ${page}`,
            );
        }
    }
});

test('the faction label is available in the shared game navigation in every locale', () => {
    for (const [locale, translations] of Object.entries(locales)) {
        assert.ok(getPageTranslations(translations, 'shared').nav.faction, `${locale} is missing the faction label`);
    }
});

test('officer information descriptions are localized for every officer', () => {
    for (const [locale, translations] of Object.entries(locales)) {
        assert.ok(getPageTranslations(translations, 'officers').officers.moduleDisabled, `${locale} is missing the disabled-module message`);
        for (let code = 601; code <= 615; code++) {
            assert.ok(
                translations.elementDescriptions[`officer_${code}`],
                `${locale} is missing the information text for officer ${code}`,
            );
        }
    }

    assert.equal(getPageTranslations(fr, 'officers').elementDescriptions.officer_601, fr.elementDescriptions.officer_601);
});

test('the administration scope includes module settings and officer names', () => {
    for (const [locale, translations] of Object.entries(locales)) {
        const messages = getPageTranslations(translations, 'admin');

        assert.ok(messages.adminModules.title, `${locale} is missing the module title`);
        assert.deepEqual(messages.officerNames, translations.officerNames);
    }
});

test('faction image controls have translations in the administration scope for every locale', () => {
    for (const [locale, translations] of Object.entries(locales)) {
        const editor = getPageTranslations(translations, 'admin').factionEditor;

        for (const key of ['imageUpload', 'imageChoose', 'imageNone']) {
            assert.ok(editor[key], `${locale} is missing factionEditor.${key}`);
        }
    }
});

test('every locale namespace can be displayed through a page scope', () => {
    const assignedNamespaces = new Set(Object.values(pageTranslationPaths).flat().map((path) => path.split('.')[0]));

    for (const [locale, translations] of Object.entries(locales)) {
        for (const namespace of Object.keys(translations)) {
            assert.ok(assignedNamespaces.has(namespace), `${locale} namespace ${namespace} has no page scope`);
        }
    }
});

test('shared pagination text does not pull in unrelated administration strings', () => {
    const messages = getPageTranslations(fr, 'announcements');

    assert.deepEqual(messages.admin, {
        previous: fr.admin.previous,
        next: fr.admin.next,
    });
});

test('shared unit catalog text excludes labels exclusive to other page types', () => {
    const shipyardMessages = getPageTranslations(fr, 'shipyard');
    const officersMessages = getPageTranslations(fr, 'officers');

    assert.equal(Object.hasOwn(shipyardMessages.production, 'defenceTitle'), false);
    assert.equal(Object.hasOwn(shipyardMessages.production, 'officerTitle'), false);
    assert.equal(Object.hasOwn(officersMessages.production, 'shipyardTitle'), false);
    assert.equal(Object.hasOwn(officersMessages.production, 'defenceTitle'), false);
});

test('unknown page scopes fail with an actionable error', () => {
    assert.throws(
        () => getPageTranslations(fr, 'unknown'),
        /Unknown translation page "unknown"/,
    );
    assert.throws(
        () => getPageTranslations(fr, 'constructor'),
        /Unknown translation page "constructor"/,
    );
});
