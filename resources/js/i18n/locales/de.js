/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Assembles translated messages for the German locale.
 */

import mergeMessages from './mergeMessages.js';
import shared from './de/shared.js';
import welcome from './de/welcome.js';
import auth from './de/auth.js';
import changelog from './de/changelog.js';
import overview from './de/overview.js';
import admin from './de/admin.js';
import announcements from './de/announcements.js';
import declarations from './de/declarations.js';
import galaxy from './de/galaxy.js';
import moon from './de/moon.js';
import buildings from './de/buildings.js';
import research from './de/research.js';
import combat from './de/combat.js';
import fleet from './de/fleet.js';
import officers from './de/officers.js';
import unitCatalog from './de/unit-catalog.js';
import shipyard from './de/shipyard.js';
import defences from './de/defences.js';

export default mergeMessages(shared, welcome, auth, changelog, overview, admin, announcements, declarations, galaxy, moon, buildings, research, combat, fleet, officers, unitCatalog, shipyard, defences);
