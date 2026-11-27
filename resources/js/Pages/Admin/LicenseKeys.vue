<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route as ziggyRoute } from 'ziggy-js';
import GameLayout from '../../Layouts/GameLayout.vue';

defineProps({
    licenseKeys: { type: Object, required: true },
    generatedKeys: { type: Array, default: () => [] },
});

const page = usePage();
const { t } = useI18n();
const route = (name) => ziggyRoute(name, undefined, true, page.props.ziggy);
const form = useForm({ quantity: 1 });

function generateKeys() {
    form.post(route('admin.license-keys.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="t('licenseKeys.title')" />
    <GameLayout>
        <main class="command-layout admin-page">
            <section class="command-content flex flex-col gap-6">
                <div class="command-heading">
                    <div>
                        <p class="eyebrow"><span class="eyebrow-line"></span> {{ t('admin.eyebrow') }}</p>
                        <h1>{{ t('licenseKeys.title') }}</h1>
                        <p class="command-subtitle">{{ t('licenseKeys.hint') }}</p>
                    </div>
                    <Link class="button button-outline" :href="route('admin.index')">{{ t('admin.title') }}</Link>
                </div>

                <section class="rounded border border-slate-700 bg-slate-900/70 p-4 sm:p-6">
                    <form class="flex max-w-2xl flex-col gap-4 sm:flex-row sm:items-end" @submit.prevent="generateKeys">
                        <label class="flex flex-col gap-1 text-sm" for="license-key-quantity">
                            {{ t('licenseKeys.quantity') }}
                            <input id="license-key-quantity" v-model.number="form.quantity" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" type="number" min="1" max="50" step="1" required>
                        </label>
                        <p v-if="form.errors.quantity" class="field-error" role="alert">{{ form.errors.quantity }}</p>
                        <button class="button button-primary self-start" type="submit" :disabled="form.processing">
                            {{ form.processing ? t('admin.saving') : t('licenseKeys.generate') }}
                        </button>
                    </form>
                </section>

                <section v-if="generatedKeys.length" class="rounded border border-emerald-700/60 bg-slate-900/70 p-4 sm:p-6" role="status">
                    <h2 class="mb-2 text-lg font-semibold text-white">{{ t('licenseKeys.generated') }}</h2>
                    <p class="mb-4 text-sm text-slate-300">{{ t('licenseKeys.oneTime') }}</p>
                    <ul class="flex flex-col gap-2">
                        <li v-for="key in generatedKeys" :key="key">
                            <code class="select-all rounded bg-slate-950 px-3 py-2 text-sm text-emerald-200">{{ key }}</code>
                        </li>
                    </ul>
                </section>

                <section class="rounded border border-slate-700 bg-slate-900/70 p-4 sm:p-6">
                    <h2 class="mb-4 text-lg font-semibold text-white">{{ t('licenseKeys.history') }}</h2>
                    <p v-if="!licenseKeys.data.length" class="text-slate-400">{{ t('licenseKeys.empty') }}</p>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr>
                                    <th class="pb-2">{{ t('licenseKeys.createdAt') }}</th>
                                    <th class="pb-2">{{ t('licenseKeys.status') }}</th>
                                    <th class="pb-2">{{ t('licenseKeys.usedBy') }}</th>
                                    <th class="pb-2">{{ t('licenseKeys.redeemedAt') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="key in licenseKeys.data" :key="key.id" class="border-t border-slate-700">
                                    <td class="py-2">{{ key.created_at }}</td>
                                    <td>{{ t(key.redeemed_at ? 'licenseKeys.used' : 'licenseKeys.available') }}</td>
                                    <td>{{ key.redeemed_by ?? '—' }}</td>
                                    <td>{{ key.redeemed_at ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <nav v-if="licenseKeys.prev_page_url || licenseKeys.next_page_url" class="mt-4 flex gap-2" :aria-label="t('licenseKeys.pagination')">
                        <Link v-if="licenseKeys.prev_page_url" class="button button-outline" :href="licenseKeys.prev_page_url">{{ t('admin.previous') }}</Link>
                        <Link v-if="licenseKeys.next_page_url" class="button button-outline" :href="licenseKeys.next_page_url">{{ t('admin.next') }}</Link>
                    </nav>
                </section>
            </section>
        </main>
    </GameLayout>
</template>
