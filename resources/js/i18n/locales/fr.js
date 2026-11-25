/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Assembles translated messages for the French locale.
 */

import mergeMessages from './mergeMessages.js';
import shared from './fr/shared.js';
import welcome from './fr/welcome.js';
import auth from './fr/auth.js';
import changelog from './fr/changelog.js';
import overview from './fr/overview.js';
import admin from './fr/admin.js';
import announcements from './fr/announcements.js';
import declarations from './fr/declarations.js';
import galaxy from './fr/galaxy.js';
import moon from './fr/moon.js';
import buildings from './fr/buildings.js';
import research from './fr/research.js';
import combat from './fr/combat.js';
import fleet from './fr/fleet.js';
import officers from './fr/officers.js';
import unitCatalog from './fr/unit-catalog.js';
import shipyard from './fr/shipyard.js';
import defences from './fr/defences.js';

export default mergeMessages(shared, welcome, auth, changelog, overview, admin, announcements, declarations, galaxy, moon, buildings, research, combat, fleet, officers, unitCatalog, shipyard, defences);
