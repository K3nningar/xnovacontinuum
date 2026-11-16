<script setup>
import { useI18n } from 'vue-i18n';
import { localeOptions, setLocale } from '../i18n';

const { locale, t } = useI18n();

function updateLocale(event) {
    setLocale(event.target.value);
}
</script>

<template>
    <label class="language-picker">
        <span class="sr-only">{{ t('common.language') }}</span>
        <select :value="locale" :aria-label="t('common.language')" @change="updateLocale">
            <option v-for="option in localeOptions" :key="option.code" :value="option.code">{{ option.code.toUpperCase() }}</option>
        </select>
    </label>
</template>
