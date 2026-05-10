# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# First-time setup: install deps, copy .env, generate key, migrate, build assets
composer run setup

# Development server (Laravel + queue worker + Pail log viewer + Vite, all concurrently)
composer run dev

# Run full test suite
composer run test
# or
php artisan test

# Run a single test file
php artisan test tests/Feature/WebhookControllerTest.php

# Static analysis
vendor/bin/phpstan analyse

# Code style — check only (what CI runs)
vendor/bin/pint --test

# Code style — auto-fix
vendor/bin/pint

# platform deploy (maintenance mode → migrate → cache clear/rebuild → workers restart)
php artisan app:deploy

# Artisan utilities
php artisan platform:replay-webhooks --since="2 hours ago"
php artisan platform:prune-webhooks --days=30
php artisan platform:prune-monitoring          # prunes 7 tables (see routes/console.php)
php artisan horizon:list-failed
```

CI runs `vendor/bin/pint --test` and `php artisan test` (no parallelism). Tests use in-memory SQLite and `QUEUE_CONNECTION=sync` (jobs run inline, no Redis required). A separate `assets` CI job runs `npm ci && npm audit && npm run build` (Tailwind + JS only — no TypeScript compile step).

### Docker dev environment

```bash
# Start full stack: app, nginx, vite HMR, horizon, scheduler, MariaDB, Redis, Mailpit
docker compose up

# Dashboard: http://localhost:8080  Mailpit: http://localhost:8025
```

Services: `app` (php-fpm:9000), `nginx` (:8080), `vite` (:5173 HMR), `horizon`, `scheduler`, `db` (MariaDB 11.4 on host :3307), `redis` (7.4 on host :6380), `mailpit` (SMTP :1025). DB/Redis use non-standard host ports to avoid conflicts with local installs.

## Architecture overview

platform is a **self-hosted Git-to-Render site operations platform** built on Laravel 13 + Livewire v4 + Flux v2 + Tailwind v4. Its central model is `Site` (UUID primary key). Everything revolves around automating the lifecycle: push → sync → parse → deploy.

The dashboard UI is **PHP-only**: Blade templates (`resources/views/dashboard/`) + Livewire 4 components (`app/Livewire/`) + Flux 2 UI kit. There is no React, TypeScript, or Inertia.js. The only JavaScript is `resources/js/app.js` (vanilla JS search palette) and the Livewire/Flux runtime bundles loaded via `@livewireScripts` / `@fluxScripts`.

### The pipeline: webhook → queue chain

```
POST /api/webhooks/github
  └─ WebhookController::github()
       Verifies HMAC-SHA256 signature, deduplicates via unique(provider, delivery_id)
       on WebhookDelivery, dispatches SyncFromWebhookJob.

       SyncFromWebhookJob  [git queue, 300 s]
         └─ GitSyncService::pull()  ← Redis distributed lock via SiteLockService
              Fires: SiteSynced event
                ├─ ParseSiteOnSync listener  →  ParseSiteJob  [parsing queue, 600 s]
                │     ParserService → strategy parser → Page upserts + prune
                │     then dispatches AnalyzeSeoJob per page [parsing queue, 30 s each]
                └─ DeployOnSync listener  →  DeploySiteJob  [deploy queue, 120 s]  (if deploy_on_webhook)
                     Bus::chain() on deploy queue:
                     ├─ ProvisionEnvironmentJob   (mkdir, create deployment targets)
                     ├─ BuildSiteJob              (npm/pnpm/yarn/bun + build command)
                     ├─ InjectTrackingJob         (inject GTM/GA snippet)
                     └─ ActivateReleaseJob        (atomic file swap, Nginx reload, git snapshot tag)
                          Fires: SiteDeployed event
                            ├─ ParseSiteOnDeploy listener  →  ParseSiteJob
                            └─ VerifyRuntimeOnDeploy listener  →  VerifyRuntimeHealthJob [monitoring, 15 s × 24]
