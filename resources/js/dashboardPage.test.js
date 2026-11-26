import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { parse } from '@vue/compiler-sfc';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

const source = readFileSync(new URL('./Pages/Dashboard.vue', import.meta.url), 'utf8');
const template = parse(source).descriptor.template.content;
const capacity = { metal: 1000, crystal: 1000, deuterium: 1000 };

async function renderDashboard({ faction = null, hasUniverse = true, moons = [], factionsEnabled = true } = {}) {
    const planet = {
        id: 1, name: 'Planète', coordinates: '1:2:3', visualUrl: '/legacy-assets/skins/xnova/planeten/normaltempplanet01.jpg', queue: [], moons,
        shields: { small: true, large: false, planetary: false }, capacity,
        usedFields: 5, fields: 100, max_temperature: 20,
    };
    const app = createSSRApp({
        template,
        setup: () => ({
            user: { name: 'Commandant' }, page: { props: { auth: { user: {} }, factionsEnabled } },
            hasUniverse, universe: { name: 'Univers' }, planets: [planet], activePlanet: planet,
            activePlanetId: 1, ownedMoons: moons.map((moon) => ({ ...moon, planet })),
            faction, alliance: null, activeFleets: [], researchQueue: null, fleetCount: 0,
            livePlanets: [], serverClock: '12:00', serverTimezone: 'Europe/Paris',
            empireResources: { metal: 10, crystal: 20, deuterium: 30 }, empireCapacities: capacity,
            shieldTypes: [{ key: 'small', name: 'smallShield' }], isRenaming: false,
            availableUniverses: [{ id: 1, name: 'Univers', factions: [] }], availableFactions: [],
            selectedFactionsEnabled: factionsEnabled,
            form: { universe_id: 1, name: 'Prime', faction_key: '0', errors: {} },
            t: (key) => key, route: (name) => `/${name}`, formatResource: String,
            resourceLabel: (resource) => resource, resourcePercentage: () => 10,
            resourceAmount: () => 100, empireResourcePercentage: () => 10,
            startRename: () => {}, joinUniverse: () => {},
        }),
    });
    app.component('Head', { render: () => null });
    app.component('Link', { render() { return h('a', this.$slots.default?.()); } });
    app.component('GameLayout', { render() { return h('div', this.$slots.default?.()); } });

    return renderToString(app);
}

test('dashboard groups operational panels, worlds and community in reading order', async () => {
    const html = await renderDashboard({
        faction: { name: 'Faction', description: 'Description' },
        moons: [{ name: 'Lune', generatedName: 'Lune', orbit: 1, capacity }],
    });
    const markers = ['overview-main-panels', 'overview-defences', 'overview-colonisation', 'id="planetes"', 'overview-moons', 'id="alliance"', 'faction-link-panel'];
    const positions = markers.map((marker) => html.indexOf(marker));

    assert.ok(positions.every((position) => position >= 0));
    assert.deepEqual(positions, [...positions].sort((first, second) => first - second));
    assert.match(html, /Lune/);
});

test('dashboard keeps planets, moons and alliance visible without a faction', async () => {
    const html = await renderDashboard();

    assert.match(html, /id="planetes"/);
    assert.match(html, /overview\.noMoons/);
    assert.match(html, /id="alliance"/);
    assert.doesNotMatch(html, /faction-link-panel/);
});

test('dashboard displays the selected planet visual', async () => {
    const html = await renderDashboard();

    assert.match(html, /class="overview-planet-image" src="\/legacy-assets\/skins\/xnova\/planeten\/normaltempplanet01\.jpg"/);
});

test('onboarding respects the faction module setting', async () => {
    const enabled = await renderDashboard({ hasUniverse: false });
    const disabled = await renderDashboard({ hasUniverse: false, factionsEnabled: false });

    assert.match(enabled, /id="faction-key"/);
    assert.doesNotMatch(disabled, /id="faction-key"/);
    assert.match(disabled, /id="planet-name"/);
    assert.doesNotMatch(disabled, /overview-main-panels/);
});
