# 0001. Single-database multi-tenancy by subdomain

- Status: Accepted
- Date: 2026-11-19

## Context

scheduling-saas hosts many small businesses (tenants). Each tenant needs its own
public booking page and back office, and data must never leak between tenants.
The usual options are a database per tenant, a schema per tenant, or one
shared database with a `tenant_id` column.

The product targets many small tenants with identical schemas and modest data
volumes. Operational simplicity (one migration run, one backup, one connection
pool) matters more than physical isolation.

## Decision

- One PostgreSQL database. Every tenant-scoped table has an indexed
  `tenant_id` foreign key.
- The tenant is identified by subdomain (`{slug}.{APP_DOMAIN}`). The root
  domain serves the landing page, tenant registration, central login and the
  super admin panel.
- No third-party tenancy package. The implementation is in-house:
  - `IdentifyTenant` middleware resolves the tenant and stores it in the
    `CurrentTenant` singleton;
  - the `BelongsToTenant` trait adds the `TenantScope` global scope, fills
    `tenant_id` on `creating` and defines the `tenant()` relation;
  - queued jobs carry the `tenant_id` and restore the context through a job
    middleware (`RestoreTenantContext`).
- Roles use `spatie/laravel-permission` with teams enabled (team = tenant).

## Consequences

- Isolation depends on application code, so cross-tenant isolation tests
  (HTTP, Eloquent and jobs) are mandatory and must stay green.
- Queries that intentionally bypass the scope (super admin, scheduler) must
  use `withoutGlobalScope(TenantScope::class)` explicitly, which keeps them
  easy to audit.
- Uniqueness rules become composite (for example `unique(tenant_id, email)`).
- Moving a large tenant to its own database later would require a data
  migration, which is acceptable for the MVP.
