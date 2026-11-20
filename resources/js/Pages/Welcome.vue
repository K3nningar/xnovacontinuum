<!--
Author / Contributor : K3nningar
Code Version : 0.1.0-alpha.5
Revision : 34ddc84
Description : Renders the Welcome Inertia page.
-->

<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import LocaleSwitcher from '../Components/LocaleSwitcher.vue';
import ChangelogEntries from '../Components/ChangelogEntries.vue';

defineProps({
    releases: { type: Array, required: true },
});

const page = usePage();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const { t } = useI18n();
</script>

<template>
    <Head :title="t('welcome.title')" />
    <div class="universe-shell">
        <header class="topbar">
            <Link class="brand" :href="route('home')" :aria-label="t('welcome.homeLabel')"><span class="brand-mark" aria-hidden="true">X</span><span class="brand-copy"><strong>XNova</strong><small>Continuum</small></span></Link>
            <div class="welcome-build-status"><span class="status-dot"></span>{{ t('welcome.gameStatus') }}</div>
            <nav class="top-actions" :aria-label="t('welcome.primaryNav')"><a class="text-link" href="#univers">{{ t('welcome.universeLink') }}</a><Link class="button button-quiet" :href="route('login')">{{ t('welcome.login') }}</Link><Link class="button button-primary" :href="route('register')">{{ t('welcome.register') }} <span aria-hidden="true">↗</span></Link><LocaleSwitcher /></nav>
        </header>
        <main>
            <section class="hero" id="univers" aria-labelledby="hero-title">
                <div class="hero-copy"><p class="eyebrow"><span class="eyebrow-line"></span> {{ t('welcome.tagline') }}</p><h1 id="hero-title">{{ t('welcome.titleFirst') }}<br>{{ t('welcome.titleSecond') }} <span>{{ t('welcome.titleAccent') }}</span></h1><p class="hero-description">{{ t('welcome.description') }}</p><div class="hero-actions"><Link class="button button-primary button-large" :href="route('register')">{{ t('welcome.join') }} <span aria-hidden="true">→</span></Link><a class="button button-outline button-large" href="#decouvrir">{{ t('welcome.discover') }}</a></div><div class="player-proof"><div class="avatar-stack" aria-hidden="true"><span><i class="bi bi-person-fill"></i></span><span><i class="bi bi-globe2"></i></span><span><i class="bi bi-plus"></i></span></div><span><strong>{{ t('welcome.adventure') }}</strong><small>{{ t('welcome.infinite') }}</small></span></div></div>
                <div class="welcome-game-preview" :aria-label="t('welcome.previewLabel')">
                    <div class="welcome-preview-topbar"><span class="welcome-preview-brand"><b>X</b> XNOVA</span><span class="welcome-preview-status"><i></i>{{ t('welcome.previewLive') }}</span></div>
                    <div class="welcome-preview-profile"><div class="welcome-preview-avatar"><i class="bi bi-person-fill" aria-hidden="true"></i></div><div><small>{{ t('welcome.commanderLabel') }}</small><strong>Nova Pilot</strong><span><i class="bi bi-globe2" aria-hidden="true"></i> Andromède</span></div><span class="welcome-preview-chevron">⌄</span></div>
                    <div class="welcome-preview-resources"><span><i class="welcome-metal">▰</i><small>{{ t('welcome.metal') }}</small><b>12 480</b></span><span><i class="welcome-crystal">◇</i><small>{{ t('welcome.crystal') }}</small><b>8 320</b></span><span><i class="welcome-deuterium">⬡</i><small>{{ t('welcome.deuterium') }}</small><b>3 760</b></span></div>
                    <div class="welcome-preview-world"><div class="welcome-preview-orbit welcome-preview-orbit-one"></div><div class="welcome-preview-orbit welcome-preview-orbit-two"></div><div class="welcome-preview-star"></div><div class="welcome-preview-planet"></div><div class="welcome-preview-world-label"><i></i><span><small>{{ t('welcome.activeWorld') }}</small><b>Nova Prime <em>[4:218:8]</em></b></span></div></div>
                    <div class="welcome-preview-footer"><span><i class="bi bi-buildings"></i>{{ t('welcome.buildings') }}</span><span><i class="bi bi-lightbulb"></i>{{ t('welcome.research') }}</span><span><i class="bi bi-send"></i>{{ t('welcome.fleets') }}</span><span><i class="bi bi-crosshair"></i>{{ t('welcome.combat') }}</span></div>
                    <div class="welcome-preview-scan" aria-hidden="true"></div>
                </div>
                <div class="hero-index"><span>01</span><span class="index-line"></span><span>03</span></div>
            </section>
            <section class="intel-bar" :aria-label="t('welcome.universeFeatures')"><div><span class="intel-icon"><i class="bi bi-globe2" aria-hidden="true"></i></span><span><small>{{ t('welcome.universeLink') }}</small><strong>{{ t('welcome.multipleUniverses') }}</strong></span></div><div><span class="intel-icon"><i class="bi bi-cpu" aria-hidden="true"></i></span><span><small>{{ t('welcome.yourStrategy') }}</small><strong>{{ t('welcome.economyScienceFleet') }}</strong></span></div><div><span class="intel-icon"><i class="bi bi-crosshair" aria-hidden="true"></i></span><span><small>{{ t('welcome.yourGalaxy') }}</small><strong>{{ t('welcome.fleetsAndCombat') }}</strong></span></div><a href="#decouvrir" class="intel-discover">{{ t('welcome.exploreSystems') }} <span aria-hidden="true">→</span></a></section>
            <section class="discover-section" id="decouvrir"><div class="section-heading"><p class="eyebrow"><span class="eyebrow-line"></span> {{ t('welcome.yourRules') }}</p><h2>{{ t('welcome.shape') }} <span>{{ t('welcome.shapeAccent') }}</span></h2><p class="welcome-section-intro">{{ t('welcome.currentGameIntro') }}</p></div><div class="feature-grid welcome-feature-grid"><article class="feature-card"><span class="feature-number">{{ t('welcome.economy') }}</span><div class="feature-symbol"><i class="bi bi-buildings" aria-hidden="true"></i></div><h3>{{ t('welcome.buildPower') }}</h3><p>{{ t('welcome.economyText') }}</p><span class="feature-mark">01</span></article><article class="feature-card"><span class="feature-number">{{ t('welcome.universeFeature') }}</span><div class="feature-symbol"><i class="bi bi-globe2" aria-hidden="true"></i></div><h3>{{ t('welcome.playAcrossWorlds') }}</h3><p>{{ t('welcome.multiverseText') }}</p><span class="feature-mark">02</span></article><article class="feature-card"><span class="feature-number">{{ t('welcome.fleetFeature') }}</span><div class="feature-symbol"><i class="bi bi-send" aria-hidden="true"></i></div><h3>{{ t('welcome.commandYourFleet') }}</h3><p>{{ t('welcome.fleetText') }}</p><span class="feature-mark">03</span></article><article class="feature-card"><span class="feature-number">{{ t('welcome.securityFeature') }}</span><div class="feature-symbol"><i class="bi bi-shield-lock" aria-hidden="true"></i></div><h3>{{ t('welcome.protectAccount') }}</h3><p>{{ t('welcome.securityText') }}</p><span class="feature-mark">04</span></article></div></section>
            <section class="welcome-roadmap" :aria-label="t('welcome.developmentStatus')">
                <div class="welcome-roadmap-copy"><span class="welcome-roadmap-icon"><i class="bi bi-stars" aria-hidden="true"></i></span><div><p class="feature-number">{{ t('welcome.nextMilestone') }}</p><h2>{{ t('welcome.achievementsTitle') }}</h2><p>{{ t('welcome.achievementsText') }}</p></div></div>
                <span class="welcome-roadmap-badge"><i class="bi bi-code-slash" aria-hidden="true"></i>{{ t('welcome.inDevelopment') }}</span>
            </section>
            <section class="discover-section" aria-labelledby="latest-changes">
                <div class="section-heading">
                    <p class="eyebrow"><span class="eyebrow-line"></span> {{ t('changelog.eyebrow') }}</p>
                    <h2 id="latest-changes">{{ t('changelog.latest') }}</h2>
                </div>
                <div class="rounded border border-slate-700 bg-slate-900/70 p-4 sm:p-6">
                    <template v-if="releases.length">
                        <p class="mb-4 text-slate-400">{{ releases[0].version }}</p>
                        <ChangelogEntries :entries="releases[0].entries.slice(0, 3)" />
                    </template>
                    <p v-else class="text-slate-400">{{ t('changelogPublication.empty') }}</p>
                    <Link class="button button-outline mt-5" :href="route('changelog')">{{ t('changelog.viewAll') }} <span aria-hidden="true">→</span></Link>
                </div>
            </section>
            <section class="join-panel" id="inscription"><div><p class="eyebrow"><span class="eyebrow-line"></span> {{ t('welcome.firstStep') }}</p><h2>{{ t('welcome.ready') }}<br><span>{{ t('welcome.legend') }}</span></h2></div><div class="join-action"><p>{{ t('welcome.starsAligned') }}</p><Link class="button button-primary button-large" :href="route('register')">{{ t('welcome.enter') }} <span aria-hidden="true">→</span></Link><Link class="subtle-link" :href="route('login')">{{ t('welcome.alreadyExplorer') }} {{ t('welcome.signIn') }}</Link></div><div class="join-orbit" aria-hidden="true"></div></section>
        </main>
        <footer class="footer"><Link class="brand footer-brand" :href="route('home')"><span class="brand-mark" aria-hidden="true">X</span><span class="brand-copy"><strong>XNova</strong><small>Continuum</small></span></Link><span>{{ t('nav.footer') }}</span><span>© {{ new Date().getFullYear() }} XNova</span></footer>
    </div>
</template>
