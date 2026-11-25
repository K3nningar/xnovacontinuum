/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Provides application functionality for sanitizeElementDescription.
 */

const formattingTagPattern = /<(\/?)(b|strong|i|em|u|s|del|ins|sub|sup|code|pre|p|br|hr|ul|ol|li|blockquote|span)(?:\s+(class="alert alert-(?:primary|secondary|success|danger|warning|info|light|dark)"))?(\s*\/?)>/gi;

/** Escape plain-text fragments while retaining already-encoded entities. */
function escapeHtml(text) {
    return text
        .replace(/&(?!(?:amp|lt|gt|quot|apos|#\d+|#x[\da-f]+);)/gi, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/** Allow only the formatting subset and approved alert spans; escape all other markup as text. */
export function sanitizeElementDescription(description) {
    if (typeof description !== 'string') {
        return '';
    }

    let sanitized = '';
    let cursor = 0;

    for (const match of description.matchAll(formattingTagPattern)) {
        sanitized += escapeHtml(description.slice(cursor, match.index));

        // Only spans may carry the tightly constrained alert class; unsupported attributes stay escaped.
        const [, closing, tag, classAttribute, selfClosing] = match;
        const normalizedTag = tag.toLowerCase();

        if (classAttribute && (normalizedTag !== 'span' || closing)) {
            sanitized += escapeHtml(match[0]);
        } else if (normalizedTag === 'br' || normalizedTag === 'hr') {
            sanitized += `<${normalizedTag}>`;
        } else if (selfClosing) {
            sanitized += escapeHtml(match[0]);
        } else {
            const safeClass = classAttribute ? ` ${classAttribute.toLowerCase()}` : '';
            sanitized += closing ? `</${normalizedTag}>` : `<${normalizedTag}${safeClass}>`;
        }

        cursor = match.index + match[0].length;
    }

    return sanitized + escapeHtml(description.slice(cursor));
}
