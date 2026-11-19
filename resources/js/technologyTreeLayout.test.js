/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Tests the technologyTreeLayout.test JavaScript module.
 */

import assert from 'node:assert/strict';
import test from 'node:test';
import de from './i18n/locales/de.js';
import en from './i18n/locales/en.js';
import es from './i18n/locales/es.js';
import fr from './i18n/locales/fr.js';
import it from './i18n/locales/it.js';
import { dependencyPath, filterTechnologyTreeElements, getTechnologyTreeRelationships, layoutTechnologyTree, TECHNOLOGY_TREE_NODE_WIDTH } from './technologyTreeLayout.js';

const elements = [
    { id: 'mine', code: 1, kind: 'building', requirements: [] },
    { id: 'robot', code: 14, kind: 'building', requirements: [] },
    { id: 'hangar', code: 21, kind: 'building', requirements: [{ code: 14 }] },
    { id: 'energy', code: 113, kind: 'research', requirements: [] },
    { id: 'ship', code: 202, kind: 'ship', requirements: [{ code: 21 }, { code: 14 }] },
    { id: 'ship-advanced', code: 203, kind: 'ship', requirements: [{ code: 202 }] },
];

// This is the primary graph invariant: every resolvable direct prerequisite occupies an earlier depth.
test('technology tree puts every prerequisite to the left of its dependent', () => {
    const graph = layoutTechnologyTree(elements);

    for (const element of elements) {
        for (const requirement of element.requirements) {
            assert.ok(graph.byCode[requirement.code].x < graph.byCode[element.code].x);
        }
    }
});

test('technology tree orders dependents alongside their prerequisite branches', () => {
    const graph = layoutTechnologyTree([
        { id: 'first-root', code: 1, kind: 'building', requirements: [] },
        { id: 'second-root', code: 2, kind: 'research', requirements: [] },
        { id: 'second-branch', code: 10, kind: 'ship', requirements: [{ code: 2 }] },
        { id: 'first-branch', code: 20, kind: 'defence', requirements: [{ code: 1 }] },
    ]);

    assert.ok(graph.byCode[20].row < graph.byCode[10].row);
});

test('technology tree filtering removes unchecked categories and their nodes', () => {
    const visible = filterTechnologyTreeElements(elements, ['research', 'ship']);
    const graph = layoutTechnologyTree(visible);

    assert.ok(graph.nodes.every((element) => ['research', 'ship'].includes(element.kind)));
    assert.equal(graph.byCode[21], undefined);
    assert.ok(graph.byCode[202]);
});

test('technology tree details include direct prerequisites and dependents', () => {
    const catalog = [
        { id: 'root', code: 1, kind: 'building', requirements: [] },
        { id: 'selected', code: 2, kind: 'research', requirements: [{ code: 1, current: 3, required: 2 }] },
        { id: 'dependent', code: 3, kind: 'ship', requirements: [{ code: 2, current: 1, required: 4 }] },
        { id: 'indirect-dependent', code: 4, kind: 'defence', requirements: [{ code: 3, current: 0, required: 1 }] },
    ];
    const selected = catalog.find((element) => element.id === 'selected');
    const relationships = getTechnologyTreeRelationships(catalog, selected);

    assert.deepEqual(relationships.prerequisites.map(({ element, current, required }) => [element.code, current, required]), [[1, 3, 2]]);
    assert.deepEqual(relationships.dependencies.map(({ element, current, required }) => [element.code, current, required]), [[3, 1, 4]]);
});

test('all technology tree buildings have localized catalog names', () => {
    const buildingKeys = ['metal', 'crystal', 'deuterium', 'solar', 'fusion', 'robot_factory', 'nano_factory', 'hangar',
        'metal_storage', 'crystal_storage', 'deuterium_storage', 'laboratory', 'terraformer', 'missile_silo'];
    const locales = { de, en, es, fr, it };
    const detailLabels = [
        'category', 'currentLevel', 'targetLevel', 'status', 'costs', 'points', 'prerequisites', 'dependencies',
        'noDependencies', 'elementColumn', 'requiredLevel', 'requirementMet', 'requirementMissing',
    ];

    for (const [locale, translations] of Object.entries(locales)) {
        assert.ok(translations.technologyTree.filterByCategory, `${locale} is missing the category filter label`);
        assert.ok(translations.technologyTree.showAll, `${locale} is missing the show-all label`);
        assert.ok(translations.technologyTree.noCategoriesSelected, `${locale} is missing the empty-filter message`);

        for (const label of detailLabels) {
            assert.ok(translations.technologyTree[label], `${locale} is missing the ${label} label`);
        }
        for (const key of buildingKeys) {
            assert.ok(translations.buildingCatalog[key]?.name, `${locale} is missing the localized name for ${key}`);
        }
    }
});

// Verify connector endpoints use the same node geometry as the rendered cards and never leak invalid coordinates.
test('technology tree produces finite SVG curves from parent to dependent', () => {
    const graph = layoutTechnologyTree(elements);
    const path = dependencyPath(graph.byCode[21], graph.byCode[202], TECHNOLOGY_TREE_NODE_WIDTH);

    const source = graph.byCode[21];
    const target = graph.byCode[202];
    const [startX, startY, , , , , endX, endY] = path.match(/-?\d+(?:\.\d+)?/g).map(Number);

    assert.equal(startX, source.x + graph.nodeWidth - 10);
    assert.equal(startY, source.y + graph.nodeCenterOffset);
    assert.equal(endX, target.x + 10);
    assert.equal(endY, target.y + graph.nodeCenterOffset);
    assert.doesNotMatch(path, /NaN|Infinity|Object/);
});

test('technology nodes in a dependency column do not overlap vertically', () => {
    const graph = layoutTechnologyTree(elements);
    const dependentNodes = graph.nodes.filter((node) => node.depth === graph.byCode[202].depth);

    for (let index = 1; index < dependentNodes.length; index++) {
        assert.ok(dependentNodes[index].y - dependentNodes[index - 1].y >= graph.nodeCardHeight);
    }
});
