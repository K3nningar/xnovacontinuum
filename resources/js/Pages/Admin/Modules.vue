<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route as ziggyRoute } from 'ziggy-js';
import GameLayout from '../../Layouts/GameLayout.vue';

const props = defineProps({ factionsEnabled: Boolean, officersEnabled: Boolean, officers: { type: Array, required: true }, universes: { type: Array, default: () => [] }, selectedUniverseId: { type: Number, default: null } });
const page = usePage();
const { t } = useI18n();
const route = (name) => ziggyRoute(name, undefined, true, page.props.ziggy);
const form = useForm({
    universe_id: props.selectedUniverseId,
    factions_enabled: props.factionsEnabled,
    officers_enabled: props.officersEnabled,
    enabled_officers: props.officers.filter((officer) => officer.enabled).map((officer) => officer.code),
});

function save() {
    form.patch(route('admin.modules.update'), { preserveScroll: true });
}

function selectUniverse(event) {
    router.get(route('admin.modules'), { universe_id: event.target.value });
}
</script>

<template>
    <Head :title="t('adminModules.title')" />
    <GameLayout>
        <main class="command-layout admin-page">
            <section class="command-content flex flex-col gap-6">
                <div class="command-heading">
                    <div><h1>{{ t('adminModules.title') }}</h1><p class="command-subtitle">{{ t('adminModules.perUniverse') }}</p></div>
                    <Link class="button button-outline" :href="route('admin.index')">{{ t('admin.title') }}</Link>
                </div>
                <label v-if="universes.length" class="module-universe">{{ t('adminModules.universe') }}
                    <select :value="selectedUniverseId" @change="selectUniverse"><option v-for="universe in universes" :key="universe.id" :value="universe.id">{{ universe.name }}</option></select>
                </label>
                <p v-else>{{ t('adminModules.noUniverses') }}</p>
                <form v-if="selectedUniverseId" class="data-panel modules-panel" @submit.prevent="save">
                    <div v-if="Object.keys(form.errors).length" class="form-alert" role="alert">
                        <p v-for="(error, field) in form.errors" :key="field">{{ error }}</p>
                    </div>
                    <div class="modules-intro">
                        <label class="module-switch">
                            <input v-model="form.factions_enabled" type="checkbox">
                            <span>{{ t('adminModules.enableFactions') }}</span>
                        </label>
                        <p class="module-help">{{ t('adminModules.factionsHelp') }}</p>
                        <label class="module-switch">
                            <input v-model="form.officers_enabled" type="checkbox">
                            <span>{{ t('adminModules.enableOfficers') }}</span>
                        </label>
                        <p class="module-help">{{ t('adminModules.help') }}</p>
                    </div>
                    <p class="module-warning"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{{ t('adminModules.warning') }}</span></p>
                    <fieldset class="officer-settings">
                        <legend>{{ t('adminModules.individual') }}</legend>
                        <div class="officer-grid">
                            <label v-for="officer in officers" :key="officer.code" class="officer-option" :class="{ 'is-selected': form.enabled_officers.includes(officer.code) }">
                                <input v-model="form.enabled_officers" type="checkbox" :value="officer.code">
                                <span class="officer-name">{{ t(`officerNames.${officer.key}`) }}</span>
                                <span class="officer-code">#{{ officer.code }}</span>
                            </label>
                        </div>
                    </fieldset>
                    <div class="module-actions">
                        <button class="button button-primary" type="submit" :disabled="form.processing">
                            {{ form.processing ? t('admin.saving') : t('adminModules.save') }}
                        </button>
                    </div>
                </form>
            </section>
        </main>
    </GameLayout>
</template>

<style scoped>
.modules-panel { min-height: 0; display: grid; gap: 24px; padding: clamp(20px, 2.5vw, 32px); }
.module-universe { display: grid; gap: 8px; max-width: 360px; color: #eef9f5; }
.modules-intro { display: grid; gap: 12px; }
.module-switch { display: flex; align-items: center; gap: 12px; width: fit-content; color: #eef9f5; font-size: 16px; font-weight: 650; cursor: pointer; }
.module-switch input, .officer-option input { width: 18px; height: 18px; flex: 0 0 18px; margin: 0; accent-color: #44d9ad; cursor: pointer; }
.module-switch input:focus-visible, .officer-option input:focus-visible { outline: 2px solid #75edc9; outline-offset: 3px; }
.module-help { max-width: 90ch; margin: 0; color: #a7b9c3; font-size: 13px; line-height: 1.7; }
.module-warning { display: flex; align-items: flex-start; gap: 10px; margin: 0; padding: 14px 16px; border: 1px solid rgba(226, 178, 78, .3); background: rgba(91, 63, 18, .16); color: #f0d596; font-size: 13px; line-height: 1.6; }
.module-warning i { flex: 0 0 auto; color: #e8bd67; }
.officer-settings { min-width: 0; margin: 0; padding: 0; border: 0; }
.officer-settings legend { width: 100%; margin-bottom: 14px; padding: 0; color: #eef9f5; font-size: 16px; font-weight: 650; }
.officer-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 10px; }
.officer-option { min-width: 0; min-height: 56px; display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid rgba(151, 177, 207, .2); background: rgba(4, 17, 22, .45); color: #d7e7e5; cursor: pointer; transition: border-color .2s ease, background .2s ease; }
.officer-option:hover, .officer-option:focus-within { border-color: rgba(86, 214, 194, .65); }
.officer-option.is-selected { border-color: rgba(86, 214, 194, .32); background: rgba(35, 101, 83, .16); }
.officer-name { min-width: 0; flex: 1; font-size: 13px; line-height: 1.4; }
.officer-code { color: #90a8aa; font-size: 11px; white-space: nowrap; }
.module-actions { display: flex; justify-content: flex-end; padding-top: 20px; border-top: 1px solid rgba(128, 200, 190, .16); }
.module-actions .button { min-width: 160px; }
.module-actions .button:disabled { opacity: .6; cursor: wait; }
@media (max-width: 480px) { .module-actions .button { width: 100%; } }
</style>
