<!--
Author / Contributor : K3nningar
Code Version : 0.2.0-alpha
Revision : 34ddc84
Description : Renders the Index Inertia page.
-->

<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import GameLayout from '../../Layouts/GameLayout.vue';
import { emptyCombatForm, prepareCombatSubmission, randomCombatForm } from './combatForm.js';

const props = defineProps({
    units: Array,
    maxUnitsPerSide: Number,
    presetFleet: Object,
    planet: Object,
    technologies: Object,
    result: Object,
});
const page = usePage();
const { t, n } = useI18n();
const route = (name) => ziggyRoute(name, undefined, true, page.props.ziggy);
const unitByCode = computed(() => Object.fromEntries(props.units.map((unit) => [unit.code, unit])));
const shipUnits = computed(() => props.units.filter((unit) => unit.type === 'ships'));
const defenceUnits = computed(() => props.units.filter((unit) => unit.type === 'defences'));
const specialUnits = computed(() => props.units.filter((unit) => unit.customProfile));
const technologyFields = [
    { key: 'weapons', label: 'combat.weapons' },
    { key: 'shields', label: 'combat.shields' },
    { key: 'armour', label: 'combat.armour' },
];
const sides = [
    { key: 'attacker', title: 'combat.attacker' },
    { key: 'defender', title: 'combat.defender' },
];
const form = useForm({
    attacker: {
        fleet: Object.fromEntries(shipUnits.value.map((unit) => [unit.code, props.presetFleet?.[unit.code] ?? 0])),
        technologies: { ...props.technologies },
    },
    defender: {
        fleet: Object.fromEntries(props.units.map((unit) => [unit.code, 0])),
        technologies: { weapons: 0, shields: 0, armour: 0 },
    },
    special_profiles: Object.fromEntries(props.units.filter((unit) => unit.customProfile).map((unit) => [
        unit.code, { attack: null, shield: null, hull: unit.hull },
    ])),
    seed: '',
});
// Custom combat profiles are shown only while that unit participates on either side.
const selectedSpecialUnits = computed(() => specialUnits.value.filter((unit) =>
    Number(form.attacker.fleet[unit.code]) > 0 || Number(form.defender.fleet[unit.code]) > 0,
));
const profileFields = [
    { key: 'attack', label: 'combat.weapons', min: 0 },
    { key: 'shield', label: 'combat.shields', min: 0 },
    { key: 'hull', label: 'combatProfiles.hull', min: 1 },
];
const winnerLabel = computed(() => {
    if (! props.result) {
        return '';
    }

    return t(`combat.winner${props.result.winner.charAt(0).toUpperCase()}${props.result.winner.slice(1)}`);
});

function unitName(code) {
    const unit = unitByCode.value[code];

    return unit ? t(`unitNames.${unit.key}`) : t('combat.unknownUnit', { code });
}

function unitImage(code) {
    const unit = unitByCode.value[code];

    if (!unit) {
        return '';
    }

    return unit.type === 'ships'
        ? `/images/ships/${unit.code}.webp`
        : `/legacy-assets/skins/xnova/gebaeude/${unit.code}.gif`;
}

function format(value) {
    return n(Number(value ?? 0), 'game');
}

function unitTotal(units) {
    return units.reduce((total, unit) => total + unit.count, 0);
}

function reloadPlanetFleet() {
    for (const unit of shipUnits.value) {
        form.attacker.fleet[unit.code] = props.presetFleet?.[unit.code] ?? 0;
    }
}

// Reset the complete form shape and clear stale validation feedback together.
function clearFields() {
    Object.assign(form, emptyCombatForm(props.units));
    form.clearErrors();
}

function randomizeFields() {
    Object.assign(form, randomCombatForm(props.units, props.maxUnitsPerSide));
    form.clearErrors();
}

