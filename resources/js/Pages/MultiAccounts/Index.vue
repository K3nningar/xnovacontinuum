<!--
Author / Contributor : K3nningar
Code Version : 0.1.0-alpha.5
Revision : 34ddc84
Description : Renders the Index Inertia page.
-->

<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route as ziggyRoute } from 'ziggy-js';
import GameLayout from '../../Layouts/GameLayout.vue';

defineProps({
    canDeclare: { type: Boolean, required: true },
    declarations: { type: Object, required: true },
});

const page = usePage();
const { t } = useI18n();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const form = useForm({ player_ids: ['', '', ''], reason: '' });

function submit() {
    form.transform((data) => ({
        ...data,
        player_ids: data.player_ids.filter((id) => String(id).trim() !== ''),
    })).post(route('multi-accounts.store'), { onSuccess: () => form.reset() });
}

function paginationLabel(label, index, length) {
    if (index === 0) return `« ${t('admin.previous')}`;
    if (index === length - 1) return `${t('admin.next')} »`;
    return label.replace(/<[^>]*>/g, '');
}
</script>

<template>
    <Head :title="t('multiAccounts.title')" />
    <GameLayout>
        <main class="command-layout">
            <section class="command-content flex flex-col gap-6">
                <div class="command-heading"><div><h1>{{ t('multiAccounts.title') }}</h1><p class="command-subtitle">{{ t('multiAccounts.subtitle') }}</p></div></div>
                <section class="rounded border border-slate-700 bg-slate-900/70 p-4 sm:p-6">
                    <p>{{ t('multiAccounts.yourId', { id: page.props.auth.user.id }) }}</p>
                    <p class="mt-2 text-slate-400">{{ t('multiAccounts.explanation') }}</p>
                    <p v-if="!canDeclare" class="mt-4 text-slate-400">{{ t('multiAccounts.noUniverse') }}</p>
                    <form v-else class="mt-4 flex max-w-lg flex-col gap-4" @submit.prevent="submit">
                        <div v-if="Object.keys(form.errors).length" class="form-alert" role="alert"><p v-for="(error, field) in form.errors" :key="field">{{ error }}</p></div>
                        <label v-for="index in 3" :key="index" class="flex flex-col gap-1" :for="`multi-player-${index}`">
                            {{ t('multiAccounts.playerId', { number: index }) }} <span v-if="index > 1">({{ t('multiAccounts.optional') }})</span>
                            <input :id="`multi-player-${index}`" v-model="form.player_ids[index - 1]" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" type="number" min="1" step="1" :required="index === 1">
                        </label>
                        <label class="flex flex-col gap-1" for="multi-reason">{{ t('multiAccounts.reason') }}
                            <textarea id="multi-reason" v-model="form.reason" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" rows="3" maxlength="500" required></textarea>
                        </label>
                        <button class="button button-primary self-start" type="submit" :disabled="form.processing">{{ form.processing ? t('multiAccounts.saving') : t('multiAccounts.submit') }}</button>
                    </form>
                </section>
                <section class="rounded border border-slate-700 bg-slate-900/70 p-4 sm:p-6">
                    <h2 class="mb-4 text-lg font-semibold text-white">{{ t('multiAccounts.history') }}</h2>
                    <p v-if="!declarations.data.length" class="text-slate-400">{{ t('multiAccounts.empty') }}</p>
                    <ol v-else class="flex flex-col gap-3">
                        <li v-for="item in declarations.data" :key="item.id" class="rounded border border-slate-700 p-3">
                            <p class="text-sm text-slate-400">{{ item.created_at }} · {{ t('multiAccounts.universe') }} #{{ item.universe_id }}</p>
                            <p>{{ t('multiAccounts.players') }}: <span v-for="(player, index) in item.players" :key="player.id">{{ index ? ', ' : '' }}{{ player.name }} (#{{ player.id }})</span></p>
                            <p class="mt-2 whitespace-pre-wrap break-words">{{ item.reason }}</p>
                        </li>
                    </ol>
                    <nav v-if="declarations.links?.length > 3" class="mt-4 flex flex-wrap gap-2" :aria-label="t('multiAccounts.pagination')">
                        <template v-for="(link, index) in declarations.links" :key="index">
                            <Link v-if="link.url" class="rounded border border-slate-700 px-3 py-1" :class="{ 'border-cyan-400 bg-slate-800': link.active }" :href="link.url" :aria-current="link.active ? 'page' : undefined">{{ paginationLabel(link.label, index, declarations.links.length) }}</Link>
                            <span v-else class="rounded border border-slate-800 px-3 py-1 text-slate-500" aria-disabled="true">{{ paginationLabel(link.label, index, declarations.links.length) }}</span>
                        </template>
                    </nav>
                </section>
            </section>
        </main>
    </GameLayout>
</template>
