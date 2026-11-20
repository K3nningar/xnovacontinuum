<!-- HUD-inspired player profile and account security settings. -->
<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import GameLayout from '../../Layouts/GameLayout.vue';

defineOptions({ layout: GameLayout });
const props = defineProps({ profile: { type: Object, required: true } });
const page = usePage();
const { t } = useI18n();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const user = computed(() => page.props.auth.user);
const avatarForm = useForm({ avatar: null });
const avatarPreview = computed(() => avatarForm.avatar instanceof File ? URL.createObjectURL(avatarForm.avatar) : props.profile.avatarUrl);
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
const setupForm = useForm({});
const enableForm = useForm({ code: '' });
const disableForm = useForm({ current_password: '', code: '' });

function uploadAvatar() {
    avatarForm.post(route('profile.avatar'), { forceFormData: true, preserveScroll: true, onSuccess: () => avatarForm.reset('avatar') });
}
function updatePassword() {
    passwordForm.patch(route('profile.password'), { preserveScroll: true, onSuccess: () => passwordForm.reset() });
}
</script>

<template>
    <Head :title="t('profile.title')" />
    <main class="command-layout profile-page">
        <section class="command-content">
            <header class="profile-cover">
                <div class="profile-cover-grid" aria-hidden="true"></div>
                <div class="profile-cover-content">
                    <div class="profile-large-avatar">
                        <img v-if="avatarPreview" :src="avatarPreview" :alt="t('profile.avatarAlt', { name: profile.name })">
                        <span v-else aria-hidden="true">{{ profile.name.slice(0, 1).toUpperCase() }}</span>
                    </div>
                    <div class="profile-identity"><p class="panel-kicker">{{ t('profile.commanderProfile') }}</p><h1>{{ profile.name }}</h1><p>{{ profile.email }}</p><span class="profile-status"><i aria-hidden="true"></i>{{ t('profile.activeCommander') }}</span></div>
                    <Link class="button button-outline profile-universes-link" :href="route('account.universes')"><i class="bi bi-globe2" aria-hidden="true"></i>{{ t('profile.myUniverses') }}</Link>
                </div>
            </header>

            <div class="profile-columns">
                <div class="profile-main-column">
                    <section class="data-panel profile-panel">
                        <div class="panel-heading"><div><p class="panel-kicker">{{ t('profile.account') }}</p><h2>{{ t('profile.avatarTitle') }}</h2></div><i class="bi bi-person-bounding-box profile-panel-icon" aria-hidden="true"></i></div>
                        <div class="profile-panel-body">
                            <p class="profile-description">{{ t('profile.avatarDescription') }}</p>
                            <div v-if="Object.keys(avatarForm.errors).length" class="form-alert" role="alert">{{ Object.values(avatarForm.errors)[0] }}</div>
                            <form class="profile-avatar-form" @submit.prevent="uploadAvatar">
                                <label class="profile-file-input"><i class="bi bi-upload" aria-hidden="true"></i><span>{{ t('profile.chooseAvatar') }}</span><input type="file" accept="image/png,image/jpeg,image/webp" @input="avatarForm.avatar = $event.target.files[0] ?? null"></label>
                                <span class="profile-file-hint">{{ t('profile.avatarFormats') }}</span>
                                <button class="button button-primary" type="submit" :disabled="!avatarForm.avatar || avatarForm.processing">{{ avatarForm.processing ? t('profile.saving') : t('profile.saveAvatar') }}</button>
                            </form>
                            <button v-if="profile.avatarUrl" class="button button-quiet profile-remove-avatar" type="button" @click="avatarForm.delete(route('profile.avatar.destroy'), { preserveScroll: true })">{{ t('profile.removeAvatar') }}</button>
                        </div>
                    </section>

                    <section class="data-panel profile-panel">
                        <div class="panel-heading"><div><p class="panel-kicker">{{ t('profile.security') }}</p><h2>{{ t('profile.passwordTitle') }}</h2></div><i class="bi bi-key profile-panel-icon" aria-hidden="true"></i></div>
                        <form class="profile-panel-body profile-form" @submit.prevent="updatePassword">
                            <p class="profile-description">{{ t('profile.passwordDescription') }}</p>
                            <label>{{ t('profile.currentPassword') }}<input v-model="passwordForm.current_password" type="password" autocomplete="current-password" required></label>
                            <span v-if="passwordForm.errors.current_password" class="field-error">{{ passwordForm.errors.current_password }}</span>
                            <label>{{ t('profile.newPassword') }}<input v-model="passwordForm.password" type="password" autocomplete="new-password" minlength="12" required></label>
                            <span v-if="passwordForm.errors.password" class="field-error">{{ passwordForm.errors.password }}</span>
                            <label>{{ t('profile.confirmPassword') }}<input v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" minlength="12" required></label>
                            <button class="button button-primary" type="submit" :disabled="passwordForm.processing">{{ passwordForm.processing ? t('profile.saving') : t('profile.updatePassword') }}</button>
                        </form>
                    </section>

                    <section class="data-panel profile-panel">
                        <div class="panel-heading"><div><p class="panel-kicker">{{ t('profile.security') }}</p><h2>{{ t('profile.twoFactorTitle') }}</h2></div><i class="bi bi-shield-lock profile-panel-icon" aria-hidden="true"></i></div>
                        <div class="profile-panel-body">
                            <div class="profile-security-status" :class="{ enabled: profile.twoFactorEnabled }"><span class="profile-security-dot"></span>{{ profile.twoFactorEnabled ? t('profile.twoFactorEnabled') : t('profile.twoFactorDisabled') }}</div>
                            <p class="profile-description">{{ t('profile.twoFactorDescription') }}</p>
                            <button v-if="!profile.twoFactorEnabled && !profile.pendingTwoFactorSecret" class="button button-primary" type="button" :disabled="setupForm.processing" @click="setupForm.post(route('profile.two-factor.setup'), { preserveScroll: true })">{{ t('profile.setupTwoFactor') }}</button>
                            <template v-else-if="!profile.twoFactorEnabled">
                                <div class="profile-totp-secret"><span>{{ t('profile.setupKey') }}</span><code>{{ profile.pendingTwoFactorSecret }}</code></div>
                                <a class="profile-totp-link" :href="profile.pendingTwoFactorUri">{{ t('profile.openAuthenticator') }}</a>
                                <p class="profile-description">{{ t('profile.setupInstruction') }}</p>
                                <form class="profile-form profile-inline-form" @submit.prevent="enableForm.post(route('profile.two-factor.enable'), { preserveScroll: true })">
                                    <label>{{ t('profile.authenticatorCode') }}<input v-model="enableForm.code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
                                    <span v-if="enableForm.errors.code" class="field-error">{{ enableForm.errors.code }}</span>
                                    <button class="button button-primary" type="submit" :disabled="enableForm.processing">{{ t('profile.enableTwoFactor') }}</button>
                                </form>
                            </template>
                            <form v-else class="profile-form profile-inline-form" @submit.prevent="disableForm.delete(route('profile.two-factor'), { preserveScroll: true })">
                                <label>{{ t('profile.currentPassword') }}<input v-model="disableForm.current_password" type="password" autocomplete="current-password" required></label>
                                <label>{{ t('profile.authenticatorCode') }}<input v-model="disableForm.code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
                                <span v-if="disableForm.errors.code" class="field-error">{{ disableForm.errors.code }}</span>
                                <button class="button button-quiet" type="submit" :disabled="disableForm.processing">{{ t('profile.disableTwoFactor') }}</button>
                            </form>
                        </div>
                    </section>
                </div>
                <aside class="profile-side-column">
                    <section class="data-panel profile-panel profile-achievements">
                        <div class="panel-heading"><div><p class="panel-kicker">{{ t('profile.progress') }}</p><h2>{{ t('profile.achievementsTitle') }}</h2></div><i class="bi bi-award profile-panel-icon" aria-hidden="true"></i></div>
                        <div class="profile-achievements-body"><div class="profile-achievement-emblem"><i class="bi bi-stars" aria-hidden="true"></i></div><strong>{{ t('profile.achievementsComing') }}</strong><p>{{ t('profile.achievementsDescription') }}</p><span class="profile-development-badge"><i class="bi bi-code-slash" aria-hidden="true"></i>{{ t('profile.inDevelopment') }}</span></div>
                    </section>
                    <section class="data-panel profile-panel">
                        <div class="panel-heading"><div><p class="panel-kicker">{{ t('profile.account') }}</p><h2>{{ t('profile.accountDetails') }}</h2></div></div>
                        <dl class="profile-details"><div><dt>{{ t('profile.email') }}</dt><dd>{{ profile.email }}</dd></div><div><dt>{{ t('profile.commanderId') }}</dt><dd>#{{ user.id }}</dd></div><div><dt>{{ t('profile.emailStatus') }}</dt><dd><span class="profile-detail-status" :class="{ 'is-unverified': !profile.emailVerified }">{{ profile.emailVerified ? t('profile.verified') : t('profile.unverified') }}</span></dd></div></dl>
                    </section>
                </aside>
            </div>
        </section>
    </main>
</template>
