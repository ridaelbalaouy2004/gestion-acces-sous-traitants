<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

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

## Subcontractor access database

Start MySQL in XAMPP and create the `db_gestion_acces` database in phpMyAdmin if it does not already exist. Configure `.env` to connect to it. Back up any database containing useful data before applying migrations; never use `migrate:fresh` or `db:wipe` on it.

```bash
php artisan migrate
php artisan db:seed --class=PermissionSeeder
```

For local/development sample accounts only, run the full seeder:

```bash
php artisan db:seed
```

The full seeder creates these sample accounts only in `local` or `development`. Run it only in those environments; on an existing installation, seed the permission catalog only.

| Role | Email |
| --- | --- |
| prestataire | prestataire@example.com |
| gestionnaire | gestionnaire@example.com |
| responsable_securite | securite@example.com |

The development-only password for all three accounts is `LocalDev!Access2026`. Change it before using these accounts outside a local development environment.

Existing Gestionnaire accounts are preserved, but they receive no implicit permissions or Prestataire assignments. Until a Principal is designated and an employee is explicitly configured, that employee cannot access Prestataire records.

### Désigner le Gestionnaire Principal

Aucun compte n'est désigné automatiquement comme Gestionnaire Principal. Après avoir sauvegardé la base et appliqué les migrations en environnement contrôlé, créez ou choisissez un compte Gestionnaire actif, puis exécutez la commande dans un terminal interactif :

```bash
php artisan app:designate-primary-manager gestionnaire@example.com
```

La commande vérifie le rôle Gestionnaire, exige une confirmation explicite et ne fonctionne que si aucun Gestionnaire Principal n'est encore défini. Elle n'affiche ni ne crée de mot de passe. Une fois désigné, le Gestionnaire Principal peut transférer cette responsabilité depuis la fiche d'un autre Gestionnaire actif. Les affectations et transferts sont conservés dans leur historique; les employés n'obtiennent aucune permission ni affectation implicite.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
