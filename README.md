<p align="center"><img src="https://nicolas.boutinaud.ch/resources/images/xnovacontinuum.png" width="400" alt="Laravel Logo"></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Administration du jeu

Après sauvegarde de la base, appliquez les migrations avec `php artisan migrate`.
Attribuez ensuite le premier compte fondateur, une seule fois depuis une console
de confiance, avec `php artisan admin:founder <id-du-joueur>`. Le fondateur peut
attribuer les niveaux 0 à 3 depuis le panneau ; le niveau 4 ne peut pas être
attribué ni retiré depuis le site.

- **0 Joueur** : aucun accès au panneau.
- **1 Opérateur** : vue générale, recherche de joueurs, suspensions temporaires, annonces générales et consultation des déclarations de multicomptes.
- **2 Modérateur** : mêmes accès, levée des suspensions et ajouts de ressources.
- **3 Administrateur** : mêmes accès, ajout d'éléments et retrait d'annonces.
- **4 Fondateur** : mêmes accès, attribution des niveaux et consultation du journal.

Les ajouts sont réservés aux comptes de niveau inférieur et ne peuvent pas viser
son propre compte. Les ressources respectent les capacités de stockage ; les dons
d'éléments vérifient les files et les limites applicables. Les actions sensibles
sont journalisées. L'exécution de SQL arbitraire, la suppression de comptes et la
remise à zéro de l'univers ne sont pas proposées. Les fonctions historiques
reposant sur des fonctionnalités encore absentes du nouveau jeu, notamment les
messages entre joueurs et les lunes, ne sont
pas portées. Les annonces générales sont limitées à cinq par heure et par
opérateur et apparaissent dans la page « Annonces » des comptes existants.
Les joueurs d'un même univers peuvent déclarer jusqu'à trois autres comptes
en fournissant leurs identifiants ; chaque joueur concerné doit faire sa propre
déclaration. Ces déclarations volontaires ne constituent pas une confirmation
des autres comptes. Leur dépôt est limité à cinq par heure et par joueur.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
