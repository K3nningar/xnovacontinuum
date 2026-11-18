/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Provides application functionality for technologyTreeLayout.
 */

export const TECHNOLOGY_TREE_NODE_WIDTH = 300;
const COLUMN_GAP = 72;
const ROW_HEIGHT = 168;
const TOP_PADDING = 28;
const NODE_HEIGHT = 122;
const NODE_CARD_HEIGHT = NODE_HEIGHT - 10;
const NODE_CENTER_OFFSET = TOP_PADDING + NODE_CARD_HEIGHT / 2;
const NODE_ENTRY_OFFSET = 10;
const NODE_EXIT_OFFSET = NODE_ENTRY_OFFSET + TECHNOLOGY_TREE_NODE_WIDTH - 20;
const CATEGORY_ORDER = ['building', 'research', 'ship', 'defence', 'officer'];

/** Keep only nodes whose kind is enabled; relationships to filtered nodes are omitted later. */
export function filterTechnologyTreeElements(elements, visibleKinds) {
    const kinds = new Set(visibleKinds);

    return elements.filter((element) => kinds.has(element.kind));
}

/** Resolve the selected node's direct prerequisites and direct dependents from the full catalog. */
export function getTechnologyTreeRelationships(elements, element) {
    const byCode = new Map(elements.map((item) => [item.code, item]));
    const prerequisites = element.requirements
        .map((requirement) => ({ ...requirement, element: byCode.get(requirement.code) }))
        .filter((requirement) => requirement.element);
    const dependencies = elements.flatMap((dependent) => dependent.requirements
        .filter((requirement) => requirement.code === element.code)
        .map((requirement) => ({ ...requirement, element: dependent })));

    return { prerequisites, dependencies };
}

/** Build a cubic SVG connector from the right edge of a prerequisite to its dependent. */
export function dependencyPath(source, target, nodeWidth = TECHNOLOGY_TREE_NODE_WIDTH) {
    const startX = source.x + (nodeWidth === TECHNOLOGY_TREE_NODE_WIDTH ? NODE_EXIT_OFFSET : nodeWidth);
    const startY = source.y + NODE_CENTER_OFFSET;
    const endX = target.x + NODE_ENTRY_OFFSET;
    const endY = target.y + NODE_CENTER_OFFSET;
    const bend = Math.max(28, (endX - startX) * 0.48);

    return `M ${startX} ${startY} C ${startX + bend} ${startY}, ${endX - bend} ${endY}, ${endX} ${endY}`;
}

/** Assign dependency-depth columns and reorder rows to keep related branches visually aligned. */
export function layoutTechnologyTree(elements) {
    const byCode = Object.fromEntries(elements.map((element) => [element.code, element]));
    const depthById = new Map();

    // A cycle-safe longest-prerequisite walk keeps every resolvable parent in an earlier column.
    function depthOf(element, visiting = new Set()) {
        if (depthById.has(element.id)) return depthById.get(element.id);
        if (visiting.has(element.id)) return 0;

        const parents = element.requirements
            .map((requirement) => byCode[requirement.code])
            .filter((parent) => parent && parent.id !== element.id);
        const depth = parents.length
            ? Math.max(...parents.map((parent) => depthOf(parent, new Set(visiting).add(element.id)))) + 1
            : 0;

        depthById.set(element.id, depth);

        return depth;
    }

    elements.forEach((element) => depthOf(element));

    const columns = new Map();
    for (const element of elements) {
        const depth = depthById.get(element.id) ?? 0;
        if (!columns.has(depth)) columns.set(depth, []);
        columns.get(depth).push(element);
    }

    const orderedColumns = [...columns.entries()].sort(([left], [right]) => left - right);
    const categoryRank = new Map(CATEGORY_ORDER.map((kind, index) => [kind, index]));
    const rowById = new Map();
    const childrenById = new Map(elements.map((element) => [element.id, []]));
    for (const element of elements) {
        for (const requirement of element.requirements) {
            const parent = byCode[requirement.code];
            if (parent && parent.id !== element.id) childrenById.get(parent.id).push(element);
        }
    }

    const orderColumn = (columnElements, neighbors) => {
        const scoreById = new Map(columnElements.map((element) => {
            const rows = neighbors(element)
                .map((neighbor) => rowById.get(neighbor.id))
                .filter(Number.isFinite);

            return [element.id, rows.length ? rows.reduce((sum, row) => sum + row, 0) / rows.length : Number.POSITIVE_INFINITY];
        }));

        columnElements.sort((left, right) => scoreById.get(left.id) - scoreById.get(right.id)
            || (categoryRank.get(left.kind) ?? CATEGORY_ORDER.length) - (categoryRank.get(right.kind) ?? CATEGORY_ORDER.length)
            || left.code - right.code);
        columnElements.forEach((element, row) => rowById.set(element.id, row));
    };

    // Alternating barycenter sweeps reduce edge crossings without changing dependency depth.
    const reverseColumns = () => {
        for (const [, columnElements] of orderedColumns.slice(0, -1).reverse()) {
            orderColumn(columnElements, (element) => childrenById.get(element.id));
        }
    };

    const forwardColumns = () => {
        for (const [depth, columnElements] of orderedColumns) {
            if (depth === 0) continue;

            orderColumn(columnElements, (element) => element.requirements
                .map((requirement) => byCode[requirement.code])
                .filter((parent) => parent && parent.id !== element.id));
        }
    };

    if (orderedColumns.length) {
        orderColumn(orderedColumns[0][1], () => []);
        forwardColumns();
        reverseColumns();
        forwardColumns();
    }

    const nodes = orderedColumns.flatMap(([depth, columnElements]) => columnElements.map((element, row) => ({
        ...element,
        depth,
        row,
        x: depth * (TECHNOLOGY_TREE_NODE_WIDTH + COLUMN_GAP),
        y: TOP_PADDING + row * ROW_HEIGHT,
    })));

    return {
        nodes,
        byCode: Object.fromEntries(nodes.map((node) => [node.code, node])),
        width: (Math.max(0, ...nodes.map((node) => node.depth)) + 1) * (TECHNOLOGY_TREE_NODE_WIDTH + COLUMN_GAP) - COLUMN_GAP,
        height: Math.max(1, ...[...columns.values()].map((column) => column.length)) * ROW_HEIGHT + TOP_PADDING,
        columnCounts: Object.fromEntries([...columns.entries()].map(([depth, column]) => [depth, column.length])),
        nodeWidth: TECHNOLOGY_TREE_NODE_WIDTH,
        columnPitch: TECHNOLOGY_TREE_NODE_WIDTH + COLUMN_GAP,
        rowHeight: ROW_HEIGHT,
        nodeHeight: NODE_HEIGHT,
        nodeCardHeight: NODE_CARD_HEIGHT,
        nodeCenterOffset: NODE_CENTER_OFFSET,
        topPadding: TOP_PADDING,
    };
}
