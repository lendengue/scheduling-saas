# 0007. Tenant routing, sessions and cross-domain login

- Status: Accepted
- Date: 2026-11-19

## Context

ADR 0001 fixes subdomain identification and an in-house `tenant_id` scope.
Phase 1 had to settle how routes are split between the central domain and the
tenant subdomains, how authentication works when the same email can exist in
several tenants, and how a freshly registered owner moves from the central
domain to their subdomain while logged in.

## Decision

**Routing.** Routes are not bound with `Route::domain('{tenant}...')`.
`IdentifyTenant` runs first in the `web` group and resolves the tenant from
the host: the central domain sets no tenant, `{slug}.{APP_DOMAIN}` sets that
tenant (404 if unknown or soft-deleted), any other host is a 404. Route groups
use the `central` and `tenant` middleware to restrict themselves to one side.
Without a `{tenant}` route parameter, controllers and the Wayfinder helpers
stay free of tenant arguments, and relative URLs work on every subdomain.
Absolute cross-domain URLs are built by `TenantDomain` (scheme and port from
`APP_URL`).

**Local domain.** `.env.example` uses `APP_DOMAIN=scheduling-saas.localhost`.
`*.localhost` resolves to the loopback address in modern browsers with no DNS
setup, and Vite's default CORS policy accepts it. `scheduling-saas.test` (spec
default) still works with `/etc/hosts`, Valet or Herd; the test suite uses it.

**Scope semantics.** `TenantScope` fails closed: with no current tenant it
adds `tenant_id IS NULL`. Tenant-owned tables never have such rows, so nothing
leaks from console commands or jobs without a context. For `users`, it
matches platform super admins (the only users without a tenant, enforced by
a `CHECK` constraint). `BelongsToTenant` also refuses to create a record for
another tenant than the current one and to change `tenant_id` afterwards.

**Authentication.** `User` uses `BelongsToTenant`, so Fortify's provider
queries (login, password reset, 2FA challenge, session user) are scoped to the
current host. `email` is unique per tenant (`unique(tenant_id, email) NULLS
NOT DISTINCT`). `EnsureTenantMember` re-checks the authenticated user's tenant
on the panel routes as defense in depth.

**Sessions.** Cookies are host-only (`SESSION_DOMAIN=null`): a session never
spans tenants. The central login page is a "find your workspace" form that
forwards to `{slug}.{APP_DOMAIN}/login`.

**Registration handoff.** After `CreateTenant`, the central domain issues a
single-use token (32 random bytes, stored hashed in the cache for 60 seconds,
bound to the tenant) and redirects with `Inertia::location()` to
`/auth/handoff/{token}` on the subdomain, which logs the owner in and opens
`/app/onboarding`.

## Consequences

- Every query in a request is scoped before the session user is loaded, and
  console and queue code must opt in to a tenant (`CurrentTenant::runAs`).
- Fortify's routes serve both sides. Super admin login on the central domain
  works at the provider level; its screens arrive with the admin panel
  (Phase 4).
- Password reset tokens are keyed by email only (Laravel's default
  repository): if the same email requests resets in two tenants at once, the
  latest request invalidates the earlier link. Acceptable for now.
- The onboarding wizard ships its team step in Phase 1. The services and
  working hours steps arrive with those features in Phase 2.
