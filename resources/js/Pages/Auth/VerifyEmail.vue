<!--
Author / Contributor : K3nningar
Description : Prompts a newly registered commander to verify their email address.
-->

<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import LocaleSwitcher from '../../Components/LocaleSwitcher.vue';

const props = defineProps({
    email: { type: String, required: true },
    status: { type: String, default: null },
});
const page = usePage();
const { t } = useI18n();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const form = useForm({});

function resendVerificationEmail() {
    form.post(route('verification.send'), { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('auth.emailVerificationTitle')" />
    <div class="auth-shell">
        <div class="auth-topline">
            <Link class="brand" :href="route('home')"><span class="brand-mark" aria-hidden="true">X</span><span class="brand-copy"><strong>XNova</strong><small>Continuum</small></span></Link>
            <LocaleSwitcher />
        </div>
        <main class="auth-card">
            <p class="eyebrow"><span class="eyebrow-line"></span>{{ t('auth.emailVerificationEyebrow') }}</p>
            <h1>{{ t('auth.emailVerificationHeading') }}</h1>
            <p class="auth-intro">{{ t('auth.emailVerificationIntro', { email: props.email }) }}</p>
            <p class="auth-intro">{{ t('auth.emailVerificationInstructions') }}</p>
            <div v-if="props.status === 'verification-link-sent'" class="form-alert" role="status">
                {{ t('auth.emailVerificationSent') }}
            </div>
            <div v-if="Object.keys(form.errors).length" class="form-alert" role="alert">
                <p v-for="(error, field) in form.errors" :key="field">{{ error }}</p>
            </div>
            <form class="auth-form" @submit.prevent="resendVerificationEmail">
                <button class="button button-primary button-large" type="submit" :disabled="form.processing">
                    {{ form.processing ? t('auth.sendingVerificationEmail') : t('auth.resendVerificationEmail') }}
                    <span aria-hidden="true">→</span>
                </button>
            </form>
            <p class="auth-switch">
                <Link :href="route('home')">{{ t('auth.returnHome') }}</Link>
                <span aria-hidden="true"> · </span>
                <Link :href="route('logout')" method="post" as="button">{{ t('accountUniverses.logout') }}</Link>
            </p>
        </main>
        <span class="auth-footer">{{ t('auth.authFooter') }}</span>
    </div>
</template>
