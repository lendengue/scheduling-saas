# 0002. PostgreSQL exclusion constraint against double-booking

- Status: Accepted
- Date: 2026-11-19

## Context

Two customers can try to book the same professional for overlapping times at
the same moment. Application-level checks ("is this slot still free?") are
subject to race conditions between the check and the insert.

## Decision

Enforce non-overlap in the database with a GiST exclusion constraint
(requires the `btree_gist` extension):

```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;
ALTER TABLE appointments
  ADD CONSTRAINT appointments_no_overlap
  EXCLUDE USING gist (
    staff_profile_id WITH =,
    tstzrange(starts_at, ends_at, '[)') WITH &&
  ) WHERE (status IN ('pending', 'confirmed'));
```

The `BookAppointment` action still validates availability first (for friendly
errors), runs inside a transaction, and translates SQLSTATE `23P01` into the
domain exception `SlotNoLongerAvailable` (HTTP 409 or a validation error).

## Consequences

- PostgreSQL is required in every environment, including tests. SQLite cannot
  be used for the test suite (see ADR 0006).
- Half-open ranges (`[)`) allow back-to-back appointments.
- `ends_at` must already include the service buffer so that the buffer is
  protected too.
- A concurrency test must prove that only one of two simultaneous bookings for
  the same slot succeeds.
