import assert from 'node:assert/strict';
import test from 'node:test';

test('English is the default even when the browser language is French', async (t) => {
    globalThis.window = {
        localStorage: { getItem: () => null },
        navigator: { language: 'fr-FR' },
    };
    t.after(() => delete globalThis.window);

    const { i18n } = await import('./index.js?default');

    assert.equal(i18n.global.locale.value, 'en');
    assert.equal(i18n.global.fallbackLocale.value, 'en');
});

test('a saved language remains selected', async (t) => {
    globalThis.window = {
        localStorage: { getItem: () => 'fr' },
        navigator: { language: 'en-US' },
    };
    t.after(() => delete globalThis.window);

    const { i18n } = await import('./index.js?saved');

    assert.equal(i18n.global.locale.value, 'fr');
});

test('an unsupported saved language falls back to English', async (t) => {
    globalThis.window = {
        localStorage: { getItem: () => 'invalid' },
    };
    t.after(() => delete globalThis.window);

    const { i18n } = await import('./index.js?invalid');

    assert.equal(i18n.global.locale.value, 'en');
});
