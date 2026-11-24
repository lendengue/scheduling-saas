# 0013. The team agenda

- Status: Accepted
- Date: 2026-11-24

## Context

The panel needs an agenda with day, week and list views, colors per
professional, booking by clicking an empty slot, rescheduling by drag and
drop and actions on each appointment (spec 6.6). The specification allows
FullCalendar or a custom component and does not say how panel bookings relate
to the rules of the public page.

## Decision

- **FullCalendar v7** (`@fullcalendar/vue3`, MIT). v7 ships its plugins inside
  the Vue package, renders named timezones natively (Temporal polyfill) and
  is themed with CSS variables, which we map to the app's design tokens so it
  follows light and dark mode. Resource (per-professional column) views are a
  premium feature, so professionals are told apart by color and an optional
  filter instead.
- **The agenda shows the business timezone**, whatever the browser's. The
  feed (`GET /app/agenda/events`) returns UTC instants; the page sends back
  wall-clock times (`2026-11-24T14:30`) that the server reads in the tenant's
  timezone. Events span the whole blocked time (service + buffer), so what
  looks free is free.
- **Visibility:** owners and admins see every professional and may filter;
  staff always get their own agenda, whatever they ask the feed for, and may
  only book into or move appointments within it (`AppointmentPolicy`).
- **Panel bookings and moves ignore the public rules** (working hours, minimum
  notice, slot grid, customer change window): the team handles walk-ins,
  phone bookings and exceptions. Only real overlaps are rejected, by the
  exclusion constraint, and the drag is reverted with the reason.
- **Lifecycle:** confirm a pending booking; mark completed or no-show only
  once the appointment has started; cancel with an optional reason that is
  emailed to the customer. Changing time or professional is a reschedule, not
  a status change. Customers are emailed on booking, cancellation and
  reschedule (with a rotated link, ADR 0011).

## Consequences

- A single endpoint serves all views; periods are limited to 62 days.
- Moving an appointment to another professional is done from the appointment
  sheet rather than by dragging between columns.
- Switching to the premium resource views later only changes the front end.
