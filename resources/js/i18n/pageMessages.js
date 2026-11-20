/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Provides application localization support for pageMessages.
 */

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
    auth: ['auth', 'accountUniverses', 'dashboard.noUniverses', 'dashboard.returnSoon', 'dashboard.galaxiesSystems'],
    profile: ['profile'],
    changelog: ['changelog', 'admin.previous', 'admin.next'],
    dashboard: ['dashboard', 'overviewClock', 'overviewIncoming', 'overviewView', 'overviewDefences', 'overview', 'galaxyMoon'],
    admin: ['admin', 'adminFlights', 'adminStats', 'adminLevels', 'adminAudit', 'adminModeration', 'adminUniverses', 'changelogManual', 'changelogPublication'],
    announcements: ['announcements', 'admin.previous', 'admin.next'],
    declarations: ['multiAccounts', 'admin.previous', 'admin.next'],
    galaxy: ['galaxyLegacySensors', 'galaxyMap', 'galaxyCombatLimit', 'galaxyEncounter', 'galaxyEarlyReturn', 'galaxyMoon', 'galaxyShipsFound', 'galaxyMissions', 'galaxyConcentrationNote', 'galaxy', 'unitNames', 'fleet.ship', 'common.select'],
    moon: ['moonLogistics', 'galaxyMissions', 'galaxyMoon', 'fleet.ship', 'buildingCatalog', 'overview.returning', 'overview.outbound', 'units.endsAt'],
    moonBuildings: ['moonLogistics', 'galaxyMoon', 'common', 'nav', 'units.endsAt', 'overview.returning', 'overview.outbound'],
    moonPhalanx: ['moonLogistics', 'galaxyMissions', 'galaxyMoon', 'fleet.ship', 'overview.returning', 'overview.outbound'],
    buildings: ['catalogFilters', 'buildingFilters', 'buildings', 'buildingActions', 'buildingCatalog'],
    research: ['research', 'researchLabels', 'technologyTree', 'elementInfo', 'buildingCatalog', 'unitNames', 'officerNames', 'galaxy.sensorsDesc'],
    combat: ['combatActions', 'combatProfiles', 'combat'],
    fleet: [
        'fleet',
        'fleet.ship',
        'galaxyMoon',
        'galaxy',
        'unitNames',
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
        'common.select',
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

/** Validate required nested keys, then copy only the page's declared translation branches. */
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
