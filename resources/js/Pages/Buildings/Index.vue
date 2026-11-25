<!--
Author / Contributor : K3nningar
Code Version : 0.1.1-alpha
Revision : 34ddc84
Description : Renders the Index Inertia page.
-->

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import GameLayout from '../../Layouts/GameLayout.vue';
import { useI18n } from 'vue-i18n';
import LocaleSwitcher from '../../Components/LocaleSwitcher.vue';
import ElementInfoButton from '../../Components/ElementInfoButton.vue';
import RarityBadge from '../../Components/RarityBadge.vue';
import infosFr from '../../i18n/infos-fr.json';
import { buildingCatalog, buildingCategories, buildingsInCategory } from '../../buildingCatalog.js';

const page = usePage();
const { t, n, locale } = useI18n();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const props = defineProps({
    user: { type: Object, required: true },
    universe: { type: Object, default: null },
    technologyEnergyLevel: { type: Number, default: 0 },
    technologyComputerLevel: { type: Number, default: 0 },
    buildingRarities: { type: Object, default: () => ({}) },
    factionBuildings: { type: Object, default: () => ({}) },
    planets: { type: Array, default: () => [] },
    selectedPlanetId: { type: Number, required: true },
});

const selectedPlanet = computed(() => props.planets.find((planet) => planet.id === props.selectedPlanetId) ?? props.planets[0]);
const busyBuilding = computed(() => page.props.processingBuilding ?? null);
const currentTime = ref(Date.now());
const queuePanelOpen = ref(false);
const selectedCategory = ref('all');
let countdownInterval;
const { start: startPolling, stop: stopPolling } = usePoll(5000, {
    only: ['planets'],
    preserveScroll: true,
}, {
    autoStart: false,
    keepAlive: true,
});
const buildings = computed(() => buildingCatalog.filter((building) => props.factionBuildings[building.key]?.available !== false).map((building) => ({
    ...building,
    rarity: props.buildingRarities[building.code],
    name: props.factionBuildings[building.key]?.name ?? t(`buildingCatalog.${building.key}.name`),
    factionImage: props.factionBuildings[building.key]?.image,
    description: t(`buildingCatalog.${building.key}.description`),
    unit: t(`buildingCatalog.${building.key}.unit`),
    requirement: building.requirementKey ? t(`buildingCatalog.${building.key}.requirement`) : null,
})));
const visibleBuildings = computed(() => buildingsInCategory(buildings.value, selectedCategory.value));
const buildingName = (key) => props.factionBuildings[key]?.name ?? t(`buildingCatalog.${key}.name`);

function format(value) {
    return n(Number(value ?? 0), 'game');
}

function formatDuration(totalSeconds) {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return [hours, minutes, seconds].map((part) => String(part).padStart(2, '0')).join(':');
}

function formatDate(value) {
    return new Intl.DateTimeFormat(locale.value, { dateStyle: 'short', timeStyle: 'medium' }).format(new Date(value));
}

function buildingLevel(building) {
    return Number(selectedPlanet.value?.[building.level] ?? 0);
}

function buildingOutput(building) {
    if (building.resource) {
        return format(selectedPlanet.value.energy.capacity[building.resource]);
    }

    if (building.key === 'terraformer') {
        return `${format(selectedPlanet.value.production[building.key])} ${t('buildings.fieldsAdded')}`;
    }

    return format(selectedPlanet.value.production[building.key]);
}

function canUpgrade(building) {
    const hasRequirements = building.key === 'fusion'
        ? Number(selectedPlanet.value.deuterium_synthesizer_level) >= 5 && props.technologyEnergyLevel >= 3
        : building.key === 'nano_factory'
            ? Number(selectedPlanet.value.robot_factory_level) >= 10 && props.technologyComputerLevel >= 10
            : building.key === 'terraformer'
                ? props.technologyEnergyLevel >= 12 && selectedPlanet.value.energy.supply >= 1000
                : true;
    const uniqueBuildings = ['laboratory', 'robot_factory', 'nano_factory', 'hangar', 'defence_center', 'missile_silo'];
    const queueIsUnique = !uniqueBuildings.includes(building.key)
        || !selectedPlanet.value.queue.some((item) => item.building_key === building.key);
    const maximumLevel = building.maxLevel ?? 30;
    const hasResources = selectedPlanet.value?.canUpgrade[building.key];

    return !building.disabled && hasResources && buildingLevel(building) < maximumLevel && hasRequirements && queueIsUnique;
}

