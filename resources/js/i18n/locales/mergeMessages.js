/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Combines locale message objects with their shared translations.
 */

export default function mergeMessages(...sources) {
    const merged = {};

    for (const source of sources) {
        for (const [key, value] of Object.entries(source)) {
            if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
                const existing = merged[key];
                merged[key] = mergeMessages(existing && typeof existing === 'object' ? existing : {}, value);
            } else {
                merged[key] = value;
            }
        }
    }

    return merged;
}
