<!--
Author / Contributor : K3nningar
Code Version : 0.1.1-alpha
Revision : 34ddc84
Description : Renders the Index Inertia page.
-->

<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import GameLayout from '../../Layouts/GameLayout.vue';
import { translateUnitName } from '../../unitNames.js';
import { normalizeFleetMission } from '../../fleetMissionForm.js';

const props = defineProps({
    planets: { type: Array, required: true },
    selectedPlanetId: { type: Number, required: true },
    galaxy: { type: Number, required: true },
    system: { type: Number, required: true },
    galaxyLimit: { type: Number, required: true },
    systemLimit: { type: Number, required: true },
    spyLevel: { type: Number, required: true },
    star: { type: String, required: true },
    starName: { type: String, required: true },
    slots: { type: Array, required: true },
    availableShips: { type: Array, required: true },
    fleetSpeed: { type: Number, required: true },
    combatLimit: { type: Number, required: true },
    missionConfig: { type: Object, required: true },
});
const page = usePage();
const { t, n } = useI18n();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const targetGalaxy = ref(props.galaxy);
const targetSystem = ref(props.system);
const originPlanet = ref(props.selectedPlanetId);
const selectedPosition = ref(1);
const dismissedTooltip = ref(null);
const missionForm = useForm({ mission: '', galaxy: props.galaxy, system: props.system, position: 16, ships: {}, target_type: 'planet', moon_orbit: null, origin_target_type: 'planet', origin_moon_orbit: null, cargo: { metal: 0, crystal: 0, deuterium: 0 } });
const shipNames = computed(() => Object.fromEntries(props.availableShips.map((ship) => [ship.code, translateUnitName(ship.code, t)])));
const sendingShips = computed(() => {
    const available = missionForm.origin_target_type === 'moon'
        ? props.availableShips.filter((ship) => Object.hasOwn(missionForm.ships, ship.code))
        : props.availableShips.filter((ship) => ship.count > 0);
    if (missionForm.mission === 'destroy_moon') return available.filter((ship) => ship.code === 214);
    if (missionForm.mission === 'destroy_planet') return available.filter((ship) => ship.code === 217);
    if (missionForm.mission === 'spy') return available.filter((ship) => ship.code === 210);
    if (missionForm.mission === 'recycle') return available.filter((ship) => ship.code === 209);

    return available;
});
const selectedTarget = computed(() => props.slots.find((slot) => slot.position === missionForm.position));
const selectedOriginPlanet = computed(() => props.planets.find((planet) => Number(planet.id) === Number(props.selectedPlanetId)));
const selectedOriginTarget = computed(() => props.slots.find((slot) => Number(slot.position) === Number(selectedOriginPlanet.value?.coordinates?.split(':')[2])));
const originPlanetName = computed(() => selectedOriginPlanet.value?.name ?? '');
const originMoonOptions = computed(() => {
    if (!selectedOriginPlanet.value) return [];
    const [galaxy, system, position] = selectedOriginPlanet.value.coordinates.split(':').map(Number);
    if (galaxy !== props.galaxy || system !== props.system) return [];

    return props.slots.find((slot) => slot.position === position)?.naturalMoons ?? [];
});
const targetMissions = computed(() => {
    const slot = selectedTarget.value;
    if (missionForm.position === 16) return ['expedition'];
    if (!slot?.visible) return [];
    if (slot.kind === 'asteroid') return ['recycle'];
    if (slot.kind !== 'planet') return [];
    if (!slot.planet) return ['colonize'];
    if (slot.planet.owned) return ['transport'];
    return [
        'transport', 'attack', 'spy',
        ...(slot.naturalMoons?.length ? ['destroy_moon'] : []),
        ...(props.missionConfig.hasAlliance && slot.planet.alliedDepot ? ['station'] : []),
        ...(props.missionConfig.planetaryDestroyerAvailable ? ['destroy_planet'] : []),
    ];
});
const availableMissionOptions = computed(() => props.missionConfig.missions.filter((mission) => targetMissions.value.includes(mission) && (mission !== 'destroy_planet' || props.missionConfig.planetaryDestroyerAvailable)));
const targetTypeOptions = computed(() => {
    if (missionForm.mission === 'recycle') return ['asteroid', 'debris'];
    if (missionForm.mission === 'destroy_moon') return ['moon'];
    if (missionForm.mission === 'station') return ['depot', 'moon'];
    if (['transport', 'attack', 'spy', 'destroy_planet'].includes(missionForm.mission)) return ['planet', 'moon'];
    return ['planet'];
});
const cargoCapacity = computed(() => Object.entries(missionForm.ships).reduce((sum, [code, count]) => sum + Number(count || 0) * ({ 202: 5000, 203: 25000, 204: 50, 205: 100, 206: 800, 207: 1500, 209: 20000, 211: 500, 213: 2000, 214: 1000000, 215: 750, 216: 10000 }[code] || 0), 0));
const cargoTotal = computed(() => Object.values(missionForm.cargo).reduce((sum, amount) => sum + Number(amount || 0), 0));
// Match mission distance tiers: galaxy travel, same-galaxy system travel, then same-system orbit travel.
const distance = computed(() => {
    const origin = props.planets.find((planet) => planet.id === props.selectedPlanetId)?.coordinates?.split(':').map(Number);
    if (!origin) return 0;
    if (missionForm.galaxy !== origin[0]) return 20000 * Math.abs(missionForm.galaxy - origin[0]);
    if (missionForm.system !== origin[1]) return 2700 + 95 * Math.abs(missionForm.system - origin[1]);
    return 1000 + 5 * Math.abs(missionForm.position - origin[2]);
});
const fuel = computed(() => Math.ceil(Object.values(missionForm.ships).reduce((sum, count) => sum + Number(count || 0), 0) * distance.value / 1000));
const flight = computed(() => props.fleetSpeed > 0 ? Math.max(60, Math.ceil((600 + distance.value) / props.fleetSpeed)) : '—');