function queueProgress(item) {
    const now = currentTime.value;
    const startsAt = new Date(item.starts_at).getTime();
    const completesAt = new Date(item.completes_at).getTime();
    const duration = Math.max(1, item.duration_seconds * 1000);

    return Math.min(100, Math.max(0, ((now - startsAt) / duration) * 100));
}

function queueRemainingSeconds(item) {
    return Math.max(0, Math.ceil((new Date(item.completes_at).getTime() - currentTime.value) / 1000));
}

function formatCountdown(totalSeconds) {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return [hours, minutes, seconds].map((part) => String(part).padStart(2, '0')).join(':');
}

onMounted(() => {
    countdownInterval = window.setInterval(() => {
        currentTime.value = Date.now();
    }, 1000);
    startPolling();
});

onUnmounted(() => {
    window.clearInterval(countdownInterval);
    stopPolling();
});

function updateActivity(building, event) {
    useForm({ building: building.key, activity: Number(event.target.value) }).post(route('planet.buildings.upgrade', selectedPlanet.value.id), { preserveScroll: true });
}

function upgrade(building) {
    router.post(route('planet.buildings.upgrade', selectedPlanet.value.id), { building: building.key }, {
        preserveScroll: true,
        onStart: () => { page.props.processingBuilding = building.key; },
        onFinish: () => { page.props.processingBuilding = null; },
    });
}
</script>

