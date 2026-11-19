# scheduling-saas

> Multi-tenant appointment scheduling for small service businesses: barbershops, clinics, studios and personal trainers.

[![CI](https://github.com/lendengue/scheduling-saas/actions/workflows/ci.yml/badge.svg)](https://github.com/lendengue/scheduling-saas/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)

> **Status:** early development (Phase 0: foundation). See the [roadmap](#roadmap).

## Screenshots

_Coming soon: public booking page, calendar and dashboard._

## Live demo

_Coming soon._

## Features

Planned for the MVP:

- One subdomain per business (`{tenant}.scheduling-saas.test`) with a public booking page
- Staff, services, working hours and time off
- Availability engine that handles DST, buffers and minimum notice
- Double-booking prevented at the database level
- Email (and WhatsApp) confirmations and reminders
- Subscription plans with Stripe (Laravel Cashier)

## Architecture & technical highlights

_To be expanded as the phases land._ Key decisions are recorded as ADRs in
[`docs/decisions`](docs/decisions):

- [Single-database multi-tenancy by subdomain](docs/decisions/0001-single-database-multitenancy.md)
- [PostgreSQL exclusion constraint against double-booking](docs/decisions/0002-postgres-exclusion-constraint.md)

## Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.5, Laravel 13 |
| Frontend | Inertia.js v3, Vue 3 (`<script setup lang="ts">`), TypeScript, Tailwind CSS v4, shadcn-vue (Reka UI), Lucide |
| Database | PostgreSQL 18 (`btree_gist`) |
| Cache / sessions / queues | Redis |
| Local environment | Laravel Sail (Docker) with PostgreSQL, Redis and Mailpit |
| Quality | Pest, Laravel Pint, Larastan (level 8), ESLint, Prettier, `vue-tsc` |
| CI | GitHub Actions |

## Running locally

Requirements: Docker, PHP 8.4+ with Composer, and Node.js 22 (or run
everything through Sail).

```bash
git clone https://github.com/lendengue/scheduling-saas.git
cd scheduling-saas

composer install
cp .env.example .env

./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build    # or `sail npm run dev` for hot reload
```

The app is then available at <http://localhost> and Mailpit at
<http://localhost:8025>.

**Port clashes:** if ports 80, 5173, 5432, 6379, 1025 or 8025 are already in
use, set `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`,
`FORWARD_MAILPIT_PORT` or `FORWARD_MAILPIT_DASHBOARD_PORT` in your `.env`
(see the bottom of `.env.example`).

**Subdomains:** tenant subdomains (`{tenant}.scheduling-saas.test`) arrive in
Phase 1. Setup instructions (Valet/Herd, `/etc/hosts`, or `*.localhost`) will
be documented here.

## Running the tests

The test suite runs against PostgreSQL, so run it inside Sail:

```bash
./vendor/bin/sail composer test       # Pest
./vendor/bin/sail composer lint       # Pint, ESLint, Prettier (check only)
./vendor/bin/sail composer analyse    # Larastan, vue-tsc
./vendor/bin/sail composer ci:check   # everything above
```

`composer lint:fix` applies Pint, ESLint and Prettier fixes.

## Project structure

```
app/                 Laravel application (Actions, Models, Http, ...)
database/            Migrations, factories and seeders
docs/decisions/      Architecture Decision Records
resources/js/        Inertia + Vue + TypeScript front end
tests/               Pest tests (Feature, Unit)
.github/workflows/   CI pipeline
```

## Roadmap

- [x] **Phase 0:** Foundation (Laravel, Inertia/Vue/TS, Tailwind, Sail, Pest, Pint, Larastan, CI)
- [ ] **Phase 1:** Multi-tenancy and authentication
- [ ] **Phase 2:** Scheduling core (MVP)
- [ ] **Phase 3:** Monetization (Stripe)
- [ ] **Phase 4:** Engagement and operations (reminders, WhatsApp, dashboards)
- [ ] **Phase 5:** Public API and extras

## License

scheduling-saas is open-source software licensed under the [MIT license](LICENSE).
