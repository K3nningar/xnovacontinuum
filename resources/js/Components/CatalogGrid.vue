<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { catalogCategories } from '../catalogFilters.js';

const props = defineProps({
    items: { type: Array, required: true },
    groups: { type: Array, required: true },
    modelValue: { type: String, required: true },
});
const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();
const categories = computed(() => catalogCategories(props.items, props.groups));
</script>

<template>
    <div class="pos building-pos">
        <div class="pos-container">
            <nav class="pos-menu building-filters" :aria-label="t('buildingFilters.label')">
                <span class="panel-kicker">{{ t('buildingFilters.label') }}</span>
                <button v-for="category in categories" :key="category.key" type="button" class="building-filter" :class="{ active: modelValue === category.key }" :aria-pressed="modelValue === category.key" @click="emit('update:modelValue', category.key)">
                    <i class="bi" :class="category.icon" aria-hidden="true"></i>
                    <span>{{ t(`catalogFilters.${category.key}`) }}</span>
                    <small>{{ category.count }}</small>
                </button>
            </nav>
            <div class="pos-content building-catalog">
                <div class="building-pos-grid"><slot :categories="categories" /></div>
            </div>
        </div>
    </div>
</template>
