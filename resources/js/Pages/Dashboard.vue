<!--
Author / Contributor : K3nningar
Code Version : 0.1.0-alpha.5
Revision : 34ddc84
Description : Renders the Dashboard Inertia page.
-->

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import GameLayout from '../Layouts/GameLayout.vue';
import { projectPlanetResources } from '../liveResources.js';

const page = usePage();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const { t, n, te, locale } = useI18n();
const props = defineProps({
    user: { type: Object, required: true },
    universe: { type: Object, default: () => ({}) },
    hasUniverse: { type: Boolean, default: false },
    availableUniverses: { type: Array, default: () => [] },
    planets: { type: Array, default: () => [] },
    alliance: { type: Object, default: null },
    fleetCount: { type: Number, default: 0 },
    researchQueue: { type: Object, default: null },
    selectedPlanetId: { type: Number, default: null },
    activePlanetId: { type: Number, default: null },
    activeFleets: { type: Array, default: () => [] },
    serverTime: { type: String, required: true },
    serverTimezone: { type: String, required: true },
});

const form = useForm({ universe_id: props.availableUniverses[0]?.id ?? '', name: `${props.user.name} Prime` });
const renameForm = useForm({ name: '' });
const isRenaming = ref(false);
const clock = ref(Date.now());
const serverOffset = ref(new Date(props.serverTime).getTime() - Date.now());
const refreshInFlight = ref(false);
const refreshedChanges = new Set();
let timer;
const activePlanet = computed(() => props.planets.find((planet) => planet.id === props.activePlanetId));
const livePlanets = computed(() => props.planets.map((planet) => projectPlanetResources(planet, clock.value + serverOffset.value)));
const empireResources = computed(() => livePlanets.value.reduce((sum, planet) => ({
    metal: sum.metal + planet.metal,
    crystal: sum.crystal + planet.crystal,
    deuterium: sum.deuterium + planet.deuterium,
}), { metal: 0, crystal: 0, deuterium: 0 }));
const shieldTypes = [
    { key: 'small', name: 'unitNames.small_shield_dome' },
    { key: 'large', name: 'unitNames.large_shield_dome' },
    { key: 'planetary', name: 'unitNames.planetary_protector' },
];
const serverClock = computed(() => new Intl.DateTimeFormat(locale.value, {
    dateStyle: 'short', timeStyle: 'medium', timeZone: props.serverTimezone,
}).format(new Date(clock.value + serverOffset.value)));

const refreshOptions = {
    only: ['planets', 'serverTime'],
    preserveErrors: true,
    onStart: () => { refreshInFlight.value = true; },
    onFinish: () => { refreshInFlight.value = false; },
};
usePoll(60_000, refreshOptions, { background: 'pause', mode: 'rest' });

watch(() => props.serverTime, (time) => {
    clock.value = Date.now();
    serverOffset.value = new Date(time).getTime() - clock.value;
});
watch(() => activePlanet.value?.id, () => {
    renameForm.name = activePlanet.value?.name ?? '';
    renameForm.clearErrors();
    isRenaming.value = false;
}, { immediate: true });

function updateClock() {
    clock.value = Date.now();
    if (document.hidden || refreshInFlight.value) return;

    const dueChange = props.planets.find((planet) => {
        const nextChange = planet.liveResources.nextChangeAt;
        return nextChange && Date.parse(nextChange) <= clock.value + serverOffset.value
            && !refreshedChanges.has(`${planet.id}:${nextChange}`);
    });
    if (dueChange) {
        refreshedChanges.add(`${dueChange.id}:${dueChange.liveResources.nextChangeAt}`);
        router.reload(refreshOptions);
    }
}

onMounted(() => {
    timer = window.setInterval(updateClock, 1000);
    document.addEventListener('visibilitychange', updateClock);
});
onUnmounted(() => {
    window.clearInterval(timer);
    document.removeEventListener('visibilitychange', updateClock);
});

function joinUniverse() {
    form.post(route('universe.join'));
}

function formatResource(value) {
    return n(Number(value), 'game');
}

function selectPlanet(id) {
    router.get(route('dashboard'), { planet: id }, { preserveScroll: true });
}

