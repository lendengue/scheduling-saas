# Architecture Decision Records

Short records of the decisions that shape scheduling-saas. Each ADR states the
context, the decision and its consequences. ADRs are immutable once accepted:
to change a decision, write a new ADR that supersedes the old one.

| ADR | Title | Status |
|---|---|---|
| [0001](0001-single-database-multitenancy.md) | Single-database multi-tenancy by subdomain | Accepted |
| [0002](0002-postgres-exclusion-constraint.md) | PostgreSQL exclusion constraint against double-booking | Accepted |
| [0003](0003-dependency-versions.md) | Dependency versions and upgrade policy | Accepted |
| [0004](0004-vue-starter-kit-baseline.md) | Official Vue starter kit as the application baseline | Accepted |
| [0005](0005-local-environment.md) | Local environment with Laravel Sail | Accepted |
| [0006](0006-testing-and-ci.md) | Testing strategy and CI pipeline | Accepted |
| [0007](0007-tenant-routing-and-cross-domain-login.md) | Tenant routing, sessions and cross-domain login | Accepted |
| [0008](0008-tenant-context-in-queued-work.md) | Tenant context in queued work | Accepted |
| [0009](0009-roles-and-invitations.md) | Roles and team invitations | Accepted |

## Template

```markdown
# NNNN. Title

- Status: Proposed | Accepted | Superseded by NNNN
- Date: YYYY-MM-DD

## Context
## Decision
## Consequences
```
