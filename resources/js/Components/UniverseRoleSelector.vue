<script setup>
const props = defineProps({ modelValue: { type: Array, default: () => [] }, universes: { type: Array, required: true } });
const emit = defineEmits(['update:modelValue']);
function toggle(id, checked) {
    const ids = new Set(props.modelValue.map(Number));
    checked ? ids.add(Number(id)) : ids.delete(Number(id));
    emit('update:modelValue', [...ids]);
}
</script>
<template>
    <fieldset class="flex flex-col gap-2">
        <legend class="text-sm font-medium text-white">Univers autorisés</legend>
        <label v-for="universe in universes" :key="universe.id" class="flex items-center gap-2 text-sm text-slate-200">
            <input type="checkbox" :checked="modelValue.map(Number).includes(Number(universe.id))" @change="toggle(universe.id, $event.target.checked)">
            <span>{{ universe.name }}</span>
        </label>
        <p v-if="!universes.length" class="text-sm text-slate-400">Aucun univers disponible.</p>
    </fieldset>
</template>
