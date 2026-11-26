import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { parse } from '@vue/compiler-sfc';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

const source = readFileSync(new URL('./Pages/Admin/Modules.vue', import.meta.url), 'utf8');
const template = parse(source).descriptor.template.content;

async function renderModulesPage(officersEnabled) {
    const app = createSSRApp({
        template,
        setup: () => ({
            form: { factions_enabled: true, officers_enabled: officersEnabled, enabled_officers: [601], errors: {}, processing: false },
            officers: [{ code: 601, key: 'geologist' }, { code: 602, key: 'admiral' }],
            universes: [{ id: 1, name: 'Andromède' }, { id: 2, name: 'Pégase' }], selectedUniverseId: 1,
            t: (key) => key,
            route: () => '/admin',
            save: () => {}, selectUniverse: () => {},
        }),
    });
    app.component('Head', { render: () => null });
    app.component('Link', { render() { return h('a', this.$slots.default?.()); } });
    app.component('GameLayout', { render() { return h('div', this.$slots.default?.()); } });

    return renderToString(app);
}

test('module settings keep the legend outside the officer grid and do not duplicate the flash message', async () => {
    const html = await renderModulesPage(true);

    assert.match(html, /<legend>adminModules\.individual<\/legend>\s*<div class="officer-grid">/);
    assert.doesNotMatch(html, /flash-message|adminModules\.saved/);
    assert.match(html, /class="officer-option is-selected"/);
    assert.match(html, /officerNames\.geologist/);
    assert.match(html, /officerNames\.admiral/);
    assert.match(html, /adminModules\.enableFactions/);
    assert.match(html, /Pégase/);
});

test('individual choices remain editable when the officer module is disabled', async () => {
    const html = await renderModulesPage(false);

    assert.match(html, /class="officer-option is-selected"/);
    assert.match(html, /value="601" checked/);
    assert.doesNotMatch(html, /disabled/);
});
