<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route as ziggyRoute } from 'ziggy-js';
import ChangelogEntries from '../Components/ChangelogEntries.vue';
import LocaleSwitcher from '../Components/LocaleSwitcher.vue';

defineProps({
    releases: { type: Array, required: true },
    administration: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const route = (name) => ziggyRoute(name, undefined, true, page.props.ziggy);
const form = useForm({ version: '', published: false });

function changePublication(release) {
    form.version = release.version;
    form.published = !release.published;
    form.patch(route('admin.changelog.update'), { preserveScroll: true });
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
