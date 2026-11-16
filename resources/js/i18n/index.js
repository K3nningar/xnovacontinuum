import { createI18n } from 'vue-i18n';
import fr from './locales/fr.js';
import en from './locales/en.js';
import de from './locales/de.js';
import es from './locales/es.js';
import it from './locales/it.js';

export const supportedLocales = ['fr', 'en', 'de', 'es', 'it'];
export const localeOptions = supportedLocales.map((code) => ({ code, name: fr.common.languageNames[code] }));

function initialLocale() {
    if (typeof window === 'undefined') return 'fr';

    const storedLocale = window.localStorage.getItem('xnova.locale');
    if (supportedLocales.includes(storedLocale)) return storedLocale;

    const browserLocale = window.navigator.language?.slice(0, 2).toLowerCase();
    return supportedLocales.includes(browserLocale) ? browserLocale : 'fr';
}

export const i18n = createI18n({
    legacy: false,
    globalInjection: true,
    locale: initialLocale(),
    fallbackLocale: 'fr',
    messages: { fr, en, de, es, it },
    numberFormats: {
        fr: { game: { style: 'decimal', useGrouping: true, maximumFractionDigits: 0 } },
        en: { game: { style: 'decimal', useGrouping: true, maximumFractionDigits: 0 } },
        de: { game: { style: 'decimal', useGrouping: true, maximumFractionDigits: 0 } },
        es: { game: { style: 'decimal', useGrouping: true, maximumFractionDigits: 0 } },
        it: { game: { style: 'decimal', useGrouping: true, maximumFractionDigits: 0 } },
    },
});

export function setLocale(locale) {
    if (! supportedLocales.includes(locale)) return;

    i18n.global.locale.value = locale;

    if (typeof window !== 'undefined') {
        window.localStorage.setItem('xnova.locale', locale);
        document.cookie = `xnova.locale=${locale}; path=/; max-age=31536000; samesite=lax`;
        document.documentElement.lang = locale;
    }
}

if (typeof document !== 'undefined') {
    document.documentElement.lang = i18n.global.locale.value;
}
