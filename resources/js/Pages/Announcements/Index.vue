<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import GameLayout from '../../Layouts/GameLayout.vue';

defineProps({
    announcements: { type: Object, required: true },
});

const { t } = useI18n();

function paginationLabel(label, index, length) {
    if (index === 0) return `« ${t('admin.previous')}`;
    if (index === length - 1) return `${t('admin.next')} »`;
    return label.replace(/<[^>]*>/g, '');
}
</script>

<template>
    <Head :title="t('announcements.title')" />
    <GameLayout>
        <main class="command-layout">
            <section class="command-content flex flex-col gap-6">
                <div class="command-heading">
                    <div><h1>{{ t('announcements.title') }}</h1><p class="command-subtitle">{{ t('announcements.subtitle') }}</p></div>
                </div>
                <section class="rounded border border-slate-700 bg-slate-900/70 p-4 sm:p-6">
                    <p v-if="!announcements.data.length" class="text-slate-400">{{ t('announcements.empty') }}</p>
                    <ol v-else class="flex flex-col gap-4">
                        <li v-for="announcement in announcements.data" :key="announcement.id" class="rounded border border-slate-700 p-4">
                            <p class="text-sm text-slate-400">{{ announcement.created_at }}</p>
                            <p class="mt-2 whitespace-pre-wrap break-words">{{ announcement.body }}</p>
                        </li>
                    </ol>
                    <nav v-if="announcements.links?.length > 3" class="mt-4 flex flex-wrap gap-2" :aria-label="t('announcements.pagination')">
                        <template v-for="(link, index) in announcements.links" :key="index">
                            <Link v-if="link.url" class="rounded border border-slate-700 px-3 py-1" :class="{ 'border-cyan-400 bg-slate-800': link.active }" :href="link.url" :aria-current="link.active ? 'page' : undefined">{{ paginationLabel(link.label, index, announcements.links.length) }}</Link>
                            <span v-else class="rounded border border-slate-800 px-3 py-1 text-slate-500" aria-disabled="true">{{ paginationLabel(link.label, index, announcements.links.length) }}</span>
                        </template>
                    </nav>
                </section>
            </section>
        </main>
    </GameLayout>
</template>
