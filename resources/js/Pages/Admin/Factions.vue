<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { route as ziggyRoute } from 'ziggy-js';
import GameLayout from '../../Layouts/GameLayout.vue';
import { createFactionEditor, factionCategories, factionStatistics, serializeFactionEditor } from '../../factionSettings.js';

const props = defineProps({ universe: Object, factions: Array, relations: Array, catalog: Object });
const page = usePage();
const { t } = useI18n();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const faction = useForm({ key: '', name: '', description: '', image: '', image_file: null });
const diplomacy = useForm({ first: '0', second: '', status: 'neutral' });
const editing = useForm({ name: '', description: '', image: '', image_file: null, settings: '{}' });
const editId = ref(null);
const editKey = ref(null);
const editor = ref(null);
const saveFaction = () => faction.post(route('admin.factions.store', { universe: props.universe.id }), { forceFormData: true, preserveScroll: true, onSuccess: () => faction.reset() });
const saveDiplomacy = () => diplomacy.post(route('admin.factions.diplomacy', { universe: props.universe.id }), { preserveScroll: true });
function editFaction(item) {
    editId.value = item.id;
    editKey.value = item.key;
    editing.name = item.name;
    editing.description = item.description ?? '';
    editing.image = item.image ?? '';
    editing.image_file = null;
    editor.value = createFactionEditor(item.settings, props.catalog);
    editing.clearErrors();
}
function saveSettings() {
    editing.settings = serializeFactionEditor(editor.value, props.catalog);
    editing.transform((data) => ({ ...data, settings: editing.settings })).patch(editKey.value === '0'
        ? route('admin.factions.template.update', { universe: props.universe.id })
        : route('admin.factions.update', { universe: props.universe.id, faction: editId.value }), { forceFormData: true, preserveScroll: true });
}
function setImageFile(form, event) { form.image_file = event.target.files?.[0] ?? null; }
function elementName(category, item) {
    return category === 'buildings' ? t(`buildingCatalog.${item.translationKey}.name`) : t(`unitNames.${item.translationKey}`);
}
</script>

