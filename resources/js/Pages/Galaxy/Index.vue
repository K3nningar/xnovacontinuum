<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import GameLayout from '../../Layouts/GameLayout.vue';

const props = defineProps({
    planets: { type: Array, required: true },
    selectedPlanetId: { type: Number, required: true },
    galaxy: { type: Number, required: true },
    system: { type: Number, required: true },
    galaxyLimit: { type: Number, required: true },
    systemLimit: { type: Number, required: true },
    spyLevel: { type: Number, required: true },
    star: { type: String, required: true },
    slots: { type: Array, required: true },
    availableShips: { type: Array, required: true },
    fleetSpeed: { type: Number, required: true },
    combatLimit: { type: Number, required: true },
});
const page = usePage();
const { t, n } = useI18n();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const targetGalaxy = ref(props.galaxy);
const targetSystem = ref(props.system);
const originPlanet = ref(props.selectedPlanetId);
const missionForm = useForm({ mission: '', galaxy: props.galaxy, system: props.system, position: 16, ships: {} });
const sendingShips = computed(() => props.availableShips.filter((ship) => ship.count > 0 && (missionForm.mission !== 'recycle' || ship.code === 209)));
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

function slotLabel(slot) {
    const position = t('galaxy.position', { position: slot.position });
    if (!slot.visible) return `${position} · ${t('galaxy.hidden')} · ${t('galaxy.required', { level: slot.requiredSpyLevel })}`;
    const body = slot.kind === 'planet'
        ? t('galaxy.planetType', { type: t(`galaxy.types.${slot.type}`) })
        : t('galaxy.asteroid', { type: t(`galaxy.types.${slot.type}`) });
    const owner = slot.kind === 'planet' && slot.planet?.owned ? ` · ${t('galaxy.own')}` : '';
    return `${position} · ${body}${owner}`;
}

function prepareMission(mission, position) {
    missionForm.mission = mission;
    missionForm.galaxy = props.galaxy;
    missionForm.system = props.system;
    missionForm.position = position;
    missionForm.ships = Object.fromEntries(props.availableShips.map((ship) => [ship.code, 0]));
    missionForm.clearErrors();
}

function sendMission() {
    missionForm.post(route('planet.missions.store', props.selectedPlanetId));
}