function startRename() {
    renameForm.name = activePlanet.value?.name ?? '';
    renameForm.clearErrors();
    isRenaming.value = true;
}

function renamePlanet() {
    renameForm.patch(route('planet.rename', { planet: props.activePlanetId }), {
        preserveScroll: true,
        onSuccess: () => { isRenaming.value = false; },
    });
}

function cancelRename() {
    renameForm.name = activePlanet.value?.name ?? '';
    renameForm.clearErrors();
    isRenaming.value = false;
}

function remaining(value) {
    if (!value) return t('overview.unknownTime');
    const seconds = Math.max(0, Math.ceil((new Date(value).getTime() - clock.value - serverOffset.value) / 1000));
    return [Math.floor(seconds / 3600), Math.floor(seconds % 3600 / 60), seconds % 60]
        .map((part) => String(part).padStart(2, '0')).join(':');
}

function buildingName(key) {
    const path = `buildingCatalog.${key}.name`;
    return te(path) ? t(path) : key;
}

function researchName(key) {
    if (key === 'sensors') return t('galaxy.sensors');
    const path = `research.${key === 'hyperspace_drive' ? 'hyperspaceDrive' : key}`;
    return te(path) ? t(path) : key;
}
</script>

<template>
    <Head :title="t('dashboard.title')" />
    <GameLayout :live-planets="livePlanets">
        <main class="command-layout overview-page">
            <section class="command-content">
                <div class="overview-commander data-panel">
                    <div class="overview-commander-identity">
                        <span class="overview-commander-icon" aria-hidden="true"><i class="bi bi-person-circle"></i></span>
                        <div><p class="eyebrow">{{ t('dashboard.eyebrow') }}</p><h1>{{ user.name }}</h1><p class="command-subtitle">{{ hasUniverse ? universe.name : t('dashboard.subtitle') }}</p></div>
                    </div>
                    <div class="overview-commander-status">
                        <span class="online-pill"><i></i> {{ t('dashboard.transmission') }}</span>
                        <p class="overview-clock">{{ t('overviewClock', { time: serverClock, zone: serverTimezone }) }}</p>
                    </div>
                </div>
                <template v-if="hasUniverse">
                    <section v-if="planets.length" class="overview-planet-switcher data-panel" :aria-label="t('overview.choosePlanet')">
                        <span class="overview-switcher-title"><i class="bi bi-globe2" aria-hidden="true"></i> {{ t('dashboard.planets') }}</span>
                        <div class="overview-planet-options">
                            <button v-for="planet in planets" :key="planet.id" class="overview-planet-option" :class="{ 'is-active': planet.id === activePlanetId }" type="button" :aria-pressed="planet.id === activePlanetId" @click="selectPlanet(planet.id)">
                                <span class="overview-planet-dot" aria-hidden="true"></span>
                                <span>{{ planet.name }}</span><small>[{{ planet.coordinates }}]</small>
                            </button>
                        </div>
                    </section>
                    <p class="overview-resource-heading"><i class="bi bi-bar-chart" aria-hidden="true"></i> {{ t('dashboard.yourEmpire') }} · {{ t('common.resources') }}</p>
                    <div class="resource-grid"><article class="resource-card"><span class="resource-symbol metal-symbol">▰</span><span class="resource-label">{{ t('dashboard.metal') }}</span><strong>{{ formatResource(empireResources.metal) }}</strong><small>{{ t('dashboard.primaryResource') }}</small></article><article class="resource-card"><span class="resource-symbol crystal-symbol">◇</span><span class="resource-label">{{ t('dashboard.crystal') }}</span><strong>{{ formatResource(empireResources.crystal) }}</strong><small>{{ t('dashboard.researchTechnology') }}</small></article><article class="resource-card"><span class="resource-symbol deut-symbol">⬡</span><span class="resource-label">{{ t('dashboard.deuterium') }}</span><strong>{{ formatResource(empireResources.deuterium) }}</strong><small>{{ t('dashboard.fleetFuel') }}</small></article><article class="resource-card"><span class="resource-symbol fleet-symbol">➤</span><span class="resource-label">{{ t('dashboard.fleetInFlight') }}</span><strong>{{ fleetCount }}</strong><small>{{ t('dashboard.activeMissions') }}</small></article></div>
                    <section v-if="activePlanet" class="overview-defences">
                        <div class="overview-defences-heading">
                            <div><span class="panel-kicker">{{ t('overviewDefences.subtitle') }} · {{ activePlanet.name }}</span><h2>{{ t('overviewDefences.title') }}</h2></div>
                            <Link class="button button-outline" :href="route('defences.index', { planet: activePlanet.id })">{{ t('overviewDefences.open') }}</Link>
                        </div>
                        <div class="overview-shield-grid">
                            <article v-for="shield in shieldTypes" :key="shield.key" class="data-panel overview-shield-card">
                                <h3 class="text-sm font-semibold text-slate-200">{{ t(shield.name) }}</h3>
                                <p class="mt-3 flex items-center gap-2 font-semibold" :class="activePlanet.shields[shield.key] ? 'text-emerald-400' : 'text-slate-400'">
                                    <span aria-hidden="true">{{ activePlanet.shields[shield.key] ? '●' : '○' }}</span>
                                    {{ t(activePlanet.shields[shield.key] ? 'overviewDefences.installed' : 'overviewDefences.absent') }}
                                </p>
                            </article>
                        </div>
                        <p class="overview-defences-detail">{{ t('overviewDefences.detail') }}</p>
                    </section>
                    <section class="data-panel overview-colonisation">
                        <div class="panel-heading"><div><span class="panel-kicker">{{ t('dashboard.yourEmpire') }}</span><h2>{{ t('overviewDefences.colonies') }}</h2></div></div>
                        <div class="overview-panel-body flex flex-wrap items-center gap-x-8 gap-y-3">
                            <p>{{ t('overviewDefences.owned') }} : <strong>{{ formatResource(planets.length) }}</strong></p>
                            <p>{{ t('overviewDefences.available') }} : <strong>0</strong></p>
                            <p class="text-sm text-slate-400">{{ t('overviewDefences.unavailable') }}</p>
                        </div>
                    </section>
                    <div class="command-panels overview-main-panels">
                        <section v-if="activePlanet" class="data-panel">
                            <div class="panel-heading">
                                <div><span class="panel-kicker">{{ t('overview.activePlanet') }}</span><h2>{{ activePlanet.name }} · [{{ activePlanet.coordinates }}]</h2></div>
                            </div>
                            <div class="overview-panel-body">
                                <div class="overview-world-visual"><span class="planet-orb" aria-hidden="true"></span><strong>{{ activePlanet.name }}</strong><small>[{{ activePlanet.coordinates }}]</small></div>
                                <div class="flex flex-col gap-2">
                                    <span>{{ t('overview.fields', { used: activePlanet.usedFields, total: activePlanet.fields }) }}</span>
                                    <span>{{ t('overview.temperature', { temperature: activePlanet.max_temperature }) }}</span>
                                    <div><button v-if="!isRenaming" class="button button-outline" type="button" @click="startRename">{{ t('overview.rename') }}</button></div>
                                </div>
                                <form v-if="isRenaming" class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="renamePlanet">
                                    <label class="flex flex-col gap-1" for="overview-planet-name">{{ t('overview.planetName') }}
                                        <input id="overview-planet-name" v-model="renameForm.name" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" minlength="2" maxlength="80" required>
                                    </label>
                                    <button class="button button-primary" type="submit" :disabled="renameForm.processing">{{ t('overview.save') }}</button>
                                    <button class="button button-outline" type="button" @click="cancelRename">{{ t('common.cancel') }}</button>
                                    <p v-if="renameForm.errors.name" class="field-error w-full" role="alert">{{ renameForm.errors.name }}</p>
                                </form>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <Link class="button button-outline" :href="route('buildings.index', { planet: activePlanet.id })">{{ t('dashboard.buildings') }}</Link>
                                    <Link class="button button-outline" :href="route('research.index', { planet: activePlanet.id })">{{ t('nav.research') }}</Link>
                                    <Link class="button button-outline" :href="route('shipyard.index', { planet: activePlanet.id })">{{ t('nav.shipyard') }}</Link>
                                    <Link class="button button-outline" :href="route('fleet.index', { planet: activePlanet.id })">{{ t('nav.fleet') }}</Link>
                                </div>
                            </div>
                        </section>
                        <section class="data-panel">
                            <div class="panel-heading"><div><span class="panel-kicker">{{ t('overview.progress') }}</span><h2>{{ t('overview.activities') }}</h2></div></div>
                            <div class="overview-panel-body">
                                <div v-if="activePlanet?.queue?.length" class="mb-4">
                                    <p>{{ t('overview.construction') }} · {{ buildingName(activePlanet.queue[0].building_key) }} · {{ t('common.level') }} {{ activePlanet.queue[0].target_level }}</p>
                                    <p class="text-sm text-slate-400">{{ t('overview.remaining', { time: remaining(activePlanet.queue[0].completes_at) }) }}</p>
                                </div>
                                <p v-else class="mb-4 text-slate-400">{{ t('overview.noConstruction') }}</p>
                                <div v-if="researchQueue">
                                    <p>{{ t('overview.research') }} · {{ researchName(researchQueue.technology_key) }} · {{ t('common.level') }} {{ researchQueue.target_level }}</p>
                                    <p class="text-sm text-slate-400">{{ t('overview.remaining', { time: remaining(researchQueue.completes_at) }) }}</p>
                                </div>
                                <p v-else class="text-slate-400">{{ t('overview.noResearch') }}</p>
                            </div>
                        </section>
                        <section class="data-panel">
                            <div class="panel-heading"><div><span class="panel-kicker">{{ t('overview.movements') }}</span><h2>{{ t('overview.fleetEvents') }}</h2></div></div>
                            <div class="overview-panel-body">
                                <p v-if="!activeFleets.length" class="text-slate-400">{{ t('overview.noFleetEvents') }}</p>
                                <ol v-else class="flex flex-col gap-3">
                                    <li v-for="fleet in activeFleets" :key="fleet.id" class="border-b border-slate-700 pb-2">
                                        <p>{{ fleet.mission }} · {{ t(fleet.incoming ? 'overviewIncoming' : fleet.status === 'returning' ? 'overview.returning' : 'overview.outbound') }}</p>
                                        <p class="text-sm text-slate-400">[{{ fleet.origin || '—' }}] → [{{ fleet.target || '—' }}] · {{ t('overview.remaining', { time: remaining(fleet.eventAt) }) }}</p>
                                    </li>
                                </ol>
                                <p v-if="activeFleets.length === 25" class="mt-3 text-sm text-slate-400">{{ t('overview.fleetLimit') }}</p>
                            </div>
                        </section>
                    </div>
                    <div class="command-panels">
                        <section id="planetes" class="data-panel">
                            <div class="panel-heading"><div><span class="panel-kicker">{{ t('dashboard.yourEmpire') }}</span><h2>{{ t('dashboard.planets') }}</h2></div><span class="panel-count">{{ planets.length }}</span></div>
                            <article v-for="planet in planets" :key="planet.id" class="planet-row">
                                <span class="planet-orb"></span>
                                <span class="planet-details">
                                    <strong>{{ planet.name }}</strong><small>{{ planet.coordinates }}</small>
                                    <small v-if="planet.queue.length">{{ buildingName(planet.queue[0].building_key) }} · {{ t('common.level') }} {{ planet.queue[0].target_level }} · {{ remaining(planet.queue[0].completes_at) }}</small>
                                    <small v-else>{{ t('overview.noConstruction') }}</small>
                                    <span class="planet-moons">
                                        <small>{{ t('overview.moons') }}</small>
                                        <template v-if="planet.moons.length">
                                            <Link v-for="moon in planet.moons" :key="moon.orbit" class="planet-moon-link" :href="route('moon.show', { planet: planet.id, orbit: moon.orbit })">
                                                {{ t('galaxyMoon', { orbit: moon.orbit, diameter: formatResource(moon.diameter) }) }}
                                            </Link>
                                        </template>
                                        <small v-else>{{ t('overview.noMoons') }}</small>
                                    </span>
                                </span>
                                <span class="planet-stock"><small>{{ t('dashboard.fields') }}</small><strong>{{ planet.usedFields }} / {{ planet.fields }}</strong></span>
                                <div class="planet-links">
                                    <Link class="button button-quiet" :href="route('dashboard', { planet: planet.id })" :aria-current="planet.id === activePlanetId ? 'page' : undefined">{{ t('overviewView') }}</Link>
                                    <Link class="button button-outline" :href="route('buildings.index', { planet: planet.id })">{{ t('dashboard.buildings') }}</Link>
                                </div>
                            </article>
                            <div v-if="!planets.length" class="empty-state"><span class="empty-orb">◉</span><strong>{{ t('dashboard.empireAwaits') }}</strong><p>{{ t('dashboard.noPlanet') }}</p></div>
                        </section>
                        <section id="alliance" class="data-panel"><div class="panel-heading"><div><span class="panel-kicker">{{ t('dashboard.community') }}</span><h2>{{ t('dashboard.alliance') }}</h2></div><span class="panel-count">⌖</span></div><div v-if="alliance" class="alliance-state"><span class="alliance-emblem">{{ alliance.tag }}</span><strong>{{ alliance.name }}</strong><p>{{ alliance.description || t('dashboard.allianceDefault') }}</p></div><div v-else class="empty-state compact"><span class="empty-orb">◈</span><strong>{{ t('dashboard.ownStrategy') }}</strong><p>{{ t('dashboard.allianceSoon') }}</p></div></section>
                    </div>
                    <div class="notice-bar"><span>✦</span><p>{{ t('dashboard.established', { name: universe.name }) }}</p><Link class="intel-discover" :href="route('buildings.index')">{{ t('dashboard.developBuildings') }} →</Link></div>
                </template>

                <section v-else class="onboarding-panel"><p class="eyebrow"><span class="eyebrow-line"></span> {{ t('dashboard.firstDirective') }}</p><h2>{{ t('dashboard.chooseStart') }}<br><span>{{ t('dashboard.startPoint') }}</span></h2><p class="command-subtitle">{{ t('dashboard.joinUniverse') }}</p><label v-if="availableUniverses.length > 1" class="universe-picker" for="universe-id">{{ t('dashboard.chooseUniverse') }}<select id="universe-id" v-model="form.universe_id"><option v-for="item in availableUniverses" :key="item.id" :value="item.id">{{ item.name }} · {{ item.galaxies }} galaxies</option></select></label><div v-if="availableUniverses.length" class="universe-choice"><div class="universe-emblem">⌖</div><div><span class="panel-kicker">{{ t('dashboard.selectedUniverse') }}</span><h3>{{ availableUniverses.find((item) => item.id === Number(form.universe_id))?.name }}</h3><p>{{ t('dashboard.galaxiesSystems', { galaxies: availableUniverses.find((item) => item.id === Number(form.universe_id))?.galaxies, systems: availableUniverses.find((item) => item.id === Number(form.universe_id))?.systems_per_galaxy }) }}</p></div></div><form v-if="availableUniverses.length" class="auth-form onboarding-form" @submit.prevent="joinUniverse"><label for="planet-name">{{ t('dashboard.startingPlanetName') }}</label><input id="planet-name" v-model="form.name" type="text" maxlength="80" required><span v-if="form.errors.name" class="field-error">{{ form.errors.name }}</span><span v-if="form.errors.universe_id || form.errors.universe" class="field-error">{{ form.errors.universe_id || form.errors.universe }}</span><button class="button button-primary button-large" type="submit" :disabled="form.processing">{{ form.processing ? t('dashboard.initializing') : t('dashboard.foundEmpire') }} <span aria-hidden="true">→</span></button></form><div v-else class="empty-state"><span class="empty-orb">◈</span><strong>{{ t('dashboard.noUniverses') }}</strong><p>{{ t('dashboard.returnSoon') }}</p></div></section>
            </section>
        </main>
    </GameLayout>
</template>
