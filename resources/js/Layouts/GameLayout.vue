<!--
Author / Contributor : K3nningar
Code Version : 0.1.1-alpha
Revision : 34ddc84
Description : Defines the shared GameLayout Vue layout.
-->

<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Link, usePage } from '@inertiajs/vue3';
import LocaleSwitcher from '../Components/LocaleSwitcher.vue';
import { route as ziggyRoute } from 'ziggy-js';

const page = usePage();
const props = defineProps({ livePlanets: { type: Array, default: null }, liveMoon: { type: Object, default: null } });
const { t, n } = useI18n();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const menuOpen = ref(false);
// Admin navigation is exposed only when the shared Inertia user payload meets the minimum admin level.
const navigation = computed(() => [
    { route: 'dashboard', component: 'Dashboard', label: 'nav.command', icon: 'bi-cpu' },
    { route: 'galaxy.index', component: 'Galaxy/Index', label: 'galaxy.title', icon: 'bi-stars' },
    { route: 'buildings.index', component: 'Buildings/Index', label: 'nav.buildings', icon: 'bi-buildings' },
    { route: 'moon.buildings', component: 'Moon/Buildings', label: 'nav.moonBuildings', icon: 'bi-moon-stars' },
    { route: 'research.index', component: 'Research/Index', label: 'nav.research', icon: 'bi-lightbulb' },
    { route: 'shipyard.index', component: 'Shipyard/Index', label: 'nav.shipyard', icon: 'bi-tools' },
    { route: 'defences.index', component: 'Defences/Index', label: 'nav.defences', icon: 'bi-shield-check' },
    { route: 'officers.index', component: 'Officers/Index', label: 'nav.officers', icon: 'bi-people' },
    { route: 'research.tree', component: 'Research/TechnologyGraph', label: 'nav.technologyTree', icon: 'bi-diagram-3' },
    { route: 'fleet.index', component: 'Fleet/Index', label: 'nav.fleet', icon: 'bi-send' },
    { route: 'combat-simulator.index', component: 'Combat/Index', label: 'nav.combatSimulator', icon: 'bi-crosshair' },
    { route: 'announcements.index', component: 'Announcements/Index', label: 'announcements.title', icon: 'bi-megaphone' },
    { route: 'multi-accounts.index', component: 'MultiAccounts/Index', label: 'nav.multiAccounts', icon: 'bi-person-lines-fill' },
    ...(Number(page.props.auth?.user?.admin_level ?? 0) >= 1
        ? [{ route: 'admin.index', component: 'Admin/Index', label: 'admin.title', icon: 'bi-shield-lock' }]
        : []),
]);
// Show the selected world's stock when scoped to a planet; admin and empire views aggregate all planets.
const resources = computed(() => {
    const planets = props.livePlanets ?? page.props.planets ?? [];
    const selectedPlanetId = page.component === 'Admin/Index' ? null : page.props.selectedPlanetId;
    const selectedPlanet = selectedPlanetId
        ? planets.find((planet) => Number(planet.id) === Number(selectedPlanetId))
        : null;
    const moon = props.liveMoon ?? (['Moon/Show', 'Moon/Buildings'].includes(page.component) ? page.props.moon : null);
    const totals = moon ?? selectedPlanet ?? planets.reduce((sum, planet) => ({
        metal: sum.metal + Number(planet.metal ?? 0),
        crystal: sum.crystal + Number(planet.crystal ?? 0),
        deuterium: sum.deuterium + Number(planet.deuterium ?? 0),
    }), { metal: 0, crystal: 0, deuterium: 0 });

    return {
        ...totals,
        scope: moon ? (moon.name || moon.generatedName || t('galaxyMoon', { orbit: moon.orbit, diameter: n(Number(moon.diameter ?? 0), 'game') })) : (selectedPlanet ? selectedPlanet.name : (planets.length ? t('nav.empire') : null)),
    };
});

function formatResource(value) {
    return n(Number(value ?? 0), 'game');
}

</script>

