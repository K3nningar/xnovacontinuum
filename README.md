<p align="center"><img src="https://nicolas.boutinaud.ch/images/xnovacontinuum.png" width="400" alt="Xnova Continuum Logo"></p>

# XNova Continuum

XNova Continuum est un jeu de stratégie spatiale par navigateur, qui réinterprète XNova avec Laravel et Vue. Les joueurs rejoignent un univers, développent leur planète et explorent la galaxie. Le projet est en cours de développement : toutes les fonctionnalités du jeu historique ne sont pas encore disponibles.

## Fonctionnalités

- Inscription, connexion et choix d'un univers.
- Gestion des ressources, des bâtiments et des recherches, avec arbre technologique.
- Construction de vaisseaux et de défenses, recrutement d'officiers.
- Carte de la galaxie, missions d'expédition et de recyclage, lunes et transferts orbitaux.
- Simulateur de combat, annonces et déclaration volontaire de multicomptes.
- Panneau d'administration avec rôles et journal des actions sensibles.

L'application utilise Laravel 13, Inertia 3, Vue 3 et Vite.

## Installation locale

### Prérequis

- PHP 8.3 ou une version 8 ultérieure, avec les extensions requises par Composer et le pilote PDO de la base utilisée (`pdo_sqlite` pour SQLite).
- Composer.
- Node.js `^20.19.0` ou `>=22.12.0` et npm.
- SQLite, ou une autre base de données configurée dans `.env`.

### Mise en place

Depuis la racine du dépôt :

```bash
composer install
php -r "file_exists('.env') || copy('.env.example', '.env');"
php artisan key:generate
```

Par défaut, `.env.example` utilise SQLite. Créez le fichier de base s'il n'existe pas, sans écraser une base existante :

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

Pour une autre base, configurez les variables `DB_*` dans `.env` et créez la base avant de continuer. Puis initialisez les tables et l'univers Andromède, et installez les dépendances JavaScript :

```bash
php artisan migrate
php artisan db:seed
npm ci --ignore-scripts
npm run build
```

Le seeder crée l'univers Andromède s'il n'existe pas. Pour une base existante, effectuez une sauvegarde avant d'appliquer les migrations. Ne régénérez pas la clé d'application d'une installation existante : cela peut rendre ses données chiffrées illisibles.

### Lancement

Lancez l'application et le serveur Vite dans deux terminaux :

```bash
php artisan serve
```

```bash
npm run dev
```

Ouvrez l'adresse indiquée par `artisan serve` (par défaut `http://localhost:8000`), créez un compte et rejoignez l'univers.

Les missions de flotte arrivées à échéance sont également traitées par le planificateur Laravel. En développement, lancez-le dans un troisième terminal :

```bash
php artisan schedule:work
```

En production, configurez l'exécution de `php artisan schedule:run` chaque minute, depuis la racine du projet. Les missions sont aussi traitées lors des requêtes des joueurs connectés.

## Administration du jeu

Après création d'un compte joueur et sauvegarde de la base, attribuez le premier compte fondateur, une seule fois depuis une console de confiance :

```bash
php artisan admin:founder <id-du-joueur>
```

Le fondateur peut attribuer les niveaux 0 à 3 depuis le panneau ; le niveau 4 ne peut pas être attribué ni retiré depuis le site.

- **0 Joueur** : aucun accès au panneau.
- **1 Opérateur** : vue générale, recherche de joueurs, suspensions temporaires, annonces générales et consultation des déclarations de multicomptes.
- **2 Modérateur** : mêmes accès, levée des suspensions et ajouts de ressources.
- **3 Administrateur** : mêmes accès, ajout d'éléments et retrait d'annonces.
- **4 Fondateur** : mêmes accès, attribution des niveaux et consultation du journal.

Les ajouts sont réservés aux comptes de niveau inférieur et ne peuvent pas viser son propre compte. Les ressources respectent les capacités de stockage ; les dons d'éléments vérifient les files et les limites applicables. L'exécution de SQL arbitraire, la suppression de comptes et la remise à zéro de l'univers ne sont pas proposées.

Les annonces générales sont limitées à cinq par heure et par opérateur. Les joueurs d'un même univers peuvent déclarer jusqu'à trois autres comptes en fournissant leurs identifiants ; chaque joueur concerné doit faire sa propre déclaration. Ces déclarations volontaires ne constituent pas une confirmation des autres comptes. Leur dépôt est limité à cinq par heure et par joueur.

## Tests

```bash
composer test
npm run test:technology-tree
```

Les tests PHP utilisent une base SQLite en mémoire.

## Licence

Licence MIT, toute liberté de republier, modifier ou forker le code pour sa propre utilisation.
