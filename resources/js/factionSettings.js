export const factionCategories = ['buildings', 'ships', 'defences'];
export const factionStatistics = ['attack', 'shield', 'hull'];

export function createFactionEditor(settings, catalog) {
    const original = JSON.parse(JSON.stringify(Array.isArray(settings) ? {} : settings ?? {}));
    const elements = {};

    for (const category of factionCategories) {
        elements[category] = {};
        for (const item of catalog[category]) {
            const value = original[category]?.[item.key] ?? {};
            elements[category][item.key] = {
                available: value.available !== false,
                name: value.name ?? '',
                image: value.image ?? '',
            };
        }
    }

    return {
        original,
        elements,
        statistics: Object.fromEntries(factionStatistics.map((key) => [key, original.statistics?.[key] ?? 1])),
    };
}

export function serializeFactionEditor(editor, catalog) {
    const settings = JSON.parse(JSON.stringify(editor.original));

    for (const category of factionCategories) {
        const values = { ...settings[category] };
        for (const item of catalog[category]) {
            const form = editor.elements[category][item.key];
            const value = { ...values[item.key] };
            if (form.available) delete value.available;
            else value.available = false;
            if (form.name.trim()) value.name = form.name.trim();
            else delete value.name;
            if (form.image.trim()) value.image = form.image.trim();
            else delete value.image;
            if (Object.keys(value).length) values[item.key] = value;
            else delete values[item.key];
        }
        if (Object.keys(values).length) settings[category] = values;
        else delete settings[category];
    }

    const statistics = { ...settings.statistics };
    for (const key of factionStatistics) {
        const value = Number(editor.statistics[key]);
        if (value === 1) delete statistics[key];
        else statistics[key] = value;
    }
    if (Object.keys(statistics).length) settings.statistics = statistics;
    else delete settings.statistics;

    return JSON.stringify(settings);
}
