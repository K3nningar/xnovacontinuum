<!--
Author / Contributor : K3nningar
Code Version : 0.1.1-alpha
Revision : 34ddc84
Description : Renders the TechnologyTreeGraph Vue component.
-->

<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import infosFr from '../i18n/infos-fr.json';
import RarityBadge from './RarityBadge.vue';
import { dependencyPath, filterTechnologyTreeElements, getTechnologyTreeRelationships, layoutTechnologyTree, TECHNOLOGY_TREE_NODE_WIDTH } from '../technologyTreeLayout.js';

const props = defineProps({ elements: { type: Array, default: () => [] }, activeResearch: { type: Object, default: null } });
const { t } = useI18n();
const arrowId = `technology-tree-arrow-${Math.random().toString(36).slice(2)}`;
const focusedElementId = ref(null);
const selectedElement = ref(null);
const modalCloseButton = ref(null);
let modalReturnFocus = null;
const categoryOptions = [
    { kind: 'building', label: 'buildings' },
    { kind: 'research', label: 'research' },
    { kind: 'ship', label: 'ships' },
    { kind: 'defence', label: 'defences' },
    { kind: 'officer', label: 'officers' },
];
const selectedKinds = ref(categoryOptions.map(({ kind }) => kind));
const visibleElements = computed(() => filterTechnologyTreeElements(props.elements, selectedKinds.value));
const graph = computed(() => layoutTechnologyTree(visibleElements.value));
const nodes = computed(() => graph.value.nodes);
const nodeByCode = computed(() => graph.value.byCode);
const graphWidth = computed(() => graph.value.width);
const graphHeight = computed(() => graph.value.height);
const nodeWidth = TECHNOLOGY_TREE_NODE_WIDTH;
const nodeHeight = computed(() => graph.value.nodeHeight);
const topPadding = computed(() => graph.value.topPadding);
const selectedRelationships = computed(() => selectedElement.value
    ? getTechnologyTreeRelationships(props.elements, selectedElement.value)
    : null);
const selectedCosts = computed(() => {
    if (!selectedElement.value) return [];

    return ['metal', 'crystal', 'deuterium', 'energy', 'points']
        .map((key) => ({
            key,
            label: key === 'points' ? t('technologyTree.points') : t(`common.${key}`),
            amount: Number(selectedElement.value.cost?.[key] ?? 0),
        }))
        .filter((cost) => cost.amount > 0);
});
// Render edges only when both endpoints survive the category filter; detail relationships use the full catalog.
const links = computed(() => nodes.value.flatMap((node) => node.requirements
    .map((requirement) => ({ source: nodeByCode.value[requirement.code], target: node, requirement }))
    .filter((link) => link.source && link.source.id !== link.target.id)));
// Focus highlights only immediate prerequisite/dependent neighbors, not transitive descendants.
const relatedElementIds = computed(() => {
    if (!focusedElementId.value || !nodes.value.some((node) => node.id === focusedElementId.value)) return null;

    const related = new Set([focusedElementId.value]);
    links.value.forEach(({ source, target }) => {
        if (source.id === focusedElementId.value) related.add(target.id);
        if (target.id === focusedElementId.value) related.add(source.id);
    });

    return related;
});
const elementKindLabel = (kind) => t(`technologyTree.kinds.${kind}`);
const status = (element) => element.level > 0 ? 'researched' : element.requirements.every((requirement) => requirement.current >= requirement.required) ? 'available' : 'locked';
const formatLevel = (element) => t('common.levelShort') + ' ' + formatNumber(element.level);

function formatNumber(value) {
    return new Intl.NumberFormat().format(Number(value ?? 0));
}

function name(element) {
    if (element.kind === 'building') return t(`buildingCatalog.${element.key}.name`);

    const prefix = element.kind === 'research' ? 'research' : element.kind === 'officer' ? 'officerNames' : 'unitNames';
    const key = element.key === 'hyperspace_drive' ? 'hyperspaceDrive' : element.key;
    return t(`${prefix}.${key}`);
}

function description(element) {
    const type = element.kind === 'building' ? 'building' : element.kind === 'research' ? 'research' : element.kind === 'officer' ? 'officer' : 'unit';
    const text = infosFr[`${type}_${element.code}`]?.description ?? t('elementInfo.unavailable');
    return element.kind === 'research' && element.key === 'spy' ? `${text} ${t('galaxy.sensorsDesc')}` : text;
}

function path(link) {
    return dependencyPath(link.source, link.target, nodeWidth);
}

function linkClasses(link) {
    const focused = focusedElementId.value;
    const related = focused && (link.source.id === focused || link.target.id === focused);

    return {
        'technology-link-met': link.requirement.current >= link.requirement.required,
        'technology-link-muted': relatedElementIds.value && !related,
        'technology-link-focused': relatedElementIds.value && related,
    };
}