<template>
    <Head :title="`${t('factions.title')} · ${universe.name}`" />
    <GameLayout>
        <main class="command-layout admin-page admin-factions"><section class="command-content">
            <div class="command-heading"><div><h1>{{ t('factions.title') }} · {{ universe.name }}</h1></div><Link class="button button-outline" :href="route('admin.universes')">{{ t('factions.universes') }}</Link></div>
            <section class="data-panel"><h2>{{ t('factions.create') }}</h2>
                <form class="auth-form" @submit.prevent="saveFaction">
                    <label>{{ t('factions.key') }} <input v-model="faction.key" required maxlength="40" /></label><span class="field-error">{{ faction.errors.key }}</span>
                    <label>{{ t('factions.name') }} <input v-model="faction.name" required maxlength="80" /></label><span class="field-error">{{ faction.errors.name }}</span>
                    <label>{{ t('factions.description') }} <textarea v-model="faction.description" maxlength="2000" /></label>
                    <label>{{ t('factions.image') }} <input v-model="faction.image" placeholder="/images/factions/example.webp" /></label>
                    <div class="faction-file-upload"><input id="new-faction-image-file" class="faction-file-input" type="file" accept="image/jpeg,image/png,image/webp,image/gif" @change="setImageFile(faction, $event)" /><label class="button button-outline faction-file-button" for="new-faction-image-file">{{ t('factionEditor.imageChoose') }}</label><span class="faction-file-name">{{ faction.image_file?.name || t('factionEditor.imageNone') }}</span></div><span class="field-error">{{ faction.errors.image || faction.errors.image_file }}</span>
                    <button class="button button-primary" :disabled="faction.processing">{{ t('factions.create') }}</button>
                </form>
            </section>
            <section class="data-panel"><h2>{{ t('factions.diplomacy') }}</h2><p>{{ t('factions.hint') }}</p>
                <form class="auth-form" @submit.prevent="saveDiplomacy">
                    <label>{{ t('factions.faction') }} <select v-model="diplomacy.first"><option v-for="item in factions" :key="item.key" :value="item.key">{{ item.name }}</option></select></label>
                    <label>{{ t('factions.other') }} <select v-model="diplomacy.second" required><option value="" disabled>{{ t('factions.choose') }}</option><option v-for="item in factions.filter(item => item.key !== diplomacy.first)" :key="item.key" :value="item.key">{{ item.name }}</option></select></label>
                    <label>{{ t('factions.relation') }} <select v-model="diplomacy.status"><option value="war">{{ t('factions.war') }}</option><option value="neutral">{{ t('factions.neutral') }}</option><option value="allied">{{ t('factions.allied') }}</option></select></label>
                    <span class="field-error">{{ diplomacy.errors.first || diplomacy.errors.second || diplomacy.errors.status }}</span><button class="button button-primary" :disabled="diplomacy.processing">{{ t('factions.save') }}</button>
                </form>
                <p v-for="relation in relations" :key="`${relation.first_faction}-${relation.second_faction}`" class="faction-relation">{{ factions.find(item => item.key === relation.first_faction)?.name }} / {{ factions.find(item => item.key === relation.second_faction)?.name }} : {{ t(`factions.${relation.status}`) }}</p>
            </section>
            <section class="data-panel"><h2>{{ t('factions.roster') }}</h2><p v-for="item in factions" :key="item.key" class="faction-roster-row"><span>{{ item.name }} : {{ item.members }}</span><button class="button button-outline" type="button" @click="editFaction(item)">{{ t('factions.configure') }}</button></p>
                <form v-if="editKey !== null" class="auth-form" @submit.prevent="saveSettings">
                    <label>{{ t('factions.name') }} <input v-model="editing.name" required maxlength="80" /></label><label>{{ t('factions.description') }} <textarea v-model="editing.description" maxlength="2000" /></label><label>{{ t('factions.image') }} <input v-model="editing.image" /></label>
                    <div class="faction-file-upload"><input id="edit-faction-image-file" class="faction-file-input" type="file" accept="image/jpeg,image/png,image/webp,image/gif" @change="setImageFile(editing, $event)" /><label class="button button-outline faction-file-button" for="edit-faction-image-file">{{ t('factionEditor.imageChoose') }}</label><span class="faction-file-name">{{ editing.image_file?.name || t('factionEditor.imageNone') }}</span></div><span class="field-error">{{ editing.errors.image || editing.errors.image_file }}</span>
                    <fieldset class="faction-editor-statistics">
                        <legend>{{ t('factionEditor.statistics') }}</legend>
                        <label v-for="statistic in factionStatistics" :key="statistic">{{ t(`factionEditor.${statistic}`) }}
                            <input v-model.number="editor.statistics[statistic]" type="number" min="0.1" max="10" step="any" required />
                        </label>
                        <p>{{ t('factionEditor.statisticsHelp') }}</p>
                    </fieldset>
                    <h3>{{ t('factionEditor.elements') }}</h3>
                    <p>{{ t('factionEditor.elementsHelp') }}</p>
                    <details v-for="category in factionCategories" :key="category" class="faction-editor-category">
                        <summary>{{ t(`factionEditor.${category}`) }} <span>{{ catalog[category].filter(item => editor.elements[category][item.key].available).length }} / {{ catalog[category].length }}</span></summary>
                        <div v-for="item in catalog[category]" :key="item.key" class="faction-editor-element">
                            <label class="faction-editor-availability">
                                <input v-model="editor.elements[category][item.key].available" type="checkbox" />
                                <span>{{ elementName(category, item) }} <small>#{{ item.code }}</small></span>
                                <span class="faction-editor-state">{{ t(editor.elements[category][item.key].available ? 'factionEditor.available' : 'factionEditor.unavailable') }}</span>
                            </label>
                            <details class="faction-editor-overrides">
                                <summary>{{ t('factionEditor.customize') }}</summary>
                                <label>{{ t('factionEditor.customName') }}
                                    <input v-model="editor.elements[category][item.key].name" type="text" maxlength="80" :placeholder="elementName(category, item)" />
                                </label>
                                <label>{{ t('factionEditor.customImage') }}
                                    <input v-model="editor.elements[category][item.key].image" type="text" maxlength="255" placeholder="/images/factions/example.webp" />
                                </label>
                            </details>
                        </div>
                    </details>
                    <span v-for="(error, key) in editing.errors" :key="key" class="field-error">{{ error }}</span><button class="button button-primary" :disabled="editing.processing">{{ t('factions.save') }}</button>
                </form>
            </section>
        </section></main>
    </GameLayout>
</template>
