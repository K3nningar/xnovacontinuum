<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import GameLayout from '../../Layouts/GameLayout.vue';

const props = defineProps({ planet: Object, moon: Object, fleets: Array });
const page = usePage();
const { t, n } = useI18n();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const format = (value) => n(Number(value ?? 0), 'game');
const time = (value) => new Intl.DateTimeFormat(page.props.locale ?? 'fr', { dateStyle: 'short', timeStyle: 'medium' }).format(new Date(value));
const visibleFleets = computed(() => (props.fleets ?? []).filter((fleet) => fleet.status !== 'completed'));
const unitNames = { 202: 'small_cargo', 203: 'large_cargo', 204: 'light_fighter', 205: 'heavy_fighter', 206: 'cruiser', 207: 'battleship', 208: 'colony_ship', 209: 'recycler', 210: 'espionage_probe', 211: 'bomber', 212: 'solar_satellite', 213: 'destroyer', 214: 'deathstar', 215: 'battlecruiser', 216: 'supernova', 217: 'planetary_destroyer' };
const shipName = (code) => unitNames[code] ? t(`unitNames.${unitNames[code]}`) : t('fleet.ship', { code });
</script>

<template>
    <Head :title="t('moonLogistics.phalanx')" />
    <GameLayout>
        <main class="command-layout"><section class="command-content">
            <div class="command-heading"><div><p class="eyebrow">{{ t('moonLogistics.phalanx') }}</p><h1>{{ t('moonLogistics.scan') }}</h1><p class="command-subtitle">{{ t('galaxyMoon', { orbit: moon.orbit, diameter: format(moon.diameter) }) }} · {{ planet.name }} [{{ planet.coordinates }}]</p></div><Link class="button button-outline" :href="route('moon.show', { planet: planet.id, orbit: moon.orbit })">{{ t('moonLogistics.back') }}</Link></div>
            <section class="data-panel">
                <div class="panel-heading"><h2>{{ t('moonLogistics.detectedFleets') }}</h2><span class="panel-count">{{ visibleFleets.length }}</span></div>
                <article v-for="fleet in visibleFleets" :key="fleet.id" class="fleet-mission-row border-b border-slate-700 p-4">
                    <strong>{{ t(`galaxyMissions.${fleet.mission}`) }}</strong>
                    <p>{{ fleet.origin || '—' }}{{ fleet.originOrbit ? ` · ${t('moonLogistics.moonTarget', { orbit: fleet.originOrbit })}` : '' }} → {{ fleet.target || '—' }}{{ fleet.targetOrbit ? ` · ${t('moonLogistics.moonTarget', { orbit: fleet.targetOrbit })}` : '' }} · {{ t(`overview.${fleet.status === 'returning' ? 'returning' : 'outbound'}`) }}</p>
                    <p>{{ t('galaxyMissions.selectShips') }}: <span v-for="(count, code) in fleet.ships" :key="code">{{ shipName(code) }} {{ format(count) }} </span></p>
                    <time>{{ time(fleet.arrivesAt) }}</time>
                </article>
                <p v-if="!visibleFleets.length" class="p-5 text-sm text-slate-400">{{ t('moonLogistics.noFleets') }}</p>
            </section>
        </section></main>
    </GameLayout>
</template>
