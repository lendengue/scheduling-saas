# 0003. Dependency versions and upgrade policy

- Status: Accepted
- Date: 2026-11-19

## Context

The specification asks for the latest stable version of every dependency,
with minimums (PHP 8.4+, Laravel 12+, Inertia.js v2, Pest 3+,
PostgreSQL 17+). Several tools have moved past those minimums, and a few
latest releases are not yet compatible with the rest of the toolchain.

## Decision

Use the latest stable release, except where an incompatibility forces an
older line. Versions at project creation:

| Area | Version | Notes |
|---|---|---|
| PHP | 8.5 | Sail runtime `8.5`, CI `8.5` |
| Laravel | 13.x | |
| Inertia.js | v3 (`inertiajs/inertia-laravel` 3.x, `@inertiajs/vue3` 3.x) | The spec says "v2"; v3 is the current stable line. Confirmed by the project owner. |
| Vue / Tailwind CSS | 3.5 / 4.3 | |
| Pest | 5.x (on PHPUnit 13) | Spec minimum is 3+ |
| Larastan | 3.x | |
| PostgreSQL | 18 | Sail `postgres:18-alpine`, CI service `postgres:18-alpine` |
| Vite | 8.x | |
| TypeScript | **6.0.x** | TypeScript 7 (native port) is the latest, but `typescript-eslint` supports `<6.1.0` only. Revisit when it adds TS 7 support. |
| `@types/node` | 22.x | Matches the Node.js 22 runtime used locally and in CI |
| Node.js | 22 LTS | |

Licenses are checked before adding a package. Only permissive licenses are
accepted (MIT, ISC, BSD, Apache-2.0, 0BSD, BlueOak). MPL-2.0 is accepted for
build-time tools (`lightningcss`). Dual-licensed packages (`nette/*`) are used
under their permissive option (BSD-3-Clause).

## Consequences

- Major upgrades happen deliberately, in their own commit, with build, lint,
  static analysis and tests re-run.
- TypeScript stays on 6.0 until the lint toolchain supports 7.
