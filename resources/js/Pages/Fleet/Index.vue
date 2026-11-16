<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import GameLayout from '../../Layouts/GameLayout.vue';

const props = defineProps({ user: Object, universe: Object, planets: Array, selectedPlanetId: Number, fleets: Array, ships: Array, reports: { type: Array, default: () => [] } });
const page = usePage();
const { t, n } = useI18n();
usePoll(10000);
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const selectedPlanet = computed(() => props.planets.find((planet) => Number(planet.id) === Number(props.selectedPlanetId)) ?? props.planets[0]);
const fleet = computed(() => (props.ships ?? []).filter((ship) => ship.count > 0));
const format = (value) => n(Number(value ?? 0), 'game');
const formatDate = (value) => new Intl.DateTimeFormat(page.props.locale ?? 'fr', { dateStyle: 'short', timeStyle: 'medium' }).format(new Date(value));
const missionName = (mission) => mission === 'recycle' ? t('galaxyMissions.recycle') : mission === 'expedition' ? t('galaxyMissions.explore') : mission === 'moon_deposit' ? t('moonLogistics.deposit') : mission === 'moon_withdraw' ? t('moonLogistics.withdraw') : mission;
const outcomeName = (outcome) => {
    if (outcome === 'ship_found') return t('galaxyShipsFound');
    if (outcome === 'early') return t('galaxyEarlyReturn');
    if (outcome === 'pirates' || outcome === 'aliens') return t(`galaxyEncounter.${outcome}`);
    if (outcome === 'moon_deposit' || outcome === 'moon_withdraw') return t(`moonLogistics.${outcome}`);
    return t(`galaxyMissions.${outcome}`);
};
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
            <section class="command-panels fleet-panels">
                <section class="data-panel"><div class="panel-heading"><div><span class="panel-kicker">{{ t('fleet.stationed') }}</span><h2>{{ t('fleet.ships') }}</h2></div><span class="panel-count">{{ fleet.length }}</span></div><article v-for="ship in fleet" :key="ship.code" class="fleet-inventory-row"><img :src="`/images/ships/${ship.code}.png`" :alt="t('fleet.ship', { code: ship.code })" loading="lazy"><span><strong>{{ t('fleet.ship', { code: ship.code }) }}</strong><small>{{ t('fleet.stationedUnit') }}</small></span><strong class="fleet-count">{{ format(ship.count) }}</strong></article><div v-if="!fleet.length" class="empty-state compact"><span class="empty-orb">➤</span><strong>{{ t('fleet.noShips') }}</strong><p>{{ t('fleet.produceShips') }}</p><Link class="button button-primary" :href="route('shipyard.index', { planet: selectedPlanet?.id })">{{ t('fleet.openShipyard') }} →</Link></div></section>
                <section class="data-panel"><div class="panel-heading"><div><span class="panel-kicker">{{ t('fleet.missions') }}</span><h2>{{ t('fleet.inFlight') }}</h2></div><span class="panel-count">{{ fleets.length }}</span></div><article v-for="mission in fleets" :key="mission.id" class="fleet-mission-row"><span class="mission-orbit">➤</span><span><strong>{{ missionName(mission.mission) }}</strong><small>{{ mission.originName || t('fleet.unknownOrigin') }} [{{ mission.origin || '—' }}] → {{ mission.moonOrbit ? t('moonLogistics.moonTarget', { orbit: mission.moonOrbit }) : (mission.targetName || t('fleet.unknownPosition')) }} [{{ mission.target || '—' }}] · {{ mission.status === 'returning' ? t('overview.returning') : t('overview.outbound') }}</small></span><time :datetime="mission.status === 'returning' ? (mission.returnsAt ?? mission.arrivesAt) : mission.arrivesAt">{{ timeLeft(mission) }}</time><small>{{ t('units.endsAt', { date: formatDate(mission.status === 'returning' ? (mission.returnsAt ?? mission.arrivesAt) : mission.arrivesAt) }) }}</small></article><div v-if="!fleets.length" class="empty-state compact"><span class="empty-orb">⌖</span><strong>{{ t('fleet.noMissions') }}</strong></div></section>
            </section>
            <section v-if="reports.length" class="data-panel mt-4">
                <div class="panel-heading"><h2>{{ t('galaxyMissions.reports') }}</h2></div>
                <article v-for="report in reports" :key="report.id" class="border-b border-slate-700 p-4 text-sm">
                    <strong>{{ outcomeName(report.outcome) }}</strong>
                    <p v-if="report.moonOrder">{{ t('moonLogistics.moonTarget', { orbit: report.moonOrbit }) }} · {{ t(`common.${report.moonOrder.resource}`) }} : {{ format(report.moonOrder.amount) }}</p>
                    <p>{{ t('common.metal') }} {{ format(report.cargo.metal) }} · {{ t('common.crystal') }} {{ format(report.cargo.crystal) }} · {{ t('common.deuterium') }} {{ format(report.cargo.deuterium) }}</p>
                    <p><span v-for="(count, code) in report.ships" :key="code">{{ t('fleet.ship', { code }) }} : {{ format(count) }} · </span></p>
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