# 0009. Roles and team invitations

- Status: Accepted
- Date: 2026-11-19

## Context

Roles use spatie/laravel-permission with teams (team = tenant). Tenants have
ULID keys, the package defaults to integer team ids, and the specification
leaves open where roles live and how invitation links are protected.

## Decision

- **Package setup.** spatie/laravel-permission 8.x, `teams = true`,
  `team_foreign_key = tenant_id`. The published migration uses ULID team
  columns with foreign keys to `tenants` (cascade on delete).
- **Global roles.** `owner`, `admin` and `staff` are created once with
  `tenant_id = NULL` (data migration) and assigned per tenant through
  `model_has_roles.tenant_id`. The roles unique index uses `NULLS NOT
  DISTINCT`. Fine-grained permissions are not needed yet: policies check
  roles.
- **Team id resolution.** A custom `TenantTeamResolver` returns the current
  tenant id, so `setPermissionsTeamId()` is never needed (it is ignored).
- **One owner per tenant**, created with the tenant. Owners and admins can
  invite admins or staff; nobody can be invited as owner. Ownership transfer
  comes with billing (Phase 3); until then the owner cannot delete their
  account.
- **Invitations.** A random 32-byte token is emailed and only its SHA-256 hash
  is stored. The link is also a relative signed URL (`signed:relative`)
  expiring with the invitation (7 days), served on the tenant subdomain.
  Opening it checks the token with `hash_equals`; accepting re-checks the
  token, locks the row, creates the user in the tenant, grants the role and
  marks the invitation accepted. Re-inviting an email replaces its pending
  invitation.
- `App\Support\SecureToken` generates and verifies these tokens, and will be
  reused for appointment management links.

## Consequences

- A leaked database dump does not reveal usable invitation links.
- Expired links fail on the signature (403); accepted ones answer 410; a wrong
  token answers 404, revealing nothing.
- Adding permissions later only requires seeding them and attaching them to
  the global roles.