<template>
    <Head :title="t('buildings.title')" />
    <GameLayout>
        <main class="command-layout">
            <section class="command-content">
                <div v-if="page.props.flash?.success" class="flash-message" role="status">{{ page.props.flash.success }}</div>
                <div v-if="Object.keys(page.props.errors || {}).length" class="form-alert" role="alert">{{ Object.values(page.props.errors)[0] }}</div>
                <div class="command-heading buildings-heading">
                    <div><p class="eyebrow"><span class="eyebrow-line"></span> {{ t('buildings.eyebrow') }}</p><h1>{{ t('buildings.heading') }}</h1><p class="command-subtitle">{{ t('buildings.subtitle') }}</p></div>
                    <label class="planet-select-label" for="selected-planet">{{ t('buildings.planet') }}<select id="selected-planet" :value="selectedPlanet.id" @change="(event) => router.get(route('buildings.index'), { planet: event.target.value }, { preserveState: true })"><option v-for="planet in planets" :key="planet.id" :value="planet.id">{{ planet.name }} · {{ planet.coordinates }}</option></select></label>
                </div>
                <section class="building-queue">
                    <div class="queue-summary">
                        <div class="queue-summary-copy">
                            <span class="panel-kicker">{{ t('buildings.planetarySite') }}</span>
                            <strong>{{ selectedPlanet.queue.length ? t('buildings.constructionCount', { count: selectedPlanet.queue.length }) : t('buildings.noConstruction') }}</strong>
                            <small v-if="selectedPlanet.queue.length">{{ selectedPlanet.queue.find((item) => currentTime >= new Date(item.starts_at).getTime()) ? t('buildings.constructionRunning') : t('buildings.nextConstruction') }} · {{ selectedPlanet.queue[0] ? buildingName(selectedPlanet.queue[0].building_key) : '' }}</small>
                            <small v-else>{{ t('buildings.constructionWillAppear') }}</small>
                        </div>
                        <button class="button button-outline queue-toggle" type="button" :aria-expanded="queuePanelOpen" aria-controls="building-queue-panel" @click="queuePanelOpen = !queuePanelOpen">
                            {{ queuePanelOpen ? t('buildings.closeQueue') : t('buildings.openQueue') }} <span aria-hidden="true">{{ queuePanelOpen ? '×' : '→' }}</span>
                        </button>
                    </div>
                </section>
                <Teleport to="body">
                    <div v-if="queuePanelOpen" class="queue-drawer-backdrop" @click.self="queuePanelOpen = false" @keydown.esc="queuePanelOpen = false">
                        <aside id="building-queue-panel" class="queue-drawer" role="dialog" aria-modal="true" aria-labelledby="queue-drawer-title">
                            <header class="queue-drawer-header">
                                <div><span class="panel-kicker">{{ selectedPlanet.name }} · {{ t('buildings.planetarySite') }}</span><h2 id="queue-drawer-title">{{ t('buildings.constructionQueue') }}</h2></div>
                                <button class="queue-drawer-close" type="button" :aria-label="t('buildings.closeQueue')" @click="queuePanelOpen = false">×</button>
                            </header>
                            <div class="queue-drawer-count">{{ t('buildings.queuesUsed', { count: selectedPlanet.queue.length }) }}</div>
                            <article v-for="item in selectedPlanet.queue" :key="item.id" class="queue-row" :class="{ 'queue-row-active': currentTime >= new Date(item.starts_at).getTime() }">
                                <span class="queue-position">{{ item.position }}</span>
                                <div class="queue-details">
                                    <div class="queue-title"><strong>{{ buildingName(item.building_key) }} · {{ t('common.level').toLowerCase() }} {{ item.target_level }}</strong><span class="queue-state">{{ currentTime >= new Date(item.starts_at).getTime() ? t('buildings.inConstruction') : t('buildings.waiting') }}</span></div>
                                    <small>{{ t('buildings.starts') }} {{ formatDate(item.starts_at) }} · {{ t('buildings.ends') }} {{ formatDate(item.completes_at) }}</small>
                                    <div class="queue-progress" role="progressbar" :aria-valuenow="Math.floor(queueProgress(item))" aria-valuemin="0" aria-valuemax="100" :aria-label="t('buildingActions.progressLabel', { name: buildingName(item.building_key), level: item.target_level })"><span :style="{ width: `${queueProgress(item)}%` }"></span></div>
                                </div>
                                <div class="queue-countdown"><span>{{ currentTime >= new Date(item.starts_at).getTime() ? t('buildings.remaining') : t('buildings.startsIn') }}</span><strong>{{ formatCountdown(currentTime >= new Date(item.starts_at).getTime() ? queueRemainingSeconds(item) : Math.max(0, Math.ceil((new Date(item.starts_at).getTime() - currentTime) / 1000))) }}</strong></div>
                            </article>
                            <div v-if="!selectedPlanet.queue.length" class="queue-empty">{{ t('buildings.noConstructionStarted') }}</div>
                        </aside>
                    </div>
                </Teleport>
                <div class="energy-panel">
                    <div class="energy-head"><div><span class="panel-kicker">{{ t('buildings.energyBalance') }}</span><strong :class="selectedPlanet.energy.balance >= 0 ? 'energy-positive' : 'energy-negative'">{{ selectedPlanet.energy.balance >= 0 ? '+' : '' }}{{ format(selectedPlanet.energy.balance) }}</strong></div><div class="energy-production"><span>{{ t('buildings.production') }}</span><strong>+{{ format(selectedPlanet.energy.supply) }}</strong></div><div class="energy-production"><span>{{ t('buildings.consumption') }}</span><strong>−{{ format(selectedPlanet.energy.usage) }}</strong></div><div class="energy-rate"><span>{{ t('buildings.mineEfficiency') }}</span><strong>{{ selectedPlanet.energy.productionLevel }}%</strong></div></div>
                    <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2">
                        <div class="energy-production"><span>{{ t('buildingCatalog.solar.name') }} </span> <strong>+{{ format(selectedPlanet.energy.sources.solar) }}</strong></div>
                        <div class="energy-production"><span>{{ t('buildingCatalog.fusion.name') }} </span> <strong>+{{ format(selectedPlanet.energy.sources.fusion) }}</strong></div>
                        <div class="energy-production"><span>{{ t('unitNames.solar_satellite') }} </span> <strong>+{{ format(selectedPlanet.energy.sources.satellites) }}</strong></div>
                    </div>
                    <div class="energy-track"><span :style="{ width: `${selectedPlanet.energy.productionLevel}%` }"></span></div>
                </div>
                <section class="buildings-section">
                    <div class="buildings-section-heading"><div><span class="panel-kicker">{{ t('buildings.productionSector') }}</span><h2>{{ t('buildings.availableFacilities') }}</h2></div><span class="panel-count">{{ visibleBuildings.length }} / {{ buildings.length }}</span></div>
                    <div class="pos building-pos">
                        <div class="pos-container">
                            <nav class="pos-menu building-filters" :aria-label="t('buildingFilters.label')">
                                <span class="panel-kicker">{{ t('buildingFilters.label') }}</span>
                                <button v-for="category in buildingCategories" :key="category.key" type="button" class="building-filter" :class="{ active: selectedCategory === category.key }" :aria-pressed="selectedCategory === category.key" @click="selectedCategory = category.key">
                                    <i class="bi" :class="category.icon" aria-hidden="true"></i>
                                    <span>{{ t(`buildingFilters.${category.key}`) }}</span>
                                    <small>{{ buildingsInCategory(buildings, category.key).length }}</small>
                                </button>
                            </nav>
                            <div class="pos-content building-catalog">
                                <div class="building-pos-grid">
                                    <article v-for="building in visibleBuildings" :key="building.key" class="card building-pos-card" :class="`rarity-${building.rarity}`">
                                        <div class="pos-product">
                                            <div class="img building-pos-image" :class="`${building.key}-building-icon`">
                                                <img :src="building.factionImage ?? `/images/buildings/${building.code}.webp`" :alt="building.name" loading="lazy" @error="$event.currentTarget.hidden = true">
                                                <span class="building-image-fallback" aria-hidden="true">{{ building.icon }}</span>
                                                <RarityBadge class="building-rarity" :rarity="building.rarity" />
                                                <span class="building-level">{{ t('common.level') }} {{ buildingLevel(building) }}</span>
                                            </div>
                                            <div class="building-pos-body">
                                                <div class="building-title"><span class="building-code">{{ String(building.code).padStart(2, '0') }}</span><h3>{{ building.name }}</h3><ElementInfoButton :id="`building_${building.code}`" :name="building.name" :description="infosFr[`building_${building.code}`]?.description" :image-src="`/images/buildings/${building.code}.webp`" /></div>
                                                <div class="building-main">
                                                    <p>{{ building.description }}</p>
                                                    <p v-if="building.requirement" class="building-requirement">{{ building.requirement }}</p>
                                                    <div class="building-stats"><span :class="building.key === 'solar' || building.key === 'fusion' ? 'energy-positive' : ''">{{ building.resource || building.key === 'terraformer' ? '' : '+' }}{{ buildingOutput(building) }} {{ building.unit }}</span><span v-if="building.key === 'fusion'" class="energy-negative">{{ t('buildings.fusionUse') }}</span></div>
                                                    <div v-if="building.activity !== null" class="activity-row"><label :for="`activity-${building.key}`">{{ t('buildings.activity') }} <strong>{{ selectedPlanet[building.activity] * 10 }}%</strong></label><input :id="`activity-${building.key}`" type="range" min="0" max="10" step="1" :value="selectedPlanet[building.activity]" :disabled="building.key === 'fusion' || building.key === 'laboratory'" @change="updateActivity(building, $event)"></div>
                                                </div>
                                                <div class="building-action">
                                                    <div class="building-cost"><span>{{ t('common.nextLevel') }}</span><strong><i class="metal-symbol">▰</i>{{ format(selectedPlanet.nextCosts[building.key].metal) }}</strong><strong><i class="crystal-symbol">◇</i>{{ format(selectedPlanet.nextCosts[building.key].crystal) }}</strong><strong><i class="deut-symbol">⬡</i>{{ format(selectedPlanet.nextCosts[building.key].deuterium) }}</strong><strong v-if="selectedPlanet.nextCosts[building.key].energy" class="energy-positive">ϟ {{ format(selectedPlanet.nextCosts[building.key].energy) }}</strong></div>
                                                    <button class="button button-primary building-upgrade" type="button" :disabled="!canUpgrade(building) || busyBuilding === building.key" @click="upgrade(building)">{{ building.disabled ? t('common.soon') : busyBuilding === building.key ? t('buildings.constructing') : canUpgrade(building) ? building.maxLevel === 1 ? t('buildings.build') : t('buildings.buildNextLevel', { level: buildingLevel(building) + 1 }) : building.key === 'fusion' || building.key === 'nano_factory' || building.key === 'terraformer' ? t('common.missingRequirements') : buildingLevel(building) >= (building.maxLevel ?? 30) ? t('common.maxLevel') : t('common.resourcesMissing') }}</button>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </section>
        </main>
    </GameLayout>
