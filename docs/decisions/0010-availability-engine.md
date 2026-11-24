# 0010. Availability engine and appointment snapshots

- Status: Accepted
- Date: 2026-11-24

## Context

Availability is the core of the product (spec 6.4). It must be correct across
DST changes, buffers, partial time off and minimum notice, and fast enough to
be computed for every day of a month on the public page. The specification
leaves open what an appointment keeps when its service changes later, and how
the cache is invalidated.

## Decision

- **Wall-clock rules, UTC storage.** Working hours are wall-clock times
  (`time` columns, several intervals per weekday). For a given date the
  calculator builds the day in the tenant's timezone, turns each interval into
  UTC instants and subtracts time off and active (`pending`/`confirmed`)
  appointments. Slots step every `slot_interval` minutes from the start of
  each free interval and must fit `duration + buffer` entirely.
- **`ends_at` is the end of the blocked time** (service + buffer), so the
  exclusion constraint (ADR 0002) and the calculator see the same interval.
  The customer-facing end is `starts_at + duration_minutes`.
- **Snapshots.** An appointment stores `duration_minutes` and `price_cents` as
  booked. Editing or deleting (soft) a service never changes past or future
  appointments; a reschedule keeps the blocked duration of the original
  booking.
- **"Any professional"** unions the slots of every eligible professional; the
  booking goes to the one with the fewest active appointments that day, ties
  broken by the lowest id.
- **Cache.** Slots are cached per `(tenant, service, professional, date)` for
  60 seconds (`config/scheduling.php`). Every key embeds a per-tenant version
  number; any change to appointments, time off, working hours, services or
  profiles bumps it (model observer + the staff actions), so stale entries are
  never read again and expire on their own. Redis has no cheap "delete by
  prefix", which this avoids.

## Consequences

- DST is handled by Carbon when wall-clock intervals become instants: on the
  spring-forward day the skipped hour offers no slots, and on the fall-back
  day the repeated hour is offered twice (tested with `America/New_York`).
- Invalidation is coarse (the whole tenant) but cheap and always correct; the
  short TTL bounds memory.
- Revenue figures in later phases read the price snapshot, not the current
  catalog price.
