/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Provides application functionality for app.
 */

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';
import { i18n, setLocale, supportedLocales } from './i18n';

createInertiaApp({
    title: (title) => title ? `${title} · XNova` : 'XNova',
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        return pages[`./Pages/${name}.vue`]();
    },
    setup({ el, App, props, plugin }) {
        const savedLocale = window.localStorage.getItem('xnova.locale');
        const initialLocale = supportedLocales.includes(savedLocale)
            ? savedLocale
            : props.initialPage.props.locale;

        if (supportedLocales.includes(initialLocale)) {
            setLocale(initialLocale);
        }

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .use(ZiggyVue, props.initialPage.props.ziggy)
            .mount(el);
    },
    progress: {
        color: '#56d6c2',
    },
});
