# Weigu Dimsum POS

Laravel 12 and Livewire 3 application. The public homepage uses the supplied Homepage template. Static homepage assets are served from `public/assets` and `public/images`; no frontend build step is needed.

## Local setup

1. Install PHP dependencies with `composer install`.
2. Copy `.env.example` to `.env`, set `APP_KEY` with `php artisan key:generate`, and configure MySQL for a persistent local database.
3. Run migrations with `php artisan migrate` after the domain schema has been implemented.
4. Start the PHP server with `composer dev` or `php artisan serve`.

Open `/` for the public homepage. Dashboard template assets are installed under `public/mazer`; the reusable Blade shell is `resources/views/layouts/dashboard.blade.php`.

## UI assets and licenses

Homepage markup, CSS, JavaScript, logo, and hero image are from `Tamplate Homepage/homepage`. Its CSS license file is retained in that source folder. Mazer's distributed compiled CSS/JS/fonts and static scripts are copied from `Tamplate Dashboard Mazeradmin/assets` into `public/mazer`. The provided Mazer folder has no root license file; bundled component license files are retained where supplied, including Bootstrap Icons. Confirm the Mazer distribution license before redistributing the copied template.

## Current implementation boundary

This checkout is a clean Laravel skeleton. It currently contains the public homepage and a reusable Mazer layout foundation. POS modules, database domain models, authentication, permissions, and operational workflows still need to be implemented before this can be used as a production POS.
