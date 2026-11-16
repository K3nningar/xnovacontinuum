<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import GameLayout from '../../Layouts/GameLayout.vue';
import ElementInfoButton from '../../Components/ElementInfoButton.vue';
import RarityBadge from '../../Components/RarityBadge.vue';
import infosFr from '../../i18n/infos-fr.json';
const props=defineProps({user:Object,officers:Array,points:Number,queue:Array});
const page = usePage();
const { t, locale } = useI18n();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const formatDate = (value) => new Intl.DateTimeFormat(locale.value, { dateStyle: 'short', timeStyle: 'medium' }).format(new Date(value));
const officerKeys = { 601: 'geologist', 602: 'admiral', 603: 'engineer', 604: 'technocrat', 605: 'constructor', 606: 'scientist', 607: 'stockman', 608: 'defender', 609: 'bunker', 610: 'spy', 611: 'commander', 612: 'destroyer', 613: 'general', 614: 'raider', 615: 'emperor' };
function officerName(code) {
    const officerKey = officerKeys[code];

    return officerKey ? t(`officerNames.${officerKey}`) : String(code);
}

function requirementName(requirement) {
    return officerKeys[requirement.code] ? officerName(requirement.code) : t('units.requirementPrefix', { code: requirement.code });
}

function recruit(officer){router.post(route('officers.store'),{code:officer.code},{preserveScroll:true});}
</script>
<template>
<Head :title="t('production.officerTitle')"/><GameLayout><main class="command-layout"><section class="command-content">
<div v-if="page.props.flash?.success" class="flash-message" role="status">{{ t('production.officerRecruitSuccess') }}</div><div v-if="Object.keys(page.props.errors||{}).length" class="form-alert" role="alert">{{Object.values(page.props.errors)[0]}}</div>
<div class="command-heading"><div><p class="eyebrow"><span class="eyebrow-line"></span>{{ t('officers.eyebrow') }}</p><h1>{{ t('officers.heading') }}</h1><p class="command-subtitle">{{ t('officers.subtitle') }}</p></div><div class="officer-points"><span>{{ t('officers.points') }}</span><strong>✦ {{points}}</strong></div></div>
<section class="data-panel"><div class="panel-heading"><div><span class="panel-kicker">{{ t('officers.staff') }}</span><h2>{{ t('officers.title') }}</h2></div><span class="panel-count">{{officers.length}}</span></div>
<div class="officer-grid"><article v-for="officer in officers" :key="officer.code" class="officer-card"><div class="unit-art"><img :src="`/legacy-assets/images/officiers/${officer.code}.jpg`" :alt="officer.name" loading="lazy" @error="$event.currentTarget.hidden=true"><span class="unit-code">{{ officer.code }}</span></div><div class="officer-card-body"><div class="unit-title"><h3>{{ officerName(officer.code) }}</h3><RarityBadge :rarity="officer.rarity" /><ElementInfoButton :id="`officer_${officer.code}`" :name="officerName(officer.code)" :description="infosFr[`officer_${officer.code}`]?.description" /><span class="unit-owned">{{ t('production.officerLevel', { level: officer.level, max: officer.max }) }}</span></div><p>{{ t(`officers.${officer.key}`) }}</p><div class="unit-requirements"><span v-for="req in officer.requirements" :key="req.code" :class="{'requirement-met':req.current>=req.level}">{{ t('production.officerRequirement', { name: requirementName(req), level: req.level, current: req.current }) }}</span><span v-if="!officer.requirements.length" class="requirement-met">{{ t('units.noRequirement') }}</span></div><button class="button button-primary" :disabled="!officer.canRecruit||points<1" @click="recruit(officer)">{{officer.level>=officer.max?t('officers.maximum'):!officer.requirementsMet?t('common.missingRequirements'):!points?t('officers.noPoints'):t('officers.recruit')}}</button></div></article></div></section>
<section class="data-panel production-queue"><div class="panel-heading"><div><span class="panel-kicker">{{ t('officers.orders') }}</span><h2>{{ t('officers.waiting') }}</h2></div><span class="panel-count">{{queue.length}}</span></div><article v-for="entry in queue" :key="entry.id" class="unit-queue-row"><span class="queue-position">{{entry.position}}</span><div><strong>{{ officerName(entry.code) }}</strong><small>{{ t('units.endsAt', { date: formatDate(entry.completesAt) }) }}</small></div><strong class="unit-queue-time">{{entry.remainingSeconds}} s</strong></article><p v-if="!queue.length" class="queue-empty">{{ t('officers.noRecruitments') }}</p></section>
</section></main></GameLayout>
</template>
