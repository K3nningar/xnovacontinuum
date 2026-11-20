<!--
Author / Contributor : K3nningar
Code Version : 0.1.1-alpha
Revision : 34ddc84
Description : Renders the Index Inertia page.
-->

<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import GameLayout from '../../Layouts/GameLayout.vue';
import ElementInfoButton from '../../Components/ElementInfoButton.vue';
import RarityBadge from '../../Components/RarityBadge.vue';
import CatalogGrid from '../../Components/CatalogGrid.vue';
import { catalogCategories, matchesCatalogCategory, researchFilters } from '../../catalogFilters.js';
import infosFr from '../../i18n/infos-fr.json';

const props = defineProps({ user: Object, universe: Object, planets: Array, selectedPlanetId: Number, researches: Array, activeResearch: Object });
const page = usePage();
const { t, n, locale } = useI18n();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const planet = computed(() => props.planets.find((item) => Number(item.id) === Number(props.selectedPlanetId)) ?? props.planets[0]);
const form = useForm({ technology: '' });
const selectedCategory = ref('all');
const categories = computed(() => catalogCategories(props.researches, researchFilters));
const visibleCount = computed(() => props.researches.filter((item) => matchesCatalogCategory(item, selectedCategory.value, categories.value)).length);
const labelKeys = { hyperspace_drive: 'hyperspaceDrive' };
const icons = { spy: '◎', computer: '▦', military: '⌁', defence: '⬡', shield: '◇', energy: 'ϟ', hyperspace: '✧', combustion: '➤', impulse: '➟', hyperspaceDrive: '✦', laser: '⊙', ion: '⌇', plasma: '✺', intergalactic: '⌘', expedition: '⌖', graviton: '◉' };
const details = computed(() => Object.fromEntries(props.researches.map((item) => { const key = labelKeys[item.key] ?? item.key; return [item.key, { name: t('research.' + key), description: t('research.' + key + 'Desc') + (key === 'spy' ? ' ' + t('galaxy.sensorsDesc') : ''), icon: icons[key] ?? '⌘' }]; })));
const format = (value) => n(Number(value ?? 0), 'game');
const date = (value) => new Intl.DateTimeFormat(locale.value, { dateStyle: 'short', timeStyle: 'medium' }).format(new Date(value));
function duration(seconds) { const h=Math.floor(seconds/3600), m=Math.floor((seconds%3600)/60), s=seconds%60; return [h,m,s].map(v=>String(v).padStart(2,'0')).join(':'); }
/** Post the selected technology key to the currently selected planet's research queue. */
function start(item) { form.technology=item.key; form.post(route('planet.researches.store', planet.value.id), { preserveScroll:true }); }
function cancel() { router.delete(route('researches.destroy'), { preserveScroll: true }); }
</script>

