<script setup>
import { computed, onMounted, onUnmounted, ref, Teleport, watch } from 'vue';
import { Head, Link, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import ElementInfoButton from '../../Components/ElementInfoButton.vue';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import GameLayout from '../../Layouts/GameLayout.vue';

const props = defineProps({
    moons: { type: Array, default: () => [] },
    planet: { type: Object, required: true },
    moon: { type: Object, required: true },
    moonEconomy: { type: Object, required: true },
});
const page = usePage();
const { t, n } = useI18n();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const form = useForm({ building: 'moon_base' });
const renameForm = useForm({ name: props.moon.name ?? props.moon.generatedName ?? '' });
const busyBuilding = ref(null);
const currentTime = ref(Date.now());
const queuePanelOpen = ref(false);
const renameOpen = ref(false);
const selectedCategory = ref('all');
const categories = [
    { key: 'all', icon: 'bi-grid' },
    { key: 'infrastructure', icon: 'bi-building' },
    { key: 'surveillance', icon: 'bi-broadcast' },
    { key: 'transport', icon: 'bi-arrow-left-right' },
];
const buildingMetadata = {
    moon_base: { category: 'infrastructure', icon: '◐' },
    phalanx: { category: 'surveillance', icon: '⌖' },
    jump_gate: { category: 'transport', icon: '⏣' },
};
const buildings = computed(() => props.moonEconomy.buildings.map((building) => ({
    ...building,
    ...buildingMetadata[building.key],
    name: t(`moonLogistics.buildingNames.${building.key}`),
    description: t(`moonLogistics.buildingDescriptions.${building.key}`),
})));
const visibleBuildings = computed(() => selectedCategory.value === 'all'
    ? buildings.value
    : buildings.value.filter((building) => building.category === selectedCategory.value));
const { start: startPolling, stop: stopPolling } = usePoll(5000, {
    only: ['moon', 'moonEconomy'],
    preserveScroll: true,
}, { autoStart: false, keepAlive: true });
let countdownInterval;
const format = (value) => n(Number(value ?? 0), 'game');

watch(() => props.moon.name, (name) => { renameForm.name = name ?? props.moon.generatedName ?? ''; }, { immediate: true });
onMounted(() => {
    countdownInterval = window.setInterval(() => { currentTime.value = Date.now(); }, 1000);
    startPolling();
});
onUnmounted(() => {
    window.clearInterval(countdownInterval);
    stopPolling();
});

function buildingProgress(item) {
    const startsAt = new Date(item.startsAt).getTime();
    const duration = Math.max(1000, Number(item.durationSeconds) * 1000);
    return Math.min(100, Math.max(0, ((currentTime.value - startsAt) / duration) * 100));
}

function remainingSeconds(value) {
    return Math.max(0, Math.ceil((new Date(value).getTime() - currentTime.value) / 1000));
}

function countdown(seconds) {
    return [Math.floor(seconds / 3600), Math.floor(seconds % 3600 / 60), seconds % 60]
        .map((part) => String(part).padStart(2, '0')).join(':');
}

function renameMoon() {
    renameForm.patch(route('moon.rename', { planet: props.planet.id, orbit: props.moon.orbit }), {
        preserveScroll: true,
        onSuccess: () => { renameOpen.value = false; },
    });
}

function selectMoon(event) {
    const [planet, orbit] = event.target.value.split(':').map(Number);
    router.get(route('moon.buildings'), { planet, orbit }, { preserveScroll: true });
}

function upgrade(building) {
    form.building = building;
    form.post(route('moon.buildings.upgrade', { planet: props.planet.id, orbit: props.moon.orbit }), {
        preserveScroll: true,
        onStart: () => { busyBuilding.value = building; },
        onFinish: () => { busyBuilding.value = null; },
    });
}
</script>

<template>
    <Head :title="t('moonLogistics.buildings')" />
    <GameLayout>
        <main class="command-layout">
            <section class="command-content">
                <div class="command-heading buildings-heading">
                    <div>
                        <p class="eyebrow"><span class="eyebrow-line"></span>{{ t('moonLogistics.buildings') }}</p>
                        <h1>{{ moon.name || moon.generatedName || t('galaxyMoon', { orbit: moon.orbit, diameter: format(moon.diameter) }) }}</h1>
                        <p class="command-subtitle">{{ t('moonLogistics.buildings') }} · {{ planet.name }} [{{ planet.coordinates }}]<template v-if="moon.planetGeneratedName && moon.planetGeneratedName !== planet.name"> · {{ moon.planetGeneratedName }}</template> · {{ t('galaxyMoon', { orbit: moon.orbit, diameter: format(moon.diameter) }) }}</p>
                    </div>
                    <label class="planet-select-label" for="selected-moon">{{ t('moonLogistics.selectMoon') }}
                        <select id="selected-moon" :value="`${planet.id}:${moon.orbit}`" @change="selectMoon">
                            <option v-for="item in moons" :key="`${item.planet.id}:${item.orbit}`" :value="`${item.planet.id}:${item.orbit}`">{{ item.name || item.generatedName || t('galaxyMoon', { orbit: item.orbit, diameter: format(item.diameter) }) }} · {{ item.planet.name }} [{{ item.planet.coordinates }}]</option>
                        </select>
                    </label>
                </div>
                <div v-if="page.props.flash?.success" class="flash-message" role="status">{{ page.props.flash.success }}</div>
                <div v-if="Object.keys(page.props.errors || {}).length" class="form-alert" role="alert">{{ Object.values(page.props.errors)[0] }}</div>
                <section class="moon-rename-panel">
                    <form v-if="renameOpen" class="moon-rename-form" @submit.prevent="renameMoon">
                        <label for="moon-name">{{ t('moonLogistics.rename') }}<input id="moon-name" v-model="renameForm.name" minlength="2" maxlength="80" required></label>
                        <button class="button button-primary" type="submit" :disabled="renameForm.processing">{{ renameForm.processing ? t('moonLogistics.saving') : t('moonLogistics.save') }}</button>
                        <button class="button button-outline" type="button" @click="renameOpen = false; renameForm.name = moon.name ?? moon.generatedName ?? ''">{{ t('moonLogistics.cancel') }}</button>
                        <p v-if="renameForm.errors.name" class="field-error" role="alert">{{ renameForm.errors.name }}</p>
                    </form>
                    <button v-else class="button button-outline" type="button" @click="renameOpen = true; renameForm.name = moon.name || moon.generatedName || t('moonLogistics.defaultName', { orbit: moon.orbit })">{{ t('moonLogistics.rename') }}</button>
                </section>
                <section class="data-panel moon-resource-strip">
                    <div class="panel-heading"><div><span class="panel-kicker">{{ t('moonLogistics.storage') }}</span><h2>{{ t('moonLogistics.resources') }}</h2></div></div>
                    <div class="research-resource-strip"><span>{{ moon.name || moon.generatedName || t('galaxyMoon', { orbit: moon.orbit, diameter: format(moon.diameter) }) }}</span><span class="metal-symbol">▰ {{ format(moon.metal) }}</span><span class="crystal-symbol">◇ {{ format(moon.crystal) }}</span><span class="deut-symbol">⬡ {{ format(moon.deuterium) }}</span><span>{{ t('moonLogistics.fields', { used: moonEconomy.fields.used, max: moonEconomy.fields.max }) }}</span></div>
                </section>
                <section class="building-queue">
                    <div class="queue-summary">
                        <div class="queue-summary-copy"><span class="panel-kicker">{{ moon.name || moon.generatedName || t('galaxyMoon', { orbit: moon.orbit, diameter: format(moon.diameter) }) }} · {{ t('moonLogistics.buildings') }}</span><strong>{{ moonEconomy.queue.length ? t('moonLogistics.constructionCount', { count: moonEconomy.queue.length }) : t('moonLogistics.noConstruction') }}</strong><small v-if="moonEconomy.queue.length">{{ t(`moonLogistics.buildingNames.${moonEconomy.queue[0].key}`) }} · {{ t('moonLogistics.level', { level: moonEconomy.queue[0].targetLevel }) }}</small><small v-else>{{ t('moonLogistics.constructionWillAppear') }}</small></div>
                        <button class="button button-outline queue-toggle" type="button" :aria-expanded="queuePanelOpen" aria-controls="moon-building-queue" @click="queuePanelOpen = !queuePanelOpen">{{ queuePanelOpen ? t('moonLogistics.closeQueue') : t('moonLogistics.openQueue') }} <span aria-hidden="true">{{ queuePanelOpen ? '×' : '→' }}</span></button>
                    </div>
                </section>
                <Teleport to="body">
                    <div v-if="queuePanelOpen" class="queue-drawer-backdrop" @click.self="queuePanelOpen = false" @keydown.esc="queuePanelOpen = false">
                        <aside id="moon-building-queue" class="queue-drawer" role="dialog" aria-modal="true" aria-labelledby="moon-queue-title">
                            <header class="queue-drawer-header"><div><span class="panel-kicker">{{ moon.name || moon.generatedName || t('galaxyMoon', { orbit: moon.orbit, diameter: format(moon.diameter) }) }}</span><h2 id="moon-queue-title">{{ t('moonLogistics.constructionQueue') }}</h2></div><button class="queue-drawer-close" type="button" :aria-label="t('moonLogistics.closeQueue')" @click="queuePanelOpen = false">×</button></header>
                            <div class="queue-drawer-count">{{ t('moonLogistics.queuesUsed', { count: moonEconomy.queue.length }) }}</div>
                            <article v-for="item in moonEconomy.queue" :key="item.id" class="queue-row" :class="{ 'queue-row-active': currentTime >= new Date(item.startsAt).getTime() }">
                                <span class="queue-position">{{ item.position }}</span><div class="queue-details"><div class="queue-title"><strong>{{ t(`moonLogistics.buildingNames.${item.key}`) }} · {{ t('common.level').toLowerCase() }} {{ item.targetLevel }}</strong><span class="queue-state">{{ currentTime >= new Date(item.startsAt).getTime() ? t('moonLogistics.inConstruction') : t('moonLogistics.waiting') }}</span></div><small>{{ t('moonLogistics.ends') }} {{ item.completesAt }}</small><div class="queue-progress" role="progressbar" :aria-valuenow="Math.floor(buildingProgress(item))" aria-valuemin="0" aria-valuemax="100"><span :style="{ width: `${buildingProgress(item)}%` }"></span></div></div><div class="queue-countdown"><span>{{ t('moonLogistics.remaining') }}</span><strong>{{ countdown(remainingSeconds(item.completesAt)) }}</strong></div>
                            </article>
                            <div v-if="!moonEconomy.queue.length" class="queue-empty">{{ t('moonLogistics.noConstruction') }}</div>
                        </aside>
                    </div>
                </Teleport>
                <section class="buildings-section">
                    <div class="buildings-section-heading"><div><span class="panel-kicker">{{ t('moonLogistics.buildings') }}</span><h2>{{ t('moonLogistics.availableBuildings') }}</h2></div><span class="panel-count">{{ visibleBuildings.length }} / {{ buildings.length }}</span></div>
                    <div class="pos building-pos">
                        <div class="pos-container">
                            <nav class="pos-menu building-filters" :aria-label="t('moonLogistics.categories.label')">
                                <span class="panel-kicker">{{ t('moonLogistics.categories.label') }}</span>
                                <button v-for="category in categories" :key="category.key" type="button" class="building-filter" :class="{ active: selectedCategory === category.key }" :aria-pressed="selectedCategory === category.key" @click="selectedCategory = category.key"><i class="bi" :class="category.icon" aria-hidden="true"></i><span>{{ t(`moonLogistics.categories.${category.key}`) }}</span><small>{{ category.key === 'all' ? buildings.length : buildings.filter((building) => building.category === category.key).length }}</small></button>
                            </nav>
                            <div class="pos-content building-catalog"><div class="building-pos-grid">
                                <article v-for="building in visibleBuildings" :key="building.key" class="card building-pos-card">
                                    <div class="pos-product">
                                        <div class="img building-pos-image"><img :src="`/images/buildings/${building.code}.png`" :alt="building.name" loading="lazy" @error="$event.currentTarget.hidden = true"><span class="building-image-fallback" aria-hidden="true">{{ building.icon }}</span><span class="building-level">{{ t('common.level') }} {{ building.level }}</span></div>
                                        <div class="building-pos-body">
                                            <div class="building-title"><span class="building-code">{{ String(building.code).padStart(2, '0') }}</span><h3>{{ building.name }}</h3><ElementInfoButton :id="`moon-building-${building.code}`" :name="building.name" :description="building.description" :image-src="`/images/buildings/${building.code}.png`" /></div>
                                            <div class="building-main"><p>{{ building.description }}</p><p v-if="!building.requirementsMet" class="building-requirement">{{ t('moonLogistics.requirementsMissing') }}</p><div class="building-stats"><span>{{ t('moonLogistics.level', { level: building.level }) }}<template v-if="building.queued"> · {{ t('moonLogistics.queued', { count: building.queued }) }}</template></span></div></div>
                                            <div class="building-action"><div class="building-cost"><span>{{ t('moonLogistics.nextLevel') }}</span><strong class="metal-symbol">▰ {{ format(building.cost.metal) }}</strong><strong class="crystal-symbol">◇ {{ format(building.cost.crystal) }}</strong><strong class="deut-symbol">⬡ {{ format(building.cost.deuterium) }}</strong></div><button class="button button-primary building-upgrade" type="button" :disabled="!building.canBuild || form.processing" @click="upgrade(building.key)">{{ busyBuilding === building.key ? t('moonLogistics.building') : building.canBuild ? t('moonLogistics.build') : !building.requirementsMet ? t('moonLogistics.requirementsMissing') : moonEconomy.queue.length >= 5 ? t('moonLogistics.queueFull') : moonEconomy.fields.used >= moonEconomy.fields.max ? t('moonLogistics.fieldsFull') : t('common.resourcesMissing') }}</button></div>
                                        </div>
                                    </div>
                                </article>
                            </div></div>
                        </div>
                    </div>
                </section>
                <div class="mt-4 flex flex-wrap gap-2"><Link class="button button-outline" :href="route('moon.show', { planet: planet.id, orbit: moon.orbit })">{{ t('moonLogistics.moonDetails') }}</Link><Link class="button button-quiet" :href="route('dashboard')">{{ t('nav.command') }}</Link></div>
            </section>
        </main>
    </GameLayout>
</template>

<style scoped>
.moon-resource-strip { min-height: 0; margin-bottom: 16px; }
.moon-resource-strip .research-resource-strip { margin: 0; }
.moon-rename-panel { display: flex; justify-content: flex-end; margin: -12px 0 16px; }
.moon-rename-form { display: flex; flex-wrap: wrap; align-items: end; gap: 10px; }
.moon-rename-form label { display: grid; gap: 6px; color: #8291a2; font-size: 10px; }
.moon-rename-form input { min-height: 38px; padding: 0 10px; border: 1px solid var(--line); color: var(--text); background: #0b121b; }
.moon-rename-form .field-error { flex-basis: 100%; margin: 0; }
</style>