function submit() {
    form.transform(prepareCombatSubmission).post(route('combat-simulator.simulate'), { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('combat.title')" />
    <GameLayout>
        <main class="command-layout">
            <section class="command-content">
                <div class="command-heading">
                    <div>
                        <p class="eyebrow"><span class="eyebrow-line"></span>{{ t('combat.eyebrow') }}</p>
                        <h1>{{ t('combat.heading') }}</h1>
                        <p class="command-subtitle">{{ t('combat.subtitle') }}</p>
                    </div>
                </div>

                <div class="notice-bar combat-notice" role="note">
                    <span aria-hidden="true">ⓘ</span>
                    <p>{{ t('combat.simulationNote') }} <strong>{{ t('combatProfiles.notice') }}</strong></p>
                </div>

                <form class="combat-form" @submit.prevent="submit">
                    <div class="combat-form-actions">
                        <button class="button button-quiet" type="button" :disabled="form.processing" @click="clearFields">{{ t('combatActions.clear') }}</button>
                        <button class="button button-quiet" type="button" :disabled="form.processing" @click="randomizeFields">{{ t('combatActions.randomize') }}</button>
                    </div>
                    <div class="combat-sides">
                        <section v-for="side in sides" :key="side.key" class="data-panel combat-side" :class="`combat-side-${side.key}`">
                            <div class="panel-heading">
                                <div>
                                    <span class="panel-kicker">{{ t('combat.testForce') }}</span>
                                    <h2>{{ t(side.title) }}</h2>
                                </div>
                                <span v-if="side.key === 'attacker' && planet" class="combat-planet">{{ planet.name }} · {{ planet.coordinates }}</span>
                                <span class="combat-side-limit">{{ t('combat.maxUnitsHint', { count: format(maxUnitsPerSide) }) }}</span>
                            </div>

                            <div class="combat-side-body">
                                <div v-if="side.key === 'attacker' && planet" class="combat-load-row">
                                    <span>{{ t('combat.planetFleetHint') }}</span>
                                    <button class="button button-quiet" type="button" @click="reloadPlanetFleet">{{ t('combat.reloadFleet') }}</button>
                                </div>

                                <p v-if="form.errors[`${side.key}.fleet`]" class="combat-error" role="alert">{{ form.errors[`${side.key}.fleet`] }}</p>

                                <fieldset v-for="(group, groupIndex) in (side.key === 'attacker' ? [shipUnits] : [shipUnits, defenceUnits])" :key="groupIndex" class="combat-unit-group">
                                    <legend>{{ groupIndex === 0 ? t('combat.ships') : t('combat.defences') }}</legend>
                                    <div class="combat-unit-grid">
                                        <label v-for="unit in group" :key="unit.code" class="combat-unit-row" :for="`${side.key}-unit-${unit.code}`">
                                            <img :src="unitImage(unit.code)" alt="" loading="lazy" @error="$event.currentTarget.hidden = true">
                                            <span class="combat-unit-name">
                                                <strong>{{ unitName(unit.code) }}</strong>
                                                <small v-if="unit.customProfile">{{ t('combatProfiles.custom') }}</small>
                                                <small v-else>{{ t('combat.unitStats', { attack: format(unit.attack), shields: format(unit.shield), hull: format(unit.hull) }) }}</small>
                                            </span>
                                            <input
                                                :id="`${side.key}-unit-${unit.code}`"
                                                v-model.number="form[side.key].fleet[unit.code]"
                                                type="number"
                                                min="0"
                                                :max="maxUnitsPerSide"
                                                step="1"
                                                :aria-label="t('combat.unitCount', { unit: unitName(unit.code) })"
                                            >
                                            <span v-if="form.errors[`${side.key}.fleet.${unit.code}`]" class="combat-error" role="alert">{{ form.errors[`${side.key}.fleet.${unit.code}`] }}</span>
                                        </label>
                                    </div>
                                </fieldset>

                                <fieldset class="combat-tech-group">
                                    <legend>{{ t('combat.technologies') }}</legend>
                                    <label v-for="technology in technologyFields" :key="technology.key" class="combat-tech-field">
                                        <span>{{ t(technology.label) }}</span>
                                        <input
                                            v-model.number="form[side.key].technologies[technology.key]"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="1"
                                        >
                                        <span v-if="form.errors[`${side.key}.technologies.${technology.key}`]" class="combat-error" role="alert">{{ form.errors[`${side.key}.technologies.${technology.key}`] }}</span>
                                    </label>
                                </fieldset>
                            </div>
                        </section>
                    </div>

                    <section v-if="selectedSpecialUnits.length" class="data-panel">
                        <div class="panel-heading">
                            <h2>{{ t('combatProfiles.title') }}</h2>
                        </div>
                        <div class="combat-side-body">
                            <p>{{ t('combatProfiles.hint') }}</p>
                            <fieldset v-for="unit in selectedSpecialUnits" :key="unit.code" class="combat-tech-group">
                                <legend>{{ unitName(unit.code) }}</legend>
                                <label v-for="field in profileFields" :key="field.key" class="combat-tech-field">
                                    <span>{{ t(field.label) }}</span>
                                    <input v-model.number="form.special_profiles[unit.code][field.key]" type="number" :min="field.min" max="1000000000" step="1">
                                    <span v-if="form.errors[`special_profiles.${unit.code}.${field.key}`]" class="combat-error" role="alert">{{ form.errors[`special_profiles.${unit.code}.${field.key}`] }}</span>
                                </label>
                            </fieldset>
                        </div>
                    </section>

                    <div class="combat-run-controls">
                        <label class="combat-seed-field">
                            <span>{{ t('combat.seed') }}</span>
                            <input v-model="form.seed" type="number" min="0" max="2147483647" :placeholder="t('combat.randomSeed')">
                            <small>{{ t('combat.seedHint') }}</small>
                        </label>
                        <button class="button button-primary combat-submit" type="submit" :disabled="form.processing">
                            {{ form.processing ? t('combat.simulating') : t('combat.simulate') }}
                        </button>
                    </div>
                </form>

                <section v-if="result" class="combat-results" aria-live="polite">
                    <div class="combat-result-heading">
                        <div>
                            <p class="eyebrow"><span class="eyebrow-line"></span>{{ t('combat.resultTitle') }}</p>
                            <h2>{{ winnerLabel }}</h2>
                        </div>
                        <span class="panel-count">{{ t('combat.roundCount', { count: result.rounds.length }) }}</span>
                    </div>

                    <div class="combat-summary-grid">
                        <section v-for="side in sides" :key="side.key" class="data-panel combat-summary">
                            <div class="panel-heading">
                                <div><span class="panel-kicker">{{ t('combat.finalStatus') }}</span><h3>{{ t(side.title) }}</h3></div>
                                <span class="panel-count">{{ format(result[side.key].totalRemaining) }}</span>
                            </div>
                            <p class="combat-loss-total">{{ t('combat.losses') }} · {{ format(result[side.key].totalLosses) }}</p>
                            <div v-for="unit in result[side.key].units" :key="unit.code" class="combat-result-unit">
                                <span class="combat-unit-label"><img :src="unitImage(unit.code)" alt="" @error="$event.currentTarget.hidden = true"><span>{{ unitName(unit.code) }}</span></span>
                                <span>{{ format(unit.initial) }} → {{ format(unit.remaining) }}</span>
                                <strong>-{{ format(unit.losses) }}</strong>
                            </div>
                        </section>
                    </div>

                    <section class="data-panel combat-rounds">
                        <div class="panel-heading">
                            <div><span class="panel-kicker">{{ t('combat.battleLog') }}</span><h3>{{ t('combat.rounds') }}</h3></div>
                            <span class="panel-count">{{ result.rounds.length }}</span>
                        </div>
                        <details v-for="(round, index) in result.rounds" :key="round.number" class="combat-round" :open="index === 0">
                            <summary>
                                <strong>{{ t('combat.roundNumber', { number: round.number }) }}</strong>
                                <span>{{ t('combat.shotCount', { count: format(round.attacker.shots + round.defender.shots) }) }}</span>
                            </summary>
                            <div class="combat-round-body">
                                <div v-for="side in sides" :key="side.key" class="combat-round-side">
                                    <strong>{{ t(side.title) }}</strong>
                                    <span>{{ t('combat.lossCount', { count: format(round[side.key].losses) }) }}</span>
                                    <ul>
                                        <li v-for="unit in round[side.key].units" :key="unit.code"><span class="combat-unit-label"><img :src="unitImage(unit.code)" alt="" @error="$event.currentTarget.hidden = true"><span>{{ unitName(unit.code) }} × {{ format(unit.count) }}</span></span></li>
                                        <li v-if="!unitTotal(round[side.key].units)">{{ t('combat.noSurvivors') }}</li>
                                    </ul>
                                </div>
                            </div>
                        </details>
                    </section>
                </section>
            </section>
        </main>
    </GameLayout>
</template>
