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
# or with parallelism
php artisan test --parallel

# Run a single test file
php artisan test tests/Feature/WebhookControllerTest.php

# Static analysis
vendor/bin/phpstan analyse

# Code style — check only (what CI runs)
vendor/bin/pint --test

# Code style — auto-fix
vendor/bin/pint

# platform deploy (maintenance mode → migrate → cache clear → workers restart)
php artisan app:deploy

# Artisan utilities
php artisan platform:replay-webhooks --since="2 hours ago"
php artisan platform:prune-webhooks --days=30
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
       on WebhookDelivery, then dispatches:

       SyncFromWebhookJob  [git queue, 300 s]
         └─ GitSyncService::pull()  ← Redis distributed lock via SiteLockService
              If new commits and site.deploy_on_webhook:
              ├─ ParseSiteJob  [parsing queue, 600 s]
              │    ParserService → strategy-selected parser → Page upserts + prune
              └─ DeploySiteJob  [deploy queue, 120 s]
                   Bus::chain() on deploy queue:
                   ├─ ProvisionEnvironmentJob   (mkdir, write .env)
                   ├─ BuildSiteJob              (npm/pnpm/yarn/bun + build command)
                   ├─ InjectTrackingJob         (inject GTM/GA snippet)
                   └─ ActivateReleaseJob        (rsync/symlink, Nginx reload, git snapshot tag)
