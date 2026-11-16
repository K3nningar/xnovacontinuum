<script setup>
import { useI18n } from 'vue-i18n';

defineProps({
    rarity: { type: String, required: true },
});

const { t } = useI18n();
</script>

<template>
    <span class="rarity-badge" :class="`rarity-${rarity}`">{{ t(`rarities.${rarity}`) }}</span>
</template>
