<!--
Author / Contributor : K3nningar
Code Version : 0.2.0-alpha
Revision : 34ddc84
Description : Renders the Index Inertia page.
-->

<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route as ziggyRoute } from 'ziggy-js';
import GameLayout from '../../Layouts/GameLayout.vue';
import UniverseRoleSelector from '../../Components/UniverseRoleSelector.vue';

const props = defineProps({
    players: { type: Object, required: true },
    selectedPlayer: { type: Object, default: null },
    selectedPlanetId: { type: Number, default: null },
    catalog: { type: Array, default: () => [] },
    auditLogs: { type: Array, default: () => [] },
    overview: { type: Object, default: null },
    activeFleets: { type: Array, default: () => [] },
    adminAnnouncements: { type: Object, default: null },
    universes: { type: Array, default: () => [] },
    roleUniverses: { type: Array, default: () => [] },
    mode: { type: String, required: true },
});

const page = usePage();
const { t, te, locale } = useI18n();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const search = ref(new URL(page.url, 'http://localhost').searchParams.get('search') ?? '');
const adminLevel = computed(() => Number(page.props.auth?.user?.admin_level ?? 0));
const sections = computed(() => [
    { mode: 'overview', route: 'admin.index', allowed: adminLevel.value >= 1 },
    { mode: 'declarations', route: 'admin.declarations', allowed: adminLevel.value >= 1 },
    { mode: 'elements', route: 'admin.elements', allowed: page.props.auth?.user?.admin_global === true },
    { mode: 'resources', route: 'admin.resources', allowed: adminLevel.value >= 2 },
    { mode: 'roles', route: 'admin.roles', allowed: page.props.auth?.user?.admin_global === true },
    { mode: 'universes', route: 'admin.universes', allowed: adminLevel.value === 4 },
].filter((section) => section.allowed));
const currentRoute = computed(() => ({
    overview: 'admin.index',
    elements: 'admin.elements',
    resources: 'admin.resources',
    roles: 'admin.roles',
    universes: 'admin.universes',
})[props.mode]);
const query = computed(() => ({
    ...(props.selectedPlayer ? { player: props.selectedPlayer.id } : {}),
    ...(props.selectedPlanetId !== null ? { planet: props.selectedPlanetId } : {}),
    ...(search.value.trim() ? { search: search.value.trim() } : {}),
}));
const categories = computed(() => [...new Set(props.catalog.map((item) => item.category))]);
const elementForm = useForm({ player_id: null, planet_id: null, category: '', key: '', amount: 0 });
const resourceForm = useForm({ player_id: null, planet_id: null, metal: 0, crystal: 0, deuterium: 0 });
const roleForm = useForm({ player_id: null, admin_level: 0, universe_ids: [] });
const suspensionForm = useForm({ player_id: null, days: 1, reason: '' });
const unsuspendForm = useForm({ player_id: null });
const announcementForm = useForm({ body: '' });
const revokeAnnouncementForm = useForm({});
const universeForm = useForm({ name: '', slug: '', galaxies: 9, systems_per_galaxy: 499, economy_speed: 1, fleet_speed: 1, status: 'active' });
const openUniverseForm = useForm({});
const canManagePlayer = computed(() => props.selectedPlayer
    && props.selectedPlayer.id !== page.props.auth?.user?.id
    && props.selectedPlayer.admin_level < adminLevel.value);
const isSuspended = computed(() => props.selectedPlayer?.suspended_until
    && new Date(props.selectedPlayer.suspended_until).getTime() > Date.now());
const availableElements = computed(() => props.catalog.filter((item) => item.category === elementForm.category));

watch(() => page.url, (url) => {
    search.value = new URL(url, 'http://localhost').searchParams.get('search') ?? '';
});
watch(() => [props.selectedPlayer?.id, props.selectedPlanetId], () => {
    elementForm.player_id = props.selectedPlayer?.id ?? null;
    elementForm.planet_id = props.selectedPlanetId;
    resourceForm.player_id = props.selectedPlayer?.id ?? null;
    resourceForm.planet_id = props.selectedPlanetId;
    roleForm.player_id = props.selectedPlayer?.id ?? null;
    roleForm.admin_level = props.selectedPlayer?.admin_level ?? 0;
    roleForm.universe_ids = [];
    suspensionForm.player_id = props.selectedPlayer?.id ?? null;
    suspensionForm.reset('days', 'reason');
    unsuspendForm.player_id = props.selectedPlayer?.id ?? null;
    [elementForm, resourceForm, roleForm, suspensionForm, unsuspendForm].forEach((form) => {
        form.clearErrors();
        form.wasSuccessful = false;
        form.recentlySuccessful = false;
    });
}, { immediate: true });
watch(() => elementForm.category, () => {
    elementForm.key = '';
});
watch(() => props.catalog, () => {
    if (!props.catalog.some((item) => item.category === elementForm.category && item.key === elementForm.key)) {
        elementForm.key = '';
    }
});

