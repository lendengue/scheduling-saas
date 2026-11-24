# scheduling-saas

> Multi-tenant appointment scheduling for small service businesses: barbershops, clinics, studios and personal trainers.

[![CI](https://github.com/lendengue/scheduling-saas/actions/workflows/ci.yml/badge.svg)](https://github.com/lendengue/scheduling-saas/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)

> **Status:** MVP scheduling core done (Phase 2). Billing comes next. See the [roadmap](#roadmap).

## Screenshots

_Coming soon: public booking page, calendar and dashboard._

## Live demo

_Coming soon._

## Features

Available:

- Self-service sign-up: each business gets its own subdomain (`{tenant}.scheduling-saas.localhost`)
  and a three-step onboarding (services, working hours, team)
- Per-tenant login, password reset and optional two-factor authentication
- Roles (owner, admin, staff) and team invitations by email
- Service catalog with drag-and-drop ordering; professionals with colors,
  services, weekly hours (several intervals per day) and time off
- Availability engine that handles DST, buffers, minimum notice, maximum
  advance and "any professional"
- Public, mobile-first booking page with a calendar of available days, `.ics`
  download and a secure link to cancel or reschedule
- Team agenda (FullCalendar): day, week and list views, booking by clicking an
  empty slot, rescheduling by drag and drop, confirm / complete / no-show /
  cancel; staff only see their own agenda
- Double-booking prevented at the database level, even under concurrency
- Email notifications (customer and professional) on booking, cancellation
  and reschedule, sent through the queue

Planned:

- Subscription plans with Stripe (Laravel Cashier)
- Reminders, WhatsApp, dashboard metrics, customer list and settings screens
- Public REST API and outgoing webhooks

## Architecture & technical highlights

_To be expanded as the phases land._ Key decisions are recorded as ADRs in
[`docs/decisions`](docs/decisions):

- **Multi-tenancy without a package:** one database, `tenant_id` on every
  tenant-owned table, tenant resolved from the subdomain. A global scope
  isolates every query and *fails closed* when no tenant is set, and the
  tenant travels inside every queue payload, so jobs, mail and notifications
  run in the right tenant. Isolation is covered by tests at the Eloquent, HTTP
  and queue levels.
- **Host-only sessions:** a session never spans tenants; a newly registered
  owner moves from the central domain to their subdomain through a
  single-use, 60-second login handoff token.
- **Hashed secrets:** invitation links carry a random token (only its SHA-256
  hash is stored) inside a signed, expiring URL; appointment management links
  work the same way, without an account.
- **No double-booking, by construction:** a PostgreSQL exclusion constraint
  (`btree_gist`, `tstzrange && ...`) rejects overlapping active appointments
  of the same professional. The booking action turns the violation
  (SQLSTATE `23P01`) into a friendly 409/validation error; a test fires two
  concurrent bookings and only one wins.
- **Timezones done right:** rules are wall-clock times of the business,
  storage is UTC `timestamptz`, and the availability engine is tested across
  DST transitions. The agenda always shows the business timezone, whatever
  the browser's.

ADRs:

- [Single-database multi-tenancy by subdomain](docs/decisions/0001-single-database-multitenancy.md)
- [PostgreSQL exclusion constraint against double-booking](docs/decisions/0002-postgres-exclusion-constraint.md)
- [Tenant routing, sessions and cross-domain login](docs/decisions/0007-tenant-routing-and-cross-domain-login.md)
- [Tenant context in queued work](docs/decisions/0008-tenant-context-in-queued-work.md)
- [Roles and team invitations](docs/decisions/0009-roles-and-invitations.md)
- [Availability engine and appointment snapshots](docs/decisions/0010-availability-engine.md)
- [Public booking, abuse protection and the management link](docs/decisions/0011-public-booking-and-management-link.md)
- [timestamptz columns and timezone-safe writes](docs/decisions/0012-timestamptz-columns.md)
- [The team agenda](docs/decisions/0013-team-agenda.md)
- [Onboarding wizard and demo data](docs/decisions/0014-onboarding-and-demo-data.md)

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
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build    # or `sail npm run dev` for hot reload
```

The app is then available at <http://scheduling-saas.localhost> and Mailpit at
<http://localhost:8025>. Run `./vendor/bin/sail artisan queue:work` (or
`composer dev`) so queued emails, such as invitations and booking
confirmations, are sent.

**Port clashes:** if ports 80, 5173, 5432, 6379, 1025 or 8025 are already in
use, set `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`,
`FORWARD_MAILPIT_PORT` or `FORWARD_MAILPIT_DASHBOARD_PORT` in your `.env`
(see the bottom of `.env.example`).

If you change `APP_PORT`, add the port to `APP_URL` as well
(`APP_URL=http://scheduling-saas.localhost:8080`).

**Subdomains:** the central domain is `APP_DOMAIN` and each business lives on
`{slug}.APP_DOMAIN`. The default, `scheduling-saas.localhost`, needs no setup:
browsers resolve every `*.localhost` name to your machine. To use
`scheduling-saas.test` instead, set `APP_DOMAIN=scheduling-saas.test` and
`APP_URL=http://scheduling-saas.test`, then either:

- link the project with Laravel Valet or Herd (wildcard `*.test` DNS), or
- add each subdomain to `/etc/hosts`:
  `127.0.0.1 scheduling-saas.test demo.scheduling-saas.test`.

Keep `SESSION_DOMAIN=null`: sessions are deliberately limited to one host.

### Demo accounts

`migrate --seed` creates the **Demo Barbershop**: three professionals, five
services, 30 customers and an agenda covering the last week and the next two
weeks (relative to the day you seed). Every account uses the password
`password`:

| Role | Email | Takes bookings |
|---|---|---|
| Owner | `owner@demo.test` | Yes |
| Admin | `admin@demo.test` | No |
| Staff | `staff@demo.test` | Yes |
| Staff | `rafael@demo.test` | Yes |

- Team panel: <http://demo.scheduling-saas.localhost/login> (agenda at `/app/agenda`)
- Public booking page: <http://demo.scheduling-saas.localhost/book>

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
  Actions/           Business rules, one class per use case (BookAppointment, ...)
  Models/Concerns/   BelongsToTenant (tenant scope and guards), UsesTimestampTz
  Services/Availability/  AvailabilityCalculator and its cache
  Support/Tenancy/   Tenant domains, login handoff, queue context
routes/              web.php → central.php (root domain), tenant.php (subdomains)
database/            Migrations, factories and seeders
docs/decisions/      Architecture Decision Records
resources/js/        Inertia + Vue + TypeScript front end
tests/               Pest tests (Feature, Unit)
.github/workflows/   CI pipeline
```

## Roadmap

- [x] **Phase 0:** Foundation (Laravel, Inertia/Vue/TS, Tailwind, Sail, Pest, Pint, Larastan, CI)
- [x] **Phase 1:** Multi-tenancy and authentication
- [x] **Phase 2:** Scheduling core (MVP)
- [ ] **Phase 3:** Monetization (Stripe)
- [ ] **Phase 4:** Engagement and operations (reminders, WhatsApp, dashboards)
- [ ] **Phase 5:** Public API and extras

## License

scheduling-saas is open-source software licensed under the [MIT license](LICENSE).
