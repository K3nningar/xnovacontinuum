<!--
Author / Contributor : K3nningar
Code Version : 0.1.1-alpha
Revision : 34ddc84
Description : Renders the UnitCatalog Vue component.
-->

<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route as ziggyRoute } from 'ziggy-js';
import infosFr from '../i18n/infos-fr.json';
import ElementInfoButton from './ElementInfoButton.vue';
import RarityBadge from './RarityBadge.vue';
import CatalogGrid from './CatalogGrid.vue';
import { catalogCategories, defenceFilters, matchesCatalogCategory, shipFilters } from '../catalogFilters.js';

const props = defineProps({ units: Array, queue: Array, planet: Object, unitType: String, title: String });
const page = usePage();
const { t, n, locale } = useI18n();
// Keep the selected category across reactive catalog updates while deriving counts from current props.
const selectedCategory = ref('all');
const groups = computed(() => props.unitType === 'ships' ? shipFilters : defenceFilters);
const categories = computed(() => catalogCategories(props.units, groups.value));
const visibleCount = computed(() => props.units.filter((unit) => matchesCatalogCategory(unit, selectedCategory.value, categories.value)).length);
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const researchKeys = { 106: 'spy_technology_level', 109: 'military_technology_level', 110: 'defence_technology_level', 111: 'shield_technology_level', 113: 'energy_technology_level', 114: 'hyperspace_technology_level', 115: 'combustion_technology_level', 117: 'impulse_technology_level', 118: 'hyperspace_drive_level', 120: 'laser_technology_level', 121: 'ion_technology_level', 122: 'plasma_technology_level', 199: 'graviton_technology_level' };
const buildingKeys = { 14: 'robot_factory', 15: 'nano_factory', 20: 'defence_center', 21: 'hangar', 31: 'laboratory', 44: 'missile_silo' };
const officerKeys = { 609: 'bunker', 614: 'raider', 615: 'emperor' };
const unitNames = { 202:'small_cargo',203:'large_cargo',204:'light_fighter',205:'heavy_fighter',206:'cruiser',207:'battleship',208:'colony_ship',209:'recycler',210:'espionage_probe',211:'bomber',212:'solar_satellite',213:'destroyer',214:'deathstar',215:'battlecruiser',216:'supernova',217:'planetary_destroyer',401:'rocket_launcher',402:'light_laser',403:'heavy_laser',404:'gauss_cannon',405:'ion_cannon',406:'plasma_turret',407:'small_shield_dome',408:'large_shield_dome',409:'planetary_protector',502:'anti_ballistic_missile',503:'interplanetary_missile' };
const format = (value) => n(Number(value ?? 0), 'game');
const formatDate = (value) => new Intl.DateTimeFormat(locale.value, { dateStyle: 'short', timeStyle: 'medium' }).format(new Date(value));
function unitName(unit) { const key = unitNames[unit.code]; return unit.factionName ?? page.props.factionSettings?.[props.unitType]?.[unit.code]?.name ?? (key ? t(`unitNames.${key}`) : unit.name); }
function requirementName(code) {
    const research = researchKeys[code];
    const building = buildingKeys[code];
    const officer = officerKeys[code];
    return research ? t(`researchLabels.${research}`) : building ? t(`buildingCatalog.${building}.name`) : officer ? t(`officerNames.${officer}`) : t('units.requirementPrefix', { code });
}
/** Submit one unit order through the type-specific endpoint; unique units always have quantity one. */
function build(unit, event) {
    const endpoint = props.unitType === 'ships' ? 'planet.ships.store' : 'planet.defences.store';
    router.post(route(endpoint, props.planet.id), { code: unit.code, quantity: unit.unique ? 1 : Number(event.target.quantity.value) }, { preserveScroll: true });
}
</script>

