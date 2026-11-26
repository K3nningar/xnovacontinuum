import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { parse } from '@vue/compiler-sfc';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

const source = readFileSync(new URL('./Pages/Factions/Index.vue', import.meta.url), 'utf8');
const template = parse(source).descriptor.template.content;

async function renderFactionPage(factionsEnabled) {
    const app = createSSRApp({
        template,
        setup: () => ({
            factionsEnabled,
            universe: { id: 1, name: 'Test' },
            factions: [],
            currentFactionKey: '0',
            canEdit: true,
            t: (key) => key,
            route: () => '/',
        }),
    });
    app.component('Head', { render: () => null });
    app.component('Link', { render() { return h('a', this.$slots.default?.()); } });
    app.component('GameLayout', { render() { return h('div', this.$slots.default?.()); } });

    return renderToString(app);
}

test('a disabled faction module shows a warning instead of the directory', async () => {
    const html = await renderFactionPage(false);

    assert.match(html, /role="alert"[^>]*>factions\.moduleDisabled/);
    assert.doesNotMatch(html, /faction-directory-grid|faction-admin-link/);
});

test('an enabled faction module shows the directory and management link', async () => {
    const html = await renderFactionPage(true);

    assert.doesNotMatch(html, /factions\.moduleDisabled/);
    assert.match(html, /faction-directory-grid/);
    assert.match(html, /faction-admin-link/);
});
