<!--
Author / Contributor : K3nningar
Code Version : 0.1.0-alpha.4
Revision : 34ddc84
Description : Renders the Index Inertia page.
-->

<script setup>
import { computed, watch } from 'vue';
import { Head, Link, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import GameLayout from '../../Layouts/GameLayout.vue';
import { translateUnitName } from '../../unitNames.js';
import { normalizeFleetMission } from '../../fleetMissionForm.js';

const props = defineProps({ user: Object, universe: Object, planets: Array, selectedPlanetId: Number, fleets: Array, ships: Array, moonFleet: { type: Object, default: () => ({}) }, moonOrbit: { type: Number, default: null }, missionConfig: Object, reports: { type: Array, default: () => [] } });
const page = usePage();
const { t, n } = useI18n();
usePoll(10000);
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const selectedPlanet = computed(() => props.planets.find((planet) => Number(planet.id) === Number(props.selectedPlanetId)) ?? props.planets[0]);
const fleet = computed(() => (props.ships ?? []).filter((ship) => ship.count > 0));
const selectedOrigin = selectedPlanet;
const initialCoordinates = selectedOrigin.value?.coordinates?.split(':').map(Number) ?? [1, 1, 2];
const initialFleetCounts = Object.fromEntries((props.ships ?? []).map((ship) => [ship.code, 0]));
const missionForm = useForm({ mission: 'transport', galaxy: initialCoordinates[0], system: initialCoordinates[1], position: 2, ships: initialFleetCounts, target_type: 'planet', moon_orbit: null, origin_target_type: 'planet', origin_moon_orbit: null, cargo: { metal: 0, crystal: 0, deuterium: 0 } });
const moonFleet = computed(() => missionForm.origin_target_type === 'moon' && Number(missionForm.origin_moon_orbit) === Number(props.moonOrbit) ? (props.moonFleet ?? {}) : {});
const shipNames = computed(() => Object.fromEntries([
    ...(props.ships ?? []),
    ...Object.keys(props.moonFleet ?? {}).map((code) => ({ code: Number(code) })),
].map((ship) => [ship.code, translateUnitName(ship.code, t)])));
const originShipName = (code) => translateUnitName(code, t);
const moonName = (orbit) => t('moonLogistics.moonTarget', { orbit });
const moonOptions = computed(() => selectedOrigin.value?.moons ?? []);
const originMoonSelected = computed(() => missionForm.origin_target_type === 'moon' ? moonOptions.value.find((moon) => Number(moon.orbit) === Number(missionForm.origin_moon_orbit)) : null);
const chosenTargetMoons = computed(() => selectedOrigin.value?.moons ?? []);
const selectedSendingShips = computed(() => {
    const available = missionForm.origin_target_type === 'moon'
        ? Object.entries(moonFleet.value).map(([code, count]) => ({ code: Number(code), count }))
        : fleet.value;
    if (missionForm.mission === 'destroy_moon') return available.filter((ship) => ship.code === 214 && ship.count > 0);
    if (missionForm.mission === 'destroy_planet') return available.filter((ship) => ship.code === 217 && ship.count > 0);
    if (missionForm.mission === 'spy' && missionForm.target_type !== 'moon') return available.filter((ship) => ship.code === 210 && ship.count > 0);
    if (missionForm.mission === 'recycle') return available.filter((ship) => ship.code === 209 && ship.count > 0);

    return available.filter((ship) => ship.count > 0);
});
const cargoCapacity = computed(() => Object.entries(missionForm.ships).reduce((sum, [code, count]) => sum + Number(count || 0) * ({ 202: 5000, 203: 25000, 204: 50, 205: 100, 206: 800, 207: 1500, 209: 20000, 211: 500, 213: 2000, 214: 1000000, 215: 750, 216: 10000 }[code] || 0), 0));
const cargoTotal = computed(() => Object.values(missionForm.cargo).reduce((sum, amount) => sum + Number(amount || 0), 0));
const format = (value) => n(Number(value ?? 0), 'game');
const formatDate = (value) => new Intl.DateTimeFormat(page.props.locale ?? 'fr', { dateStyle: 'short', timeStyle: 'medium' }).format(new Date(value));
const missionName = (mission) => mission === 'moon_deposit' ? t('moonLogistics.deposit') : mission === 'moon_withdraw' ? t('moonLogistics.withdraw') : t(`galaxyMissions.${mission}`);
const missionOptions = computed(() => (props.missionConfig?.missions ?? []).filter((mission) => {
    if (mission === 'destroy_planet') return props.missionConfig?.planetaryDestroyerAvailable;
    return true;
}));
const targetTypeOptions = computed(() => {
    if (missionForm.mission === 'destroy_moon') return ['planet', 'moon'];
    if (missionForm.mission === 'recycle') return ['asteroid', 'debris'];
    if (missionForm.mission === 'station') return ['depot', 'moon'];
    if (['transport', 'attack', 'spy', 'destroy_planet'].includes(missionForm.mission)) return ['planet', 'moon'];
    return ['planet'];
});
watch(() => props.selectedPlanetId, () => {
    const coordinates = selectedOrigin.value?.coordinates?.split(':').map(Number) ?? [1, 1, 2];
    missionForm.galaxy = coordinates[0];
    missionForm.system = coordinates[1];
    missionForm.ships = { ...initialFleetCounts };
    missionForm.origin_target_type = 'planet';
    missionForm.origin_moon_orbit = null;
});
function resetMissionShips() {
    missionForm.ships = missionForm.origin_target_type === 'moon' ? Object.fromEntries(Object.keys(props.moonFleet ?? {}).map((code) => [code, 0])) : { ...initialFleetCounts };
}
function changeMission(mission) {
    missionForm.mission = mission;
    missionForm.origin_target_type = 'planet';
    missionForm.origin_moon_orbit = null;
    missionForm.ships = { ...initialFleetCounts };
    missionForm.target_type = mission === 'recycle' ? 'debris' : mission === 'station' ? 'depot' : mission === 'destroy_moon' ? 'moon' : 'planet';
    if (mission === 'expedition') missionForm.position = 16;
    if (mission !== 'expedition' && missionForm.position === 16) missionForm.position = 2;
    missionForm.origin_target_type = 'planet';
    missionForm.origin_moon_orbit = null;
    missionForm.moon_orbit = null;
    missionForm.cargo = { metal: 0, crystal: 0, deuterium: 0 };
    missionForm.clearErrors();
    resetMissionShips();
}
function sendMission() {
    missionForm.transform(normalizeFleetMission).post(route('planet.missions.store', selectedOrigin.value.id));
}
const outcomeName = (outcome) => {
    if (outcome === 'ship_found') return t('galaxyShipsFound');
    if (outcome === 'early') return t('galaxyEarlyReturn');
    if (outcome === 'pirates' || outcome === 'aliens') return t(`galaxyEncounter.${outcome}`);
    if (outcome === 'moon_deposit' || outcome === 'moon_withdraw') return t(`moonLogistics.${outcome}`);
    return t(`galaxyMissions.${outcome}`);
};
/** Count down to arrival or, once returning, the return deadline (falling back for legacy missions). */
function timeLeft(mission) {
    const seconds = Math.max(0, Math.ceil((new Date(mission.status === 'returning' ? (mission.returnsAt ?? mission.arrivesAt) : mission.arrivesAt).getTime() - Date.now()) / 1000));
    return [Math.floor(seconds / 3600), Math.floor((seconds % 3600) / 60), seconds % 60].map((part) => String(part).padStart(2, '0')).join(':');
}
</script>
<template>
    <Head :title="t('fleet.title')" />
    <GameLayout>
        <main class="command-layout"><section class="command-content">
            <div class="command-heading"><div><p class="eyebrow"><span class="eyebrow-line"></span>{{ t('fleet.eyebrow') }}</p><h1>{{ t('fleet.heading') }}</h1><p class="command-subtitle">{{ t('fleet.planetOverview', { planet: selectedPlanet?.name }) }}</p></div><label class="planet-select-label" for="fleet-planet">{{ t('fleet.planet') }}<select id="fleet-planet" :value="selectedPlanetId" @change="router.get(route('fleet.index'), { planet: $event.target.value }, { preserveScroll: true })"><option v-for="planet in planets" :key="planet.id" :value="planet.id">{{ planet.name }} · {{ planet.coordinates }}</option></select></label></div>
            <form class="data-panel fleet-dispatch" @submit.prevent="sendMission">
                <div class="panel-heading fleet-dispatch-heading"><div><span class="panel-kicker">{{ t('galaxyMissions.prepare') }}</span><h2>{{ t('galaxyMissions.launchFromFleet') }}</h2></div><Link class="button button-outline" :href="route('galaxy.index', { planet: selectedOrigin?.id })">{{ t('fleet.openGalaxy') }}</Link>
                    <Link v-if="missionForm.origin_target_type === 'moon' && missionForm.origin_moon_orbit" class="button button-outline" :href="route('moon.show', { planet: selectedOrigin?.id, orbit: missionForm.origin_moon_orbit })">{{ t('galaxyMoon', { orbit: originMoonSelected?.orbit ?? missionForm.origin_moon_orbit, diameter: format(originMoonSelected?.diameter ?? 0) }) }}</Link></div>
                <div class="fleet-dispatch-body">
                    <section class="fleet-dispatch-target">
                        <label v-if="moonOptions.length" class="fleet-dispatch-field">{{ t('galaxyMissions.originMoon') }}<select v-model="missionForm.origin_target_type" @change="missionForm.origin_moon_orbit = null; resetMissionShips()"><option value="planet">{{ selectedOrigin?.name }}</option><option value="moon">{{ t('galaxyMissions.moonOnOrigin') }}</option></select></label>
                        <label v-if="missionForm.origin_target_type === 'moon'" class="fleet-dispatch-field">{{ t('galaxyMissions.moonOrbit') }}<select v-model.number="missionForm.origin_moon_orbit" @change="resetMissionShips"><option :value="null">{{ t('common.select') }}</option><option v-for="moon in moonOptions" :key="moon.orbit" :value="moon.orbit">{{ moonName(moon.orbit) }}</option></select></label>
                        <label class="fleet-dispatch-field fleet-dispatch-mission">{{ t('galaxyMissions.mission') }}<select :value="missionForm.mission" @change="changeMission($event.target.value)"><option v-for="mission in missionOptions" :key="mission" :value="mission">{{ t(`galaxyMissions.${mission}`) }}</option></select></label>
                        <label class="fleet-dispatch-field">{{ t('galaxy.galaxy') }}<input v-model.number="missionForm.galaxy" type="number" min="1" :max="universe?.galaxies ?? 9" required></label>
                        <label class="fleet-dispatch-field">{{ t('galaxy.system') }}<input v-model.number="missionForm.system" type="number" min="1" :max="universe?.systems_per_galaxy ?? 499" required></label>
                        <label class="fleet-dispatch-field">{{ t('galaxyMissions.position') }}<input v-model.number="missionForm.position" type="number" :min="missionForm.mission === 'expedition' ? 16 : 2" :max="missionForm.mission === 'expedition' ? 16 : 15" required></label>
                        <label v-if="targetTypeOptions.length > 1" class="fleet-dispatch-field">{{ t('galaxyMissions.targetType') }}<select v-model="missionForm.target_type" @change="missionForm.moon_orbit = null; missionForm.position = missionForm.mission === 'destroy_moon' || missionForm.target_type === 'moon' ? Number(selectedOrigin.coordinates.split(':')[2]) || 2 : missionForm.position"><option v-for="type in targetTypeOptions" :key="type" :value="type">{{ t(`galaxyMissions.targetTypes.${type}`) }}</option></select></label>
                        <label v-if="missionForm.target_type === 'moon' || missionForm.mission === 'destroy_moon'" class="fleet-dispatch-field">{{ t('galaxyMissions.moonOrbit') }}<select v-model.number="missionForm.moon_orbit" required><option :value="null">{{ t('common.select') }}</option><option v-for="moon in chosenTargetMoons" :key="moon.orbit" :value="moon.orbit">{{ moonName(moon.orbit) }}</option></select></label>
                    </section>
                    <section class="fleet-dispatch-composition">
                        <div class="fleet-dispatch-section-heading"><h3>{{ missionForm.origin_target_type === 'moon' ? t('moonLogistics.stationedFleet') : t('galaxyMissions.selectShips') }}</h3><span v-if="missionForm.origin_target_type !== 'moon'">{{ t('galaxyMissions.available', { count: format(fleet.reduce((total, ship) => total + ship.count, 0)) }) }}</span></div>
                        <div class="fleet-dispatch-ships">
                            <label v-for="ship in selectedSendingShips" :key="ship.code" class="fleet-dispatch-ship">
                                <img :src="`/images/ships/${ship.code}.png`" :alt="originShipName(ship.code)" loading="lazy">
                                <span class="fleet-dispatch-ship-info"><strong>{{ originShipName(ship.code) }}</strong><small>{{ missionForm.origin_target_type === 'moon' ? t('moonLogistics.stationedFleet') : t('galaxyMissions.available', { count: format(ship.count) }) }}</small></span>
                                <input v-model.number="missionForm.ships[ship.code]" type="number" min="0" :max="ship.count" step="1" :aria-label="originShipName(ship.code)">
                            </label>
                        </div>
                    </section>
                    <section v-if="missionForm.mission === 'transport'" class="fleet-dispatch-cargo">
                        <div class="fleet-dispatch-section-heading"><h3>{{ t('common.resources') }}</h3><span>{{ t('galaxyMissions.cargoCapacity', { used: format(cargoTotal), capacity: format(cargoCapacity) }) }}</span></div>
                        <div class="fleet-dispatch-cargo-fields"><label v-for="resource in ['metal', 'crystal', 'deuterium']" :key="resource" class="fleet-dispatch-field">{{ t(`common.${resource}`) }}<input v-model.number="missionForm.cargo[resource]" type="number" min="0" max="1000000000" step="1"></label></div>
                    </section>
                    <div class="fleet-dispatch-footer">
                        <div><p v-for="(error, key) in missionForm.errors" :key="key" role="alert" class="fleet-dispatch-error">{{ error }}</p><p v-if="!missionForm.errors || !Object.keys(missionForm.errors).length" class="fleet-dispatch-origin">{{ selectedOrigin?.name }} · {{ selectedOrigin?.coordinates }}</p></div>
                        <button class="button button-primary" type="submit" :disabled="missionForm.processing || !selectedSendingShips.length || (missionForm.mission === 'station' && !missionConfig?.hasAlliance) || (missionForm.mission === 'destroy_planet' && !missionConfig?.planetaryDestroyerAvailable)">{{ missionForm.processing ? t('galaxyMissions.sending') : t('galaxyMissions.send') }}</button>
                    </div>
                </div>
            </form>
            <section class="command-panels fleet-panels">
                <section class="data-panel"><div class="panel-heading"><div><span class="panel-kicker">{{ missionForm.origin_target_type === 'moon' ? t('moonLogistics.stationedFleet') : t('fleet.stationed') }}</span><h2>{{ t('fleet.ships') }}</h2></div><span class="panel-count">{{ missionForm.origin_target_type === 'moon' ? Object.keys(moonFleet).length : fleet.length }}</span></div><article v-for="ship in missionForm.origin_target_type === 'moon' ? Object.entries(moonFleet).map(([code, count]) => ({ code: Number(code), count })) : fleet" :key="ship.code" class="fleet-inventory-row"><img :src="`/images/ships/${ship.code}.png`" :alt="translateUnitName(ship.code, t)" loading="lazy"><span><strong>{{ translateUnitName(ship.code, t) }}</strong><small>{{ t('fleet.stationedUnit') }}</small></span><strong class="fleet-count">{{ format(ship.count) }}</strong></article><div v-if="!(missionForm.origin_target_type === 'moon' ? Object.keys(moonFleet).length : fleet.length)" class="empty-state compact"><span class="empty-orb">➤</span><strong>{{ t('fleet.noShips') }}</strong><p>{{ t('fleet.produceShips') }}</p><Link v-if="missionForm.origin_target_type !== 'moon'" class="button button-primary" :href="route('shipyard.index', { planet: selectedPlanet?.id })">{{ t('fleet.openShipyard') }} →</Link></div></section>
                <section class="data-panel"><div class="panel-heading"><div><span class="panel-kicker">{{ t('fleet.missions') }}</span><h2>{{ t('fleet.inFlight') }}</h2></div><span class="panel-count">{{ fleets.length }}</span></div><article v-for="mission in fleets" :key="mission.id" class="fleet-mission-row"><span class="mission-orbit">➤</span><span><strong>{{ missionName(mission.mission) }}</strong><small>{{ mission.originName || t('fleet.unknownOrigin') }} [{{ mission.origin || '—' }}]{{ mission.originOrbit ? ` · ${moonName(mission.originOrbit)}` : '' }} → {{ mission.targetName || t('fleet.unknownPosition') }} [{{ mission.target || '—' }}]{{ mission.targetOrbit ? ` · ${moonName(mission.targetOrbit)}` : mission.moonOrbit ? ` · ${moonName(mission.moonOrbit)}` : '' }} · {{ mission.status === 'returning' ? t('overview.returning') : t('overview.outbound') }}</small></span><time :datetime="mission.status === 'returning' ? (mission.returnsAt ?? mission.arrivesAt) : mission.arrivesAt">{{ timeLeft(mission) }}</time><small>{{ t('units.endsAt', { date: formatDate(mission.status === 'returning' ? (mission.returnsAt ?? mission.arrivesAt) : mission.arrivesAt) }) }}</small></article><div v-if="!fleets.length" class="empty-state compact"><span class="empty-orb">⌖</span><strong>{{ t('fleet.noMissions') }}</strong></div></section>
            </section>
            <section v-if="reports.length" class="data-panel mt-4">
                <div class="panel-heading"><h2>{{ t('galaxyMissions.reports') }}</h2></div>
                <article v-for="report in reports" :key="report.id" class="border-b border-slate-700 p-4 text-sm">
                    <strong>{{ outcomeName(report.outcome) }}</strong>
                    <p v-if="report.moonOrder">{{ t('moonLogistics.moonTarget', { orbit: report.moonOrbit }) }} · {{ t(`common.${report.moonOrder.resource}`) }} : {{ format(report.moonOrder.amount) }}</p>
                    <p>{{ t('common.metal') }} {{ format(report.cargo.metal) }} · {{ t('common.crystal') }} {{ format(report.cargo.crystal) }} · {{ t('common.deuterium') }} {{ format(report.cargo.deuterium) }}</p>
                    <p><span v-for="(count, code) in report.ships" :key="code">{{ translateUnitName(code, t) }} : {{ format(count) }} · </span></p>
                    <details v-if="report.combat" class="mt-2">
                        <summary class="cursor-pointer text-cyan-300">{{ t('galaxyEncounter.report') }}</summary>
                        <p>{{ t('combat.attacker') }} · {{ t('combat.losses') }} : {{ format(report.combat.attacker.totalLosses) }} / {{ format(report.combat.attacker.totalInitial) }}</p>
                        <p>{{ t('combat.defender') }} · {{ t('combat.losses') }} : {{ format(report.combat.defender.totalLosses) }} / {{ format(report.combat.defender.totalInitial) }}</p>
                        <div v-for="round in report.combat.rounds" :key="round.number" class="mt-2 border-t border-slate-700 pt-2">
                            <strong>{{ t('combat.roundNumber', { number: round.number }) }}</strong>
                            <p>{{ t('combat.attacker') }} · {{ t('combat.shotCount', { count: format(round.attacker.shots) }) }} · {{ t('combat.lossCount', { count: format(round.attacker.losses) }) }}</p>
                            <p>{{ t('combat.defender') }} · {{ t('combat.shotCount', { count: format(round.defender.shots) }) }} · {{ t('combat.lossCount', { count: format(round.defender.losses) }) }}</p>
                        </div>
                    </details>
                </article>
            </section>
        </section></main>
    </GameLayout>
</template>