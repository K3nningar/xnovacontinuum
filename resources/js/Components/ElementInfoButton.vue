<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({ id: { type: [Number, String], required: true }, name: { type: String, required: true }, description: { type: String, default: '' } });
const { t } = useI18n();
const open = ref(false);
const description = computed(() => props.description || t(`elementDescriptions.${props.id}`, t('elementInfo.unavailable')));

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
                <header class="element-info-header"><div><span class="panel-kicker">{{ t('elementInfo.details') }}</span><h2>{{ name }}</h2></div><button class="element-info-close" type="button" :aria-label="t('elementInfo.close')" @click="open = false">×</button></header>
                <div class="element-info-body" :class="{ 'element-info-body-with-image': $slots.image || typeof id === 'number' || /^building_/.test(id) }"><slot name="image"><img v-if="typeof id === 'number' || /^building_/.test(id)" :src="`/legacy-assets/skins/xnova/gebaeude/${typeof id === 'number' ? id : id.replace('building_', '')}.gif`" :alt="name" loading="lazy" @error="$event.currentTarget.hidden = true"></slot><div><p v-for="(paragraph, index) in description.split(/\n\s*\n/)" :key="index">{{ paragraph }}</p></div></div>
            </section>
        </div>
    </Teleport>
</template>
