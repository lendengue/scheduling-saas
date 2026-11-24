# 0012. timestamptz columns and timezone-safe writes

- Status: Accepted
- Date: 2026-11-24

## Context

All instants are stored in UTC (spec 5) in PostgreSQL `timestamptz` columns.
Eloquent serializes dates with the format `Y-m-d H:i:s`, which drops the
timezone. A Carbon instance in the tenant's timezone (for example 10:00 in
São Paulo) would therefore be written as "10:00" and read back as 10:00 UTC,
three hours off, unless every caller remembered to convert to UTC first.

## Decision

Models with `timestamptz` columns (appointments, time off, customers) use the
`UsesTimestampTz` trait, which sets the date format to `Y-m-d H:i:sP`. The
offset is written with every value, so PostgreSQL stores the exact instant
whatever the timezone of the Carbon instance or of the database session.
Reads come back as UTC (`immutable_datetime` casts).

Times typed by people (agenda, booking settings, working hours) are wall-clock
times of the business and are converted to UTC at the edge, in the Form
Request or action that receives them.

## Consequences

- Passing a local-time Carbon instance to a model is safe.
- Older tables created by the framework (users, jobs, ...) keep `timestamp`
  columns and the default format; they only ever hold `now()` in UTC.
