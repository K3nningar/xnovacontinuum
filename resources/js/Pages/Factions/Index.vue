<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import GameLayout from '../../Layouts/GameLayout.vue';

defineProps({ universe: Object, factions: Array, currentFactionKey: String, canEdit: Boolean, factionsEnabled: Boolean });
const page = usePage();
const { t } = useI18n();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const categories = ['buildings', 'ships', 'defences'];
const formatMultiplier = (value) => `${Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 })}×`;
</script>

<template>
    <Head :title="`${t('factions.directoryTitle')} · ${universe.name}`" />
    <GameLayout>
        <main class="command-layout faction-directory-page"><section class="command-content">
            <div class="command-heading"><div><p class="eyebrow"><span class="eyebrow-line"></span>{{ t('factions.directoryEyebrow') }}</p><h1>{{ t('factions.directoryTitle') }}</h1><p class="command-subtitle">{{ universe.name }} · {{ t('factions.directorySubtitle') }}</p></div><Link class="button button-outline" :href="route('dashboard')">{{ t('nav.command') }}</Link></div>
            <div v-if="!factionsEnabled" class="rounded border border-amber-500 bg-amber-950 p-4 text-amber-200" role="alert">{{ t('factions.moduleDisabled') }}</div>
            <div v-else class="faction-directory-grid">
                <article v-for="faction in factions" :key="faction.key" class="data-panel faction-directory-card" :class="{ 'is-current': faction.key === currentFactionKey }">
                    <div class="faction-banner"><img v-if="faction.image" :src="faction.image" :alt="faction.name"><span v-else class="faction-banner-placeholder">⌖</span><span v-if="faction.key === currentFactionKey" class="faction-current-badge">{{ t('factions.current') }}</span></div>
                    <div class="faction-card-content"><div class="faction-card-heading"><div><span class="panel-kicker">{{ t('factions.faction') }} · {{ faction.key }}</span><h2>{{ faction.name }}</h2></div><span class="faction-members">{{ t('factions.membersPlanets', { members: faction.members, planets: faction.planets }) }}</span></div><p class="faction-description">{{ faction.description || t('factions.noDescription') }}</p>
                        <div class="faction-multipliers"><div><span>{{ t('factionEditor.attack') }}</span><strong>{{ formatMultiplier(faction.statistics.attack) }}</strong></div><div><span>{{ t('factionEditor.shield') }}</span><strong>{{ formatMultiplier(faction.statistics.shield) }}</strong></div><div><span>{{ t('factionEditor.hull') }}</span><strong>{{ formatMultiplier(faction.statistics.hull) }}</strong></div></div>
                        <section class="faction-elements"><h3>{{ t('factions.exclusiveElements') }}</h3><div v-if="faction.exclusiveElements.length" class="faction-element-list"><div v-for="element in faction.exclusiveElements" :key="`${element.category}-${element.name}`" class="faction-element"><img v-if="element.image" :src="element.image" :alt="element.name"><span><strong>{{ element.name }}</strong><small>{{ t(`factionEditor.${element.category}`) }} · {{ t(element.available ? 'factionEditor.available' : 'factionEditor.unavailable') }}</small></span></div></div><p v-else class="faction-empty-elements">{{ t('factions.noExclusiveElements') }}</p></section>
                    </div>
                </article>
            </div>
            <div v-if="factionsEnabled && canEdit" class="faction-admin-link"><span>{{ t('factions.adminHint') }}</span><Link class="button button-primary" :href="route('admin.factions.index', { universe: universe.id })">{{ t('factions.manage') }}</Link></div>
        </section></main>
    </GameLayout>
</template>

<style scoped>
.faction-directory-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(310px, 1fr)); gap: 14px; }
.faction-directory-card { overflow: hidden; min-height: 0; }
.faction-directory-card.is-current { border-color: rgba(86, 214, 194, .6); box-shadow: 0 0 22px rgba(86, 214, 194, .08); }
.faction-banner { position: relative; height: 96px; display: grid; place-items: center; overflow: hidden; background: radial-gradient(circle at 50% 0, rgba(86, 214, 194, .3), transparent 65%), #0a141e; border-bottom: 1px solid var(--line); }
.faction-banner img { display: block; width: auto; max-width: 88%; height: auto; max-height: 82px; object-fit: contain; }
.faction-banner-placeholder { color: var(--accent); font-size: 40px; opacity: .75; }
.faction-current-badge { position: absolute; top: 12px; right: 12px; padding: 5px 8px; color: #071514; background: var(--accent); font-size: 10px; font-weight: 700; text-transform: uppercase; }
.faction-card-content { display: grid; gap: 16px; padding: 18px; }
.faction-card-heading { display: flex; justify-content: space-between; gap: 12px; align-items: start; }
.faction-card-heading h2 { margin: 5px 0 0; font-size: 22px; }
.faction-members { color: #8291a2; font-size: 11px; text-align: right; }
.faction-description { min-height: 42px; margin: 0; color: #9caab8; font-size: 13px; line-height: 1.6; }
.faction-multipliers { display: grid; grid-template-columns: repeat(3, 1fr); gap: 7px; }
.faction-multipliers div { display: grid; gap: 5px; padding: 10px; border: 1px solid var(--line); background: rgba(10, 18, 28, .6); }
.faction-multipliers span, .faction-element small { color: #8291a2; font-size: 10px; }
.faction-multipliers strong { color: var(--accent); font-size: 18px; }
.faction-elements { border-top: 1px solid var(--line); padding-top: 14px; }
.faction-elements h3 { margin: 0 0 10px; font-size: 13px; }
.faction-element-list { display: grid; gap: 7px; }
.faction-element { display: flex; gap: 9px; align-items: center; padding: 8px; border: 1px solid rgba(151, 177, 207, .1); }
.faction-element img { width: 30px; height: 30px; object-fit: contain; }
.faction-element span { display: grid; gap: 3px; }
.faction-element strong { font-size: 12px; }
.faction-empty-elements { margin: 0; color: #8291a2; font-size: 12px; }
.faction-admin-link { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-top: 16px; padding: 14px 17px; border: 1px solid rgba(86, 214, 194, .2); color: #9caab8; font-size: 12px; }
@media (max-width: 600px) { .faction-card-heading { flex-direction: column; }.faction-members { text-align: left; }.faction-admin-link { align-items: flex-start; flex-direction: column; } }
</style>
