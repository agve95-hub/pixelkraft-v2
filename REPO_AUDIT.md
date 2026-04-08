# pixelkraft Repository Audit (2026-04-08)

## 1) Intended Product Direction

pixelkraft is designed as a self-hosted “site operations platform” for Git-backed websites, combining editing, deployment, SEO, monitoring, and notifications in one Laravel/Livewire dashboard.

Evidence from current repo/docs:
- Central intention statement and feature areas in README.
- Dashboard + API route structure for sites, editor, SEO, content, analytics, email, settings, and webhooks.
- Service layer implementing deploy pipeline, git sync, parser pipeline, runtime/static deployment modes.

## 2) What currently works (baseline)

- PHP app boots enough to list routes (`php artisan route:list --except-vendor`).
- Front-end build succeeds (`npm run build`) and outputs Vite assets.
- Existing tests pass (`php artisan test`), but coverage is very small.

## 3) What is not working / high risk right now

### A. Local bootstrap is fragile and fails in common fresh-clone state

Observed failure:
- `php artisan migrate:status` fails in this environment with: missing SQLite file (`database/database.sqlite`).

Why this matters:
- The app cannot run migrations until DB config/files are prepared.
- Current setup relies on manual steps in README (`cp .env.example .env` + DB configuration), and there is no guard in normal `composer install` to create `.env` or SQLite file.

Code signals:
- `setup` script runs migrate directly.
- SQLite file creation exists only in `post-create-project-cmd`, which does not run for normal git-clone workflows.

### B. Dependency state drift in PHP package metadata

Observed warning:
- `composer install` reports the lock file is not up-to-date with `composer.json`.

Why this matters:
- Non-deterministic installs across environments and confusion during CI/CD.
- Potential “works on my machine” version mismatches.

### C. Test coverage is far below feature surface

Current state:
- Only two example tests exist (root redirect + trivial unit assertion).

Why this matters:
- Core flows (git sync, deploy pipeline, queue jobs, webhook matching, editor/SEO/content APIs) are effectively unprotected.
- Regression risk is high as features expand.

### D. Production-coupled defaults can block non-VPS/dev environments

Current defaults:
- Deploy paths and Nginx paths assume VPS locations (`/var/www/sites`, `/etc/nginx/sites-available`).

Why this matters:
- New contributors can hit permissions/path failures unless env overrides are set.
- Some deploy/runtime features appear broken locally when it is really configuration coupling.

## 4) Priority remediation plan

1. **Bootstrap hardening (P0)**
   - Add a dedicated install/bootstrap command that ensures `.env` exists and validates DB reachability before migration.
   - If SQLite is used, auto-create `database/database.sqlite` in setup path (not only create-project path).

2. **Dependency hygiene (P0)**
   - Run and commit a lockfile refresh aligned to `composer.json`.
   - Add CI check that fails if lock file is out of sync.

3. **Testing strategy (P0/P1)**
   - Add feature tests for:
     - webhook signature verification + branch filtering,
     - authenticated API site actions (`sync`, `deploy`, `rollback`),
     - queue dispatch assertions for deploy/sync jobs,
     - service-layer unit tests for deployment mode selection.

4. **Environment clarity (P1)**
   - Document dev-safe filesystem defaults and required overrides for VPS-only capabilities.
   - Add diagnostics UI warnings when required system paths/binaries are missing.

## 5) Commands executed during audit

- `php -v`
- `node -v`
- `npm -v`
- `composer test` (initially failed before dependencies)
- `composer install`
- `php artisan test`
- `php artisan route:list --except-vendor`
- `php artisan migrate:status`
- `npm run build`

