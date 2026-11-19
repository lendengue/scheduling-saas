# 0004. Official Vue starter kit as the application baseline

- Status: Accepted
- Date: 2026-11-19

## Context

The stack (Inertia + Vue 3 + TypeScript + Tailwind CSS v4 + shadcn-vue/Reka UI
+ Lucide) matches Laravel's official Vue starter kit almost exactly. Building
the same foundation by hand would take time and drift from upstream
conventions.

## Decision

Scaffold from `laravel/vue-starter-kit` (`main` branch, Laravel 13 +
Inertia v3), with these adjustments:

1. **Authentication features (Fortify):** keep registration, two-factor
   authentication and password confirmation. Remove passkeys and email
   verification, which the specification does not require. Either can be added
   back later through Fortify.
2. **Tests:** PHPUnit is replaced by Pest. The kit's tests are converted with
   `pest-plugin-drift`.
3. **Frontend tooling:** the kit uses `vite-plus` (`vp check`, Oxlint/Oxfmt).
   The specification asks for ESLint + Prettier + `vue-tsc`, so the kit uses
   plain Vite 8 with ESLint (flat config, `eslint-plugin-vue`,
   `@vue/eslint-config-typescript`), Prettier (with
   `prettier-plugin-tailwindcss`) and `vue-tsc`. Generated shadcn-vue
   components (`resources/js/components/ui`) and Wayfinder output are excluded
   from lint and formatting.
4. **Package manager:** npm (the spec's CI uses `npm ci`). The kit's
   `pnpm-workspace.yaml` was removed.
5. **Front-end directory casing:** the kit uses lowercase directories
   (`resources/js/pages`, `components`, `layouts`), which is the current Laravel
   convention and what Wayfinder and the kit's page resolver expect. The
   specification's tree shows `Pages/`, `Components/` and `Layouts/`. The
   lowercase names are kept, with the same sub-structure (`pages/Central`,
   `pages/Tenant`, `pages/Booking`).
6. **Fortify user-centric pieces** (users table with a globally unique email,
   central login) are a starting point only. Phase 1 adapts them to
   `unique(tenant_id, email)` and tenant-aware login.
7. `laravel/pao` (agent-friendly test output, MIT) ships with the kit and is
   kept as a dev dependency. It is inactive for normal terminal and CI runs
   and can be turned off with `PAO_DISABLE=1`.

## Consequences

- The project starts with working, tested authentication, settings pages and
  a UI component library.
- Some kit code (users, auth controllers) will change significantly in
  Phase 1. That is expected.
- Pulling future starter-kit changes means a manual merge, since the kit is a
  template, not a dependency.
