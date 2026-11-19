/**
 * Application-wide Vue I18n setup and locale selection helpers.
 *
 * @author GitHub Copilot (contributor)
 * @license MIT (declared in composer.json)
 * @version 0.1.0-alpha.5
 * @revision 1 (first documented revision)
 */
import { createI18n } from 'vue-i18n';
import fr from './locales/fr.js';
import en from './locales/en.js';
import de from './locales/de.js';
import es from './locales/es.js';
import it from './locales/it.js';

// Keep the supported-locale allowlist in one place for the picker and saved-value validation.
export const supportedLocales = ['en', 'fr', 'de', 'es', 'it'];

// Resolve display names from the English catalog so they stay independent of the active locale.
export const localeOptions = supportedLocales.map((code) => ({ code, name: en.common.languageNames[code] }));

function initialLocale() {
    // Server rendering has no browser storage, so it starts from the same deterministic default.
    if (typeof window === 'undefined') return 'en';

    // Ignore stale or unsupported saved values instead of initializing Vue I18n with an unknown locale.
    const storedLocale = window.localStorage.getItem('xnova.locale');
    if (supportedLocales.includes(storedLocale)) return storedLocale;

    return 'en';
}

export const i18n = createI18n({
    // Use Vue I18n's Composition API, and expose its helpers to templates that rely on global injection.
    legacy: false,
    globalInjection: true,
    locale: initialLocale(),
    // English is the fallback for any translation key missing from the selected locale.
    fallbackLocale: 'en',
    messages: { fr, en, de, es, it },
    // The "game" format keeps displayed resource and counter values grouped and integer-only.
    numberFormats: {
        fr: { game: { style: 'decimal', useGrouping: true, maximumFractionDigits: 0 } },
        en: { game: { style: 'decimal', useGrouping: true, maximumFractionDigits: 0 } },
        de: { game: { style: 'decimal', useGrouping: true, maximumFractionDigits: 0 } },
        es: { game: { style: 'decimal', useGrouping: true, maximumFractionDigits: 0 } },
        it: { game: { style: 'decimal', useGrouping: true, maximumFractionDigits: 0 } },
    },
});

export function setLocale(locale) {
    // Do not update the UI or persistence layers for a locale outside the application allowlist.
    if (! supportedLocales.includes(locale)) return;

    // Update the reactive locale immediately so translated Vue content refreshes.
    i18n.global.locale.value = locale;

    if (typeof window !== 'undefined') {
        // Local storage restores the choice on the next client load.
        window.localStorage.setItem('xnova.locale', locale);

        // The server reads this cookie to render localized content before the client starts.
        document.cookie = `xnova.locale=${locale}; path=/; max-age=31536000; samesite=lax`;

        // Keep assistive technology and browser language tools in sync with the visible locale.
        document.documentElement.lang = locale;
    }
}

// Synchronize the document language during browser startup, while remaining safe during SSR.
if (typeof document !== 'undefined') {
    document.documentElement.lang = i18n.global.locale.value;
}
