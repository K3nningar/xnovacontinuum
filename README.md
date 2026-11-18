<p align="center"><img src="https://nicolas.boutinaud.ch/images/xnovacontinuum.png" width="400" alt="Xnova Continuum Logo"></p>

# XNova Continuum

XNova Continuum is a browser-based space strategy game that reimagines XNova with Laravel and Vue. Players join a universe, develop their planet, and explore the galaxy. The project is under development: not all features of the original game are available yet.

The project has a goal to expand and add some features that never got to be implemented in XNova Legacies main branch.

## Features

- Multi-language support (Français, English, Deutsch, Español, Italiano) - More supports could be appear on the future.
- Registration, login, universe selection.
- Construction of facilities, ships, defenses and recruitment of officers.
- Laboratory for researches
- Galaxy map, expedition and missions, moons, and orbital transfers.
- Administration panel with roles and an audit log for sensitive actions.

- New resource (Antimatter)
- New item (Artefacts)
- New buildings (<strong>Excavation site, Defense center, Enrollment center, Space dock, Particle accelerator</strong>)
- New researches (Antimatter-based technology)
- New ships (SuperNova and Planetary Destroyer - <strong>Finally !!!</strong>)
- New defenses (Planetary Protector - <strong>TOO !!!</strong>)
- New combat "mode" : New rounds for ground combat and adding military personal.
- Universes (One server, multi instances).

And be a bit different than OGame on some aspects : 

- Officers now has real names and their title is their class, fixed with titles from reality. New officers are incoming too.
- Reworked "noob" protection system (division system based on points earned by player actions). Two branches = Pacifist or Warmonger.
- New galaxy view
- Combat simulator
- Startup tutorial for new players
- Truly impactful expeditions (events that impact your colonies, fleet, researches and more)
- Rarity and quality system
- QoL improvments like filters and searching
- Multi-moon planets and truly impactful system of stellar objects (distance from the star will affect temperature).
- Unique droppable elements (<strong>Modified ships !</strong>)
- Artefacts (awesome items that impact your gameplay and add some diversity or bonuses)
- Weekend-based merchant for resources and unique stuff.
- Defenses no longer belong to shipyard, new dedicated building.

Some "on demand" modules will be added later :

- Factions ("global" alliances and diplomacy !)
- Alignments (choice-based gameplay scenarios <strong>-DESIGN IN PROGRESS-</strong>)
- Quests
- Earthed combat (new rounds in wars with ground units)
- DLCs (free of course) in future !

- And more !

The application uses Laravel 13, Inertia 3, Vue 3, and Vite.

## Local installation

### Requirements

- PHP 8.5 or a later PHP 8 release, with the extensions required by Composer and the PDO driver for the chosen database (`pdo_sqlite` for SQLite). Use the latest PHP security patch release.
- Composer.
- Node.js `^20.19.0` or `>=22.12.0` and npm.
- SQLite, or another database configured in `.env`.

### Setup

From the repository root:

```bash
composer install
php -r "file_exists('.env') || copy('.env.example', '.env');"
php artisan key:generate
```

By default, `.env.example` uses SQLite. Create the database file if it does not exist, without overwriting an existing database:

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

For another database, configure the `DB_*` variables in `.env` and create the database before continuing. Then initialize the tables and the Andromeda universe, and install the JavaScript dependencies:

```bash
php artisan migrate
php artisan db:seed
npm ci --ignore-scripts
npm run build
```

The seeder creates the Andromeda universe if it does not already exist. Back up an existing database before applying migrations. Do not regenerate the application key on an existing installation: doing so can make encrypted data unreadable.

English is the default language. Existing installations can update `APP_LOCALE`, `APP_FALLBACK_LOCALE`, and `APP_FAKER_LOCALE` in `.env` to `en`, `en`, and `en_US` respectively. If configuration is cached, run `php artisan config:clear` after updating `.env`. Players can still select another language; their saved choice takes precedence.

### Running the application

Start the application and the Vite server in two terminals:

```bash
php artisan serve
```

```bash
npm run dev
```

Open the address reported by `artisan serve` (by default `http://localhost:8000`), create an account, and join the universe.

Game URLs use English paths, such as `/commandcenter`. Previous French URLs are not redirected; update any bookmarks.

The Laravel scheduler also processes fleet missions when they are due. In development, run it in a third terminal:

```bash
php artisan schedule:work
```

In production, configure `php artisan schedule:run` to run every minute from the project root. Missions are also processed during requests from logged-in players.

## Game administration

After creating a player account and backing up the database, assign the first founder account once from a trusted console:

```bash
php artisan admin:founder <player-id>
```

The founder can assign levels 0 through 3 from the panel; level 4 cannot be assigned or removed from the website.

- **0 Player**: no access to the panel.
- **1 Operator**: overview, player search, temporary suspensions, general announcements, changelog visibility, and access to multi-account declarations.
- **2 Moderator**: the same access, plus lifting suspensions, adding resources, and adding manual changelog entries.
- **3 Administrator**: the same access, plus adding items and removing announcements.
- **4 Founder**: the same access, plus assigning levels and viewing the audit log.

Additions are limited to lower-level accounts and cannot target the operator's own account. Resources respect storage capacities; item grants check applicable queues and limits. Arbitrary SQL execution, account deletion, and universe resets are not provided.

Changelog versions and built-in entries are defined in `resources/js/changelog.json`, with their text in the locale files. Keep the current game version first: it is displayed in the game footer regardless of changelog visibility. New versions are hidden by default. An operator can make each version public or hide it again from the administration's changelog page. Moderators and higher levels can add dated entries to an existing version from that page; the text is displayed as entered in every language and is immediately public if the version is public. Publication changes and manual additions are recorded in the audit log. Run the database migrations when upgrading to enable this feature.

General announcements are limited to five per hour per operator. Players in the same universe can declare up to three other accounts using their identifiers; each affected player must make their own declaration. These voluntary declarations do not constitute confirmation by the other accounts. Submissions are limited to five per hour per player.

## Tests

```bash
composer test
npm run test:technology-tree
node --test resources/js/i18n/index.test.js
```

PHP tests use an in-memory SQLite database.

## License

MIT license: you are free to republish, modify, or fork the code for your own use.
