<p align="center"><img src="https://nicolas.boutinaud.ch/images/xnovacontinuum.png" width="400" alt="XNova Continuum logo"></p>

# XNova Continuum

[![SemVer](https://img.shields.io/badge/SemVer-0.1.0--alpha.4-blue)](https://semver.org/)
![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![Vue.js](https://img.shields.io/badge/Vue.js-3-4FC08D?logo=vuedotjs&logoColor=white)
[![License: MIT](https://img.shields.io/badge/License-MIT-green)](LICENSE)
XNova Continuum is an in-development browser-based space strategy game inspired by XNova. It is built with Laravel 13, Inertia.js 3, Vue 3, and Vite. Players develop planets, research technologies, build fleets, and explore a shared universe.

> **Status:** `0.1.0-alpha.4` is a pre-release. Features, balance, and database schema may change between versions. Back up your database before upgrading; do not deploy this version as a production game without reviewing its operational and security requirements.

## Contents

- [Current gameplay](#current-gameplay)
- [Technology stack](#technology-stack)
- [Requirements](#requirements)
- [Local installation](#local-installation)
- [Run locally](#run-locally)
- [How to play](#how-to-play)
- [Administration](#administration)
- [Configuration and operations](#configuration-and-operations)
- [Tests and code quality](#tests-and-code-quality)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [Version and changelog](#version-and-changelog)
- [License](#license)

## Current gameplay

The current application includes:

- Account registration, login, account suspension checks, and universe membership.
- A command center with planet resources, production, activity controls, and construction queues.
- Planet building upgrades, resource production, storage limits, energy balance, and fusion power.
- Research, technology prerequisites, research queues, and an interactive technology dependency graph.
- Ship and defence catalogs, production queues, and officer recruitment.
- A galaxy view with coordinate-based exploration, expeditions, asteroid-field recycling, and fleet return processing.
- Moons, moon resources, and orbital resource transfers.
- A seeded combat simulator with configurable fleets and reproducible simulations when a seed is provided.
- A changelog with publication controls and moderator-added entries.
- Localized interface content in English, French, German, Spanish, and Italian.
- Administration tools for player lookup, resource and item adjustments, temporary suspensions, announcements, multi-account declarations, changelog publication, and audit history.

The implementation is evolving; a feature listed above may still have incomplete rules or balancing. Consult the in-game changelog for release-specific changes. Factions, diplomacy, quests, and other planned modules are not implied to be available just because they are part of the long-term project direction.

## Technology stack

- PHP `^8.5`, Laravel `^13.42`
- Inertia Laravel `^3.7`, Inertia Vue `^3`
- Vue `^3.5`, Vue I18n `^11`
- Vite `^8`, Tailwind CSS `^4`
- SQLite by default; other Laravel-supported database connections can be configured

Dependency constraints are maintained in `composer.json` and `package.json`; those files are authoritative if this summary is out of date.

## Requirements

- PHP 8.5 with the PHP extensions required by Laravel/Composer. Enable `pdo_sqlite` when using the default SQLite setup.
- Composer 2.
- Node.js `^20.19.0` or `>=22.12.0`, and npm.
- SQLite for the default local database, or a configured supported database server.
- Git to obtain and update the source.

Use current security-patched releases of PHP, Node.js, Composer, and your database server.

## Local installation

Run these commands from the repository root. They install PHP dependencies, create local environment settings if absent, generate the application key, create the default SQLite database if needed, migrate and seed it, then install and build frontend dependencies.

```bash
composer install
php -r "file_exists('.env') || copy('.env.example', '.env');"
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
php artisan db:seed
npm ci --ignore-scripts
npm run build
```

The default `.env.example` configures SQLite, database-backed sessions and cache, and the `local` application environment. The seeder creates the default **Andromède** universe if it does not already exist.

### Existing installations

1. Back up the database and application files.
2. Update the source to the desired revision.
3. Install dependencies with `composer install` and `npm ci --ignore-scripts`.
4. Review `.env` changes against `.env.example`; do not replace an existing `.env` blindly.
5. Run `php artisan migrate` (use `--force` in production).
6. Build frontend assets with `npm run build`.
7. Clear or rebuild Laravel caches as appropriate for the deployment.

Do **not** run `php artisan key:generate` on an existing installation. Changing `APP_KEY` can make existing encrypted data unreadable and invalidate encrypted cookies or sessions.

### Using another database

Create the database first, then set `DB_CONNECTION` and the corresponding `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` values in `.env`. Use the connection-specific settings supported by Laravel. Keep credentials out of source control. Then run the migrations and seed the default universe as shown above.

## Run locally

Open two terminals in the repository root.

Terminal 1 — Laravel web server:

```bash
php artisan serve
```

Terminal 2 — Vite development server with hot reload:

```bash
npm run dev
```

Open the address printed by `artisan serve` (normally `http://localhost:8000`), register a player account, and join the available universe. For a production-like frontend check, run `npm run build` and serve the application through Laravel without the Vite development server.

Game routes use English paths, such as `/commandcenter`. Previous French paths are not redirected; update old bookmarks.

## How to play

1. Register and sign in.
2. Join an available universe. A fresh local database is seeded with Andromède.
3. Use the command center to review your planet and resources.
4. Upgrade mines and energy buildings, manage building activity, and queue construction.
5. Develop research to unlock further technologies and game elements; the technology graph displays prerequisite relationships.
6. Build ships and defences, recruit officers, and manage their queues.
7. Use the galaxy view to inspect coordinates and dispatch supported missions. Expeditions require expedition technology; recycling requires an eligible asteroid field and recycler ships.
8. Review fleet status and use the combat simulator to experiment with fleet compositions.
9. Manage moons and transfer resources where available.

The interface is available in English, French, German, Spanish, and Italian. English is the default. A player's selected language is saved in the browser and takes precedence over the default locale setting.

## Administration

### Bootstrap the first founder

Create a normal player account first. Obtain that account's numeric player ID from the trusted database or application tools, then assign the first founder role once from a trusted console:

```bash
php artisan admin:founder <player-id>
```

Replace `<player-id>` with the account's numeric ID, for example `42`. The command refuses to run if a founder already exists and records the bootstrap action in the audit log. Run it only from a trusted environment; do not expose console access to players.

The web panel cannot create or remove a level-4 founder. A founder can grant roles from the panel, but changes are restricted by the role hierarchy and cannot target the acting administrator.

### Roles

| Level | Role | Main access |
| --- | --- | --- |
| 0 | Player | No administration panel access; normal game access. |
| 1 | Operator | Administration overview, player lookup, temporary suspensions, announcements, changelog publication, and multi-account declaration review. |
| 2 | Moderator | Operator access, lifting suspensions, resource adjustments, and manual changelog entries. |
| 3 | Administrator | Moderator access, item adjustments, and announcement revocation. |
| 4 | Founder | Administrator access, role management, and audit-log access. Level 4 is bootstrapped from the console. |

Sensitive administration actions are recorded in the audit log. Resource grants respect storage capacity; item grants check relevant queues, requirements, and limits. The panel does not provide arbitrary SQL execution, account deletion, or universe resets.

### Announcements and multi-account declarations

- General announcements are limited to five per hour per operator. They are delivered to a snapshot of current player accounts; revoked announcements are hidden from recipients.
- Players in the same universe can declare up to three other accounts using their identifiers. Each affected player must submit their own declaration; a declaration is not confirmation by the other accounts.
- Multi-account declaration submissions are limited to five per hour per player.

### Changelog management

The release list and built-in entry keys are maintained in `resources/js/changelog.json`; translated entry text is in `resources/js/i18n/locales/*/changelog.js`. Keep the current version first in the JSON file because the application uses it for the game version displayed in the UI. New versions are unpublished by default. Operators can publish or hide releases; moderators and higher roles can add dated manual entries. Publication changes and manual additions are audited. Run database migrations when upgrading an installation that predates changelog administration.

## Configuration and operations

### Environment and localization

`.env.example` is the template for local configuration. Common settings include:

- `APP_ENV`: use `local` for development and `production` for deployment.
- `APP_DEBUG`: enable only in local development; set to `false` in production.
- `APP_URL`: the externally reachable application base URL.
- `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE`: server-side locale defaults. The defaults are `en`, `en`, and `en_US`.
- `DB_*`: database connection settings.

After changing environment values on an installation with cached configuration, run:

```bash
php artisan config:clear
```

Never commit `.env`, production credentials, or application keys.

### Fleet mission scheduler

Due fleets are processed during authenticated game requests and by a scheduled command. For local development, run the scheduler in a third terminal:

```bash
php artisan schedule:work
```

In production, configure the system scheduler to invoke Laravel's scheduler once per minute from the project root:

```cron
* * * * * cd /path/to/xnovalaravel && php artisan schedule:run >> /dev/null 2>&1
```

The scheduled fleet task runs every minute and prevents overlapping executions. Requests from logged-in players also process due missions, but production should still run the scheduler for consistent processing during periods of low traffic.

### Production deployment checklist

This project is an alpha and does not ship a deployment-specific infrastructure configuration. At minimum:

- Point the web server document root to the project's `public/` directory; do not expose the repository root.
- Set a unique `APP_KEY`, correct `APP_URL`, `APP_ENV=production`, and `APP_DEBUG=false`.
- Configure a persistent, backed-up database and secure database credentials.
- Ensure `storage/` and `bootstrap/cache/` are writable by the application process, but not publicly browsable.
- Install production PHP dependencies and build frontend assets:

  ```bash
  composer install --no-dev --optimize-autoloader
  npm ci --ignore-scripts
  npm run build
  php artisan migrate --force
  php artisan optimize
  ```

- Run the scheduler every minute as described above.
- Use HTTPS, secure cookies, backups, access controls, and a monitored error/logging setup appropriate to your environment.
- Never regenerate `APP_KEY` during routine deployment.

Review Laravel's deployment requirements and your hosting provider's guidance before exposing an alpha build to the public internet.

## Tests and code quality

Run the PHP feature and unit suites (PHPUnit uses an in-memory SQLite database):

```bash
composer test
```

Run the JavaScript tests:

```bash
npm run test:technology-tree
node --test resources/js/*.test.js resources/js/Pages/Combat/*.test.js resources/js/i18n/*.test.js
```

Build the production frontend bundle:

```bash
npm run build
```

Check PHP formatting in the application source:

```bash
vendor/bin/pint --test app bootstrap/app.php bootstrap/providers.php routes database
```

The Laravel cache files under `bootstrap/cache/` are generated and are intentionally excluded from this Pint command.

## Troubleshooting

- **SQLite database missing:** ensure `database/database.sqlite` exists, then run `php artisan migrate`.
- **Database-backed sessions or cache fail on a fresh setup:** run all migrations before signing in; the setup steps above do this before the application starts.
- **Configuration changes do not take effect:** run `php artisan config:clear`; in production, rebuild caches with `php artisan optimize` after reviewing the deployment settings.
- **Vite manifest not found:** run `npm ci --ignore-scripts` followed by `npm run build`, or start `npm run dev` during local development.
- **Missions do not progress without player traffic:** confirm the system scheduler invokes `php artisan schedule:run` every minute and inspect Laravel logs.
- **Old links fail:** game URLs use English paths; update bookmarks to the current paths.

Useful diagnostics include:

```bash
php artisan about
php artisan route:list
php artisan schedule:list
php artisan config:show database
```

## Contributing

1. Create a focused branch for your change.
2. Keep game rules and shared catalogs consistent with existing services and tests.
3. Add or update tests for behavior changes.
4. Update all five locale changelog files when adding translated built-in release entries.
5. Run the PHP tests, JavaScript tests, Pint check, and frontend build before submitting changes.
6. Do not commit secrets, `.env` files, generated cache contents, or production data.

## Version and changelog

The current pre-release version is **0.1.0-alpha.4**. Built-in releases are listed newest-first in `resources/js/changelog.json`; translated text is stored in the locale changelog modules. Keep the SemVer badge above and the current version in the code-file metadata headers in sync when preparing a release. The in-game changelog controls visibility separately from the version shown in the game UI.

## License

This project is distributed under the MIT License. See [LICENSE](LICENSE) for the complete terms.
