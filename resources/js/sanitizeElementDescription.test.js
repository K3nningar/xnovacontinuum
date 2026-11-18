/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Tests the sanitizeElementDescription.test JavaScript module.
 */

import assert from 'node:assert/strict';
import test from 'node:test';
import { sanitizeElementDescription } from './sanitizeElementDescription.js';

test('element descriptions preserve safe formatting tags', () => {
    const description = '<p>Build <strong>ships</strong><br>with care.</p>';

    assert.equal(sanitizeElementDescription(description), description);
});

test('element descriptions preserve spans with approved alert classes', () => {
    const alertTypes = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];

    for (const type of alertTypes) {
        const description = `<span class="alert alert-${type}">Alert text.</span>`;

        assert.equal(sanitizeElementDescription(description), description);
    }
});

// Unsupported tags and attributes must remain inert text; only the harmless allowed tag itself may render.
test('element descriptions escape scripts, images, and attributes', () => {
    const description = '<script>alert(1)</script><img src=x onerror=alert(1)><strong onclick="alert(1)">safe text</strong><span style="color:red">untrusted style</span>';
    const sanitized = sanitizeElementDescription(description);
    const unsupportedAlert = sanitizeElementDescription('<span class="alert alert-custom">untrusted alert</span>');

    assert.equal(sanitized, '&lt;script&gt;alert(1)&lt;/script&gt;&lt;img src=x onerror=alert(1)&gt;&lt;strong onclick=&quot;alert(1)&quot;&gt;safe text</strong>&lt;span style=&quot;color:red&quot;&gt;untrusted style</span>');
    assert.equal(unsupportedAlert, '&lt;span class=&quot;alert alert-custom&quot;&gt;untrusted alert</span>');
});

test('element descriptions accept only string content', () => {
    assert.equal(sanitizeElementDescription(null), '');
});