function orbitRadius(position) {
    const index = position - 2;
    return { x: 74 + index * 19, y: 62 + index * 13 };
}

function markerStyle(position) {
    const radius = orbitRadius(position);
    const angle = -Math.PI / 2 + (position - 2) * 2.4;
    return {
        left: `${(480 + radius.x * Math.cos(angle)) / 9.6}%`,
        top: `${(320 + radius.y * Math.sin(angle)) / 6.4}%`,
    };
}

function tooltipPlacement(position) {
    const radius = orbitRadius(position);
    const angle = -Math.PI / 2 + (position - 2) * 2.4;
    return {
        'tooltip-left': Math.cos(angle) > 0,
        'tooltip-above': Math.sin(angle) > 0,
    };
}

function selectPosition(event, position) {
    if (window.matchMedia('(min-width: 1200px) and (hover: hover) and (pointer: fine)').matches) {
        event.preventDefault();
        selectedPosition.value = position;
    }
}

function slotLabel(slot) {
    const position = t('galaxy.position', { position: slot.position });
    if (!slot.visible) return `${position} · ${t('galaxy.hidden')} · ${t('galaxy.required', { level: slot.requiredSpyLevel })}`;
    if (slot.kind === 'planet') {
        const name = slot.planet?.name ?? slot.generatedName;
        const owner = slot.planet?.owned ? ` · ${t('galaxy.own')}` : '';
        const generated = slot.planet?.name && slot.generatedName !== slot.planet.name ? ` · ${slot.generatedName}` : '';

        return `${position} · ${name}${generated}${owner}`;
    }

    return `${position} · ${t('galaxy.asteroid', { type: t(`galaxy.types.${slot.type}`) })}`;
}

/** Reset the ship selection and stale errors whenever a new target/mission is prepared. */
function missionsForSlot(slot) {
    if (!slot.visible) return [];
    if (slot.kind === 'asteroid') return ['recycle'];
    if (slot.kind !== 'planet') return [];
    if (!slot.planet) return ['colonize'];
    if (slot.planet.owned) return ['transport'];
    return [
        'transport', 'attack', 'spy',
        ...(slot.naturalMoons?.length ? ['destroy_moon'] : []),
        ...(props.missionConfig.hasAlliance && slot.planet.alliedDepot ? ['station'] : []),
        ...(props.missionConfig.planetaryDestroyerAvailable ? ['destroy_planet'] : []),
    ];
}

function prepareMission(mission, position) {
    missionForm.mission = mission;
    missionForm.galaxy = props.galaxy;
    missionForm.system = props.system;
    missionForm.position = mission === 'expedition' ? 16 : position;
    missionForm.target_type = mission === 'recycle' ? 'asteroid' : mission === 'station' ? 'depot' : mission === 'destroy_moon' ? 'moon' : 'planet';
    if (mission === 'transport' && missionForm.position === (originPlanet.value?.coordinates?.split(':').map(Number)[2] ?? -1)) {
        missionForm.position = missionForm.position === 15 ? 14 : missionForm.position + 1;
    }
    missionForm.origin_target_type = 'planet';
    missionForm.origin_moon_orbit = null;
    missionForm.moon_orbit = null;
    missionForm.ships = Object.fromEntries(props.availableShips.map((ship) => [ship.code, 0]));
    missionForm.cargo = { metal: 0, crystal: 0, deuterium: 0 };
    missionForm.clearErrors();
}