</template>

<style scoped>
.building-pos { height: auto; background: transparent; }
.building-pos .pos-container { display: flex; height: auto; align-items: flex-start; gap: 18px; }
.building-pos .pos-menu { width: 175px; flex: 0 0 175px; align-items: stretch; gap: 5px; overflow: visible; }
.building-filters > .panel-kicker { padding: 8px 10px; }
.building-filter { display: flex; align-items: center; gap: 10px; width: 100%; padding: 11px 10px; border: 1px solid transparent; color: #aab8c7; background: transparent; text-align: left; cursor: pointer; }
.building-filter i { color: var(--accent); font-size: 16px; }
.building-filter span { flex: 1; font-size: 11px; }
.building-filter small { color: #8291a2; font-size: 10px; }
.building-filter:hover, .building-filter.active { border-color: rgba(68, 217, 173, .35); color: #e6edf4; background: rgba(68, 217, 173, .1); }
.building-filter:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.building-pos .pos-content { min-width: 0; height: auto; overflow: visible; border: 0; }
.building-pos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 270px), 1fr)); gap: 14px; }
.building-pos-card { min-width: 0; border: 1px solid color-mix(in srgb, currentColor 35%, transparent); background: rgba(5, 20, 24, .65); }
.building-pos-card .pos-product { height: 100%; color: var(--text); }
.building-pos-card .building-pos-image { position: relative; display: grid; place-items: center; width: 100%; aspect-ratio: 16 / 9; overflow: hidden; color: var(--accent); background: radial-gradient(circle, rgba(86, 214, 194, .18), #071418); font-size: 40px; }
.building-pos-image img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.building-pos-image img:not([hidden]) + .building-image-fallback { display: none; }
.building-rarity { position: absolute; top: 10px; left: 10px; padding: 4px 8px; border-radius: 2px; background: rgba(4, 12, 16, .9); font-size: 10px; }
.building-level { position: absolute; top: 10px; right: 10px; padding: 4px 8px; border: 1px solid rgba(232, 190, 104, .6); color: #e8be68; background: rgba(4, 12, 16, .9); font-size: 10px; }
.building-pos-body { display: flex; flex: 1; flex-direction: column; gap: 10px; padding: 14px; }
.building-pos-body .building-title { align-items: center; }
.building-pos-body .building-title h3 { font-size: 14px; }
.building-pos-body .building-main { flex: 1; }
.building-pos-body .building-main > p { font-size: 11px; }
.building-pos-body .building-stats, .building-pos-body .activity-row label { font-size: 10px; }
.building-pos-body .building-action { display: grid; grid-template-columns: minmax(0, 1fr); align-items: stretch; }
.building-pos-body .building-upgrade { min-width: 0; font-size: 10px; }
.building-pos-body .building-cost strong { font-size: 10px; }

@media (max-width: 767px) {
    .building-pos .pos-container { flex-direction: column; }
    .building-pos .pos-menu { width: 100%; flex: auto; flex-direction: row; flex-wrap: wrap; }
    .building-filters > .panel-kicker { flex-basis: 100%; }
    .building-filter { width: auto; flex: 1 1 130px; }
    .building-pos .pos-content { width: 100%; }
}
</style>
