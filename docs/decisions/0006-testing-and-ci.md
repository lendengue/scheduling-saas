# 0006. Testing strategy and CI pipeline

- Status: Accepted
- Date: 2026-11-19

## Context

The specification requires Pest with PostgreSQL (never SQLite), Larastan
level 8+, ESLint, Prettier and `vue-tsc`, and a GitHub Actions workflow with
four parallel jobs (`lint`, `static-analysis`, `tests`, `build`). Some
implementation details were left open.

## Decision

- **Database:** `phpunit.xml` forces `DB_CONNECTION=pgsql` and
  `DB_DATABASE=testing`. `RefreshDatabase` is applied to every Feature test
  from `tests/Pest.php`. Parallel runs (`--parallel`) let Laravel create one
  database per process (`testing_test_N`).
- **No frontend build in backend tests:** the base `TestCase` calls
  `withoutVite()`. Backend tests then do not depend on `public/build`, and the
  `tests` and `build` CI jobs stay independent.
- **Larastan level 8.** Nullable `$request->user()` calls are fixed at the
  source (the `#[CurrentUser]` attribute), not suppressed or baselined.
- **Coverage:** the specification asks for at least 80% in `app/Actions` and
  `app/Services`, and its CI section runs `--coverage --min=80` over the whole
  suite. The CI uses the global `--min=80` as written. Phase 2 will add a
  dedicated check for `app/Actions` and `app/Services` once those directories
  exist.
- **Wayfinder in CI:** TypeScript route helpers (`resources/js/actions`,
  `resources/js/routes`, `resources/js/wayfinder`) are generated, not
  versioned. The `static-analysis` job runs
  `php artisan wayfinder:generate --with-form` before `vue-tsc`. The `build`
  job generates them through the Vite plugin.
- **Supply chain:** third-party actions are pinned to commit SHAs (with the
  tag in a comment), and `actions/checkout` does not persist credentials.
  Composer and npm downloads are cached.
- **Composer scripts:** `composer setup`, `composer lint` (check only),
  `composer lint:fix`, `composer analyse` (Larastan + `vue-tsc`),
  `composer test` and `composer ci:check` (all of the above).

## Consequences

- The four CI jobs can run in any order and in parallel.
- Running the tests locally needs the Sail PostgreSQL service.
