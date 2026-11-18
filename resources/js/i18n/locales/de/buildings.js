/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Provides translated messages for the de buildings interface.
 */

export default {
  "catalogFilters": {
    "all": "Alle",
    "information": "Information",
    "combat": "Kampf",
    "propulsion": "Antrieb",
    "energy": "Energie",
    "civilian": "Zivil",
    "artillery": "Artillerie",
    "shields": "Schilde",
    "missiles": "Raketen",
    "economy": "Wirtschaft",
    "science": "Wissenschaft",
    "defence": "Verteidigung",
    "command": "Kommando",
    "other": "Sonstige",
    "unitCost": "Kosten pro Einheit"
  },
  "buildingFilters": {
    "label": "Kategorien",
    "all": "Alle Anlagen",
    "production": "Produktion",
    "storage": "Lager",
    "industry": "Industrie",
    "research": "Forschung",
    "defence": "Verteidigung",
    "special": "Spezial"
  },
  "buildings": {
    "metal": "Metallmine",
    "crystal": "Kristallmine",
    "deuterium": "Deuterium-Synthesizer",
    "solar": "Solarkraftwerk",
    "fusion": "Fusionskraftwerk",
    "robot_factory": "Roboterfabrik",
    "nano_factory": "Nanitenfabrik",
    "hangar": "Raumschiffwerft",
    "metal_storage": "Metallspeicher",
    "crystal_storage": "Kristallspeicher",
    "deuterium_storage": "Deuteriumtank",
    "laboratory": "Forschungslabor",
    "terraformer": "Terraformer",
    "missile_silo": "Raketensilo",
    "title": "Gebäude",
    "eyebrow": "PLANETARE INFRASTRUKTUR",
    "heading": "Deine Gebäude.",
    "subtitle": "Steigere die Produktion und verwalte deine Energiebilanz.",
    "planet": "PLANET",
    "planetarySite": "PLANETARE WARTESCHLANGE",
    "constructionCount": "{count} Bauauftrag/-aufträge in der Warteschlange",
    "noConstruction": "Kein Bau im Gange",
    "constructionRunning": "Bau im Gange",
    "nextConstruction": "Nächster Bau wartet",
    "constructionWillAppear": "Laufende Bauaufträge erscheinen hier.",
    "closeQueue": "Bauwarteschlange schließen",
    "openQueue": "Warteschlange öffnen",
    "constructionQueue": "Bauwarteschlange",
    "queuesUsed": "{count} / 5 Bauplätze belegt",
    "building": "{name} · Stufe {level}",
    "inConstruction": "IM BAU",
    "waiting": "WARTEND",
    "starts": "Beginn",
    "ends": "Ende",
    "remaining": "VERBLEIBENDE ZEIT",
    "startsIn": "BEGINNT IN",
    "noConstructionStarted": "Kein Bau im Gange. Starte einen Ausbau, um ihn hier zu sehen.",
    "energyBalance": "ENERGIEBILANZ",
    "production": "Produktion",
    "consumption": "Verbrauch",
    "mineEfficiency": "Minenleistung",
    "productionSector": "PRODUKTIONSSEKTOR",
    "availableFacilities": "Verfügbare Anlagen",
    "activity": "Aktivität",
    "finalCost": "ENDKOSTEN",
    "buildNextLevel": "Stufe {level} bauen",
    "build": "Bauen",
    "constructing": "Wird gebaut…",
    "fusionUse": "Deuteriumverbrauch abhängig von der Stufe",
    "metalMine": "Metallmine",
    "metalMineDescription": "Fördert Metall für den Aufbau deines Imperiums.",
    "crystalMine": "Kristallmine",
    "crystalMineDescription": "Produziert Kristall für Technologien und Schiffe.",
    "deuteriumSynth": "Deuterium-Synthesizer",
    "deuteriumDescription": "Raffiniert Deuterium aus den tiefen Ozeanen des Planeten.",
    "solarPlant": "Solarkraftwerk",
    "solarDescription": "Liefert Energie für die planetaren Anlagen.",
    "fusionPlant": "Fusionskraftwerk",
    "fusionDescription": "Erzeugt Energie durch den Verbrauch von Deuterium.",
    "fusionRequirement": "Benötigt Deuterium-Synthesizer Stufe 5 und Energietechnik Stufe 3.",
    "robotFactory": "Roboterfabrik",
    "robotDescription": "Automatisiert Arbeiten und verkürzt Bauzeiten.",
    "robotEffect": "Jede Stufe verkürzt planetare Bauzeiten.",
    "nanoFactory": "Nanitenfabrik",
    "nanoDescription": "Spezialisierte Naniten beschleunigen planetare Arbeiten.",
    "nanoRequirement": "Benötigt Roboterfabrik Stufe 10 und Computertechnik Stufe 10.",
    "shipyard": "Raumschiffwerft",
    "shipyardDescription": "Baut Schiffe und Verteidigungsanlagen für dein Imperium.",
    "shipyardRequirement": "Für die Produktion von Raumeinheiten erforderlich.",
    "metalStorage": "Metallspeicher",
    "metalStorageDescription": "Erhöht die maximale Metalllagerkapazität des Planeten.",
    "crystalStorage": "Kristallspeicher",
    "crystalStorageDescription": "Erhöht die maximale Kristalllagerkapazität des Planeten.",
    "deutStorage": "Deuteriumtank",
    "deutStorageDescription": "Erhöht die maximale Deuteriumlagerkapazität des Planeten.",
    "laboratoryDescription": "Erforscht Technologien und fördert den wissenschaftlichen Fortschritt deines Imperiums.",
    "laboratoryRequirement": "Zum Starten von Forschungen erforderlich. Es kann jeweils nur ein Labor ausgebaut werden.",
    "terraformerDescription": "Gestaltet die Oberfläche um und fügt pro Stufe fünf Felder hinzu.",
    "terraformerRequirement": "Benötigt Energietechnik Stufe 12 und 1.000 Energieeinheiten.",
    "fieldsAdded": "zusätzliche Felder",
    "allianceDeposit": "Allianzdepot",
    "allianceDepositDescription": "Versorgt künftig verbündete Flotten während Missionen.",
    "allianceDepositRequirement": "Verfügbar, sobald Flottenmissionen implementiert sind.",
    "missileSilo": "Raketensilo",
    "siloDescription": "Lagert Abfang- und Interplanetarraketen.",
    "siloRequirement": "Für den Raketenbau erforderlich."
  },
  "buildingActions": {
    "progressLabel": "Fortschritt von {name}, Stufe {level}"
  },
  "buildingCatalog": {
    "metal": {
      "name": "Metallmine",
      "description": "Fördert das Metall für den Aufbau deines Imperiums.",
      "unit": "Metall / Std."
    },
    "crystal": {
      "name": "Kristallmine",
      "description": "Produziert Kristall für Technologien und Schiffe.",
      "unit": "Kristall / Std."
    },
    "deuterium": {
      "name": "Deuterium-Synthesizer",
      "description": "Raffiniert Deuterium aus den tiefen Ozeanen des Planeten.",
      "unit": "Deuterium / Std."
    },
    "solar": {
      "name": "Solarkraftwerk",
      "description": "Liefert Energie für die planetaren Anlagen.",
      "unit": "Energie / Std."
    },
    "fusion": {
      "name": "Fusionskraftwerk",
      "description": "Erzeugt Energie durch den Verbrauch von Deuterium.",
      "unit": "Energie / Std.",
      "requirement": "Benötigt Deuterium-Synthesizer Stufe 5 und Energietechnik Stufe 3."
    },
    "robot_factory": {
      "name": "Roboterfabrik",
      "description": "Automatisiert Arbeiten und verkürzt Bauzeiten.",
      "unit": "Bauzeit",
      "requirement": "Jede Stufe verkürzt planetare Bauzeiten."
    },
    "nano_factory": {
      "name": "Nanitenfabrik",
      "description": "Spezialisierte Naniten beschleunigen planetare Arbeiten.",
      "unit": "Bauzeit",
      "requirement": "Benötigt Roboterfabrik Stufe 10 und Computertechnik Stufe 10."
    },
    "hangar": {
      "name": "Raumschiffwerft",
      "description": "Baut Schiffe und Verteidigungsanlagen für dein Imperium.",
      "unit": "Produktion",
      "requirement": "Für die Produktion von Raumeinheiten erforderlich."
    },
    "metal_storage": {
      "name": "Metallspeicher",
      "description": "Erhöht die maximale Metalllagerkapazität des Planeten.",
      "unit": "Kapazität"
    },
    "crystal_storage": {
      "name": "Kristallspeicher",
      "description": "Erhöht die maximale Kristalllagerkapazität des Planeten.",
      "unit": "Kapazität"
    },
    "deuterium_storage": {
      "name": "Deuteriumtank",
      "description": "Erhöht die maximale Deuteriumlagerkapazität des Planeten.",
      "unit": "Kapazität"
    },
    "laboratory": {
      "name": "Forschungslabor",
      "description": "Erforscht Technologien und fördert den wissenschaftlichen Fortschritt deines Imperiums.",
      "unit": "Forschung",
      "requirement": "Zum Starten von Forschungen erforderlich. Es kann jeweils nur ein Labor ausgebaut werden."
    },
    "terraformer": {
      "name": "Terraformer",
      "description": "Gestaltet die Oberfläche um und fügt pro Stufe fünf Felder hinzu.",
      "unit": "Zusatzfelder",
      "requirement": "Benötigt Energietechnik Stufe 12 und 1.000 Energieeinheiten."
    },
    "missile_silo": {
      "name": "Raketensilo",
      "description": "Lagert Abfang- und Interplanetarraketen.",
      "unit": "Raketen",
      "requirement": "Für den Raketenbau erforderlich."
    }
  }
};