```

`DeploySiteJob::handle()` only calls `DeployService::beginDeployment()` to create the `DeployLog` + `DeploymentRelease` records, then hands off to the `Bus::chain()`. Each step is an independent job that re-fetches its models fresh from DB.

### Five named queues

| Queue | Workers | Timeout | Purpose |
|---|---|---|---|
| `default` | 3 | 120 s | Emails, notifications |
| `git` | 2 | 300 s | Clone, pull, push, tag |
| `parsing` | 2 | 600 s | Page discovery, per-page SEO analysis |
| `deploy` | 2 | 600 s | Full build + activation chain |
| `monitoring` | 3 | 300 s | Uptime checks, runtime health, crawl, analytics |

### Service layer

The deploy pipeline is split across four focused services:

| Service | Responsibility |
|---|---|
| `BuildService` | Dependency installation, build command execution, package manager detection, node version validation, env var filtering, output scrubbing |
| `AssetOptimisationService` | Image optimisation, HTML/CSS/JS minification, lazy-loading injection, tracking script injection |
| `ReleaseManager` | DeployLog + DeploymentRelease lifecycle: begin, complete, fail, prune snapshots, health check |
| `DeployService` | Orchestration only — delegates all build/asset/release work to the above three |

### Event system

Side-effects are decoupled from the queue chain via domain events registered in `AppServiceProvider::boot()`:

| Event | Listeners |
|---|---|
| `SiteSynced` | `ParseSiteOnSync`, `DeployOnSync` |
| `SiteDeployed` | `ParseSiteOnDeploy`, `VerifyRuntimeOnDeploy` |
| `DeployFailed` | `NotifyOnDeployFailed` |

Never call `ParseSiteJob::dispatch()` or `VerifyRuntimeHealthJob::dispatch()` directly from deploy code — fire the event and let listeners handle it.

### Concurrency safety

`SiteLockService` wraps every `GitSyncService` operation (clone, pull, push, tag) in a Redis `Cache::lock("platform:site:{id}:lock:{resource}")`. This prevents two queue workers from running concurrent Git operations on the same repo. Lock wait timeout is 10 s; if exceeded, a `RuntimeException` is thrown (not silently dropped).

`DeploySiteJob` and `ParseSiteJob` both implement `ShouldBeUnique` (per site) so duplicate jobs from simultaneous webhook + manual triggers are deduplicated at the queue level.

### Deployment adapters

`DeployService` selects between two adapters based on `site->deployment_mode`:

- **`StaticDeploymentAdapter`** — atomically swaps build artifacts into `site->deploy_path` via two renames (no downtime window), writes an Nginx vhost config via `NginxConfigService`, reloads Nginx. Used by `static_html`, `hugo`, `eleventy`, `react`, `vue`, `svelte`, `astro`, and static-export Next.js.
- **`RuntimeDeploymentAdapter`** — starts a Node.js process via `SiteRuntimeService`. The port is resolved by `allocatePort()`: starts at `crc32(site->id) % portSpan + portStart` (default range 4100–6099), then TCP-probes that port and scans forward if it is already listening. The resolved port is persisted in a port file so Nginx config generation and `VerifyRuntimeHealthJob` always agree with the actual process port.

### Parser strategy

`ParserService::resolveParser()` selects the concrete `ParserInterface` implementation from `app/Services/Parsers/` based on `site->project_type`:

| Parser | Types |
|---|---|
| `StaticHtmlParser` | `static_html`, `custom`, fallback |
| `RenderedPhpParser` | `php_site` |
| `SsgOutputParser` | `hugo`, `eleventy`, Astro with build output |
| `SpaComponentParser` | `react`, `vue`, `svelte`, `nextjs`, `nuxt`, Astro without build output |

`ParseSiteJob` discovers/upserts pages then dispatches one `AnalyzeSeoJob` per page (rather than running SEO analysis inline), so discovery returns fast and analysis runs in parallel across available parsing workers.

### Visual editor pipeline

`VisualEditor` (Livewire) renders the site in an iframe via `EditorPreviewController`. On save:
1. `RegionDetector` locates editable regions by CSS selector or `data-ui-region` markers.
2. `ContentPatcher` writes the edited content back to the source file (HTML/JSX/Markdown) in the repo. The `applyBatch()` method requires a `Site` parameter to scope regions — prevents cross-tenant IDOR writes.
3. `GitSyncService` commits and pushes the change (with `GitOperation` audit log entry).
4. Optionally dispatches `DeploySiteJob` for an immediate redeploy.

Git conflicts between a webhook push and an in-progress editor session throw `GitConflictException`, caught in `SyncFromWebhookJob` and converted to a `Notification::createAlert()`.

### Multi-tenancy

`Site::scopeVisibleTo(User $user)` scopes results: admins see all sites, editors see only `sites.user_id = auth()->id()`. Always use `SiteAccess::query()` (or `SiteAccess::findOrFail()`) instead of `Site::query()` directly when building user-facing lists. The `EnsureSiteAccess` middleware (alias `site.access`) enforces this on all route-bound `{site}` parameters.

### Authorization

Gates and policies are registered in `AppServiceProvider::boot()`:
- `SitePolicy`, `BlogPostPolicy`, `PagePolicy`, `InvoicePolicy` — `before()` returns `true` for admins, `view/update/delete` check `site->user_id === $user->id`.
- `SitePolicy::configureBuild` — **admin only**. The `build_command`, `build_output_dir`, `env_variables`, `node_version` fields execute shell code on the server during deploy. `SiteSettings` Livewire component enforces this gate before writing those fields.
- `App\Enums\Role` (backed string enum: `Admin`/`Editor`) is cast on `User::role`. Use `$user->isAdmin()` rather than raw string comparisons.
- The first registered user becomes admin; subsequent registrations default to `Role::Editor` — see `CreateNewUser`.
- Registration is gated by `REGISTRATION_ENABLED` env var via `config/fortify.php` features array.

### Site field groups

`Site::$fillable` is documented in four named constants to communicate access intent:

| Constant | Who may write |
|---|---|
| `OWNER_FILLABLE` | Site owner (editor or admin) |
| `BUILD_FILLABLE` | Admin only (`configureBuild` gate) |
| `SECRET_FILLABLE` | Written via explicit setters, never mass-assigned |
| `SYSTEM_FILLABLE` | Queue jobs / services only, never user input |

Use the typed helpers `updateSettings()`, `updateBuildConfig()`, `updateSystemFields()` instead of raw `->update()` where intent needs to be explicit.

### 2FA enforcement

`RequireTwoFactor` middleware (registered in the `web` group via `bootstrap/app.php`) redirects admin users without a confirmed TOTP device to the Settings page. Enabled when `ENFORCE_2FA=true` or `APP_ENV=production`. Exempt routes: `settings`, `login`, `logout`, `password.*`, `two-factor.*`, `system.*`.

### CSP nonce system

`SetSecurityHeaders` middleware generates a per-request nonce (`base64_encode(random_bytes(16))`) **before** `$next($request)` and binds it to the container:

```php
app()->instance('csp-nonce', $nonce);
Livewire::setNonce($nonce);
```

Two ways to consume in views:
- `csp_nonce()` — global helper from `app/helpers.php`
- `@cspNonce` — Blade directive registered in `AppServiceProvider`

`script-src` uses the nonce exclusively (no `unsafe-eval`, no `unsafe-inline`). `style-src` keeps `unsafe-inline` for Tailwind/Flux inline styles.

### Encrypted model fields

The following `Site` fields are cast to `encrypted` and stored encrypted at rest: `github_token`, `webhook_secret`, `inbox_inbound_secret`, `cf_api_token`, `smtp_password`, `ftp_ssh_password`. Never read these in bulk queries; always access them on a loaded model instance.

### DeployLog buffering

`DeployLog::appendLog()` buffers up to 8 lines in memory before writing to the DB, reducing the ~20 UPDATE queries per deploy to ~3. Call `$log->flushLog()` at the end of each deploy step (before model hand-off between queue jobs) to ensure lines are persisted before the next job re-fetches the model.

### Scheduled commands (app/Console/Commands)

The scheduler runs: `CheckUptime` (every 5 min), `CheckSsl`, `CrawlLinks`, `AnalyzeSeo`, `SyncAnalytics`, `RunLighthouse`, `SendCampaigns`, `PublishScheduled`, `BackupDatabase`. Data retention is handled by `PruneMonitoringData` (weekly, 7 table categories) and `PruneWebhooks`.

### Key config

`config/platform.php` is the primary application config. Notable keys: `repos_path`, `sites_deploy_path`, `nginx_sites_path`, `deploy.build_timeout_seconds` (300), `deploy.rollback_snapshots` (10), `runtime.port_start` (4100), `registration_enabled`, `enforce_two_factor`.
