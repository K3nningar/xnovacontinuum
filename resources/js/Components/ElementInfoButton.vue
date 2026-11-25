<!--
Author / Contributor : K3nningar
Code Version : 0.2.0-alpha
Revision : 34ddc84
Description : Renders the ElementInfoButton Vue component.
-->

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { sanitizeElementDescription } from '../sanitizeElementDescription.js';

const props = defineProps({
    id: { type: [Number, String], required: true },
    name: { type: String, required: true },
    description: { type: String, default: '' },
    imageSrc: { type: String, default: '' },
});
const { t } = useI18n();
const open = ref(false);
const description = computed(() => props.description || t(`elementDescriptions.${props.id}`, t('elementInfo.unavailable')));
// Every value destined for v-html is sanitized here, including translated labels.
const descriptionHtml = computed(() => sanitizeElementDescription(description.value));
const detailsHtml = computed(() => sanitizeElementDescription(t('elementInfo.details')));
const imageSrc = computed(() => {
    if (props.imageSrc) {
        return props.imageSrc;
    }

    const buildingCode = typeof props.id === 'number'
        ? String(props.id)
        : /^building_(\d+)$/.exec(props.id)?.[1];

    return buildingCode && /^\d+$/.test(buildingCode)
        ? `/legacy-assets/skins/xnova/gebaeude/${buildingCode}.gif`
        : '';
});

function closeOnEscape(event) {
    if (event.key === 'Escape') open.value = false;
}

watch(open, (isOpen) => {
    if (isOpen) window.addEventListener('keydown', closeOnEscape);
    else window.removeEventListener('keydown', closeOnEscape);
});
onBeforeUnmount(() => window.removeEventListener('keydown', closeOnEscape));
</script>

<template>
    <button class="element-info-trigger" type="button" :aria-label="t('elementInfo.open', { name })" :title="t('elementInfo.open', { name })" @click.stop="open = true">ⓘ</button>
    <Teleport to="body">
        <div v-if="open" class="element-info-backdrop" @click.self="open = false">
            <section class="element-info-modal" role="dialog" aria-modal="true" :aria-label="name" @click.stop>
                <header class="element-info-header"><div><span class="panel-kicker" v-html="detailsHtml"></span><h2>{{ name }}</h2></div><button class="element-info-close" type="button" :aria-label="t('elementInfo.close')" @click="open = false">×</button></header>
                <div class="element-info-body" :class="{ 'element-info-body-with-image': $slots.image || imageSrc }">
                    <slot name="image"><img v-if="imageSrc" :src="imageSrc" :alt="name" loading="lazy" @error="$event.currentTarget.hidden = true"></slot>
                    <div class="element-info-description" v-html="descriptionHtml"></div>
                </div>
            </section>
        </div>
    </Teleport>
</template>
