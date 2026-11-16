<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route as ziggyRoute } from 'ziggy-js';
import ChangelogEntries from '../Components/ChangelogEntries.vue';
import LocaleSwitcher from '../Components/LocaleSwitcher.vue';

const props = defineProps({
    releases: { type: Array, required: true },
    administration: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const route = (name) => ziggyRoute(name, undefined, true, page.props.ziggy);
const form = useForm({ version: '', published: false });
const entryForm = useForm({ version: props.releases[0]?.version ?? '', date: '', type: 'CHANGE', description: '' });

function changePublication(release) {
    form.version = release.version;
    form.published = !release.published;
    form.patch(route('admin.changelog.update'), { preserveScroll: true });
}

function addEntry() {
    entryForm.post(route('admin.changelog.entries.store'), {
        preserveScroll: true,
        onSuccess: () => entryForm.reset('description'),
    });
}
</script>

<template>
    <div class="universe-shell">
        <Head :title="t('changelog.title')" />
        <header class="topbar">
            <Link class="brand" :href="route('home')" :aria-label="t('welcome.homeLabel')"><span class="brand-mark" aria-hidden="true">X</span><span class="brand-copy"><strong>XNova</strong><small>Continuum</small></span></Link>
            <nav class="top-actions" :aria-label="t('welcome.primaryNav')">
                <Link v-if="page.props.auth?.user?.admin_level >= 1 && page.props.auth?.user?.admin_level <= 4" class="button button-quiet" :href="route('admin.index')">{{ t('changelog.backAdmin') }}</Link>
                <Link class="button button-quiet" :href="route('home')">{{ t('changelog.backHome') }}</Link>
                <LocaleSwitcher />
            </nav>
        </header>
        <main class="discover-section">
            <div class="section-heading">
                <p class="eyebrow"><span class="eyebrow-line"></span> {{ t('changelog.eyebrow') }}</p>
                <h1 class="text-3xl font-semibold text-white sm:text-4xl">{{ t('changelog.title') }}</h1>
                <p class="mt-3 text-slate-400">{{ t('changelog.description') }}</p>
            </div>
            <p v-if="form.errors.version || form.errors.published" class="field-error mb-4" role="alert">{{ form.errors.version || form.errors.published }}</p>
            <p v-if="form.recentlySuccessful" class="mb-4 text-green-300" role="status">{{ t('changelogPublication.saved') }}</p>
            <section v-if="administration && page.props.auth?.user?.admin_level >= 2 && releases.length" class="mb-5 rounded border border-slate-700 bg-slate-900/70 p-4 sm:p-6">
                <h2 class="mb-2 text-xl font-semibold text-white">{{ t('changelogManual.title') }}</h2>
                <p class="mb-4 text-sm text-slate-400">{{ t('changelogManual.hint') }}</p>
                <form class="flex flex-col gap-4" @submit.prevent="addEntry">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="flex flex-col gap-1">
                            <label for="entry-version">{{ t('changelogManual.version') }}</label>
                            <select id="entry-version" v-model="entryForm.version" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" required>
                                <option v-for="release in releases" :key="release.version" :value="release.version">{{ release.version }} · {{ t(release.published ? 'changelogPublication.published' : 'changelog.unreleased') }}</option>
                            </select>
                            <p v-if="entryForm.errors.version" class="field-error" role="alert">{{ entryForm.errors.version }}</p>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label for="entry-date">{{ t('changelogManual.date') }}</label>
                            <input id="entry-date" v-model="entryForm.date" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" type="date" required>
                            <p v-if="entryForm.errors.date" class="field-error" role="alert">{{ entryForm.errors.date }}</p>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label for="entry-type">{{ t('changelogManual.type') }}</label>
                            <select id="entry-type" v-model="entryForm.type" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" required>
                                <option v-for="type in ['CHANGE', 'HOTFIX', 'KNOWN ISSUE', 'NEW']" :key="type" :value="type">{{ type }}</option>
                            </select>
                            <p v-if="entryForm.errors.type" class="field-error" role="alert">{{ entryForm.errors.type }}</p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="entry-description">{{ t('changelogManual.description') }}</label>
                        <textarea id="entry-description" v-model="entryForm.description" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" maxlength="2000" rows="3" required></textarea>
                        <p v-if="entryForm.errors.description" class="field-error" role="alert">{{ entryForm.errors.description }}</p>
                    </div>
                    <div>
                        <button class="button button-primary" type="submit" :disabled="entryForm.processing">{{ t('changelogManual.add') }}</button>
                        <p v-if="entryForm.recentlySuccessful" class="mt-2 text-green-300" role="status">{{ t('changelogManual.saved') }}</p>
                    </div>
                </form>
            </section>
            <p v-if="!releases.length" class="text-slate-400">{{ t('changelogPublication.empty') }}</p>
            <div v-else class="flex flex-col gap-5">
                <section v-for="release in releases" :key="release.version" class="rounded border border-slate-700 bg-slate-900/70 p-4 sm:p-6">
                    <h2 class="mb-2 text-xl font-semibold text-white">{{ release.version }} <span v-if="administration" class="text-sm font-normal text-slate-400">· {{ t(release.published ? 'changelogPublication.published' : 'changelog.unreleased') }}</span></h2>
                    <p v-if="release.baseline" class="mb-5 text-sm text-slate-400">{{ t('changelogPublication.baseline') }}</p>
                    <ChangelogEntries :entries="release.entries" />
                    <button v-if="administration" class="button button-outline mt-5" type="button" :disabled="form.processing" @click="changePublication(release)">{{ t(release.published ? 'changelogPublication.unpublish' : 'changelogPublication.publish') }}</button>
                </section>
            </div>
        </main>
        <footer class="footer"><span>{{ t('nav.footer') }}</span><span>© {{ new Date().getFullYear() }} XNova</span></footer>
    </div>
</template>
