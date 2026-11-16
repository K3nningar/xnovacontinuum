<script setup>
import { useI18n } from 'vue-i18n';

defineProps({
    entries: { type: Array, required: true },
});

const { t } = useI18n();
const typeClasses = {
    'CHANGE': 'border-slate-400 text-white',
    'HOTFIX': 'border-yellow-400 text-yellow-300',
    'KNOWN ISSUE': 'border-red-400 text-red-300',
    'NEW': 'border-blue-400 text-blue-300',
};
</script>

<template>
    <ol class="flex flex-col gap-3">
        <li v-for="entry in entries" :key="entry.key" class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <time class="text-sm text-slate-400" :datetime="entry.date">{{ entry.date }}</time>
            <span class="rounded border px-2 py-0.5 text-xs font-bold" :class="typeClasses[entry.type]">{{ entry.type }}</span>
            <span class="whitespace-pre-wrap text-slate-200">{{ entry.text ?? t(`changelog.entries.${entry.key}`) }}</span>
        </li>
    </ol>
</template>
