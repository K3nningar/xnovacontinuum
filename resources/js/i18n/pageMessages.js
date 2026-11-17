const unitCatalogProductionPaths = [
    'production.installations',
    'production.queue',
    'production.planetarySite',
    'production.noneInProduction',
    'production.addToQueue',
    'production.buildUnique',
    'production.inService',
    'production.queued',
    'production.quantity',
    'production.finished',
    'production.finish',
    'buildingFilters.label',
    'catalogFilters',
    'elementInfo',
    'rarities',
    'units.requirementPrefix',
    'units.level',
    'units.requirementProgress',
    'units.noRequirement',
    'units.resourcesMissing',
    'units.requirementsMissing',
    'units.seconds',
];

export const pageTranslationPaths = {
    welcome: ['welcome'],
    auth: ['auth'],
    changelog: ['changelog', 'admin.previous', 'admin.next'],
    dashboard: ['dashboard', 'overviewClock', 'overviewIncoming', 'overviewView', 'overviewDefences', 'overview'],
    admin: ['admin', 'adminFlights', 'adminStats', 'adminLevels', 'adminAudit', 'adminModeration', 'changelogManual', 'changelogPublication'],
    announcements: ['announcements', 'admin.previous', 'admin.next'],
    declarations: ['multiAccounts', 'admin.previous', 'admin.next'],
    galaxy: ['galaxyLegacySensors', 'galaxyMap', 'galaxyCombatLimit', 'galaxyEncounter', 'galaxyEarlyReturn', 'galaxyMoon', 'galaxyShipsFound', 'galaxyMissions', 'galaxyConcentrationNote', 'galaxy'],
    moon: ['moonLogistics'],
    buildings: ['catalogFilters', 'buildingFilters', 'buildings', 'buildingActions', 'buildingCatalog'],
    research: ['research', 'researchLabels', 'technologyTree', 'elementInfo', 'buildingCatalog', 'unitNames', 'officerNames', 'galaxy.sensorsDesc'],
    combat: ['combatActions', 'combatProfiles', 'combat'],
    fleet: [
        'fleet',
        'galaxyMissions',
        'galaxyEncounter',
        'galaxyEarlyReturn',
        'galaxyShipsFound',
        'moonLogistics.deposit',
        'moonLogistics.withdraw',
        'moonLogistics.moon_deposit',
        'moonLogistics.moon_withdraw',
        'moonLogistics.moonTarget',
        'overview.returning',
        'overview.outbound',
        'units.endsAt',
        'combat.attacker',
        'combat.defender',
        'combat.losses',
        'combat.roundNumber',
        'combat.shotCount',
        'combat.lossCount',
        'common.metal',
        'common.crystal',
        'common.deuterium',
    ],
    shipyard: [
        'production.shipyardTitle',
        'production.shipyardHeading',
        'production.shipsAndSatellites',
        'production.navalYard',
        'production.planetShipyard',
        ...unitCatalogProductionPaths,
        'unitNames',
        'buildingCatalog',
        'researchLabels',
        'officerNames',
    ],
    defences: [
        'production.defenceTitle',
        'production.defenceHeading',
        'production.defencesAndMissiles',
        'production.defenceShield',
        'production.planetDefence',
        ...unitCatalogProductionPaths,
        'unitNames',
        'buildingCatalog',
        'researchLabels',
        'officerNames',
    ],
    officers: [
        'officers',
        'officerNames',
        'elementDescriptions',
        'production.officerTitle',
        'production.officerLevel',
        'production.officerRequirement',
        'production.officerRecruitSuccess',
    ],
    shared: ['common', 'nav', 'hud', 'rarities'],
};

export function getPageTranslations(messages, page) {
    if (!Object.prototype.hasOwnProperty.call(pageTranslationPaths, page)) {
        throw new RangeError(`Unknown translation page "${page}". Available pages: ${Object.keys(pageTranslationPaths).join(', ')}`);
    }

    const paths = pageTranslationPaths[page];
    const missingPaths = paths.filter((path) => {
        let value = messages;

        for (const segment of path.split('.')) {
            if (!value || !Object.prototype.hasOwnProperty.call(value, segment)) {
                return true;
            }

            value = value[segment];
        }

        return false;
    });

    if (missingPaths.length > 0) {
        throw new Error(`Missing translation paths for "${page}": ${missingPaths.join(', ')}`);
    }

    const pageMessages = {};

    for (const path of paths) {
        const segments = path.split('.');
        const leaf = segments.pop();
        let source = messages;
        let target = pageMessages;

        for (const segment of segments) {
            source = source[segment];
            target[segment] ??= {};
            target = target[segment];
        }

        target[leaf] = source[leaf];
    }

    return pageMessages;
}
