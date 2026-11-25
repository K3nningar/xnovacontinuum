/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Tests the changelog.test JavaScript module.
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { createI18n } from 'vue-i18n';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import en from './i18n/locales/en.js';
import fr from './i18n/locales/fr.js';
import de from './i18n/locales/de.js';
import es from './i18n/locales/es.js';
import it from './i18n/locales/it.js';

const releases = JSON.parse(readFileSync(new URL('./changelog.json', import.meta.url), 'utf8'));

test('changelog entries have valid dates, categories and translations', () => {
    const categories = ['CHANGE', 'HOTFIX', 'KNOWN ISSUE', 'NEW'];
    const locales = { en, fr, de, es, it };

    assert.ok(releases.length > 0);
    assert.equal(new Set(releases.map((release) => release.version)).size, releases.length, 'Release versions must be unique');
    for (const release of releases) {
        assert.match(release.version, /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?$/);
        assert.ok(release.entries.length > 0);
        for (const entry of release.entries) {
            assert.match(entry.date, /^\d{4}-\d{2}-\d{2}$/);
            assert.equal(new Date(`${entry.date}T00:00:00Z`).toISOString().slice(0, 10), entry.date);
            assert.ok(categories.includes(entry.type), `Unknown category: ${entry.type}`);
            for (const [locale, messages] of Object.entries(locales)) {
                assert.ok(messages.changelog.entries[entry.key], `Missing ${locale} translation for ${entry.key}`);
            }
        }
    }
});

test('manual entry text is rendered as text rather than HTML', async () => {
    const server = await createServer({
        configFile: false,
        plugins: [vue()],
        server: { middlewareMode: true },
        appType: 'custom',
    });

    try {
        const { default: ChangelogEntries } = await server.ssrLoadModule('/resources/js/Components/ChangelogEntries.vue');
        const app = createSSRApp(ChangelogEntries, {
            entries: [{ key: 'manual-1', date: '2026-11-16', type: 'NEW', text: '<script>alert("xss")</script>' }],
        });
        app.use(createI18n({ legacy: false, locale: 'en', messages: { en } }));

        const html = await renderToString(app);

        assert.ok(html.includes('&lt;script&gt;'));
        assert.ok(!html.includes('<script>'));
    } finally {
        await server.close();
    }
});

test('current release entries render translated text in every supported locale', async () => {
    const server = await createServer({
        configFile: false,
        plugins: [vue()],
        server: { middlewareMode: true },
        appType: 'custom',
    });

    try {
        const { default: ChangelogEntries } = await server.ssrLoadModule('/resources/js/Components/ChangelogEntries.vue');

        for (const [locale, messages] of Object.entries({ en, fr, de, es, it })) {
            const app = createSSRApp(ChangelogEntries, { entries: releases[0].entries });
            app.use(createI18n({ legacy: false, locale, messages: { [locale]: messages } }));

            const html = await renderToString(app);

            for (const entry of releases[0].entries) {
                assert.ok(html.includes(messages.changelog.entries[entry.key]), `Missing rendered ${locale} text for ${entry.key}`);
                assert.ok(!html.includes(`changelog.entries.${entry.key}`), `Untranslated ${locale} key: ${entry.key}`);
            }
        }
    } finally {
        await server.close();
    }
});
