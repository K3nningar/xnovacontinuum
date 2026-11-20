<!--
Author / Contributor : K3nningar
Code Version : 0.1.0-alpha.5
Revision : 34ddc84
Description : Renders the Login Inertia page.
-->

<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import LocaleSwitcher from '../../Components/LocaleSwitcher.vue';

const page = usePage();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const { t } = useI18n();
const form = useForm({ email: '', password: '', remember: false });
const challengeForm = useForm({ code: '' });
const twoFactorChallenge = computed(() => Boolean(page.props.auth?.twoFactorChallenge));

function submit() {
    if (twoFactorChallenge.value) {
        challengeForm.post(route('login'), {
            preserveState: true,
            onSuccess: () => challengeForm.reset(),
        });

        return;
    }

    form.post(route('login'), {
        preserveState: true,
        onSuccess: () => {
            form.reset('password');
            form.clearErrors();
        },
    });
}

function restartLogin() {
    form.reset('email', 'password');
    challengeForm.reset();
    form.clearErrors();
    challengeForm.clearErrors();
    form.get(route('login', { restart_two_factor: 1 }), { preserveState: true, replace: true });
}
</script>

<template>
    <Head :title="t('auth.loginTitle')" />
    <div class="auth-shell">
        <div class="auth-topline"><Link class="brand" :href="route('home')"><span class="brand-mark" aria-hidden="true">X</span><span class="brand-copy"><strong>XNova</strong><small>Continuum</small></span></Link><LocaleSwitcher /></div>
        <main class="auth-card"><p class="eyebrow"><span class="eyebrow-line"></span> {{ t('auth.loginEyebrow') }}</p><h1>{{ t('auth.welcomeBack') }}<br><span>{{ t('auth.commander') }}</span></h1><p class="auth-intro">{{ t('auth.universeWaits') }}</p>
            <div v-if="page.props.flash?.error" class="form-alert" role="alert">{{ page.props.flash.error }}</div>
            <form class="auth-form" @submit.prevent="submit">
                <template v-if="!twoFactorChallenge">
                    <label for="email">{{ t('auth.email') }}</label>
                    <input id="email" v-model="form.email" type="email" autocomplete="email" required autofocus>
                    <span v-if="form.errors.email" class="field-error">{{ form.errors.email }}</span>
                    <label for="password">{{ t('auth.password') }}</label>
                    <input id="password" v-model="form.password" type="password" autocomplete="current-password" required>
                    <span v-if="form.errors.password" class="field-error">{{ form.errors.password }}</span>
                    <label class="remember-field"><input v-model="form.remember" type="checkbox"> {{ t('auth.remember') }}</label>
                </template>
                <template v-else>
                    <p class="auth-intro">{{ t('auth.twoFactorPrompt') }}</p>
                    <button class="text-link" type="button" @click="restartLogin">{{ t('auth.restartLogin') }}</button>
                    <label for="code">{{ t('auth.twoFactorCode') }}</label>
                    <input id="code" v-model="challengeForm.code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus>
                    <span v-if="challengeForm.errors.code" class="field-error">{{ challengeForm.errors.code }}</span>
                </template>
                <button class="button button-primary button-large" type="submit" :disabled="twoFactorChallenge ? challengeForm.processing : form.processing">{{ (twoFactorChallenge ? challengeForm.processing : form.processing) ? t('auth.loggingIn') : twoFactorChallenge ? t('auth.verifyTwoFactor') : t('auth.secureLogin') }} <span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-switch">{{ t('auth.newGalaxy') }} <Link :href="route('register')">{{ t('auth.createAccount') }}</Link></p>
        </main><span class="auth-footer">{{ t('auth.authFooter') }}</span>
    </div>
</template>
