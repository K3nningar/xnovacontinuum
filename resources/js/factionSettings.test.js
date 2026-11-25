import assert from 'node:assert/strict';
import test from 'node:test';
import { createFactionEditor, serializeFactionEditor } from './factionSettings.js';

const catalog = {
    buildings: [{ key: 'metal' }, { key: 'crystal' }],
    ships: [{ key: '202' }],
    defences: [{ key: '401' }],
};

test('default settings expose every element and save only changes', () => {
    const editor = createFactionEditor([], catalog);
    assert.equal(editor.elements.buildings.metal.available, true);
    assert.equal(editor.statistics.attack, 1);
    editor.elements.ships['202'].available = false;
    editor.elements.buildings.metal.name = 'Mine terrienne';
    editor.statistics.shield = 1.5;
    assert.deepEqual(JSON.parse(serializeFactionEditor(editor, catalog)), {
        buildings: { metal: { name: 'Mine terrienne' } },
        ships: { 202: { available: false } },
        statistics: { shield: 1.5 },
    });
});

test('existing settings and unlisted elements survive edits and defaults can be restored', () => {
    const original = {
        ships: { 202: { available: false, name: 'Éclaireur', image: '/images/scout.webp' }, 999: { available: false } },
        statistics: { attack: 2, hull: 1.2 },
    };
    const editor = createFactionEditor(original, catalog);
    assert.equal(editor.elements.ships['202'].available, false);
    assert.equal(editor.elements.ships['202'].name, 'Éclaireur');
    assert.equal(editor.statistics.hull, 1.2);
    editor.elements.ships['202'].available = true;
    editor.elements.ships['202'].name = '';
    editor.statistics.attack = 1;
    assert.deepEqual(JSON.parse(serializeFactionEditor(editor, catalog)), {
        ships: { 202: { image: '/images/scout.webp' }, 999: { available: false } },
        statistics: { hull: 1.2 },
    });
    assert.equal(original.ships['202'].available, false);
});
