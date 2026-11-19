import assert from 'node:assert/strict';
import test from 'node:test';
import de from './i18n/locales/de.js';
import en from './i18n/locales/en.js';
import es from './i18n/locales/es.js';
import fr from './i18n/locales/fr.js';
import it from './i18n/locales/it.js';
import { translateUnitName, unitNameKeys } from './unitNames.js';
import { getPageTranslations } from './i18n/pageMessages.js';

test('fleet ship codes map to localized ship names on fleet and galaxy pages', () => {
    const locales = { de, en, es, fr, it };

    for (const [locale, messages] of Object.entries(locales)) {
        for (const [code, key] of Object.entries(unitNameKeys)) {
            assert.ok(messages.unitNames[key], `${locale} is missing the name for ship ${code}`);
            assert.equal(translateUnitName(code, (path) => messages.unitNames[path.split('.')[1]]), messages.unitNames[key]);
        }

        assert.ok(getPageTranslations(messages, 'fleet').unitNames);
        assert.ok(getPageTranslations(messages, 'galaxy').unitNames);
    }
});
