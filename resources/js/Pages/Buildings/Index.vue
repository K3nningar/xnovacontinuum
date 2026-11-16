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

const page = usePage();
const { t, n, locale } = useI18n();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const props = defineProps({
    user: { type: Object, required: true },
    universe: { type: Object, default: null },
    technologyEnergyLevel: { type: Number, default: 0 },
    technologyComputerLevel: { type: Number, default: 0 },
    buildingRarities: { type: Object, default: () => ({}) },
    planets: { type: Array, default: () => [] },
    selectedPlanetId: { type: Number, required: true },
});

const selectedPlanet = computed(() => props.planets.find((planet) => planet.id === props.selectedPlanetId) ?? props.planets[0]);
const busyBuilding = computed(() => page.props.processingBuilding ?? null);
const currentTime = ref(Date.now());
const queuePanelOpen = ref(false);
let countdownInterval;
const { start: startPolling, stop: stopPolling } = usePoll(5000, {
    only: ['planets'],
    preserveScroll: true,
}, {
    autoStart: false,
    keepAlive: true,
});
const buildingCatalog = [
    { key: 'metal', level: 'metal_mine_level', activity: 'metal_mine_activity', code: 1, icon: '▰' },
    { key: 'crystal', level: 'crystal_mine_level', activity: 'crystal_mine_activity', code: 2, icon: '◇' },
    { key: 'deuterium', level: 'deuterium_synthesizer_level', activity: 'deuterium_synthesizer_activity', code: 3, icon: '⬡' },
    { key: 'solar', level: 'solar_plant_level', activity: 'solar_plant_activity', code: 4, icon: '☼' },
    { key: 'fusion', level: 'fusion_plant_level', activity: 'fusion_plant_activity', code: 12, icon: '⌬', requirementKey: 'fusionRequirement' },
    { key: 'robot_factory', level: 'robot_factory_level', activity: null, code: 14, icon: 'R', requirementKey: 'robotEffect', maxLevel: 30 },
    { key: 'nano_factory', level: 'nano_factory_level', activity: null, code: 15, icon: 'N', requirementKey: 'nanoRequirement', maxLevel: 30 },
    { key: 'hangar', level: 'hangar_level', activity: null, code: 21, icon: 'H', requirementKey: 'shipyardRequirement' },
    { key: 'metal_storage', level: 'metal_storage_level', activity: null, code: 22, icon: 'M', resource: 'metal', maxLevel: 30 },
    { key: 'crystal_storage', level: 'crystal_storage_level', activity: null, code: 23, icon: 'C', resource: 'crystal', maxLevel: 30 },
    { key: 'deuterium_storage', level: 'deuterium_storage_level', activity: null, code: 24, icon: 'D', resource: 'deuterium', maxLevel: 30 },
    { key: 'laboratory', level: 'laboratory_level', activity: 'laboratory_activity', code: 31, icon: 'L', requirementKey: 'laboratoryRequirement' },
    { key: 'terraformer', level: 'terraformer_level', activity: null, code: 33, icon: 'T', maxLevel: 30, requirementKey: 'terraformerRequirement' },
    { key: 'missile_silo', level: 'missile_silo_level', activity: null, code: 44, icon: 'X', requirementKey: 'siloRequirement' },
];
const buildings = computed(() => buildingCatalog.map((building) => ({
    ...building,
    rarity: props.buildingRarities[building.code],
    name: t(`buildingCatalog.${building.key}.name`),
    description: t(`buildingCatalog.${building.key}.description`),
    unit: t(`buildingCatalog.${building.key}.unit`),
    requirement: building.requirementKey ? t(`buildingCatalog.${building.key}.requirement`) : null,
})));

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
    const uniqueBuildings = ['laboratory', 'robot_factory', 'nano_factory', 'hangar', 'missile_silo'];
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
                            <small v-if="selectedPlanet.queue.length">{{ selectedPlanet.queue.find((item) => currentTime >= new Date(item.starts_at).getTime()) ? t('buildings.constructionRunning') : t('buildings.nextConstruction') }} · {{ selectedPlanet.queue[0] ? t(`buildingCatalog.${selectedPlanet.queue[0].building_key}.name`) : '' }}</small>
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
                                    <div class="queue-title"><strong>{{ t(`buildingCatalog.${item.building_key}.name`) }} · {{ t('common.level').toLowerCase() }} {{ item.target_level }}</strong><span class="queue-state">{{ currentTime >= new Date(item.starts_at).getTime() ? t('buildings.inConstruction') : t('buildings.waiting') }}</span></div>
                                    <small>{{ t('buildings.starts') }} {{ formatDate(item.starts_at) }} · {{ t('buildings.ends') }} {{ formatDate(item.completes_at) }}</small>
                                    <div class="queue-progress" role="progressbar" :aria-valuenow="Math.floor(queueProgress(item))" aria-valuemin="0" aria-valuemax="100" :aria-label="t('buildingActions.progressLabel', { name: t(`buildingCatalog.${item.building_key}.name`), level: item.target_level })"><span :style="{ width: `${queueProgress(item)}%` }"></span></div>
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
                        <div class="energy-production"><span>{{ t('buildingCatalog.solar.name') }} </span><strong>+{{ format(selectedPlanet.energy.sources.solar) }}</strong></div>
                        <div class="energy-production"><span>{{ t('buildingCatalog.fusion.name') }} </span><strong>+{{ format(selectedPlanet.energy.sources.fusion) }}</strong></div>
                        <div class="energy-production"><span>{{ t('unitNames.solar_satellite') }} </span><strong>+{{ format(selectedPlanet.energy.sources.satellites) }}</strong></div>
                    </div>
                    <div class="energy-track"><span :style="{ width: `${selectedPlanet.energy.productionLevel}%` }"></span></div>
                </div>
                <section class="buildings-section"><div class="buildings-section-heading"><div><span class="panel-kicker">{{ t('buildings.productionSector') }}</span><h2>{{ t('buildings.availableFacilities') }}</h2></div><span class="panel-count">{{ buildings.length }}</span></div>
                    <div class="building-list"><article v-for="building in buildings" :key="building.key" class="building-card"><div class="building-icon" :class="`${building.key}-building-icon`"><img :src="`images/buildings/${building.code}.png`" :alt="building.name" loading="lazy" @error="$event.currentTarget.hidden = true"><span aria-hidden="true">{{ building.icon }}</span></div><div class="building-main"><div class="building-title"><span class="building-code">{{ String(building.code).padStart(2, '0') }}</span><h3>{{ building.name }}</h3><RarityBadge :rarity="building.rarity" /><ElementInfoButton :id="`building_${building.code}`" :name="building.name" :description="infosFr[`building_${building.code}`]?.description" /></div><p>{{ building.description }}</p><p v-if="building.requirement" class="building-requirement">{{ building.requirement }}</p><div class="building-stats"><span>{{ t('common.level') }} <strong>{{ buildingLevel(building) }}</strong></span><span :class="building.key === 'solar' || building.key === 'fusion' ? 'energy-positive' : ''">{{ building.resource || building.key === 'terraformer' ? '' : '+' }}{{ buildingOutput(building) }} {{ building.unit }}</span><span v-if="building.key === 'fusion'" class="energy-negative">{{ t('buildings.fusionUse') }}</span></div><div v-if="building.activity !== null" class="activity-row"><label :for="`activity-${building.key}`">{{ t('buildings.activity') }} <strong>{{ selectedPlanet[building.activity] * 10 }}%</strong></label><input :id="`activity-${building.key}`" type="range" min="0" max="10" step="1" :value="selectedPlanet[building.activity]" :disabled="building.key === 'fusion' || building.key === 'laboratory'" @change="updateActivity(building, $event)"></div></div><div class="building-action"><div class="building-cost"><span>{{ t('common.nextLevel') }}</span><strong><i class="metal-symbol">▰</i>{{ format(selectedPlanet.nextCosts[building.key].metal) }}</strong><strong><i class="crystal-symbol">◇</i>{{ format(selectedPlanet.nextCosts[building.key].crystal) }}</strong><strong><i class="deut-symbol">⬡</i>{{ format(selectedPlanet.nextCosts[building.key].deuterium) }}</strong><strong v-if="selectedPlanet.nextCosts[building.key].energy" class="energy-positive">ϟ {{ format(selectedPlanet.nextCosts[building.key].energy) }}</strong></div><button class="button button-primary building-upgrade" type="button" :disabled="!canUpgrade(building) || busyBuilding === building.key" @click="upgrade(building)">{{ building.disabled ? t('common.soon') : busyBuilding === building.key ? t('buildings.constructing') : canUpgrade(building) ? building.maxLevel === 1 ? t('buildings.build') : t('buildings.buildNextLevel', { level: buildingLevel(building) + 1 }) : building.key === 'fusion' || building.key === 'nano_factory' || building.key === 'terraformer' ? t('common.missingRequirements') : buildingLevel(building) >= (building.maxLevel ?? 30) ? t('common.maxLevel') : t('common.resourcesMissing') }}</button></div></article></div>
    
                </section>
            </section>
        </main>
    </GameLayout>
</template>
