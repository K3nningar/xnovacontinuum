import de from './locales/de.js';
import en from './locales/en.js';
import es from './locales/es.js';
import fr from './locales/fr.js';
import it from './locales/it.js';
import { getPageTranslations, pageTranslationPaths } from './pageMessages.js';

const locales = { en, fr, de, es, it };

if (process.argv.includes('--help')) {
    console.log('Usage: npm run translations:page -- -- --page=<page> --locale=<locale>');
    console.log(`Pages: ${Object.keys(pageTranslationPaths).join(', ')}`);
    console.log(`Locales: ${Object.keys(locales).join(', ')}`);
    process.exit(0);
}

const argumentsByName = {};

for (const argument of process.argv.slice(2)) {
    const separator = argument.indexOf('=');
    const name = argument.slice(2, separator);

    if (!argument.startsWith('--') || separator < 3 || !['page', 'locale'].includes(name)) {
        console.error(`Invalid argument "${argument}". Use --help to see the accepted options.`);
        process.exit(1);
    }

    if (Object.prototype.hasOwnProperty.call(argumentsByName, name)) {
        console.error(`Option --${name} may only be provided once.`);
        process.exit(1);
    }

    argumentsByName[name] = argument.slice(separator + 1);
}

const { page, locale } = argumentsByName;

if (!page || !locale) {
    console.error('Both --page and --locale are required. Use --help to list available values.');
    process.exit(1);
}

if (!Object.prototype.hasOwnProperty.call(locales, locale)) {
    console.error(`Unknown locale "${locale}". Available locales: ${Object.keys(locales).join(', ')}`);
    process.exit(1);
}

try {
    console.log(JSON.stringify({
        page,
        locale,
        messages: getPageTranslations(locales[locale], page),
    }, null, 2));
} catch (error) {
    console.error(error.message);
    process.exit(1);
}
