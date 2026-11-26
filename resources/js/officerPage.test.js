import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { parse } from '@vue/compiler-sfc';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

const source = readFileSync(new URL('./Pages/Officers/Index.vue', import.meta.url), 'utf8');
const template = parse(source).descriptor.template.content;

async function renderOfficerPage(officersEnabled) {
    const app = createSSRApp({
        template,
        setup: () => ({
            officersEnabled,
            officers: [],
            queue: [],
            points: 3,
            visibleCount: 0,
            page: { props: { flash: {}, errors: {} } },
            t: (key) => key,
            selectedCategory: 'all',
            officerFilters: [],
        }),
    });
    app.component('Head', { render: () => null });
    app.component('RarityBadge', { render: () => null });
    app.component('ElementInfoButton', { render: () => null });
    app.component('GameLayout', { render() { return h('div', this.$slots.default?.()); } });
    app.component('CatalogGrid', { render() { return h('div', this.$slots.default?.()); } });

    return renderToString(app);
}

test('the disabled officer module only displays its warning', async () => {
    const html = await renderOfficerPage(false);

    assert.match(html, /role="alert"[^>]*>officers\.moduleDisabled/);
    assert.doesNotMatch(html, /production-queue|officer-points|officers\.staff/);
});

test('the enabled officer module displays its catalog and recruitment queue', async () => {
    const html = await renderOfficerPage(true);

    assert.doesNotMatch(html, /officers\.moduleDisabled/);
    assert.match(html, /officers\.staff/);
    assert.match(html, /production-queue/);
    assert.match(html, /officer-points/);
});