<template>
    <div class="app hud-app" :class="{ 'app-sidebar-mobile-toggled': menuOpen }" @keydown.esc="menuOpen = false">
        <header class="app-header hud-header">
            <button class="hud-menu-toggle" type="button" aria-controls="game-navigation" :aria-expanded="menuOpen" :aria-label="t(menuOpen ? 'hud.closeMenu' : 'hud.openMenu')" @click="menuOpen = !menuOpen">
                <i class="bi" :class="menuOpen ? 'bi-x-lg' : 'bi-list'" aria-hidden="true"></i>
            </button>
            <Link class="hud-brand" :href="route('dashboard')"><span class="hud-brand-mark" aria-hidden="true">X</span><span>XNOVA</span></Link>
            <div class="server-status"><span class="status-dot"></span> {{ t('nav.online') }}</div>
            <div class="user-menu"><LocaleSwitcher /><span class="commander-label">{{ t('nav.commander') }}</span><Link class="hud-profile-link" :href="route('profile.show')"><img v-if="page.props.auth.user.avatar_url" class="hud-profile-avatar" :src="page.props.auth.user.avatar_url" alt=""><span v-else class="hud-profile-avatar hud-profile-avatar-fallback" aria-hidden="true">{{ page.props.auth.user.name.slice(0, 1).toUpperCase() }}</span><span class="hud-profile-identity"><strong>{{ page.props.auth.user.name }}</strong><span v-if="page.props.auth.user.faction" class="hud-profile-faction" :title="`${t('nav.faction')} : ${page.props.auth.user.faction.name}`">{{ t('nav.faction') }} · {{ page.props.auth.user.faction.name }}</span></span></Link><Link class="text-link" :href="route('logout')" method="post" as="button">{{ t('nav.logout') }}</Link></div>
        </header>
        <aside id="game-navigation" class="app-sidebar hud-sidebar">
            <div class="app-sidebar-content">
                <nav class="menu" :aria-label="t('hud.navigation')">
                    <div class="menu-header">{{ t('hud.navigation') }}</div>
                    <div v-for="item in navigation" :key="item.route" class="menu-item" :class="{ active: page.component === item.component }">
                        <Link class="menu-link" :href="route(item.route, item.route === 'moon.buildings' && page.props.planet && page.props.moon ? { planet: page.props.planet.id, orbit: page.props.moon.orbit } : undefined)" :aria-current="page.component === item.component ? 'page' : undefined" @click="menuOpen = false">
                            <span class="menu-icon"><i class="bi" :class="item.icon" aria-hidden="true"></i></span>
                            <span class="menu-text">{{ t(item.label) }}</span>
                        </Link>
                    </div>
                </nav>
            </div>
        </aside>
        <button v-if="menuOpen" class="app-sidebar-mobile-backdrop" type="button" :aria-label="t('hud.closeMenu')" @click="menuOpen = false"></button>
        <div class="app-content hud-content">
            <div v-if="resources.scope" class="header-resources" :aria-label="t('nav.resourcesOf', { scope: resources.scope })">
                <span class="header-resource-scope">{{ resources.scope }}</span>
                <span class="header-resource metal-symbol" :title="t('common.metal')"><i>▰</i>{{ formatResource(resources.metal) }}</span>
                <span class="header-resource crystal-symbol" :title="t('common.crystal')"><i>◇</i>{{ formatResource(resources.crystal) }}</span>
                <span class="header-resource deut-symbol" :title="t('common.deuterium')"><i>⬡</i>{{ formatResource(resources.deuterium) }}</span>
            </div>
            <div v-if="page.props.flash?.success" class="flash-message" role="status">{{ page.props.flash.success }}</div>
            <div v-if="page.props.flash?.error" class="flash-message error" role="alert">{{ page.props.flash.error }}</div>
            <slot />
            <footer class="footer"><span>XNova Continuum · v{{ page.props.gameVersion }}</span><span>{{ t('nav.footer') }}</span><span>© {{ new Date().getFullYear() }} XNova</span></footer>
        </div>
    </div>


</template>