```

`DeploySiteJob::handle()` only calls `DeployService::beginDeployment()` to create the `DeployLog` + `DeploymentRelease` records, then hands off to the `Bus::chain()`. This means the four steps are independent jobs, each re-fetching models fresh from DB.

### Five named queues

| Queue | Workers | Timeout | Purpose |
|---|---|---|---|
| `default` | 3 | 120 s | Emails, notifications |
| `git` | 2 | 300 s | Clone, pull, push, tag |
| `parsing` | 2 | 600 s | Page discovery, SEO, editable regions |
| `deploy` | 2 | 600 s | Full build + activation chain |
| `monitoring` | 3 | 300 s | Uptime checks, crawl, analytics sync |

### Concurrency safety

`SiteLockService` wraps every `GitSyncService` operation (clone, pull, push, tag) in a Redis `Cache::lock("platform:site:{id}:lock:{resource}")`. This prevents two queue workers from running concurrent Git operations on the same repo. Lock wait timeout is 10 s; if exceeded, a `RuntimeException` is thrown (not silently dropped).

### Deployment adapters

`DeployService` selects between two adapters based on `site->deployment_mode`:

- **`StaticDeploymentAdapter`** — copies build artifacts to `site->deploy_path`, writes an Nginx vhost config via `NginxConfigService`, reloads Nginx. Used by `static_html`, `hugo`, `eleventy`, `react`, `vue`, `svelte`, `astro`, and static-export Next.js.
- **`RuntimeDeploymentAdapter`** — starts a Node.js process via `SiteRuntimeService`. The port is resolved by `allocatePort()`: starts at `crc32(site->id) % portSpan + portStart` (default range 4100–6099), then TCP-probes that port and scans forward if it is already listening. The resolved port is threaded through the execution config — never call `portFor()` directly inside the deploy flow.

### Parser strategy

`ParserService::resolveParser()` selects the concrete `ParserInterface` implementation from `app/Services/Parsers/` based on `site->project_type`:

| Parser | Types |
|---|---|
| `StaticHtmlParser` | `static_html`, `custom`, fallback |
| `RenderedPhpParser` | `php_site` |
| `SsgOutputParser` | `hugo`, `eleventy`, Astro with build output |
| `SpaComponentParser` | `react`, `vue`, `svelte`, `nextjs`, `nuxt`, Astro without build output |

Each parser implements `discoverPages()` + `parsePage()` returning a `ParsedPage` value object. `ParserService` then upserts `Page` records and prunes deleted ones.

### Visual editor pipeline

`VisualEditor` (Livewire) renders the site in an iframe via `EditorPreviewController`. On save:
1. `RegionDetector` locates editable regions by CSS selector or `data-ui-region` markers.
2. `ContentPatcher` writes the edited content back to the source file (HTML/JSX/Markdown) in the repo.
3. `GitSyncService` commits and pushes the change (with `GitOperation` audit log entry).
4. Optionally dispatches `DeploySiteJob` for an immediate redeploy.

Git conflicts between a webhook push and an in-progress editor session throw `GitConflictException`, which is caught in `SyncFromWebhookJob` and converted to a `Notification::createAlert()` for manual resolution.

### Multi-tenancy

`Site::scopeVisibleTo(User $user)` on the `Site` model scopes results: admins see all sites, regular users see only `sites.user_id = auth()->id()`. Always use `SiteAccess::query()` (or `SiteAccess::findOrFail()`) instead of `Site::query()` directly when building user-facing site lists. The `EnsureSiteAccess` middleware (alias `site.access`) enforces this on all route-bound `{site}` parameters.

### Authorization

Gates and policies are registered in `AppServiceProvider::boot()`:
- `SitePolicy`, `BlogPostPolicy`, `PagePolicy`, `InvoicePolicy` — all follow the same pattern: `before()` returns `true` for admins (bypasses all checks), `view/update/delete` check `site->user_id === $user->id`.
- `App\Enums\Role` (backed string enum: `Admin`/`Editor`) is cast on `User::role`. Use `$user->isAdmin()` (compares against `Role::Admin`) rather than raw string comparisons.
- The first registered user becomes admin; subsequent registrations default to `Role::Editor` — see `CreateNewUser`.
- Registration is gated by `REGISTRATION_ENABLED` env var. This is controlled entirely via `config/fortify.php` features array — `Features::registration()` is conditionally included. Do **not** call `Fortify::withoutRegistration()`; that method does not exist.

### CSP nonce system

`SetSecurityHeaders` middleware generates a per-request nonce (`base64_encode(random_bytes(16))`) **before** `$next($request)` (before view rendering) and binds it to the container:

```php
app()->instance('csp-nonce', $nonce);
Livewire::setNonce($nonce);   // Livewire 4 nonce support
```

The nonce is used in the `Content-Security-Policy` header after the response is rendered. Two ways to consume it in views:
- `csp_nonce()` — global helper from `app/helpers.php` (autoloaded via `composer.json` `files` array)
- `@cspNonce` — Blade directive registered in `AppServiceProvider`, emits `nonce="..."` attribute

The `@vite()` directive receives the nonce via `nonce: csp_nonce()` in all layout files. `style-src` keeps `'unsafe-inline'` (required by Tailwind/Flux at runtime).

### Encrypted model fields

The following `Site` fields are cast to `encrypted` and stored encrypted at rest: `github_token`, `webhook_secret`, `inbox_inbound_secret`, `cf_api_token`, `smtp_password`, `ftp_ssh_password`. Never read these in bulk queries; always access them on a loaded model instance.

### Scheduled commands (app/Console/Commands)

The scheduler runs: `CheckUptime`, `CheckSsl`, `CrawlLinks`, `AnalyzeSeo`, `SyncAnalytics`, `RunLighthouse`, `SendCampaigns`, `PublishScheduled`, `BackupDatabase`. Each operates per-site and dispatches to the `monitoring` queue where appropriate.

### Pre/post deploy hooks (schema-only)

`sites.pre_deploy_hook` and `post_deploy_hook` columns exist in the schema but are **intentionally excluded from `$fillable`** and **never executed**. `DeployService` contains a commented security contract (`runDeployHook`) listing the 7 requirements that must be met before these are wired up. Do not execute these columns without satisfying that checklist.

### Key config

`config/platform.php` is the primary application config. Notable keys: `repos_path` (where repos are cloned), `sites_deploy_path` (Nginx-served root), `nginx_sites_path`, `deploy.build_timeout_seconds` (300), `deploy.rollback_snapshots` (10), `runtime.port_start` (4100), `registration_enabled` (mirrors `REGISTRATION_ENABLED` env var).