<template>
    <Head :title="t('research.title')" />
    <GameLayout>
        <main class="command-layout"><section class="command-content research-content">
            <div v-if="Object.keys(page.props.errors || {}).length" class="form-alert" role="alert">{{ Object.values(page.props.errors)[0] }}</div>
            <div class="command-heading"><div><p class="eyebrow"><span class="eyebrow-line"></span>{{ t('research.eyebrow') }}</p><h1>{{ t('research.heading') }}</h1><p class="command-subtitle">{{ t('research.subtitle') }}</p><Link class="button button-outline" :href="route('research.tree', { planet: planet.id })">{{ t('nav.technologyTree') }}</Link></div><label class="research-planet-picker" for="research-planet"><span>{{ t('research.activePlanet') }}</span><select id="research-planet" :value="planet.id" @change="router.get(route('research.index'), { planet: $event.target.value }, { preserveState: true, preserveScroll: true })"><option v-for="item in planets" :key="item.id" :value="item.id">{{ item.name }} · {{ item.coordinates }}</option></select></label></div>
            <div class="research-resource-strip"><span><strong>{{ planet.name }}</strong> · {{ t('research.laboratory', { level: planet.laboratory_level }) }}</span><span class="metal-symbol">▰ {{ format(planet.metal) }}</span><span class="crystal-symbol">◇ {{ format(planet.crystal) }}</span><span class="deut-symbol">⬡ {{ format(planet.deuterium) }}</span><span class="energy-positive">ϟ {{ format(planet.energy) }} {{ t('common.energy').toLowerCase() }}</span></div>
            <section v-if="activeResearch" class="research-active-panel"><div class="research-active-icon">⌘</div><div class="research-active-details"><span class="panel-kicker">{{ t('research.inProgress', { level: activeResearch.targetLevel }) }}</span><h2>{{ activeResearch.technologyKey === 'sensors' ? t('galaxy.sensors') : details[activeResearch.technologyKey]?.name }}</h2><p>{{ t('research.laboratoryOf', { planet: activeResearch.planetName, date: date(activeResearch.completesAt) }) }}</p><p v-if="activeResearch.technologyKey === 'sensors'">{{ t('galaxyLegacySensors') }}</p><small>{{ t('research.remaining', { time: duration(activeResearch.remainingSeconds) }) }}</small></div><button class="button button-outline research-cancel" @click="cancel">{{ t('research.cancelRefund') }}</button></section>
            <section class="data-panel research-catalog">
                <div class="panel-heading"><div><span class="panel-kicker">{{ t('research.available') }}</span><h2>{{ t('research.study') }}</h2></div><span class="panel-count">{{ visibleCount }} / {{ researches.length }}</span></div>
                <CatalogGrid v-model="selectedCategory" :items="researches" :groups="researchFilters" class="catalog-pos-wrap">
                    <article v-for="item in researches" v-show="matchesCatalogCategory(item, selectedCategory, categories)" :key="item.key" class="card building-pos-card" :class="`rarity-${item.rarity}`">
                        <div class="pos-product">
                            <div class="img building-pos-image">
                                <img :src="`/images/laboratory/${item.code}.png`" :alt="details[item.key].name" loading="lazy" @error="$event.currentTarget.hidden = true">
                                <span class="building-image-fallback" aria-hidden="true">{{ details[item.key].icon }}</span>
                                <RarityBadge class="building-rarity" :rarity="item.rarity" />
                                <span class="building-level">{{ t('common.level') }} {{ item.level }}</span>
                            </div>
                            <div class="building-pos-body">
                                <div class="building-title"><span class="building-code">{{ item.code }}</span><h3>{{ details[item.key].name }}</h3><ElementInfoButton :id="'research_' + item.code" :name="details[item.key].name" :description="item.key === 'spy' ? infosFr['research_106'].description + ' ' + t('galaxy.sensorsDesc') : infosFr['research_' + item.code]?.description" :image-src="`/images/laboratory/${item.code}.png`" /></div>
                                <div class="building-main">
                                    <p>{{ details[item.key].description }}</p>
                                    <div class="research-requirements"><span v-for="req in item.requirements" :key="req.key" :class="{ 'requirement-met': req.current >= req.required }">{{ t('researchLabels.' + req.key) }} {{ req.current }}/{{ req.required }}</span><span v-if="!item.requirements.length" class="requirement-met">{{ t('common.none') }}</span></div>
                                </div>
                                <div class="building-action">
                                    <div class="building-cost"><span>{{ t('research.nextLevel', { level: item.targetLevel }) }}</span><strong class="metal-symbol">▰ {{ format(item.cost.metal) }}</strong><strong class="crystal-symbol">◇ {{ format(item.cost.crystal) }}</strong><strong class="deut-symbol">⬡ {{ format(item.cost.deuterium) }}</strong><strong v-if="item.cost.energy" class="energy-positive">ϟ {{ format(item.cost.energy) }}</strong></div>
                                    <div class="research-card-footer"><span>{{ t('research.duration') }} <strong>{{ duration(item.duration) }}</strong></span><button class="button button-primary research-button" :disabled="!item.canResearch || form.processing" @click="start(item)">{{ form.processing ? t('research.launching') : activeResearch ? t('research.activeExists') : !item.requirementsMet ? t('common.missingRequirements') : !item.hasResources ? t('common.resourcesMissing') : t('research.start', { level: item.targetLevel }) }}</button></div>
                                </div>
                            </div>
                        </div>
                    </article>
                </CatalogGrid>
            </section>
        </section></main>
    </GameLayout>
</template>