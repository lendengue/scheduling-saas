# 0008. Tenant context in queued work

- Status: Accepted
- Date: 2026-11-19

## Context

The fail-closed tenant scope (ADR 0007) means a queued job sees no tenant data
unless the tenant is restored in the worker. The specification suggests a
`RestoreTenantContext` job middleware or a `TenantAware` trait on each job.
Both are opt-in per class and do not reach queued work we do not own:
queued mailables, notifications and event listeners are wrapped in framework
jobs (`SendQueuedMailable`, `SendQueuedNotifications`, `CallQueuedListener`).

## Decision

Carry the tenant in the queue payload for all queued work:

- `Queue::createPayloadUsing()` adds `tenant_id` (the current tenant, or
  `null`) to every payload.
- On `JobProcessing`, the worker restores that tenant into `CurrentTenant`
  (a deleted tenant restores as "no tenant"). On `JobProcessed` and
  `JobExceptionOccurred`, it clears it.
- The `sync` connection is skipped, since it runs inline in the caller's
  context.
- `CurrentTenant` is a scoped singleton, so workers (and Octane) also reset it
  between jobs and requests.

spatie/laravel-permission reads its team id from `CurrentTenant` through a
custom `PermissionsTeamResolver`, so role checks follow the restored tenant
automatically.

## Consequences

- No job, mail or notification can forget to restore the tenant.
- Models in a payload are restored without global scopes (Laravel's
  `newQueryForRestoration`), but every query the job makes afterwards is
  scoped, as tested with a real database-queue worker.
- Work that must cover all tenants (scheduler, reminders) iterates tenants
  explicitly with `CurrentTenant::runAs()`.
