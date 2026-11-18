<!--
Author / Contributor : K3nningar
Code Version : 0.1.0-alpha.4
Revision : 34ddc84
Description : Renders the Declarations Inertia page.
-->

<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route as ziggyRoute } from 'ziggy-js';
import GameLayout from '../../Layouts/GameLayout.vue';

defineProps({
    declarations: { type: Object, required: true },
});

const page = usePage();
const { t } = useI18n();
const route = (name) => ziggyRoute(name, undefined, true, page.props.ziggy);

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
                <div class="command-heading"><div><h1>{{ t('multiAccounts.title') }}</h1><p class="command-subtitle">{{ t('multiAccounts.notice') }}</p></div></div>
                <Link class="button button-outline self-start" :href="route('admin.index')">{{ t('multiAccounts.back') }}</Link>
                <section class="rounded border border-slate-700 bg-slate-900/70 p-4 sm:p-6">
                    <p v-if="!declarations.data.length" class="text-slate-400">{{ t('multiAccounts.empty') }}</p>
                    <ol v-else class="flex flex-col gap-3">
                        <li v-for="item in declarations.data" :key="item.id" class="rounded border border-slate-700 p-3">
                            <p class="text-sm text-slate-400">{{ item.created_at }} · {{ t('multiAccounts.universe') }} #{{ item.universe_id }}</p>
                            <p>{{ t('multiAccounts.declarant') }}: {{ item.declarant_name }} (#{{ item.declarant_id }})</p>
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
