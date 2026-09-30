# Farmer First ERP

Tractor-dealership ERP covering the full lifecycle: enquiry → telecaller validation → pipeline → customer → deal →
order → parallel fulfilment (finance, accounts, inventory, RTO, insurance, PDI) → waivers → delivery readiness →
delivery → sales achievement.

Requirements baseline: *Farmer First ERP Final SRS v6.1*. Developer documentation lives in [`docs/`](docs/):
start with [`docs/ASSESSMENT.md`](docs/ASSESSMENT.md) and [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

## Stack
Laravel 13 · PHP 8.4+ · MySQL 8 · Livewire 4 · Alpine.js · Tailwind CSS 4 · Vite · Sanctum · spatie/laravel-permission

## Local setup
```bash
composer install
cp .env.example .env            # set DB_* (MySQL) or DB_CONNECTION=sqlite for a quick start
php artisan key:generate
php artisan migrate --seed      # reference data + demo users (demo users are never seeded in production)
npm install && npm run build    # or: npm run dev
php artisan serve
```

### Background processes
Notifications are queued and CRM housekeeping is scheduled, so run these alongside the web server:
```bash
php artisan queue:work      # assignment / reopen / follow-up notifications
php artisan schedule:work   # temperature refresh, follow-up reminders, stale telecaller claims
```

### Demo accounts (local only)
All use password `password`: `superadmin@`, `owner@`, `sales.manager@`, `dwarika@` (salesman), `salesman2@`, `telecaller@`,
`retail@`, `accounts@`, `inventory@`, `rto@`, `insurance@`, `pdi@`, `delivery@` — each `…@farmerfirst.test`.

## Tests
```bash
php artisan test --compact
```
Runs on SQLite in-memory. Reference data is seeded per test via `ReferenceDataSeeder`.
`composer test:mysql` runs the same suite on MySQL 8 (database `farmer_first_erp_test`).

## Conventions
- Business rules live in `app/Actions` and `app/Services`; Livewire components and API controllers stay thin.
- Permissions are declared in `config/erp/permissions.php`; default role grants in `config/erp/roles.php`.
- Sidebar items are declared in `config/erp/navigation.php` and appear automatically once their route exists.
- Masters are deactivated, never deleted. Audit log rows are append-only.
- Document numbers come from `NumberSeriesService` (configurable in Administration → System Settings).
- Run `vendor/bin/pint` before committing.