function sendMission() {
    missionForm.transform(normalizeFleetMission).post(route('planet.missions.store', props.selectedPlanetId));
}

// Keep local navigation and mission state synchronized after an Inertia visit changes the viewed system.
watch(() => [props.galaxy, props.system, props.selectedPlanetId], ([galaxy, system, planet]) => {
    targetGalaxy.value = galaxy;
    targetSystem.value = system;
    originPlanet.value = planet;
    selectedPosition.value = 1;
    missionForm.mission = '';
});

function navigate(galaxy = targetGalaxy.value, system = targetSystem.value) {
    router.get(route('galaxy.index'), { planet: originPlanet.value, galaxy, system }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('galaxy.title')" />
    <GameLayout>
        <main class="command-layout">
            <section class="command-content">
                <div class="command-heading">
                    <div><p class="eyebrow"><span class="eyebrow-line"></span> {{ t('galaxy.title') }}</p><h1>{{ t('galaxy.heading') }}</h1><p class="command-subtitle">{{ t('galaxy.subtitle') }}</p></div>
                    <Link class="button button-outline" :href="route('research.index', { planet: selectedPlanetId })">{{ t('galaxy.sensorLevel', { level: spyLevel }) }}</Link>
                </div>
                <div v-if="Object.keys(page.props.errors || {}).length" class="form-alert" role="alert">{{ Object.values(page.props.errors)[0] }}</div>
                <p class="mb-4 text-sm text-slate-400">{{ t('galaxy.sensorsDesc') }}</p>
                <form class="data-panel galaxy-search mb-4 p-4" @submit.prevent="navigate()">
                    <div class="flex flex-wrap items-end gap-3">
                        <label class="flex flex-col gap-1 text-sm" for="galaxy-origin">{{ t('galaxy.planet') }}
                            <select id="galaxy-origin" v-model="originPlanet" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white">
                                <option v-for="planet in planets" :key="planet.id" :value="planet.id">{{ planet.name }} · {{ planet.coordinates }}</option>
                            </select>
                        </label>
                        <label class="flex flex-col gap-1 text-sm" for="galaxy-number">{{ t('galaxy.galaxy') }}
                            <input id="galaxy-number" v-model.number="targetGalaxy" class="w-24 rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" type="number" min="1" :max="galaxyLimit" required>
                        </label>
                        <label class="flex flex-col gap-1 text-sm" for="system-number">{{ t('galaxy.system') }}
                            <input id="system-number" v-model.number="targetSystem" class="w-24 rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" type="number" min="1" :max="systemLimit" required>
                        </label>
                        <button class="button button-primary" type="submit">{{ t('galaxy.go') }}</button>
                        <button class="button button-outline" type="button" :disabled="system <= 1" :aria-label="t('galaxy.previous')" @click="navigate(galaxy, system - 1)">←</button>
                        <button class="button button-outline" type="button" :disabled="system >= systemLimit" :aria-label="t('galaxy.next')" @click="navigate(galaxy, system + 1)">→</button>
                    </div>
                </form>

                <div class="galaxy-overview">
                <section class="data-panel system-map-panel mb-4" aria-labelledby="system-map-title">
                    <div class="panel-heading">
                        <div>
                            <span class="panel-kicker">{{ t('galaxy.galaxy') }} {{ galaxy }} · {{ t('galaxy.system') }} {{ system }}</span>
                            <h2 id="system-map-title">{{ t('galaxyMap.title') }}</h2>
                        </div>
                    </div>
                    <p class="galaxy-mobile-hint px-4 pt-3 text-sm text-slate-400">{{ t('galaxyMap.hint') }}</p>
                    <p class="galaxy-desktop-hint px-4 pt-3 text-sm text-slate-400">{{ t('galaxyMap.desktopHint') }}</p>
                    <div class="system-map-scroll" tabindex="0" role="region" :aria-label="t('galaxyMap.title')">
                        <div class="system-map">
                            <svg class="system-map-orbits" viewBox="0 0 960 640" aria-hidden="true">
                                <ellipse v-for="slot in slots" :key="slot.position" cx="480" cy="320" :rx="orbitRadius(slot.position).x" :ry="orbitRadius(slot.position).y" />
                            </svg>
                            <a class="system-map-star" :class="[`star-${star}`, { 'is-current': selectedPosition === 1 }]" href="#galaxy-position-1" :aria-label="`${t('galaxy.position', { position: 1 })} · ${t('galaxy.starName')} ${starName} · ${t(`galaxy.types.${star}`)}`" aria-describedby="galaxy-tooltip-1" @mouseenter="dismissedTooltip = null" @focus="dismissedTooltip = null" @keydown.esc="dismissedTooltip = 1" @click="selectPosition($event, 1)">
                                <span class="system-map-star-core"></span><span class="system-map-number">1</span>
                                <span id="galaxy-tooltip-1" class="system-map-tooltip" :class="{ 'tooltip-dismissed': dismissedTooltip === 1 }" role="tooltip">{{ starName }} · {{ t(`galaxy.types.${star}`) }}</span>
                            </a>
                            <a v-for="slot in slots" :key="slot.position" class="system-map-marker" :class="[!slot.visible ? 'body-hidden' : slot.kind === 'asteroid' ? `body-asteroid body-${slot.type}` : [`body-planet`, `body-${slot.type}`, { 'body-owned': slot.planet?.owned }], { 'is-current': selectedPosition === slot.position }]" :style="markerStyle(slot.position)" :href="`#galaxy-position-${slot.position}`" :aria-label="slotLabel(slot)" :aria-describedby="`galaxy-tooltip-${slot.position}`" @mouseenter="dismissedTooltip = null" @focus="dismissedTooltip = null" @keydown.esc="dismissedTooltip = slot.position" @click="selectPosition($event, slot.position)">
                                <span class="system-map-body" aria-hidden="true"></span>
                                <span class="system-map-number">{{ slot.position }}</span>
                                <span v-if="slot.visible && slot.kind === 'planet'" class="system-map-body-name" aria-hidden="true">{{ slot.planet?.name ?? slot.generatedName }}</span>
                                <span :id="`galaxy-tooltip-${slot.position}`" class="system-map-tooltip" :class="[tooltipPlacement(slot.position), { 'tooltip-dismissed': dismissedTooltip === slot.position }]" role="tooltip">
                                    <strong>{{ slotLabel(slot) }}</strong>
                                    <template v-if="slot.visible && slot.kind === 'planet'">
                                        <span class="system-map-star-name">{{ t('galaxy.star') }} · {{ starName }} · {{ t(`galaxy.types.${star}`) }}</span>
                                        <span>{{ slot.planet ? (slot.planet.owned ? t('galaxy.own') : t('galaxy.owner', { name: slot.planet.owner })) : t('galaxy.unoccupied') }} · {{ slot.planet?.name ?? slot.generatedName }}</span>
                                        <small v-if="slot.planet?.name && slot.planet.name !== slot.generatedName">{{ t('galaxy.generatedPlanet') }} · {{ slot.generatedName }}</small>
                                        <span>{{ t('galaxy.moons', { count: slot.moons }) }}</span>
                                        <ul v-if="slot.naturalMoons.length" class="system-map-moon-names"><li v-for="moon in slot.naturalMoons" :key="moon.orbit">{{ moon.name }} · {{ format(moon.diameter) }} km</li></ul>
                                        <span>{{ t('galaxy.potential') }} : ▰ {{ slot.resources.metal }}% · ◇ {{ slot.resources.crystal }}% · ⬡ {{ slot.resources.deuterium }}%</span>
                                    </template>
                                    <template v-else-if="slot.visible">
                                        <span>{{ t('galaxy.concentration', { value: slot.concentration }) }}</span>
                                        <span>{{ t('common.metal') }} {{ n(slot.remaining.metal, 'game') }} · {{ t('common.crystal') }} {{ n(slot.remaining.crystal, 'game') }} · {{ t('common.deuterium') }} {{ n(slot.remaining.deuterium, 'game') }}</span>
                                    </template>
                                </span>
                            </a>
                            <a class="system-map-expedition" href="#galaxy-position-16" :aria-label="`${t('galaxy.position', { position: 16 })} · ${t('galaxy.expedition')}`" @click="selectPosition($event, 16)">
                                <span aria-hidden="true">✧</span> 16 · {{ t('galaxy.expedition') }}
                            </a>
                        </div>
                    </div>
                    <div class="system-map-legend px-4 pb-4 text-sm">
                        <span><i class="legend-dot legend-planet"></i>{{ t('galaxyMap.planet') }}</span>
                        <span><i class="legend-dot legend-asteroid"></i>{{ t('galaxyMap.asteroid') }}</span>
                        <span><i class="legend-dot legend-owned"></i>{{ t('galaxy.own') }}</span>
                        <span><i class="legend-dot legend-hidden"></i>{{ t('galaxy.hidden') }}</span>
                    </div>
                </section>

                <section id="galaxy-position-1" class="data-panel galaxy-details">
                    <div class="panel-heading"><div><span class="panel-kicker">{{ t('galaxy.galaxy') }} {{ galaxy }} · {{ t('galaxy.system') }} {{ system }} · <span class="galaxy-mobile-hint">{{ t('galaxy.position', { position: 1 }) }}</span><span class="galaxy-desktop-hint" aria-live="polite">{{ t('galaxy.position', { position: selectedPosition }) }}</span></span><h2><span class="galaxy-mobile-hint">{{ starName }} · {{ t(`galaxy.types.${star}`) }}</span><span class="galaxy-desktop-hint">{{ selectedPosition === 1 ? `${starName} · ${t(`galaxy.types.${star}`)}` : t('galaxyMap.title') }}</span></h2></div><span class="text-xl text-amber-300" aria-hidden="true">✦</span></div>
                    <div class="galaxy-details-grid grid grid-cols-1 gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
                        <article v-for="slot in slots" :id="`galaxy-position-${slot.position}`" :key="slot.position" class="galaxy-position-card border border-slate-700 bg-slate-950/40 p-4" :class="{ 'opacity-60': !slot.visible, 'is-selected': selectedPosition === slot.position }">
                            <p class="text-sm text-slate-400">{{ t('galaxy.position', { position: slot.position }) }}</p>
                            <template v-if="!slot.visible">
                                <h3 class="mt-2 font-semibold text-slate-400">{{ t('galaxy.hidden') }}</h3>
                                <p class="mt-2 text-sm text-slate-400">{{ t('galaxy.required', { level: slot.requiredSpyLevel }) }}</p>
                            </template>

                            <template v-else-if="slot.kind === 'planet'">
                                <h3 class="mt-2 font-semibold text-cyan-300">{{ slot.planet?.name ?? slot.generatedName }}</h3>
                                <p v-if="slot.planet?.name && slot.planet.name !== slot.generatedName" class="mt-1 text-xs text-slate-400">{{ t('galaxy.generatedPlanet') }} · {{ slot.generatedName }}</p>
                                <p class="mt-2 text-sm">{{ t('galaxy.planetType', { type: t(`galaxy.types.${slot.type}`) }) }} · {{ slot.planet ? (slot.planet.owned ? t('galaxy.own') : t('galaxy.owner', { name: slot.planet.owner })) : t('galaxy.unoccupied') }}</p>
                                <p class="mt-2 text-sm text-slate-400">{{ t('galaxy.moons', { count: slot.moons }) }}</p>
                                <p v-if="slot.naturalMoons.length" class="mt-1 text-xs text-slate-400">{{ t('galaxy.moonNames') }}</p>
                                        <ul v-if="slot.naturalMoons.length" class="mt-1 text-sm text-slate-400"><li v-for="moon in slot.naturalMoons" :key="moon.orbit"><Link v-if="slot.planet?.owned" class="text-cyan-300 underline" :href="route('moon.show', { planet: slot.planet.id, orbit: moon.orbit })">{{ moon.name }} · {{ n(moon.diameter, 'game') }} km</Link><template v-else>{{ moon.name }} · {{ n(moon.diameter, 'game') }} km</template></li></ul>
                                <p class="mt-2 text-sm text-slate-400">{{ t('galaxy.potential') }} : ▰ {{ slot.resources.metal }}% · ◇ {{ slot.resources.crystal }}% · ⬡ {{ slot.resources.deuterium }}%</p>
                                <div class="mt-3 flex flex-wrap gap-2"><button v-for="mission in missionsForSlot(slot)" :key="mission" class="button button-outline" type="button" @click="prepareMission(mission, slot.position)">{{ t(`galaxyMissions.${mission}`) }}</button></div>
                            </template>
                            <template v-else>
                                <h3 class="mt-2 font-semibold text-amber-300">{{ t('galaxy.asteroid', { type: t(`galaxy.types.${slot.type}`) }) }}</h3>
                                <p class="mt-2 text-sm">{{ t('galaxy.concentration', { value: slot.concentration }) }}</p>
                                <p class="mt-2 text-sm text-slate-400">{{ t('common.metal') }} {{ n(slot.remaining.metal, 'game') }} · {{ t('common.crystal') }} {{ n(slot.remaining.crystal, 'game') }} · {{ t('common.deuterium') }} {{ n(slot.remaining.deuterium, 'game') }}</p>
                                <div class="mt-3 flex flex-wrap gap-2"><button v-for="mission in missionsForSlot(slot)" :key="mission" class="button button-outline" type="button" @click="prepareMission(mission, slot.position)">{{ t(`galaxyMissions.${mission}`) }}</button></div>
                            </template>
                        </article>
                        <article id="galaxy-position-16" class="galaxy-position-card border border-slate-700 bg-slate-950/40 p-4" :class="{ 'is-selected': selectedPosition === 16 }">
                            <p class="text-sm text-slate-400">{{ t('galaxy.position', { position: 16 }) }}</p>
                            <h3 class="mt-2 font-semibold text-purple-300">{{ t('galaxy.expedition') }}</h3>
                            <p class="mt-2 text-sm">{{ t('galaxy.expeditionDesc') }}</p>
                            <button class="button button-outline mt-3" type="button" @click="prepareMission('expedition', 16)">{{ t('galaxyMissions.explore') }}</button>
                        </article>
                    </div>
                    <p class="px-4 pb-4 text-sm text-slate-400">{{ t('galaxyConcentrationNote') }}</p>
                </section>
                <form v-if="missionForm.mission" class="data-panel galaxy-search mt-4 p-4" @submit.prevent="sendMission">
                    <h2 class="text-lg font-semibold">{{ t('galaxyMissions.prepare') }} · {{ galaxy }}:{{ system }}:{{ missionForm.position }}</h2>
                    <label v-if="originMoonOptions.length" class="mt-3 flex max-w-sm flex-col gap-1 text-sm">{{ t('galaxyMissions.originMoon') }}
                        <select v-model="missionForm.origin_target_type" @change="missionForm.origin_moon_orbit = null; missionForm.ships = Object.fromEntries(props.availableShips.map((ship) => [ship.code, 0]))" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white"><option value="planet">{{ originPlanetName }}</option><option value="moon">{{ t('galaxyMissions.moonOnOrigin') }}</option></select>
                    </label>
                    <label v-if="missionForm.origin_target_type === 'moon'" class="mt-3 flex max-w-sm flex-col gap-1 text-sm">{{ t('galaxyMissions.originOrbit') }}
                        <select v-model.number="missionForm.origin_moon_orbit" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" @change="missionForm.ships = Object.fromEntries(props.availableShips.map((ship) => [ship.code, 0]))"><option :value="null">{{ t('common.select') }}</option><option v-for="moon in originMoonOptions" :key="moon.orbit" :value="moon.orbit">{{ t('galaxyMoon', { orbit: moon.orbit, diameter: n(moon.diameter, 'game') }) }}</option></select>
                    </label>
                    <label class="mt-3 flex max-w-sm flex-col gap-1 text-sm">{{ t('galaxyMissions.mission') }}
                        <select :value="missionForm.mission" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" @change="prepareMission($event.target.value, missionForm.position)">
                            <option v-for="mission in availableMissionOptions" :key="mission" :value="mission">{{ t(`galaxyMissions.${mission}`) }}</option>
                        </select>
                    </label>
                    <label v-if="targetTypeOptions.length > 1" class="mt-3 flex max-w-sm flex-col gap-1 text-sm">{{ t('galaxyMissions.targetType') }}
                        <select v-model="missionForm.target_type" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" @change="missionForm.moon_orbit = null">
                            <option v-for="type in targetTypeOptions" :key="type" :value="type">{{ t(`galaxyMissions.targetTypes.${type}`) }}</option>
                        </select>
                    </label>
                    <label v-if="missionForm.target_type === 'moon' || missionForm.mission === 'destroy_moon'" class="mt-3 flex max-w-sm flex-col gap-1 text-sm">{{ t('galaxyMissions.moonOrbit') }}
                        <select v-model.number="missionForm.moon_orbit" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white">
                            <option :value="null">{{ t('common.select') }}</option>
                            <option v-for="moon in (missionForm.mission === 'destroy_moon' ? (selectedOriginTarget?.naturalMoons ?? []) : (selectedTarget?.naturalMoons ?? []))" :key="moon.orbit" :value="moon.orbit">{{ t('galaxyMoon', { orbit: moon.orbit, diameter: n(moon.diameter, 'game') }) }}</option>
                        </select>
                    </label>
                    <div v-if="missionForm.mission === 'transport'" class="mt-3 grid max-w-2xl grid-cols-1 gap-3 sm:grid-cols-3">
                        <label v-for="resource in ['metal', 'crystal', 'deuterium']" :key="resource" class="flex flex-col gap-1 text-sm">{{ t(`common.${resource}`) }}<input v-model.number="missionForm.cargo[resource]" type="number" min="0" max="1000000000" step="1" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white"></label>
                        <p class="text-sm text-slate-400 sm:col-span-3">{{ t('galaxyMissions.cargoCapacity', { used: n(cargoTotal, 'game'), capacity: n(cargoCapacity, 'game') }) }}</p>
                    </div>
                    <p class="mt-2 text-sm">{{ t('galaxyMissions.selectShips') }}</p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <label v-for="ship in sendingShips" :key="ship.code" class="flex flex-col gap-1 text-sm">
                            {{ shipNames[ship.code] }} · {{ t('galaxyMissions.available', { count: n(ship.count, 'game') }) }}
                            <input v-model.number="missionForm.ships[ship.code]" type="number" min="0" :max="ship.count" step="1" class="w-32 rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white">
                        </label>
                    </div>
                    <p class="mt-3 text-sm">{{ t('galaxyMissions.fuel', { fuel }) }} · {{ t('galaxyMissions.flight', { seconds: flight }) }}</p>
                    <p v-if="missionForm.mission === 'expedition'" class="mt-2 text-sm">{{ t('galaxyMissions.expeditionTime') }}</p>
                    <p v-if="missionForm.mission === 'expedition'" class="mt-2 text-sm text-slate-400">{{ t('galaxyCombatLimit', { limit: combatLimit }) }}</p>
                    <p v-for="(error, key) in missionForm.errors" :key="key" role="alert" class="mt-2 text-sm text-red-300">{{ error }}</p>
                    <div class="mt-3 flex gap-3"><button class="button button-primary" type="submit" :disabled="missionForm.processing || !sendingShips.length || !availableMissionOptions.includes(missionForm.mission)">{{ missionForm.processing ? t('galaxyMissions.sending') : t('galaxyMissions.send') }}</button><button class="button button-outline" type="button" @click="missionForm.mission = ''">{{ t('common.cancel') }}</button></div>
                </form>
                </div>
            </section>
        </main>
    </GameLayout>
</template>

<style scoped>
.galaxy-search, .system-map-panel { min-height: 0; }
.galaxy-desktop-hint, .system-map-tooltip { display: none; }
.system-map-scroll { overflow-x: auto; padding: 0 1rem; }
.system-map {
    position: relative;
    width: 100%;
    min-width: 760px;
    aspect-ratio: 3 / 2;
    overflow: hidden;
    border: 1px solid #263d55;
    background: radial-gradient(ellipse at center, #172b41 0%, #091426 55%, #050b16 100%);
}
.system-map-orbits { position: absolute; inset: 0; width: 100%; height: 100%; fill: none; stroke: #7494ae; stroke-opacity: .22; stroke-width: 1; }
.system-map-marker, .system-map-star {
    position: absolute;
    display: flex;
    width: 46px;
    height: 46px;
    align-items: center;
    justify-content: center;
    transform: translate(-50%, -50%);
    border-radius: 50%;
    text-decoration: none;
}
.system-map-marker:focus-visible, .system-map-star:focus-visible, .system-map-expedition:focus-visible { outline: 2px solid #fff; outline-offset: 3px; }
.system-map-marker:hover, .system-map-star:hover { background: #ffffff20; }
.system-map-body { display: block; width: 22px; height: 22px; border-radius: 50%; background: radial-gradient(circle at 30% 25%, #f8fafc, var(--body-color, #6ea8bd) 35%, #15243b 100%); box-shadow: 0 0 10px #6ca9ca55; }
.body-silicate { --body-color: #9caa9b; }
.body-carbon { --body-color: #8c7899; }
.body-metallic { --body-color: #a7b3bf; }
.body-magmatic { --body-color: #ef6c43; }
.body-gaseous { --body-color: #d3a773; }
.body-desert { --body-color: #d9b17c; }
.body-oceanic { --body-color: #4aa9d3; }
.body-asteroid .system-map-body { width: 25px; height: 16px; border-radius: 35% 60% 40% 55%; background: radial-gradient(circle at 30% 30%, #e8cba7, #8b705a 45%, #3d3533); box-shadow: 7px -6px 0 -5px #b69a7c, -9px 5px 0 -5px #b69a7c; }
.body-ice .system-map-body { background: radial-gradient(circle at 30% 30%, #effcff, #7cb9cb 45%, #344d63); }
.body-hidden .system-map-body { background: #606b79; box-shadow: none; }
.body-owned .system-map-body { outline: 2px solid #67e8f9; outline-offset: 3px; }
.system-map-number { position: absolute; top: 34px; color: #e2e8f0; font-size: 11px; font-weight: 700; line-height: 1; text-shadow: 0 1px 3px #000; }
.system-map-body-name { position: absolute; top: 48px; max-width: 110px; overflow: hidden; color: #d9e4ee; font-size: 7px; text-align: center; text-overflow: ellipsis; text-shadow: 0 1px 5px #03070c; white-space: nowrap; pointer-events: none; }
.system-map-star { left: 50%; top: 50%; }
.system-map-star-core { width: 36px; height: 36px; border-radius: 50%; background: radial-gradient(circle, #fffdf0, var(--star-color, #ffc66a) 50%, #ee772d); box-shadow: 0 0 22px var(--star-color, #ffc66a); }
.star-blue_giant, .star-neutron_star { --star-color: #8ed3ff; }
.star-white_dwarf { --star-color: #e8f5ff; }
.star-red_giant, .star-red_dwarf { --star-color: #ff8062; }
.star-black_hole .system-map-star-core { background: #03050a; border: 3px solid #e8ad7b; box-shadow: 0 0 18px #ee9c68; }
.system-map-expedition { position: absolute; right: 2%; top: 3%; color: #dcc3ff; border: 1px solid #80609d; background: #261b3acc; padding: .5rem .75rem; font-size: .8rem; text-decoration: none; }
.system-map-legend { display: flex; flex-wrap: wrap; gap: .75rem 1.25rem; padding-top: 1rem; color: #cbd5e1; }
.system-map-legend span { display: inline-flex; align-items: center; gap: .4rem; }
.legend-dot { display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: #65a8c5; }
.legend-asteroid { background: #b69a7c; border-radius: 35%; }
.legend-owned { background: #65a8c5; outline: 2px solid #67e8f9; outline-offset: 2px; }
.legend-hidden { background: #606b79; }

@media (min-width: 1200px) and (hover: hover) and (pointer: fine) {
    .galaxy-overview { display: grid; grid-template-columns: minmax(0, 830px) minmax(280px, 320px); gap: 1rem; align-items: start; }
    .system-map-panel { grid-column: 1; grid-row: 1 / span 2; margin-bottom: 0; }
    .galaxy-details { grid-column: 2; grid-row: 1; min-height: 0; }
    .galaxy-overview > form { grid-column: 2; grid-row: 2; margin-top: 0; }
    .system-map { max-width: 760px; min-width: 0; margin-inline: auto; }
    .galaxy-mobile-hint { display: none; }
    .galaxy-desktop-hint { display: inline; }
    p.galaxy-desktop-hint { display: block; }
    .galaxy-details-grid { display: block; }
    .galaxy-position-card:not(.is-selected) { display: none; }
    .system-map-marker.is-current, .system-map-star.is-current { outline: 1px solid #67e8f9; }
    .system-map-marker:hover, .system-map-marker:focus-visible, .system-map-star:hover, .system-map-star:focus-visible { z-index: 2; }
    .system-map-marker:hover .system-map-tooltip, .system-map-marker:focus-visible .system-map-tooltip,
    .system-map-star:hover .system-map-tooltip, .system-map-star:focus-visible .system-map-tooltip {
        position: absolute;
        top: 100%;
        left: 50%;
        display: grid;
        width: 220px;
        max-height: 7rem;
        gap: .3rem;
        overflow-y: auto;
        overflow-wrap: anywhere;
        padding: .65rem;
        border: 1px solid #527187;
        background: #0c1929;
        color: #e2e8f0;
        font-size: .75rem;
        line-height: 1.4;
        text-align: left;
        box-shadow: 0 8px 20px #0009;
    }
    .system-map-marker .system-map-tooltip.tooltip-left { right: 50%; left: auto; }
    .system-map-marker .system-map-tooltip.tooltip-above { top: auto; bottom: 100%; }
    .system-map-marker .system-map-tooltip.tooltip-dismissed, .system-map-star .system-map-tooltip.tooltip-dismissed { display: none; }
}
</style>
