/*
 * Author / Contributor : K3nningar
 * Code Version : 0.2.0-alpha
 * Revision : 34ddc84
 * Description : Provides translated messages for the de admin interface.
 */

export default {
  "adminModules": { "title": "Module", "enableFactions": "Fraktionen aktivieren", "factionsHelp": "Deaktivierte Fraktionen verlieren vorübergehend ihre Boni, Einschränkungen und diplomatischen Beziehungen. Ihre Einstellungen bleiben erhalten.", "global": "Diese Einstellungen gelten für alle Universen.", "enableOfficers": "Offiziere aktivieren", "individual": "Verfügbare Offiziere", "help": "Deaktivierte Offiziere können nicht rekrutiert werden; ihre Voraussetzungen entfallen. Ausstehende Rekrutierungen werden bei Fälligkeit erstattet. Die Einzelauswahl bleibt erhalten.", "warning": "Forschungsboni von Spion und Kommandant werden bei Deaktivierung dieser Offiziere ausgesetzt.", "save": "Speichern", "saved": "Moduleinstellungen gespeichert." },
  "changelogManual": {
    "title": "Manuelles Änderungsprotokoll",
    "hint": "Dieser Text ist in allen Sprachen identisch und sofort sichtbar, wenn die Version öffentlich ist.",
    "version": "Version",
    "date": "Datum",
    "type": "Typ",
    "description": "Beschreibung",
    "add": "Eintrag hinzufügen",
    "saved": "Eintrag gespeichert."
  },
  "changelogPublication": {
    "published": "Öffentlich",
    "publish": "Veröffentlichen",
    "unpublish": "Öffentlich verbergen",
    "empty": "Noch keine öffentlichen Änderungen.",
    "saved": "Sichtbarkeit aktualisiert.",
    "baseline": "Erster Entwicklungsmeilenstein; die Veröffentlichung des Protokolls bedeutet keine Veröffentlichung der Software. Die Daten geben den Zeitpunkt der Änderungen an."
  },
  "adminFlights": {
    "title": "Nächste Flotten unterwegs (maximal 25)",
    "empty": "Keine Flotten unterwegs.",
    "player": "Spieler",
    "mission": "Mission",
    "arrival": "Ankunft"
  },
  "adminStats": {
    "title": "Statistiken",
    "players": "Spieler",
    "planets": "Planeten",
    "universes": "Universen",
    "fleets": "Flotten unterwegs"
  },
  "adminLevels": {
    "level0": "Spieler",
    "level1": "Operator",
    "level2": "Moderator",
    "level3": "Administrator",
    "level4": "Gründer"
  },
  "adminAudit": {
    "title": "Letzte 50 Aktionen",
    "empty": "Keine Aktionen aufgezeichnet.",
    "actors": "Ausführender #{actor} · Spieler #{target} · Planet #{planet}"
  },
  "adminModeration": {
    "title": "Moderation",
    "protected": "Du kannst weder dein eigenes Konto noch ein Konto mit gleicher oder höherer Stufe ändern.",
    "suspendedUntil": "Gesperrt bis {date}",
    "days": "Dauer in Tagen (1 bis 365)",
    "reason": "Grund",
    "suspend": "Konto sperren",
    "unsuspend": "Sperre aufheben",
    "storageLimit": "Zugaben dürfen die Lagerkapazität nicht überschreiten."
  },
  "adminUniverses": {
    "title": "Neues Universum öffnen",
    "hint": "Erstelle ein aktives Universum. Neue Spieler können es bei der Gründung ihres Imperiums auswählen.",
    "name": "Name des Universums",
    "slug": "Eindeutige Kennung",
    "galaxies": "Galaxien (1 bis 9)",
    "systems": "Systeme pro Galaxie (1 bis 499)",
    "economySpeed": "Wirtschaftsgeschwindigkeit (0,1 bis 100)",
    "fleetSpeed": "Flottengeschwindigkeit (0,1 bis 100)",
    "create": "Universum erstellen und öffnen",
    "created": "Universum erstellt und geöffnet.",
    "list": "Bestehende Universen",
    "empty": "Keine Universen.",
    "open": "Öffnen",
    "galaxiesShort": "Galaxien",
    "systemsShort": "Systeme",
    "namedSystems": "{count} Systeme benannt",
    "generatingNames": "Himmelsnamen werden generiert…",
    "speeds": "Wirtschaft ×{economy} · Flotten ×{fleet}",
    "status": { "active": "Offen", "closed": "Geschlossen" }
  },
  "admin": {
    "previous": "Zurück",
    "next": "Weiter",
    "title": "Verwaltung",
    "eyebrow": "OBERKOMMANDO",
    "subtitle": "Verwalte Spieler und ihre Planeten.",
    "overview": "Übersicht",
    "elements": "Elemente",
    "resources": "Ressourcen",
    "roles": "Rollen",
    "universes": "Universen",
    "players": "Spieler",
    "search": "Spieler suchen",
    "searchButton": "Suchen",
    "noPlayers": "Keine Spieler gefunden.",
    "pagination": "Spielerseiten",
    "selection": "Auswahl",
    "selectPlayer": "Wähle einen Spieler aus.",
    "playerLevel": "Administratorstufe: {level}",
    "planet": "Planet",
    "selectPlanet": "Planet auswählen",
    "noPlanets": "Dieser Spieler hat keine Planeten.",
    "selectPlanetFirst": "Wähle einen Planeten aus, um fortzufahren.",
    "category": "Kategorie",
    "selectCategory": "Kategorie auswählen",
    "element": "Element",
    "selectElement": "Element auswählen",
    "noElements": "Keine Elemente verfügbar.",
    "amount": "Menge",
    "adminLevel": "Administratorstufe",
    "submit": "Speichern",
    "saving": "Wird gespeichert…",
    "success": "Änderung gespeichert."
  }
};
