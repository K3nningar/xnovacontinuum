<!--
Author / Contributor : K3nningar
Code Version : 0.1.0-alpha.4
Revision : 34ddc84
Description : Renders the Index Inertia page.
-->

<script setup>
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import GameLayout from '../../Layouts/GameLayout.vue';
import UnitCatalog from '../../Components/UnitCatalog.vue';
const props = defineProps({ user: Object, universe: Object, planets: Array, selectedPlanetId: Number, units: Array, queue: Array });
const { t } = useI18n();
const planet = props.planets.find((item) => Number(item.id) === Number(props.selectedPlanetId)) ?? props.planets[0];
</script>
<template><Head :title="t('production.shipyardTitle')"/><GameLayout><main class="command-layout"><section class="command-content"><div class="command-heading"><div><p class="eyebrow"><span class="eyebrow-line"></span>{{ t('production.navalYard') }}</p><h1>{{ t('production.shipyardHeading') }}</h1><p class="command-subtitle">{{ t('production.planetShipyard', { planet: planet?.name }) }}</p></div></div><UnitCatalog :units="units" :queue="queue" :planet="planet" unit-type="ships" :title="t('production.shipsAndSatellites')"><template #image="{ unit, label }"><img :src="`/images/ships/${unit.code}.png`" :alt="label" loading="lazy" @error="$event.currentTarget.hidden = true" /></template></UnitCatalog></section></main></GameLayout></template>