function nodeClasses(element) {
    return {
        'technology-node-muted': relatedElementIds.value && !relatedElementIds.value.has(element.id),
        'technology-node-related': relatedElementIds.value?.has(element.id),
    };
}

function clearFocus(elementId) {
    if (focusedElementId.value === elementId) focusedElementId.value = null;
}

function clearFocusOnExit(elementId, event) {
    if (!event.currentTarget.contains(event.relatedTarget)) clearFocus(elementId);
}

function showAllCategories() {
    selectedKinds.value = categoryOptions.map(({ kind }) => kind);
}

function requirementStatus(current, required) {
    return t(current >= required ? 'technologyTree.requirementMet' : 'technologyTree.requirementMissing');
}

// Remember the trigger so closing the dialog restores keyboard focus to its originating node.
function openElement(element, event) {
    modalReturnFocus = event.currentTarget;
    selectedElement.value = element;
    window.addEventListener('keydown', closeOnEscape);
    nextTick(() => modalCloseButton.value?.focus());
}

// Remove the global key handler and restore focus only when the original node remains mounted.
function closeElementModal() {
    selectedElement.value = null;
    window.removeEventListener('keydown', closeOnEscape);
    nextTick(() => {
        if (modalReturnFocus?.isConnected) modalReturnFocus.focus();
        modalReturnFocus = null;
    });
}

function closeOnEscape(event) {
    if (event.key === 'Escape') closeElementModal();
}

onBeforeUnmount(() => window.removeEventListener('keydown', closeOnEscape));

</script>


