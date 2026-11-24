# 0014. Onboarding wizard and demo data

- Status: Accepted
- Date: 2026-11-24

## Context

After registration the owner lands on a three-step wizard (spec 6.1):
services, working hours and team invitations. The specification also asks for
a `DemoSeeder` with a realistic barbershop.

## Decision

- The wizard reuses the regular endpoints (service dialog, working hours
  editor, invitation form); each redirects back to the wizard, which marks a
  step done from the data itself (at least one service, at least one working
  interval). There is no separate onboarding state to keep in sync.
- The owner is created as a bookable professional (`CreateTenant`), so a solo
  business is ready to take bookings after steps 1 and 2. The working hours
  step edits the current user's own hours; other members' hours live on the
  Team page.
- `DemoSeeder` uses a fixed Faker seed (`pt_BR` names) and builds the agenda
  relative to today: the last 7 days (mostly completed, some no-shows and
  cancellations) and the next 14 days (filling up less the further out), with
  gaps so the public page still offers slots. It writes appointments directly
  instead of going through `BookAppointment`, so seeding sends no emails.
  The demo tenant uses the `en` locale until the pt_BR translation lands
  (Phase 5).

## Consequences

- `migrate:fresh --seed` always produces a believable agenda for screenshots
  and demos.
- The seeder bypasses availability rules on purpose; a test checks its shape.
