# Repository Guidelines

## Project Structure

This repository is a Laravel 12, Livewire 3, and Blade POS application. Business code lives in `app/`: Livewire screens in `app/Livewire`, domain operations in `app/Services`, Eloquent models in `app/Models`, and shared queries/helpers in `app/Queries` and `app/Support`. Routes are in `routes/`; Blade templates are in `resources/views`; static dashboard and homepage assets are in `public/`. Database changes, factories, and seeders belong in `database/`. Automated tests are under `tests/Feature` and `tests/Unit`. Product and operations documentation is in `docs/`.

## Development Commands

- `composer install` installs PHP dependencies.
- `composer dev` starts the local Laravel server; `php artisan serve` is an equivalent direct command.
- `php artisan migrate` applies normal schema migrations. Seed only a new local database with `php artisan db:seed`.
- `php artisan test` runs the PHPUnit suite against in-memory SQLite as configured in `phpunit.xml`.
- `php vendor/bin/pint` formats PHP files; `php artisan view:cache` checks and compiles Blade views.

The project intentionally serves static frontend assets and has no Node, npm, Vite, or frontend build step.

## Coding Style and Naming

Follow `.editorconfig`: UTF-8, LF endings, four spaces, and a final newline. Use Laravel conventions: StudlyCase classes, camelCase methods and variables, descriptive singular model names, and `*Test.php` test files. Group Livewire by feature; keep business rules in services. Use the homepage-themed `layouts.pos` for cashier screens, not the dashboard shell. Run Pint on changed PHP files before submitting.

## Testing

Use PHPUnit feature tests for routes and complete workflows, and unit tests for isolated domain logic. Name tests after the behavior they protect (for example, `PointOfSaleCheckoutTest.php`). Run `php artisan test`; for focused checks, pass a path such as `php artisan test tests/Feature/PointOfSaleCheckoutTest.php`. Tests use SQLite in memory; verify database-specific behavior on MySQL when SQLite compatibility is uncertain.

## Data Safety and Configuration

Copy `.env.example` to `.env` for local setup and keep secrets out of commits. Use ordinary forward migrations on existing databases. Never run `migrate:fresh` or `db:wipe` against an operational database; seeders intended for isolated fixtures may replace settings. Preserve stock, payment, audit, and outlet-scope invariants when changing workflows.

## Commits and Pull Requests

The short Git history uses concise task-oriented subjects (for example, `Update MVP`). Keep new commit subjects brief and action-oriented. Pull requests should describe the affected workflow, summarize migration or configuration changes, list test commands and results, link related issues when available, and include screenshots for visible UI changes.

<!-- antislop:start -->
## Anti Slop

For UI work, use the installed `antislop` plugin. Read its core guidance, then the relevant skills before reviewing or editing an interface:

- Core: `antislop`
- UI and visual design: `antislop-ui`
- Accessibility and interaction: `antislop-human`
- Responsive layouts: `antislop-layoutmobile`
- UI copy: `antislop-copywriting`

Resolve the Anti Slop usage mode before UI review or edits: explicit session choice first, then the saved global preference, otherwise ask. Resolve design direction from `DESIGN.md` or the user's brief; do not invent brand direction without labeling the work as a draft without direction. Preserve existing Mazer design and POS workflow unless the user asks to change them.
<!-- antislop:end -->
