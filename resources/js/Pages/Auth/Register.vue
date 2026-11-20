<!--
Author / Contributor : K3nningar
Code Version : 0.1.1-alpha
Revision : 34ddc84
Description : Renders the Register Inertia page.
-->

<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import LocaleSwitcher from '../../Components/LocaleSwitcher.vue';

const page = usePage();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const { t } = useI18n();
const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

function submit() {
    form.post(route('register'), { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head :title="t('auth.registerTitle')" />
    <div class="auth-shell">
        <div class="auth-topline"><Link class="brand" :href="route('home')"><span class="brand-mark" aria-hidden="true">X</span><span class="brand-copy"><strong>XNova</strong><small>Continuum</small></span></Link><LocaleSwitcher /></div>
        <main class="auth-card"><p class="eyebrow"><span class="eyebrow-line"></span> {{ t('auth.registerEyebrow') }}</p><h1>{{ t('auth.galaxyToConquer') }}<br><span>{{ t('auth.conquerAccent') }}</span></h1><p class="auth-intro">{{ t('auth.createIntro') }}</p><div v-if="Object.keys(form.errors).length" class="form-alert" role="alert">{{ t('auth.checkFields') }}</div>
            <form class="auth-form" @submit.prevent="submit"><label for="name">{{ t('auth.commanderName') }}</label><input id="name" v-model="form.name" type="text" autocomplete="name" maxlength="80" required autofocus><span v-if="form.errors.name" class="field-error">{{ form.errors.name }}</span><label for="email">{{ t('auth.email') }}</label><input id="email" v-model="form.email" type="email" autocomplete="email" required><span v-if="form.errors.email" class="field-error">{{ form.errors.email }}</span><label for="password">{{ t('auth.password') }} <span class="label-hint">{{ t('auth.passwordMin') }}</span></label><input id="password" v-model="form.password" type="password" autocomplete="new-password" required><span v-if="form.errors.password" class="field-error">{{ form.errors.password }}</span><label for="password_confirmation">{{ t('auth.confirmPassword') }}</label><input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required><button class="button button-primary button-large" type="submit" :disabled="form.processing">{{ form.processing ? t('auth.creating') : t('auth.createMyAccount') }} <span aria-hidden="true">→</span></button></form>
            <p class="auth-switch">{{ t('auth.alreadyCommander') }} <Link :href="route('login')">{{ t('auth.loginLink') }}</Link></p>
        </main><span class="auth-footer">{{ t('auth.authFooter') }}</span>
    </div>
</template>
