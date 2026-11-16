<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import GameLayout from '../../Layouts/GameLayout.vue';

const props = defineProps({
    planet: { type: Object, required: true },
    moon: { type: Object, required: true },
    ships: { type: Array, required: true },
    fleetSpeed: { type: Number, required: true },
});
const page = usePage();
const { t, n } = useI18n();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const form = useForm({ direction: 'deposit', resource: 'metal', amount: 1, ships: { 202: 0, 203: 0, 209: 0 } });
const capacities = { 202: 5000, 203: 25000, 209: 20000 };
const capacity = computed(() => Object.entries(form.ships).reduce((sum, [code, count]) => sum + Number(count || 0) * capacities[code], 0));
const fuel = computed(() => Object.values(form.ships).reduce((sum, count) => sum + Number(count || 0), 0));
const flight = computed(() => props.fleetSpeed > 0 ? Math.max(60, Math.ceil(300 / props.fleetSpeed)) : '—');
const format = (amount) => n(Number(amount), 'game');

function submit() {
    form.post(route('moon.transfer', { planet: props.planet.id, orbit: props.moon.orbit }));
}
</script>

<template>
    <div>
        <Head :title="t('moonLogistics.title')" />
        <GameLayout>
            <main class="command-layout">
                <section class="command-content">
                    <div class="command-heading">
                        <div>
                            <p class="eyebrow"><span class="eyebrow-line"></span>{{ t('moonLogistics.title') }}</p>
                            <h1>{{ t('galaxyMoon', { orbit: moon.orbit, diameter: format(moon.diameter) }) }}</h1>
                            <p class="command-subtitle">{{ planet.name }} [{{ planet.coordinates }}]</p>
                        </div>
                        <Link class="button button-outline" :href="route('galaxy.index', { planet: planet.id, galaxy: planet.galaxy, system: planet.system })">{{ t('moonLogistics.back') }}</Link>
                    </div>
                    <section class="data-panel moon-panel">
                        <div class="panel-heading"><h2>{{ t('moonLogistics.storage') }}</h2></div>
                        <div class="flex flex-wrap gap-6 p-4 text-sm">
                            <span>{{ t('common.metal') }} : {{ format(moon.metal) }}</span>
                            <span>{{ t('common.crystal') }} : {{ format(moon.crystal) }}</span>
                            <span>{{ t('common.deuterium') }} : {{ format(moon.deuterium) }}</span>
                        </div>
                    </section>
                    <form class="data-panel moon-panel mt-4 p-4" @submit.prevent="submit">
                        <h2 class="text-lg font-semibold">{{ t('moonLogistics.transfer') }}</h2>
                        <p class="mt-2 text-sm text-slate-400">{{ t('moonLogistics.reserved') }}</p>
                        <Link class="text-sm text-cyan-300 underline" :href="route('fleet.index', { planet: planet.id })">{{ t('fleet.inFlight') }}</Link>
                        <div class="mt-4 flex flex-wrap items-end gap-3">
                            <label class="flex flex-col gap-1 text-sm">
                                {{ t('moonLogistics.transfer') }}
                                <select v-model="form.direction" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white">
                                    <option value="deposit">{{ t('moonLogistics.deposit') }}</option>
                                    <option value="withdraw">{{ t('moonLogistics.withdraw') }}</option>
                                </select>
                            </label>
                            <label class="flex flex-col gap-1 text-sm">
                                {{ t('moonLogistics.resource') }}
                                <select v-model="form.resource" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white">
                                    <option value="metal">{{ t('common.metal') }}</option>
                                    <option value="crystal">{{ t('common.crystal') }}</option>
                                    <option value="deuterium">{{ t('common.deuterium') }}</option>
                                </select>
                            </label>
                            <label class="flex flex-col gap-1 text-sm">
                                {{ t('moonLogistics.amount') }}
                                <input v-model.number="form.amount" type="number" min="1" max="1000000000" required class="w-40 rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white">
                            </label>
                        </div>
                        <p class="mt-4 text-sm">{{ t('galaxyMissions.selectShips') }}</p>
                        <div class="mt-2 flex flex-wrap gap-3">
                            <label v-for="ship in ships" :key="ship.code" class="flex flex-col gap-1 text-sm">
                                {{ t('fleet.ship', { code: ship.code }) }} · {{ t('galaxyMissions.available', { count: format(ship.count) }) }}
                                <input v-model.number="form.ships[ship.code]" type="number" min="0" :max="ship.count" step="1" class="w-32 rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white">
                            </label>
                        </div>
                        <p class="mt-3 text-sm">{{ t('moonLogistics.capacity', { capacity: format(capacity) }) }} · {{ t('moonLogistics.fuel', { fuel: format(fuel) }) }} · {{ t('moonLogistics.flight', { seconds: flight }) }}</p>
                        <p v-for="(error, key) in form.errors" :key="key" role="alert" class="mt-2 text-sm text-red-300">{{ error }}</p>
                        <button class="button button-primary mt-4" type="submit" :disabled="form.processing">{{ form.processing ? t('moonLogistics.sending') : t('moonLogistics.send') }}</button>
                    </form>
                </section>
            </main>
        </GameLayout>
    </div>
</template>

<style scoped>
.moon-panel {
    min-height: 0;
}
</style>
