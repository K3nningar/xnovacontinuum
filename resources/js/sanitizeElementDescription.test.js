import assert from 'node:assert/strict';
import test from 'node:test';
import { sanitizeElementDescription } from './sanitizeElementDescription.js';

test('element descriptions preserve safe formatting tags', () => {
    const description = '<p>Build <strong>ships</strong><br>with care.</p>';

    assert.equal(sanitizeElementDescription(description), description);
});

test('element descriptions preserve spans with the approved alert class', () => {
    const description = '<span class="alert alert-danger">Unlocks the planetary destroyer.</span>';

    assert.equal(sanitizeElementDescription(description), description);
});

test('element descriptions escape scripts, images, and attributes', () => {
    const description = '<script>alert(1)</script><img src=x onerror=alert(1)><strong onclick="alert(1)">safe text</strong><span style="color:red">untrusted style</span>';
    const sanitized = sanitizeElementDescription(description);

    assert.equal(sanitized, '&lt;script&gt;alert(1)&lt;/script&gt;&lt;img src=x onerror=alert(1)&gt;&lt;strong onclick=&quot;alert(1)&quot;&gt;safe text</strong>&lt;span style=&quot;color:red&quot;&gt;untrusted style</span>');
});

test('element descriptions accept only string content', () => {
    assert.equal(sanitizeElementDescription(null), '');
});
