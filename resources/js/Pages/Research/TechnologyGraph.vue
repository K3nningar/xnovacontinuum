<!--
Author / Contributor : K3nningar
Code Version : 0.1.1-alpha
Revision : 34ddc84
Description : Renders the TechnologyGraph Inertia page.
-->

<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import GameLayout from '../../Layouts/GameLayout.vue';
import TechnologyTreeGraph from '../../Components/TechnologyTreeGraph.vue';

const props = defineProps({
    user: { type: Object, required: true },
    universe: { type: Object, default: null },
    planets: { type: Array, default: () => [] },
    selectedPlanetId: { type: Number, required: true },
    activeResearch: { type: Object, default: null },
    treeElements: { type: Array, default: () => [] },
});
const page = usePage();
const { t, n } = useI18n();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const planet = computed(() => props.planets.find((item) => Number(item.id) === Number(props.selectedPlanetId)) ?? props.planets[0]);
const count = computed(() => props.treeElements.length);
function format(value) { return n(Number(value ?? 0), 'game'); }
</script>

<template>
    <Head :title="t('nav.technologyTree')" />
    <GameLayout>
        <main class="command-layout">
            <section class="command-content research-content technology-graph-page">
                <div class="command-heading technology-tree-heading">
                    <div>
                        <p class="eyebrow"><span class="eyebrow-line"></span>{{ t('research.eyebrow') }}</p>
                        <h1>{{ t('nav.technologyTree') }}</h1>
                        <p class="command-subtitle">{{ t('technologyTree.subtitle') }}</p>
                        <Link class="button button-outline" :href="route('research.index', { planet: planet.id })">{{ t('research.study') }}</Link>
                    </div>
                    <label class="research-planet-picker" for="technology-tree-planet">
                        <span>{{ t('research.activePlanet') }}</span>
                        <select id="technology-tree-planet" :value="selectedPlanetId" @change="router.get(route('research.tree'), { planet: $event.target.value }, { preserveState: true, preserveScroll: true })">
                            <option v-for="item in planets" :key="item.id" :value="item.id">{{ item.name }} · {{ item.coordinates }}</option>
                        </select>
                    </label>
                </div>
                <div class="research-resource-strip">
                    <span><strong>{{ planet.name }}</strong> · {{ t('research.laboratory', { level: planet.laboratory_level }) }}</span>
                    <span class="metal-symbol">▰ {{ format(planet.metal) }}</span>
                    <span class="crystal-symbol">◇ {{ format(planet.crystal) }}</span>
                    <span class="deut-symbol">⬡ {{ format(planet.deuterium) }}</span>
                    <span class="energy-positive">ϟ {{ format(planet.energy) }} {{ t('common.energy').toLowerCase() }}</span>
                </div>
                <section class="research-section technology-tree-section">
                    <div class="buildings-section-heading">
                        <div><span class="panel-kicker">{{ t('technologyTree.catalog') }}</span><h2>{{ t('technologyTree.elements', { count }) }}</h2></div>
                        <span class="panel-count">{{ count }}</span>
                    </div>
                    <TechnologyTreeGraph :elements="treeElements" :active-research="activeResearch" />
                </section>
            </section>
        </main>
    </GameLayout>
</template>
