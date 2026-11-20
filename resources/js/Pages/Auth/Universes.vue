<!--
Author / Contributor : K3nningar
Description : Lets an authenticated commander choose an existing empire or start in an active universe.
-->

<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import LocaleSwitcher from '../../Components/LocaleSwitcher.vue';

const props = defineProps({
    universes: { type: Array, default: () => [] },
    accountId: { type: Number, default: null },
});
const page = usePage();
const { t } = useI18n();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const form = useForm({ universe_id: null, account_id: props.accountId });

function choose(universe) {
    form.universe_id = universe.id;
    form.post(route('account.universes.select'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('accountUniverses.title')" />
    <div class="auth-shell">
        <div class="auth-topline">
            <Link class="brand" :href="route('home')"><span class="brand-mark" aria-hidden="true">X</span><span class="brand-copy"><strong>XNova</strong><small>Continuum</small></span></Link>
            <LocaleSwitcher />
        </div>
        <main class="auth-card">
            <p class="eyebrow"><span class="eyebrow-line"></span>{{ t('accountUniverses.eyebrow') }}</p>
            <h1>{{ t('accountUniverses.title') }}</h1>
            <p class="auth-intro">{{ t('accountUniverses.hint') }}</p>
            <div v-if="Object.keys(form.errors).length" class="form-alert" role="alert">
                <p v-for="(error, field) in form.errors" :key="field">{{ error }}</p>
            </div>
            <ul v-if="universes.length" class="flex flex-col gap-3">
                <li v-for="universe in universes" :key="universe.id" class="rounded border border-slate-700 bg-slate-950/70 p-4">
                    <p class="panel-kicker">{{ universe.hasEmpire ? t('accountUniverses.existing') : t('accountUniverses.new') }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-white">{{ universe.name }}</h2>
                    <p class="text-sm text-slate-400">{{ t('dashboard.galaxiesSystems', { galaxies: universe.galaxies, systems: universe.systems_per_galaxy }) }}</p>
                    <p v-if="universe.profileName" class="mt-1 text-sm text-slate-300">{{ t('accountUniverses.empire', { name: universe.profileName }) }}</p>
                    <button class="button button-primary button-large mt-4 w-full" type="button" :disabled="form.processing" @click="choose(universe)">
                        {{ form.processing && form.universe_id === universe.id ? t('accountUniverses.loading') : universe.hasEmpire ? t('accountUniverses.resume') : t('accountUniverses.start') }}
                        <span aria-hidden="true">→</span>
                    </button>
                </li>
            </ul>
            <div v-else class="empty-state"><span class="empty-orb">◈</span><strong>{{ t('dashboard.noUniverses') }}</strong><p>{{ t('dashboard.returnSoon') }}</p></div>
            <form class="mt-5" @submit.prevent="form.post(route('logout'))">
                <button class="button button-outline w-full" type="submit">{{ t('accountUniverses.logout') }}</button>
            </form>
        </main>
        <span class="auth-footer">{{ t('auth.authFooter') }}</span>
    </div>
</template>
