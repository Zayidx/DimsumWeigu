# Weigu Dimsum POS

Laravel 12, Livewire 3 and Blade POS application following `Instruction.json`. Dashboard pages use the supplied Mazer template; the public homepage uses the supplied Homepage template. Static assets are served locally; no Node/npm/Vite or frontend build step is needed.

## Local setup

1. Install PHP dependencies with `composer install`.
2. Copy `.env.example` to `.env`, set `APP_KEY` with `php artisan key:generate` on a new installation, and configure MySQL, `APP_URL`, `APP_VERSION`, mail and your own `SUPER_ADMIN_EMAIL`/`SUPER_ADMIN_PASSWORD`.
3. Run `php artisan migrate` and, on a new installation only, `php artisan db:seed`. Run `php artisan storage:link` for uploaded images. New administrator accounts must change their initial password.
4. Start the PHP server with `composer dev` or `php artisan serve`.

Open `/` for the public homepage. Dashboard template assets are installed under `public/mazer`; the reusable Blade shell is `resources/views/layouts/dashboard.blade.php`.

Use normal migrations when upgrading an existing database. The default seeders preserve existing roles, account credentials, master data and opening-stock ledgers. Local setup adapts the supplied legacy business seeders; see [source seeder data and mappings](docs/source-seeders.md). Change permissions through the role administration screen. Legacy InitialBusinessSeeder and RolePermissionSeeder remain for isolated fixtures and can overwrite their fixture settings; they are not called by DatabaseSeeder. Never run migrate:fresh/db:wipe on an operational database.

## Operational flow

Login → select outlet → open shift in the branch warehouse → cart/hold/resume → checkout → receipt → close shift with cash reconciliation. Prices and stock are checked by the server. Cash is enabled initially; pay-later requires an enabled payment method and an active customer with sufficient credit.

Production should run `php artisan schedule:run` every minute and a supervised `php artisan queue:work` PHP worker. Implemented schedules: low-stock notifications at 02:00, aging receivable/payable notifications at 01:15. CRM segment/reminder/campaign jobs remain to be implemented. Operational audit commands: `php artisan inventory:reconcile` and `php artisan outlet:audit --strict`; reconcile `--fix` repairs only the derived global stock aggregate.

## Module documentation

- [Feature traceability](docs/feature-traceability.md): scope, implemented workflows and remaining work.
- [Supplier returns](docs/supplier-returns.md): approval, source batches, invoice offsets, credit and refunds.
- [Operating costs](docs/operating-costs.md): expense categories, cash shift, waste, valuation and reports.
- [Mobile POS API](docs/mobile-pos-api.md): endpoints, Sanctum abilities, outlet scope, cart UUID and replay/sync.
- [Cash management](docs/cash-management.md): pickup approval, handover receipt, cash ledgers and normal/forced shift closing.

Run `php artisan test` for the automated suite using SQLite in-memory, isolated from the application database. Compile Blade templates with `php artisan view:cache`; format PHP with `php vendor/bin/pint`.

## UI assets and licenses

Homepage markup, CSS, JavaScript, logo, and hero image are from `Tamplate Homepage/homepage`. Its CSS license file is retained in that source folder. Mazer's distributed compiled CSS/JS/fonts and static scripts are copied from `Tamplate Dashboard Mazeradmin/assets` into `public/mazer`. The provided Mazer folder has no root license file; bundled component license files are retained where supplied, including Bootstrap Icons. Confirm the Mazer distribution license before redistributing the copied template.

## Current implementation boundary

Authentication, role administration, outlet context, cashier shifts, cash/pay-later POS, catalog/units, inventory/batches/transfer/opname, purchasing/receiving, supplier returns, payable/receivable settlements, expense/waste records, CSV reports, notifications, audit, settings and mobile POS API workflows have been implemented. See the matrix for remaining work within each area.

**The complete POS scope is still unfinished.** Sales returns, dine-in, recipes/production, promotions/price lists/vouchers/loyalty, CRM, gateway checkout/webhooks, public invoice portal, full import/XLSX/PDF documents, browser offline/PWA/hardware and additional reports remain. Cash handover approval/receipt and password-confirmed normal/forced closing have been added. Encrypted gateway configuration is available; gateway payments are not processed yet. MySQL concurrency, live provider sandbox, responsive browser/device QA and hardware validation remain unverified.

The source repository at `C:\Users\zayidx\Documents\point-of-sales` is available and its seeders, supporting models, migrations and factories were inspected for the source-seeder adaptation. Requirements are traced to `Instruction.json`; full workflow behavior parity has not been verified.


## Mobile UI and system audit

The dashboard uses the supplied Mazer assets with static overrides in `public/assets/dashboard-mobile.css` and `public/assets/dashboard-mobile.js`. Mobile navigation follows current permissions. Simple tables become labeled cards on phones, and POS has a persistent cart button, package pricing, touch controls and a mobile cart panel.

See `analisis-system-ui-ux.json` for findings, applied fixes, remaining risks and browser inspection evidence. The current change was inspected in headless Edge at widths 320, 390, 768 and 1280; physical phone keyboards/hardware and concurrency still require separate review. Automated tests were not run for this change.

`tools/ui-audit-browser.py` uses Python standard library and an isolated Edge CDP session; it requires no Node or browser package. `tools/prepare-ui-preview.php` pins a disposable SQLite database under `storage/app/ui-audit/preview.sqlite`; credentials must be supplied over stdin or an ephemeral environment variable. The preview router is `tools/ui-preview-router.php`. Browser screenshots, profiles and fixture databases stay outside `public` and must not be deployed as business data. No preview shift, product stock or transaction was created in the operational MySQL database.
