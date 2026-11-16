<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';
import { useI18n } from 'vue-i18n';
import LocaleSwitcher from '../Components/LocaleSwitcher.vue';

const page = usePage();
const route = (name, params) => ziggyRoute(name, params, true, page.props.ziggy);
const { t } = useI18n();
</script>

<template>
    <Head :title="t('welcome.title')" />
    <div class="universe-shell">
        <header class="topbar">
            <Link class="brand" :href="route('home')" :aria-label="t('welcome.homeLabel')"><span class="brand-mark" aria-hidden="true">X</span><span class="brand-copy"><strong>XNOVA</strong><small>LEGACIES · REBORN</small></span></Link>
            <div class="server-status"><span class="status-dot"></span> {{ t('welcome.online') }} <span class="status-divider">/</span> {{ t('welcome.season') }}</div>
            <nav class="top-actions" :aria-label="t('welcome.primaryNav')"><a class="text-link" href="#univers">{{ t('welcome.universeLink') }}</a><Link class="button button-quiet" :href="route('login')">{{ t('welcome.login') }}</Link><Link class="button button-primary" :href="route('register')">{{ t('welcome.register') }} <span aria-hidden="true">↗</span></Link><LocaleSwitcher /></nav>
        </header>
        <main>
            <section class="hero" id="univers" aria-labelledby="hero-title">
                <div class="hero-copy"><p class="eyebrow"><span class="eyebrow-line"></span> {{ t('welcome.tagline') }}</p><h1 id="hero-title">{{ t('welcome.titleFirst') }}<br>{{ t('welcome.titleSecond') }} <span>{{ t('welcome.titleAccent') }}</span></h1><p class="hero-description">{{ t('welcome.description') }}</p><div class="hero-actions"><Link class="button button-primary button-large" :href="route('register')">{{ t('welcome.join') }} <span aria-hidden="true">→</span></Link><a class="button button-outline button-large" href="#decouvrir">{{ t('welcome.discover') }}</a></div><div class="player-proof"><div class="avatar-stack" aria-hidden="true"><span>A</span><span>M</span><span>+</span></div><span><strong>{{ t('welcome.adventure') }}</strong><small>{{ t('welcome.infinite') }}</small></span></div></div>
                <div class="system-card" :aria-label="t('welcome.systemPreview')"><div class="system-topline"><span>{{ t('welcome.localSystem') }}</span><span>GX–047 <i></i></span></div><div class="orbit orbit-one"></div><div class="orbit orbit-two"></div><div class="orbit orbit-three"></div><div class="star-core"><span></span></div><span class="planet planet-one"></span><span class="planet planet-two"></span><span class="planet planet-three"></span><div class="system-caption"><span class="caption-marker"></span><span><strong>Kepler-186f</strong><small>{{ t('welcome.habitable') }}</small></span><span class="caption-arrow">↗</span></div><span class="coordinates coordinates-one">04:218</span><span class="coordinates coordinates-two">{{ t('welcome.sector') }}</span></div>
                <div class="hero-index"><span>01</span><span class="index-line"></span><span>03</span></div>
            </section>
            <section class="intel-bar" :aria-label="t('welcome.universeFeatures')"><div><span class="intel-icon">⌁</span><span><small>{{ t('welcome.universeLink') }}</small><strong>{{ t('welcome.persistent') }}</strong></span></div><div><span class="intel-icon">◈</span><span><small>{{ t('welcome.yourStrategy') }}</small><strong>{{ t('welcome.economyScienceFleet') }}</strong></span></div><div><span class="intel-icon">⌖</span><span><small>{{ t('welcome.yourGalaxy') }}</small><strong>{{ t('welcome.exploreTogether') }}</strong></span></div><a href="#decouvrir" class="intel-discover">{{ t('welcome.exploreSystems') }} <span aria-hidden="true">→</span></a></section>
            <section class="discover-section" id="decouvrir"><div class="section-heading"><p class="eyebrow"><span class="eyebrow-line"></span> {{ t('welcome.yourRules') }}</p><h2>{{ t('welcome.shape') }} <span>{{ t('welcome.shapeAccent') }}</span></h2></div><div class="feature-grid"><article class="feature-card"><span class="feature-number">{{ t('welcome.economy') }}</span><div class="feature-symbol">▧</div><h3>{{ t('welcome.buildPower') }}</h3><p>{{ t('welcome.economyText') }}</p><span class="feature-mark">↗</span></article><article class="feature-card"><span class="feature-number">{{ t('welcome.research') }}</span><div class="feature-symbol">⌬</div><h3>{{ t('welcome.pushLimits') }}</h3><p>{{ t('welcome.researchText') }}</p><span class="feature-mark">↗</span></article><article class="feature-card"><span class="feature-number">{{ t('welcome.exploration') }}</span><div class="feature-symbol">⦿</div><h3>{{ t('welcome.exploreUnknown') }}</h3><p>{{ t('welcome.explorationText') }}</p><span class="feature-mark">↗</span></article></div></section>
            <section class="join-panel" id="inscription"><div><p class="eyebrow"><span class="eyebrow-line"></span> {{ t('welcome.firstStep') }}</p><h2>{{ t('welcome.ready') }}<br><span>{{ t('welcome.legend') }}</span></h2></div><div class="join-action"><p>{{ t('welcome.starsAligned') }}</p><Link class="button button-primary button-large" :href="route('register')">{{ t('welcome.enter') }} <span aria-hidden="true">→</span></Link><Link class="subtle-link" :href="route('login')">{{ t('welcome.alreadyExplorer') }} {{ t('welcome.signIn') }}</Link></div><div class="join-orbit" aria-hidden="true"></div></section>
        </main>
        <footer class="footer"><Link class="brand footer-brand" :href="route('home')"><span class="brand-mark" aria-hidden="true">X</span><span class="brand-copy"><strong>XNOVA</strong><small>LEGACIES · REBORN</small></span></Link><span>{{ t('nav.footer') }}</span><span>© {{ new Date().getFullYear() }} XNova</span></footer>
    </div>
</template>