watch(() => [props.galaxy, props.system, props.selectedPlanetId], ([galaxy, system, planet]) => {
    targetGalaxy.value = galaxy;
    targetSystem.value = system;
    originPlanet.value = planet;
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

                <section class="data-panel system-map-panel mb-4" aria-labelledby="system-map-title">
                    <div class="panel-heading">
                        <div>
                            <span class="panel-kicker">{{ t('galaxy.galaxy') }} {{ galaxy }} · {{ t('galaxy.system') }} {{ system }}</span>
                            <h2 id="system-map-title">{{ t('galaxyMap.title') }}</h2>
                        </div>
                    </div>
                    <p class="px-4 pt-3 text-sm text-slate-400">{{ t('galaxyMap.hint') }}</p>
                    <div class="system-map-scroll" tabindex="0" role="region" :aria-label="t('galaxyMap.title')">
                        <div class="system-map">
                            <svg class="system-map-orbits" viewBox="0 0 960 640" aria-hidden="true">
                                <ellipse v-for="slot in slots" :key="slot.position" cx="480" cy="320" :rx="orbitRadius(slot.position).x" :ry="orbitRadius(slot.position).y" />
                            </svg>
                            <a class="system-map-star" :class="`star-${star}`" href="#galaxy-position-1" :aria-label="`${t('galaxy.position', { position: 1 })} · ${t('galaxy.star')} · ${t(`galaxy.types.${star}`)}`" :title="t(`galaxy.types.${star}`)">
                                <span class="system-map-star-core"></span><span class="system-map-number">1</span>
                            </a>
                            <a v-for="slot in slots" :key="slot.position" class="system-map-marker" :class="!slot.visible ? 'body-hidden' : slot.kind === 'asteroid' ? `body-asteroid body-${slot.type}` : [`body-planet`, `body-${slot.type}`, { 'body-owned': slot.planet?.owned }]" :style="markerStyle(slot.position)" :href="`#galaxy-position-${slot.position}`" :aria-label="slotLabel(slot)" :title="slotLabel(slot)">
                                <span class="system-map-body" aria-hidden="true"></span>
                                <span class="system-map-number">{{ slot.position }}</span>
                            </a>
                            <a class="system-map-expedition" href="#galaxy-position-16" :aria-label="`${t('galaxy.position', { position: 16 })} · ${t('galaxy.expedition')}`">
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

                <section id="galaxy-position-1" class="data-panel">
                    <div class="panel-heading"><div><span class="panel-kicker">{{ t('galaxy.galaxy') }} {{ galaxy }} · {{ t('galaxy.system') }} {{ system }} · {{ t('galaxy.position', { position: 1 }) }}</span><h2>{{ t('galaxy.star') }} · {{ t(`galaxy.types.${star}`) }}</h2></div><span class="text-xl text-amber-300" aria-hidden="true">✦</span></div>
                    <div class="grid grid-cols-1 gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
                        <article v-for="slot in slots" :id="`galaxy-position-${slot.position}`" :key="slot.position" class="border border-slate-700 bg-slate-950/40 p-4" :class="{ 'opacity-60': !slot.visible }">
                            <p class="text-sm text-slate-400">{{ t('galaxy.position', { position: slot.position }) }}</p>
                            <template v-if="!slot.visible">
                                <h3 class="mt-2 font-semibold text-slate-400">{{ t('galaxy.hidden') }}</h3>
                                <p class="mt-2 text-sm text-slate-400">{{ t('galaxy.required', { level: slot.requiredSpyLevel }) }}</p>
                            </template>

                            <template v-else-if="slot.kind === 'planet'">
                                <h3 class="mt-2 font-semibold text-cyan-300">{{ t('galaxy.planetType', { type: t(`galaxy.types.${slot.type}`) }) }}</h3>
                                <p class="mt-2 text-sm">{{ slot.planet ? (slot.planet.owned ? t('galaxy.own') : t('galaxy.owner', { name: slot.planet.owner })) : t('galaxy.unoccupied') }}<template v-if="slot.planet"> · {{ slot.planet.name }}</template></p>
                                <p class="mt-2 text-sm text-slate-400">{{ t('galaxy.moons', { count: slot.moons }) }}</p>
                                <ul v-if="slot.naturalMoons.length" class="mt-1 text-sm text-slate-400"><li v-for="moon in slot.naturalMoons" :key="moon.orbit"><Link v-if="slot.planet?.owned" class="text-cyan-300 underline" :href="route('moon.show', { planet: slot.planet.id, orbit: moon.orbit })">{{ t('galaxyMoon', { orbit: moon.orbit, diameter: n(moon.diameter, 'game') }) }}</Link><template v-else>{{ t('galaxyMoon', { orbit: moon.orbit, diameter: n(moon.diameter, 'game') }) }}</template></li></ul>
                                <p class="mt-2 text-sm text-slate-400">{{ t('galaxy.potential') }} : ▰ {{ slot.resources.metal }}% · ◇ {{ slot.resources.crystal }}% · ⬡ {{ slot.resources.deuterium }}%</p>
                            </template>
                            <template v-else>
                                <h3 class="mt-2 font-semibold text-amber-300">{{ t('galaxy.asteroid', { type: t(`galaxy.types.${slot.type}`) }) }}</h3>
                                <p class="mt-2 text-sm">{{ t('galaxy.concentration', { value: slot.concentration }) }}</p>
                                <p class="mt-2 text-sm text-slate-400">{{ t('common.metal') }} {{ n(slot.remaining.metal, 'game') }} · {{ t('common.crystal') }} {{ n(slot.remaining.crystal, 'game') }} · {{ t('common.deuterium') }} {{ n(slot.remaining.deuterium, 'game') }}</p>
                                <button class="button button-outline mt-3" type="button" @click="prepareMission('recycle', slot.position)">{{ t('galaxyMissions.recycle') }}</button>
                            </template>
                        </article>
                        <article id="galaxy-position-16" class="border border-slate-700 bg-slate-950/40 p-4">
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
                    <p class="mt-2 text-sm">{{ t('galaxyMissions.selectShips') }}</p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <label v-for="ship in sendingShips" :key="ship.code" class="flex flex-col gap-1 text-sm">
                            {{ t('fleet.ship', { code: ship.code }) }} · {{ t('galaxyMissions.available', { count: ship.count }) }}
                            <input v-model.number="missionForm.ships[ship.code]" type="number" min="0" :max="ship.count" step="1" class="w-32 rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white">
                        </label>
                    </div>
                    <p class="mt-3 text-sm">{{ t('galaxyMissions.fuel', { fuel }) }} · {{ t('galaxyMissions.flight', { seconds: flight }) }}</p>
                    <p v-if="missionForm.mission === 'expedition'" class="mt-2 text-sm">{{ t('galaxyMissions.expeditionTime') }}</p>
                    <p v-if="missionForm.mission === 'expedition'" class="mt-2 text-sm text-slate-400">{{ t('galaxyCombatLimit', { limit: combatLimit }) }}</p>
                    <p v-for="(error, key) in missionForm.errors" :key="key" role="alert" class="mt-2 text-sm text-red-300">{{ error }}</p>
                    <div class="mt-3 flex gap-3"><button class="button button-primary" type="submit" :disabled="missionForm.processing || !sendingShips.length">{{ missionForm.processing ? t('galaxyMissions.sending') : t('galaxyMissions.send') }}</button><button class="button button-outline" type="button" @click="missionForm.mission = ''">{{ t('common.cancel') }}</button></div>
                </form>
            </section>
        </main>
    </GameLayout>
</template>

<style scoped>
.galaxy-search, .system-map-panel { min-height: 0; }
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
</style>
