/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.5
 * Revision : 34ddc84
 * Description : Provides translated messages for the en buildings interface.
 */

export default {
  "catalogFilters": {
    "all": "All",
    "information": "Information",
    "combat": "Combat",
    "propulsion": "Propulsion",
    "energy": "Energy",
    "civilian": "Civilian",
    "artillery": "Artillery",
    "shields": "Shields",
    "missiles": "Missiles",
    "economy": "Economy",
    "science": "Science",
    "defence": "Defence",
    "command": "Command",
    "other": "Other",
    "unitCost": "Cost per unit"
  },
  "buildingFilters": {
    "label": "Categories",
    "all": "All facilities",
    "production": "Production",
    "storage": "Storage",
    "industry": "Industry",
    "research": "Research",
    "defence": "Defence",
    "special": "Special"
  },
  "buildings": {
    "metal": "Metal mine",
    "crystal": "Crystal mine",
    "deuterium": "Deuterium synthesizer",
    "solar": "Solar plant",
    "fusion": "Fusion plant",
    "robot_factory": "Robotics factory",
    "nano_factory": "Nanite factory",
    "hangar": "Shipyard",
    "metal_storage": "Metal storage",
    "crystal_storage": "Crystal storage",
    "deuterium_storage": "Deuterium tank",
    "laboratory": "Research laboratory",
    "terraformer": "Terraformer",
    "missile_silo": "Missile silo",
    "title": "Buildings",
    "eyebrow": "PLANETARY INFRASTRUCTURE",
    "heading": "Your buildings.",
    "subtitle": "Grow production and manage your energy balance.",
    "planet": "PLANET",
    "planetarySite": "PLANETARY QUEUE",
    "constructionCount": "{count} construction(s) queued",
    "noConstruction": "No construction in progress",
    "constructionRunning": "Construction in progress",
    "nextConstruction": "Next construction waiting",
    "constructionWillAppear": "Buildings under construction will appear here.",
    "closeQueue": "Close construction queue",
    "openQueue": "Open queue",
    "constructionQueue": "Construction queue",
    "queuesUsed": "{count} / 5 queue slots used",
    "building": "{name} · level {level}",
    "inConstruction": "BUILDING",
    "waiting": "WAITING",
    "starts": "Starts",
    "ends": "Ends",
    "remaining": "TIME LEFT",
    "startsIn": "STARTS IN",
    "noConstructionStarted": "No construction in progress. Start an upgrade to see it here.",
    "energyBalance": "ENERGY BALANCE",
    "production": "Production",
    "consumption": "Consumption",
    "mineEfficiency": "Mine efficiency",
    "productionSector": "PRODUCTION SECTOR",
    "availableFacilities": "Available facilities",
    "activity": "Activity",
    "finalCost": "FINAL COST",
    "buildNextLevel": "Build level {level}",
    "build": "Build",
    "constructing": "Building…",
    "fusionUse": "Deuterium consumption depends on level",
    "metalMine": "Metal mine",
    "metalMineDescription": "Extracts the metal needed to build your empire.",
    "crystalMine": "Crystal mine",
    "crystalMineDescription": "Produces crystal essential for technologies and ships.",
    "deuteriumSynth": "Deuterium synthesizer",
    "deuteriumDescription": "Refines deuterium from the planet’s deep oceans.",
    "solarPlant": "Solar plant",
    "solarDescription": "Provides energy to power planetary facilities.",
    "fusionPlant": "Fusion plant",
    "fusionDescription": "Produces energy by consuming deuterium.",
    "fusionRequirement": "Requires deuterium synthesizer level 5 and energy technology level 3.",
    "robotFactory": "Robotics factory",
    "robotDescription": "Automates work and reduces construction times.",
    "robotEffect": "Each level shortens planetary construction times.",
    "nanoFactory": "Nanite factory",
    "nanoDescription": "Specialized nanites speed up planetary work.",
    "nanoRequirement": "Requires robotics factory level 10 and computer technology level 10.",
    "shipyard": "Shipyard",
    "shipyardDescription": "Builds ships and defensive installations for your empire.",
    "shipyardRequirement": "Required to produce space units.",
    "metalStorage": "Metal storage",
    "metalStorageDescription": "Increases the planet’s maximum metal storage.",
    "crystalStorage": "Crystal storage",
    "crystalStorageDescription": "Increases the planet’s maximum crystal storage.",
    "deutStorage": "Deuterium tank",
    "deutStorageDescription": "Increases the planet’s maximum deuterium storage.",
    "laboratoryDescription": "Studies new technologies and advances your empire’s scientific progress.",
    "laboratoryRequirement": "Required to start research. Only one laboratory upgrade can be queued at a time.",
    "terraformerDescription": "Reshapes the surface and adds five available fields per level.",
    "terraformerRequirement": "Requires energy technology level 12 and 1,000 energy units.",
    "fieldsAdded": "additional fields",
    "allianceDeposit": "Alliance depot",
    "allianceDepositDescription": "Will resupply allied fleets during missions.",
    "allianceDepositRequirement": "Available when fleet missions are implemented.",
    "missileSilo": "Missile silo",
    "siloDescription": "Stores interceptor and interplanetary missiles.",
    "siloRequirement": "Required to build missiles."
  },
  "buildingActions": {
    "progressLabel": "Progress of {name}, level {level}"
  },
  "buildingCatalog": {
    "metal": {
      "name": "Metal mine",
      "description": "Extracts the metal needed to build your empire.",
      "unit": "metal / h"
    },
    "crystal": {
      "name": "Crystal mine",
      "description": "Produces crystal essential for technologies and ships.",
      "unit": "crystal / h"
    },
    "deuterium": {
      "name": "Deuterium synthesizer",
      "description": "Refines deuterium from the planet’s deep oceans.",
      "unit": "deuterium / h"
    },
    "solar": {
      "name": "Solar plant",
      "description": "Provides energy to power planetary facilities.",
      "unit": "energy / h"
    },
    "fusion": {
      "name": "Fusion plant",
      "description": "Produces energy by consuming deuterium.",
      "unit": "energy / h",
      "requirement": "Requires deuterium synthesizer level 5 and energy technology level 3."
    },
    "robot_factory": {
      "name": "Robotics factory",
      "description": "Automates work and reduces construction times.",
      "unit": "construction time",
      "requirement": "Each level shortens planetary construction times."
    },
    "nano_factory": {
      "name": "Nanite factory",
      "description": "Specialized nanites speed up planetary work.",
      "unit": "construction time",
      "requirement": "Requires robotics factory level 10 and computer technology level 10."
    },
    "hangar": {
      "name": "Shipyard",
      "description": "Builds ships and defensive installations for your empire.",
      "unit": "production",
      "requirement": "Required to produce space units."
    },
    "metal_storage": {
      "name": "Metal storage",
      "description": "Increases the planet’s maximum metal storage.",
      "unit": "capacity"
    },
    "crystal_storage": {
      "name": "Crystal storage",
      "description": "Increases the planet’s maximum crystal storage.",
      "unit": "capacity"
    },
    "deuterium_storage": {
      "name": "Deuterium tank",
      "description": "Increases the planet’s maximum deuterium storage.",
      "unit": "capacity"
    },
    "laboratory": {
      "name": "Research laboratory",
      "description": "Studies new technologies and advances your empire’s scientific progress.",
      "unit": "research",
      "requirement": "Required to start research. Only one laboratory upgrade can be queued at a time."
    },
    "terraformer": {
      "name": "Terraformer",
      "description": "Reshapes the surface and adds five available fields per level.",
      "unit": "additional fields",
      "requirement": "Requires energy technology level 12 and 1,000 energy units."
    },
    "missile_silo": {
      "name": "Missile silo",
      "description": "Stores interceptor and interplanetary missiles.",
      "unit": "missiles",
      "requirement": "Required to build missiles."
    }
  }
};
