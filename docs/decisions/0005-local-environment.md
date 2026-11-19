# 0005. Local environment with Laravel Sail

- Status: Accepted
- Date: 2026-11-19

## Context

The specification requires Laravel Sail with PostgreSQL, Redis and Mailpit.
Some details were left open: PHP runtime, which Laravel subsystems use Redis,
and how to handle host port clashes.

## Decision

- `php artisan sail:install --with=pgsql,redis,mailpit --php=8.5`, producing
  `compose.yaml` with services `laravel.test`, `pgsql` (`postgres:18-alpine`),
  `redis` and `mailpit`.
- The Sail PostgreSQL container creates two databases: `scheduling_saas`
  (development) and `testing` (test suite).
- Redis backs **cache, sessions and queues** (`CACHE_STORE`, `SESSION_DRIVER`
  and `QUEUE_CONNECTION` are all `redis`), as the stack table says.
- `.env.example` keeps Sail's default host ports. Developers whose ports are
  already taken override `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT`,
  `FORWARD_REDIS_PORT`, `FORWARD_MAILPIT_PORT` and
  `FORWARD_MAILPIT_DASHBOARD_PORT` in their own `.env`. These variables are
  listed, commented out, in `.env.example`.
- `APP_FAKER_LOCALE=pt_BR`, so seeders produce Brazilian data (the demo seeder
  requires Faker pt_BR).

## Consequences

- `./vendor/bin/sail up -d` brings up the whole stack. Tests run inside the
  container (`sail test` or `sail composer test`) because they need PostgreSQL.
- Subdomain routing (`APP_DOMAIN`) is introduced in Phase 1 together with
  tenant identification.