<template>
    <div class="technology-tree-legend">
        <span><i class="researched"></i>{{ t('research.statusResearched') }}</span>
        <span><i class="available"></i>{{ t('research.statusAvailable') }}</span>
        <span><i class="locked"></i>{{ t('research.statusLocked') }}</span>
        <p class="technology-tree-hint">{{ t('technologyTree.focusHint') }}</p>
    </div>
    <div class="technology-graph-scroll">
        <fieldset class="technology-kind-legend">
            <legend>{{ t('technologyTree.filterByCategory') }}</legend>
            <label v-for="category in categoryOptions" :key="category.kind" :class="`technology-kind-${category.kind}`">
                <input v-model="selectedKinds" type="checkbox" :value="category.kind">
                <span>{{ t(`technologyTree.categories.${category.label}`) }}</span>
            </label>
            <button type="button" class="technology-category-reset" @click="showAllCategories">{{ t('technologyTree.showAll') }}</button>
        </fieldset>
        <p v-if="visibleElements.length === 0" class="technology-tree-empty">{{ t('technologyTree.noCategoriesSelected') }}</p>
        <div v-else class="technology-graph" :style="{ width: `${graphWidth}px`, height: `${graphHeight}px` }">
            <svg class="technology-graph-svg technology-graph-links" :viewBox="`0 0 ${graphWidth} ${graphHeight}`" :width="graphWidth" :height="graphHeight" preserveAspectRatio="none" aria-hidden="true">
                <defs><marker :id="arrowId" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto" markerUnits="userSpaceOnUse"><path d="M0,0 L0,6 L7,3 z" /></marker></defs>
                <path v-for="link in links" :key="`${link.source.id}-${link.target.id}`" :class="linkClasses(link)" :d="path(link)" :marker-end="`url(#${arrowId})`" />
            </svg>
            <div v-for="element in nodes" :key="element.id" class="technology-node-foreign" :class="nodeClasses(element)" :style="{ left: `${element.x + 10}px`, top: `${element.y + topPadding}px`, width: `${nodeWidth - 20}px`, height: `${nodeHeight - 10}px` }" @pointerenter="focusedElementId = element.id" @pointerleave="clearFocus(element.id)" @focusin="focusedElementId = element.id" @focusout="clearFocusOnExit(element.id, $event)">
                <button type="button" class="technology-graph-node" :aria-label="t('elementInfo.open', { name: name(element) })" aria-haspopup="dialog" :class="[`technology-node-${status(element)}`, `technology-kind-${element.kind}`, { 'technology-node-active': activeResearch?.technologyKey === element.key && element.kind === 'research' }]" @click="openElement(element, $event)">
                    <header class="technology-node-heading">
                        <img class="technology-node-image" :src="`/legacy-assets/skins/xnova/gebaeude/${element.code}.gif`" alt="" loading="lazy" @error="$event.currentTarget.hidden = true">
                        <div class="technology-node-title"><small>{{ element.code }} · {{ elementKindLabel(element.kind) }}</small><h4>{{ name(element) }}</h4><RarityBadge :rarity="element.rarity" /></div>
                        <span class="technology-node-level">{{ formatLevel(element) }}</span>
                    </header>
                </button>
            </div>
        </div>
    </div>
    <Teleport to="body">
        <div v-if="selectedElement && selectedRelationships" class="element-info-backdrop" @click.self="closeElementModal">
            <section class="element-info-modal technology-detail-modal" role="dialog" aria-modal="true" :aria-label="name(selectedElement)" @click.stop>
                <header class="element-info-header">
                    <div><span class="panel-kicker">{{ t('elementInfo.details') }}</span><h2>{{ name(selectedElement) }}</h2></div>
                    <button ref="modalCloseButton" class="element-info-close" type="button" :aria-label="t('elementInfo.close')" @click="closeElementModal">×</button>
                </header>
                <div class="technology-detail-body">
                    <div class="technology-detail-overview">
                        <img :src="`/legacy-assets/skins/xnova/gebaeude/${selectedElement.code}.gif`" :alt="name(selectedElement)" loading="lazy" @error="$event.currentTarget.hidden = true">
                        <div>
                            <p class="technology-detail-description">{{ description(selectedElement) }}</p>
                            <RarityBadge :rarity="selectedElement.rarity" />
                            <dl class="technology-detail-summary">
                                <div><dt>{{ t('technologyTree.category') }}</dt><dd>{{ elementKindLabel(selectedElement.kind) }}</dd></div>
                                <div><dt>{{ t('technologyTree.currentLevel') }}</dt><dd>{{ formatNumber(selectedElement.level) }}</dd></div>
                                <div><dt>{{ t('technologyTree.targetLevel') }}</dt><dd>{{ formatNumber(selectedElement.targetLevel) }}</dd></div>
                                <div><dt>{{ t('technologyTree.status') }}</dt><dd>{{ t(`research.status${status(selectedElement).charAt(0).toUpperCase()}${status(selectedElement).slice(1)}`) }}</dd></div>
                            </dl>
                        </div>
                    </div>
                    <section class="technology-detail-section">
                        <h3>{{ t('technologyTree.costs') }}</h3>
                        <p v-if="selectedCosts.length === 0" class="technology-detail-empty">{{ t('common.none') }}</p>
                        <dl v-else class="technology-detail-costs">
                            <div v-for="cost in selectedCosts" :key="cost.key"><dt>{{ cost.label }}</dt><dd>{{ formatNumber(cost.amount) }}</dd></div>
                        </dl>
                    </section>
                    <section class="technology-detail-section">
                        <h3>{{ t('technologyTree.prerequisites') }}</h3>
                        <p v-if="selectedRelationships.prerequisites.length === 0" class="technology-detail-empty">{{ t('common.none') }}</p>
                        <div v-else class="technology-detail-table-scroll">
                            <table class="technology-detail-table">
                                <thead><tr><th>{{ t('technologyTree.elementColumn') }}</th><th>{{ t('technologyTree.currentLevel') }}</th><th>{{ t('technologyTree.requiredLevel') }}</th><th>{{ t('technologyTree.status') }}</th></tr></thead>
                                <tbody>
                                    <tr v-for="requirement in selectedRelationships.prerequisites" :key="requirement.element.id">
                                        <td>{{ name(requirement.element) }}</td><td>{{ formatNumber(requirement.current) }}</td><td>{{ formatNumber(requirement.required) }}</td>
                                        <td><span :class="requirement.current >= requirement.required ? 'technology-requirement-met' : 'technology-requirement-missing'">{{ requirementStatus(requirement.current, requirement.required) }}</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <section class="technology-detail-section">
                        <h3>{{ t('technologyTree.dependencies') }}</h3>
                        <p v-if="selectedRelationships.dependencies.length === 0" class="technology-detail-empty">{{ t('technologyTree.noDependencies') }}</p>
                        <div v-else class="technology-detail-table-scroll">
                            <table class="technology-detail-table">
                                <thead><tr><th>{{ t('technologyTree.elementColumn') }}</th><th>{{ t('technologyTree.currentLevel') }}</th><th>{{ t('technologyTree.requiredLevel') }}</th><th>{{ t('technologyTree.status') }}</th></tr></thead>
                                <tbody>
                                    <tr v-for="dependency in selectedRelationships.dependencies" :key="dependency.element.id">
                                        <td>{{ name(dependency.element) }}</td><td>{{ formatNumber(dependency.current) }}</td><td>{{ formatNumber(dependency.required) }}</td>
                                        <td><span :class="dependency.current >= dependency.required ? 'technology-requirement-met' : 'technology-requirement-missing'">{{ requirementStatus(dependency.current, dependency.required) }}</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </section>
        </div>
    </Teleport>
</template>
