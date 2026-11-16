<p align="center"><img src="https://nicolas.boutinaud.ch/images/xnovacontinuum.png" width="400" alt="Xnova Continuum Logo"></p>

# XNova Continuum

XNova Continuum is a browser-based space strategy game that reimagines XNova with Laravel and Vue. Players join a universe, develop their planet, and explore the galaxy. The project is under development: not all features of the original game are available yet.

The project has a goal to expand and add some features that never got to be implemented in XNova Legacies main branch :

- New buildings
- New researches
- New ships (SuperNova and Planetary Destructor - <strong>Finally !!!</strong>)
- New defenses (Planetary Protector (<strong>TOO !!!</strong>)
- Universes (One server, multi instances).

And be a bit different than OGame on some aspects (Officers now has real names and their title is their class). New officers are incoming too.

Some "on demand" modules will be added later :

- Factions ("global" alliances and diplomacy !)
- Alignments (choice-based gameplay scenarios <strong>-DESIGN IN PROGRESS-</strong>)
- Reworked "noob" protection system
- New galaxy view
- QoL improvments like filters and searching
- Multi-moon planets and truly impactful system of stellar objects (distance from the star will affect temperature).
- Tutorials and quests
- Rarity and quality system
- Unique droppable elements (<strong>Modified ships !</strong>)
- Truly impactful expeditions
- Defenses no longer belong to shipyard, new dedicated building.
- Weekend-based merchant for resources and unique stuff.
- DLCs (free of course) TBD in future !

- And more !

## Features

- Registration, login, and universe selection.
- Management of resources, buildings, and research, with a technology tree.
- Construction of ships and defenses, and recruitment of officers.
- Galaxy map, expedition and recycling missions, moons, and orbital transfers.
- Combat simulator, announcements, and voluntary multi-account declarations.
- Administration panel with roles and an audit log for sensitive actions.

The application uses Laravel 13, Inertia 3, Vue 3, and Vite.

## Local installation

### Requirements

- PHP 8.3 or a later PHP 8 release, with the extensions required by Composer and the PDO driver for the chosen database (`pdo_sqlite` for SQLite).
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
- **1 Operator**: overview, player search, temporary suspensions, general announcements, and access to multi-account declarations.
- **2 Moderator**: the same access, plus lifting suspensions and adding resources.
- **3 Administrator**: the same access, plus adding items and removing announcements.
- **4 Founder**: the same access, plus assigning levels and viewing the audit log.

Additions are limited to lower-level accounts and cannot target the operator's own account. Resources respect storage capacities; item grants check applicable queues and limits. Arbitrary SQL execution, account deletion, and universe resets are not provided.

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
