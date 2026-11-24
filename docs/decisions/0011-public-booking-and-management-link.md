# 0011. Public booking, abuse protection and the management link

- Status: Accepted
- Date: 2026-11-24

## Context

Customers book without an account (spec 6.5) and later cancel or reschedule
through a link. The specification suggests the link
`/{slug}/appointments/{ulid}/manage?token=...` and "Cloudflare Turnstile or a
honeypot" against abuse.

## Decision

- **Link without `{slug}`:** `/appointments/{ulid}/manage?token=...` on the
  tenant's subdomain. The subdomain already identifies the tenant (ADR 0001),
  and the tenant scope makes an appointment of another tenant answer 404.
- **Token:** 32 random bytes (base64url), shown once in the confirmation page
  and email; only its SHA-256 hash is stored and it is compared with
  `hash_equals` (`SecureToken`). A wrong or missing token answers 404, never
  403, so a ULID alone reveals nothing.
- **Token rotation:** when the *team* reschedules an appointment, a new token
  is generated so the "new time" email carries a working link (the plain token
  is unknown to the server). The old link stops working. Customer reschedules
  keep their token.
- **Change window:** customers may cancel or reschedule online until
  `cancellation_notice_hours` (default 24) before the start; after that the
  page asks them to contact the business. The team has no such limit.
- **Abuse:** a per-IP rate limit (10 bookings/hour, 120 availability
  requests/minute) plus a honeypot field. Turnstile is not used: it needs
  third-party keys and network access in every environment (including CI and
  local demos) for little extra protection at this stage.

## Consequences

- Links are short and cannot be used across tenants.
- A customer who forwarded an old email after a team reschedule has a dead
  link; the new email has the working one.
- Turnstile can be added later behind the same `StoreBookingRequest` without
  changing the flow.