function visit(parameters) {
    router.get(route(currentRoute.value), parameters, { preserveScroll: true });
}

function selectPlayer(player) {
    visit({ ...query.value, player: player.id, planet: undefined });
}

function selectPlanet(event) {
    visit({ ...query.value, planet: event.target.value || undefined });
}

function publishAnnouncement() {
    announcementForm.post(route('admin.announcements.store'), {
        preserveScroll: true,
        onSuccess: () => announcementForm.reset('body'),
    });
}

function revokeAnnouncement(id) {
    revokeAnnouncementForm.delete(route('admin.announcements.revoke', { announcement: id }), { preserveScroll: true });
}

function openUniverse(id) {
    openUniverseForm.post(route('admin.universes.open', { universe: id }), { preserveScroll: true });
}

function paginationUrl(url) {
    const target = new URL(url, 'http://localhost');
    ['player', 'planet', 'search'].forEach((key) => target.searchParams.delete(key));
    for (const [key, value] of Object.entries(query.value)) target.searchParams.set(key, value);
    return `${target.pathname}${target.search}${target.hash}`;
}

function paginationLabel(label, index) {
    if (index === 0) return `« ${t('admin.previous')}`;
    if (index === props.players.links.length - 1) return `${t('admin.next')} »`;
    return label.replace(/&laquo;|&#171;/g, '«').replace(/&raquo;|&#187;/g, '»').replace(/<[^>]*>/g, '');
}

function elementLabel(item) {
    const kind = item.category.toLowerCase();
    const key = item.key === 'hyperspace_drive' ? 'hyperspaceDrive' : item.key;
    const paths = [
        item.label,
        ...(['building', 'buildings'].includes(kind) ? [`buildingCatalog.${item.key}.name`] : []),
        ...(['research', 'technology', 'technologies'].includes(kind) ? [`research.${key}`, `researchLabels.${item.key}`] : []),
        ...(['ship', 'ships', 'fleet', 'defence', 'defences', 'defense', 'defenses'].includes(kind) ? [`unitNames.${item.key}`] : []),
        ...(['officer', 'officers'].includes(kind) ? [`officerNames.${item.key}`] : []),
    ];
    const translated = paths.find((path) => typeof path === 'string' && te(path, locale.value));
    return translated ? t(translated) : item.label;
}

function categoryLabel(category) {
    const singular = { buildings: 'building', technologies: 'research', ships: 'ship', fleet: 'ship', defences: 'defence', defense: 'defence', defenses: 'defence', officers: 'officer', technology: 'research' }[category.toLowerCase()] ?? category.toLowerCase();
    const key = `technologyTree.kinds.${singular}`;
    return te(key, locale.value) ? t(key) : category;
}
</script>

<template>
    <Head :title="t('admin.title')" />
    <GameLayout>
        <main class="command-layout admin-page">
            <section class="command-content flex flex-col gap-6">
                <div class="command-heading">
                    <div>
                        <p class="eyebrow"><span class="eyebrow-line"></span> {{ t('admin.eyebrow') }}</p>
                        <h1>{{ t('admin.title') }}</h1>
                        <p class="command-subtitle">{{ t('admin.subtitle') }}</p>
                    </div>
                </div>

                <nav class="flex flex-wrap gap-2" :aria-label="t('admin.title')">
                    <Link v-for="section in sections" :key="section.mode" class="button button-outline" :class="{ 'button-primary': mode === section.mode }" :href="route(section.route, query)" :aria-current="mode === section.mode ? 'page' : undefined">
                        {{ t(section.mode === 'declarations' ? 'multiAccounts.title' : `admin.${section.mode}`) }}
                    </Link>
                    <Link v-if="page.props.auth?.user?.admin_global" class="button button-outline" :href="route('admin.changelog')">{{ t('changelog.title') }}</Link>
                </nav>

                <section v-if="mode === 'universes' && page.props.auth?.user?.admin_global" class="rounded border border-slate-700 bg-slate-900/70 p-4 sm:p-6">
                    <h2 class="mb-4 text-lg font-semibold text-white">{{ t('adminUniverses.title') }}</h2>
                    <p class="mb-4 text-sm text-slate-400">{{ t('adminUniverses.hint') }}</p>
                    <form class="grid max-w-3xl gap-4 sm:grid-cols-2" @submit.prevent="universeForm.post(route('admin.universes.store'), { preserveScroll: true })">
                        <div v-if="Object.keys(universeForm.errors).length" class="form-alert sm:col-span-2" role="alert"><p v-for="(error, field) in universeForm.errors" :key="field">{{ error }}</p></div>
                        <p v-if="universeForm.recentlySuccessful && !page.props.flash?.success" class="flash-message sm:col-span-2" role="status">{{ t('adminUniverses.created') }}</p>
                        <label class="flex flex-col gap-1 text-sm" for="universe-name">{{ t('adminUniverses.name') }}<input id="universe-name" v-model="universeForm.name" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" type="text" minlength="2" maxlength="80" required></label>
                        <label class="flex flex-col gap-1 text-sm" for="universe-slug">{{ t('adminUniverses.slug') }}<input id="universe-slug" v-model="universeForm.slug" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" type="text" minlength="2" maxlength="80" pattern="[A-Za-z0-9_-]+" required></label>
                        <label class="flex flex-col gap-1 text-sm" for="universe-galaxies">{{ t('adminUniverses.galaxies') }}<input id="universe-galaxies" v-model.number="universeForm.galaxies" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" type="number" min="1" max="9" step="1" required></label>
                        <label class="flex flex-col gap-1 text-sm" for="universe-systems">{{ t('adminUniverses.systems') }}<input id="universe-systems" v-model.number="universeForm.systems_per_galaxy" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" type="number" min="1" max="499" step="1" required></label>
                        <label class="flex flex-col gap-1 text-sm" for="universe-economy-speed">{{ t('adminUniverses.economySpeed') }}<input id="universe-economy-speed" v-model.number="universeForm.economy_speed" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" type="number" min="0.1" max="100" step="0.1" required></label>
                        <label class="flex flex-col gap-1 text-sm" for="universe-fleet-speed">{{ t('adminUniverses.fleetSpeed') }}<input id="universe-fleet-speed" v-model.number="universeForm.fleet_speed" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" type="number" min="0.1" max="100" step="0.1" required></label>
                        <label class="flex flex-col gap-1 text-sm" for="universe-status">{{ t('factions.opening') }}<select id="universe-status" v-model="universeForm.status"><option value="active">{{ t('factions.openNow') }}</option><option value="closed">{{ t('factions.configureBefore') }}</option></select></label>
                        <button class="button button-primary self-start sm:col-span-2" type="submit" :disabled="universeForm.processing">{{ universeForm.processing ? t('admin.saving') : t('adminUniverses.create') }}</button>
                    </form>
                </section>

                <section v-if="mode === 'universes' && page.props.auth?.user?.admin_global" class="rounded border border-slate-700 bg-slate-900/70 p-4 sm:p-6">
                    <h2 class="mb-4 text-lg font-semibold text-white">{{ t('adminUniverses.list') }}</h2>
                    <p v-if="openUniverseForm.errors.universe" class="field-error mb-3" role="alert">{{ openUniverseForm.errors.universe }}</p>
                    <p v-if="!universes.length" class="text-slate-400">{{ t('adminUniverses.empty') }}</p>
                    <ul v-else class="flex flex-col gap-3">
                        <li v-for="universe in universes" :key="universe.id" class="flex flex-col gap-3 rounded border border-slate-700 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-semibold text-white">{{ universe.name }} <span class="text-sm font-normal text-slate-400">({{ universe.slug }})</span></p>
                                <p class="text-sm text-slate-400">{{ t(`adminUniverses.status.${universe.status}`) }} · {{ universe.galaxies }} {{ t('adminUniverses.galaxiesShort') }} × {{ universe.systems_per_galaxy }} {{ t('adminUniverses.systemsShort') }} · {{ t('adminUniverses.namedSystems', { count: universe.named_systems ?? 0 }) }} · {{ t('adminUniverses.speeds', { economy: universe.economy_speed, fleet: universe.fleet_speed }) }}</p>
                            </div>
                            <Link class="button button-outline" :href="route('admin.factions.index', { universe: universe.id })">{{ t('factions.title') }}</Link>
                            <button v-if="universe.status === 'closed'" class="button button-primary shrink-0 self-start" type="button" :disabled="openUniverseForm.processing" @click="openUniverse(universe.id)">{{ t('adminUniverses.open') }}</button>
                        </li>
                    </ul>
                </section>

                <section v-if="overview" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" :aria-label="t('adminStats.title')">
                    <article v-for="name in ['players', 'planets', 'universes', 'fleets']" :key="name" class="rounded border border-slate-700 bg-slate-900/70 p-4">
                        <p class="text-sm text-slate-400">{{ t(`adminStats.${name}`) }}</p>
                        <strong class="text-2xl text-white">{{ overview[name] }}</strong>
                    </article>
                </section>
                <section v-if="overview" class="rounded border border-slate-700 bg-slate-900/70 p-4 sm:p-6">
                    <h2 class="mb-4 text-lg font-semibold text-white">{{ t('adminFlights.title') }}</h2>
                    <p v-if="!activeFleets.length" class="text-slate-400">{{ t('adminFlights.empty') }}</p>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead><tr><th>{{ t('adminFlights.player') }}</th><th>{{ t('adminFlights.mission') }}</th><th>{{ t('adminFlights.arrival') }}</th></tr></thead>
                            <tbody><tr v-for="fleet in activeFleets" :key="fleet.id" class="border-t border-slate-700"><td class="py-2">{{ fleet.player }}</td><td>{{ fleet.mission }}</td><td>{{ fleet.arrives_at }}</td></tr></tbody>
                        </table>
                    </div>
                </section>
                <section v-if="mode === 'overview' && page.props.auth?.user?.admin_global" class="rounded border border-slate-700 bg-slate-900/70 p-4 sm:p-6">
                    <h2 class="mb-4 text-lg font-semibold text-white">{{ t('announcements.manage') }}</h2>
                    <form class="flex max-w-2xl flex-col gap-3" @submit.prevent="publishAnnouncement">
                        <label class="flex flex-col gap-1" for="announcement-body">{{ t('announcements.body') }}
                            <textarea id="announcement-body" v-model="announcementForm.body" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" rows="4" maxlength="2000" required></textarea>
                        </label>
                        <p v-if="announcementForm.errors.body" class="field-error" role="alert">{{ announcementForm.errors.body }}</p>
                        <button class="button button-primary self-start" type="submit" :disabled="announcementForm.processing">{{ t('announcements.publish') }}</button>
                    </form>
                    <p class="mt-4 text-sm text-slate-400">{{ t('announcements.delivery') }}</p>
                    <p v-if="revokeAnnouncementForm.errors.announcement" class="field-error" role="alert">{{ revokeAnnouncementForm.errors.announcement }}</p>
                    <ul v-if="adminAnnouncements?.data.length" class="mt-4 flex flex-col gap-3">
                        <li v-for="announcement in adminAnnouncements.data" :key="announcement.id" class="rounded border border-slate-700 p-3">
                            <p class="text-sm text-slate-400">{{ announcement.created_at }} · {{ t(announcement.revoked_at ? 'announcements.revoked' : 'announcements.active') }}</p>
                            <p class="my-2 whitespace-pre-wrap wrap-break-word">{{ announcement.body }}</p>
                            <button v-if="!announcement.revoked_at && adminLevel >= 3" class="button button-outline" type="button" :disabled="revokeAnnouncementForm.processing" @click="revokeAnnouncement(announcement.id)">{{ t('announcements.revoke') }}</button>
                        </li>
                    </ul>
                    <nav v-if="adminAnnouncements?.links.length > 3" class="mt-4 flex flex-wrap gap-2" :aria-label="t('announcements.pagination')">
                        <template v-for="(link, index) in adminAnnouncements.links" :key="index">
                            <Link v-if="link.url" class="rounded border border-slate-700 px-3 py-1" :class="{ 'border-cyan-400 bg-slate-800': link.active }" :href="link.url" :aria-current="link.active ? 'page' : undefined">{{ index === 0 ? t('admin.previous') : (index === adminAnnouncements.links.length - 1 ? t('admin.next') : link.label) }}</Link>
                            <span v-else class="rounded border border-slate-800 px-3 py-1 text-slate-500" aria-disabled="true">{{ index === 0 ? t('admin.previous') : (index === adminAnnouncements.links.length - 1 ? t('admin.next') : link.label) }}</span>
                        </template>
                    </nav>
                </section>

                <section v-if="mode !== 'universes'" class="rounded border border-slate-700 bg-slate-900/70 p-4 sm:p-6">
                    <h2 class="mb-4 text-lg font-semibold text-white">{{ t('admin.players') }}</h2>
                    <form class="flex flex-wrap items-end gap-3" @submit.prevent="visit({ ...query, search: search.trim() || undefined })">
                        <label class="flex min-w-48 flex-1 flex-col gap-1 text-sm" for="admin-search">
                            {{ t('admin.search') }}
                            <input id="admin-search" v-model="search" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" type="search">
                        </label>
                        <button class="button button-primary" type="submit">{{ t('admin.searchButton') }}</button>
                    </form>
                    <p v-if="!players.data.length" class="mt-4 text-slate-400">{{ t('admin.noPlayers') }}</p>
                    <ul v-else class="mt-4 flex flex-col gap-2">
                        <li v-for="player in players.data" :key="player.id">
                            <button class="flex w-full flex-wrap justify-between gap-2 rounded border border-slate-700 px-3 py-2 text-left hover:border-cyan-400" :class="{ 'border-cyan-400 bg-slate-800': selectedPlayer?.id === player.id }" type="button" :aria-pressed="selectedPlayer?.id === player.id" @click="selectPlayer(player)">
                                <span>{{ player.name }}</span><span class="text-sm text-slate-400">{{ t('admin.playerLevel', { level: player.admin_level }) }}</span>
                            </button>
                        </li>
                    </ul>
                    <nav v-if="players.links?.length" class="mt-4 flex flex-wrap gap-2" :aria-label="t('admin.pagination')">
                        <template v-for="(link, index) in players.links" :key="index">
                            <Link v-if="link.url" class="rounded border border-slate-700 px-3 py-1 hover:border-cyan-400" :class="{ 'border-cyan-400 bg-slate-800': link.active }" :href="paginationUrl(link.url)" :aria-current="link.active ? 'page' : undefined" preserve-scroll>{{ paginationLabel(link.label, index) }}</Link>
                            <span v-else class="rounded border border-slate-800 px-3 py-1 text-slate-500" aria-disabled="true">{{ paginationLabel(link.label, index) }}</span>
                        </template>
                    </nav>
                </section>

                <section v-if="mode !== 'universes'" class="rounded border border-slate-700 bg-slate-900/70 p-4 sm:p-6">
                    <h2 class="mb-4 text-lg font-semibold text-white">{{ t('admin.selection') }}</h2>
                    <p v-if="!selectedPlayer" class="text-slate-400">{{ t('admin.selectPlayer') }}</p>
                    <template v-else>
                        <p class="mb-4"><strong>{{ selectedPlayer.name }}</strong> · {{ t('admin.playerLevel', { level: selectedPlayer.admin_level }) }}</p>
                        <label class="flex max-w-md flex-col gap-1 text-sm" for="admin-planet">
                            {{ t('admin.planet') }}
                            <select id="admin-planet" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" :value="selectedPlanetId ?? ''" @change="selectPlanet">
                                <option value="">{{ t('admin.selectPlanet') }}</option>
                                <option v-for="planet in selectedPlayer.planets" :key="planet.id" :value="planet.id">{{ planet.name }} [{{ planet.galaxy }}:{{ planet.system }}:{{ planet.position }}]</option>
                            </select>
                        </label>
                        <p v-if="!selectedPlayer.planets.length" class="mt-2 text-slate-400">{{ t('admin.noPlanets') }}</p>
                    </template>
                </section>

                <section v-if="mode === 'overview' && selectedPlayer && page.props.auth?.user?.admin_global" class="rounded border border-slate-700 bg-slate-900/70 p-4 sm:p-6">
                    <h2 class="mb-4 text-lg font-semibold text-white">{{ t('adminModeration.title') }}</h2>
                    <p v-if="!canManagePlayer" class="text-slate-400">{{ t('adminModeration.protected') }}</p>
                    <template v-else>
                        <p v-if="isSuspended" class="mb-4" role="status">{{ t('adminModeration.suspendedUntil', { date: new Date(selectedPlayer.suspended_until).toLocaleString(locale) }) }} · {{ selectedPlayer.suspension_reason }}</p>
                        <form v-if="!isSuspended" class="flex max-w-lg flex-col gap-4" @submit.prevent="suspensionForm.post(route('admin.suspend'), { preserveScroll: true })">
                            <div v-if="Object.keys(suspensionForm.errors).length" class="form-alert" role="alert"><p v-for="(error, field) in suspensionForm.errors" :key="field">{{ error }}</p></div>
                            <label class="flex flex-col gap-1" for="admin-suspension-days">{{ t('adminModeration.days') }}<input id="admin-suspension-days" v-model.number="suspensionForm.days" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" type="number" min="1" max="365" required></label>
                            <label class="flex flex-col gap-1" for="admin-suspension-reason">{{ t('adminModeration.reason') }}<input id="admin-suspension-reason" v-model="suspensionForm.reason" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" maxlength="255" required></label>
                            <button class="button button-outline self-start" type="submit" :disabled="suspensionForm.processing">{{ t('adminModeration.suspend') }}</button>
                        </form>
                        <form v-if="selectedPlayer.suspended_until && adminLevel >= 2" class="mt-4" @submit.prevent="unsuspendForm.delete(route('admin.unsuspend'), { preserveScroll: true })">
                            <div v-if="Object.keys(unsuspendForm.errors).length" class="form-alert" role="alert"><p v-for="(error, field) in unsuspendForm.errors" :key="field">{{ error }}</p></div>
                            <button class="button button-outline" type="submit" :disabled="unsuspendForm.processing">{{ t('adminModeration.unsuspend') }}</button>
                        </form>
                    </template>
                </section>

                <section v-if="mode !== 'overview' && mode !== 'universes' && selectedPlayer" class="rounded border border-slate-700 bg-slate-900/70 p-4 sm:p-6">
                    <h2 class="mb-4 text-lg font-semibold text-white">{{ t(`admin.${mode}`) }}</h2>
                    <p v-if="mode !== 'roles' && selectedPlanetId === null" class="mb-4 text-slate-400">{{ t('admin.selectPlanetFirst') }}</p>

                    <form v-if="mode === 'elements' && adminLevel >= 3" class="flex max-w-lg flex-col gap-4" @submit.prevent="elementForm.post(route('admin.elements.store'), { preserveScroll: true })">
                        <div v-if="Object.keys(elementForm.errors).length" class="form-alert" role="alert"><p v-for="(error, field) in elementForm.errors" :key="field">{{ error }}</p></div>
                        <p v-if="elementForm.recentlySuccessful && !page.props.flash?.success" class="flash-message" role="status">{{ t('admin.success') }}</p>
                        <p v-if="!catalog.length" class="text-slate-400">{{ t('admin.noElements') }}</p>
                        <label class="flex flex-col gap-1" for="admin-category">{{ t('admin.category') }}
                            <select id="admin-category" v-model="elementForm.category" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" required>
                                <option value="" disabled>{{ t('admin.selectCategory') }}</option>
                                <option v-for="category in categories" :key="category" :value="category">{{ categoryLabel(category) }}</option>
                            </select>
                        </label>
                        <label class="flex flex-col gap-1" for="admin-element">{{ t('admin.element') }}
                            <select id="admin-element" v-model="elementForm.key" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" required>
                                <option value="" disabled>{{ t('admin.selectElement') }}</option>
                                <option v-for="item in availableElements" :key="item.key" :value="item.key">{{ elementLabel(item) }}</option>
                            </select>
                        </label>
                        <label class="flex flex-col gap-1" for="admin-amount">{{ t('admin.amount') }}<input id="admin-amount" v-model.number="elementForm.amount" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" type="number" min="1" max="100000" step="1" required></label>
                        <button class="button button-primary self-start" type="submit" :disabled="elementForm.processing || selectedPlanetId === null || !catalog.length">{{ elementForm.processing ? t('admin.saving') : t('admin.submit') }}</button>
                    </form>

                    <form v-else-if="mode === 'resources' && adminLevel >= 2" class="flex max-w-lg flex-col gap-4" @submit.prevent="resourceForm.post(route('admin.resources.store'), { preserveScroll: true })">
                        <div v-if="Object.keys(resourceForm.errors).length" class="form-alert" role="alert"><p v-for="(error, field) in resourceForm.errors" :key="field">{{ error }}</p></div>
                        <p v-if="resourceForm.recentlySuccessful && !page.props.flash?.success" class="flash-message" role="status">{{ t('admin.success') }}</p>
                        <p class="text-slate-400">{{ t('adminModeration.storageLimit') }}</p>
                        <label v-for="resource in ['metal', 'crystal', 'deuterium']" :key="resource" class="flex flex-col gap-1" :for="`admin-${resource}`">{{ t(`common.${resource}`) }}<input :id="`admin-${resource}`" v-model.number="resourceForm[resource]" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white" type="number" min="0" max="1000000000" step="1" required></label>
                        <button class="button button-primary self-start" type="submit" :disabled="resourceForm.processing || selectedPlanetId === null">{{ resourceForm.processing ? t('admin.saving') : t('admin.submit') }}</button>
                    </form>

                    <form v-else-if="mode === 'roles' && page.props.auth?.user?.admin_global" class="flex max-w-lg flex-col gap-4" @submit.prevent="roleForm.post(route('admin.roles.update'), { preserveScroll: true })">
                        <ul v-if="selectedPlayer.staff_roles?.length" class="text-sm text-slate-300">
                            <li v-for="role in selectedPlayer.staff_roles" :key="role.id">{{ role.name }} : {{ t(`adminLevels.level${role.level}`) }}</li>
                        </ul>
                        <div v-if="Object.keys(roleForm.errors).length" class="form-alert" role="alert"><p v-for="(error, field) in roleForm.errors" :key="field">{{ error }}</p></div>
                        <p v-if="roleForm.recentlySuccessful && !page.props.flash?.success" class="flash-message" role="status">{{ t('admin.success') }}</p>
                        <p v-if="!canManagePlayer" class="text-slate-400">{{ t('adminModeration.protected') }}</p>
                        <label class="flex flex-col gap-1" for="admin-level">{{ t('admin.adminLevel') }}
                            <select id="admin-level" v-model.number="roleForm.admin_level" class="rounded border border-slate-600 bg-slate-950 px-3 py-2 text-white">
                                <option v-for="level in [0, 1, 2, 3]" :key="level" :value="level">{{ level }} · {{ t(`adminLevels.level${level}`) }}</option>
                            </select>
                        </label>
                        <UniverseRoleSelector v-if="roleForm.admin_level < 3 && selectedPlayer.admin_level < 3" v-model="roleForm.universe_ids" :universes="roleUniverses" />
                        <button class="button button-primary self-start" type="submit" :disabled="roleForm.processing || !canManagePlayer">{{ roleForm.processing ? t('admin.saving') : t('admin.submit') }}</button>
                    </form>
                </section>
                <section v-if="mode === 'roles' && page.props.auth?.user?.admin_global" class="rounded border border-slate-700 bg-slate-900/70 p-4 sm:p-6">
                    <h2 class="mb-4 text-lg font-semibold text-white">{{ t('adminAudit.title') }}</h2>
                    <p v-if="!auditLogs.length" class="text-slate-400">{{ t('adminAudit.empty') }}</p>
                    <ol v-else class="flex flex-col gap-3">
                        <li v-for="entry in auditLogs" :key="entry.id" class="rounded border border-slate-700 p-3">
                            <p>{{ entry.created_at }} · {{ entry.action }}</p>
                            <p class="text-sm text-slate-400">{{ t('adminAudit.actors', { actor: entry.actor_id, target: entry.target_user_id, planet: entry.planet_id ?? '—' }) }}</p>
                            <pre class="mt-2 overflow-auto whitespace-pre-wrap break-all text-sm text-slate-300">{{ entry.details }}</pre>
                        </li>
                    </ol>
                </section>
            </section>
        </main>
    </GameLayout>
</template>
