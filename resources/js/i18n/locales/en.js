/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Assembles translated messages for the English locale.
 */

import mergeMessages from './mergeMessages.js';
import shared from './en/shared.js';
import welcome from './en/welcome.js';
import auth from './en/auth.js';
import changelog from './en/changelog.js';
import overview from './en/overview.js';
import admin from './en/admin.js';
import announcements from './en/announcements.js';
import declarations from './en/declarations.js';
import galaxy from './en/galaxy.js';
import moon from './en/moon.js';
import buildings from './en/buildings.js';
import research from './en/research.js';
import combat from './en/combat.js';
import fleet from './en/fleet.js';
import officers from './en/officers.js';
import unitCatalog from './en/unit-catalog.js';
import shipyard from './en/shipyard.js';
import defences from './en/defences.js';

export default mergeMessages(shared, welcome, auth, changelog, overview, admin, announcements, declarations, galaxy, moon, buildings, research, combat, fleet, officers, unitCatalog, shipyard, defences);