<template>
    <section class="data-panel unit-catalog">
        <div class="panel-heading"><div><span class="panel-kicker">{{ t('production.installations') }}</span><h2>{{ title }}</h2></div><span class="panel-count">{{ visibleCount }} / {{ units.length }}</span></div>
        <CatalogGrid v-model="selectedCategory" :items="units" :groups="groups" class="catalog-pos-wrap">
            <article v-for="unit in units" v-show="matchesCatalogCategory(unit, selectedCategory, categories)" :key="unit.code" class="card building-pos-card" :class="`rarity-${unit.rarity}`">
                <div class="pos-product">
                    <div class="img building-pos-image">
                        <slot name="image" :unit="unit" :label="unitName(unit)" />
                        <span class="building-image-fallback" aria-hidden="true">{{ unit.code }}</span>
                        <RarityBadge class="building-rarity" :rarity="unit.rarity" />
                        <span class="building-level" :aria-label="`${format(unit.count)} ${t('production.inService')}`">{{ format(unit.count) }}</span>
                    </div>
                    <div class="building-pos-body">
                        <div class="building-title"><span class="building-code">{{ unit.code }}</span><h3>{{ unitName(unit) }}</h3><ElementInfoButton :id="`unit_${unit.code}`" :name="unitName(unit)" :description="infosFr[`unit_${unit.code}`]?.description" :image-src="`/images/${unitType}/${unit.code}.${unitType === 'ships' ? 'webp' : 'png'}`" /></div>
                        <div class="building-main">
                            <span class="unit-owned">{{ format(unit.count) }} {{ t('production.inService') }}<span v-if="unit.queued"> · {{ format(unit.queued) }} {{ t('production.queued') }}</span></span>
                            <div class="unit-requirements"><span v-for="req in unit.requirements" :key="req.code" :class="{ 'requirement-met': req.current >= req.level }">{{ requirementName(req.code) }} {{ t('units.level', { level: req.level }) }} · {{ t('units.requirementProgress', { current: req.current, required: req.level }) }}</span><span v-if="!unit.requirements.length" class="requirement-met">{{ t('units.noRequirement') }}</span></div>
                        </div>
                        <div class="building-action">
                            <div class="building-cost"><span>{{ t('catalogFilters.unitCost') }}</span><strong class="metal-symbol">▰ {{ format(unit.cost.metal) }}</strong><strong class="crystal-symbol">◇ {{ format(unit.cost.crystal) }}</strong><strong class="deut-symbol">⬡ {{ format(unit.cost.deuterium) }}</strong></div>
                            <form class="unit-order" @submit.prevent="build(unit, $event)"><input name="quantity" type="number" min="1" :max="unit.maxAffordable || 1" value="1" :disabled="!unit.canBuild || unit.unique" :aria-label="t('production.quantity', { name: unitName(unit) })"><button class="button button-primary" type="submit" :disabled="!unit.canBuild">{{ unit.unique ? t('production.buildUnique') : unit.canBuild ? t('production.addToQueue') : !unit.requirementsMet ? t('units.requirementsMissing') : t('units.resourcesMissing') }}</button></form>
                        </div>
                    </div>
                </div>
            </article>
        </CatalogGrid>
    </section>
    <section class="data-panel production-queue">
        <div class="panel-heading"><div><span class="panel-kicker">{{ t('production.planetarySite') }}</span><h2>{{ t('production.queue') }}</h2></div><span class="panel-count">{{ queue.length }} / 5</span></div>
        <article v-for="entry in queue" :key="entry.id" class="unit-queue-row"><span class="queue-position">{{ entry.position }}</span><div><strong>{{ unitName({ code: entry.code, name: entry.name }) }} × {{ entry.quantity }}</strong><small>{{ entry.completed }} {{ t('production.finished') }} · {{ t('production.finish') }} {{ formatDate(entry.completesAt) }}</small></div><strong class="unit-queue-time">{{ t('units.seconds', { count: format(entry.remainingSeconds) }) }}</strong></article>
        <p v-if="!queue.length" class="queue-empty">{{ t('production.noneInProduction') }}</p>
    </section>
</template>
