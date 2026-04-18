<div align="center">

# ⚡ pixelkraft

**The Git-to-Render Site Operations Platform**

*Push to Git. Pixelkraft handles everything else.*

[![PHP](https://img.shields.io/badge/PHP-8.3+-8892BF?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![React](https://img.shields.io/badge/React-19-61DAFB?style=flat-square&logo=react&logoColor=black)](https://react.dev)
[![TypeScript](https://img.shields.io/badge/TypeScript-6-3178C6?style=flat-square&logo=typescript&logoColor=white)](https://www.typescriptlang.org)
[![Livewire](https://img.shields.io/badge/Livewire-4-FB70A9?style=flat-square)](https://livewire.laravel.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-v4-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![MariaDB](https://img.shields.io/badge/MariaDB-11-003545?style=flat-square&logo=mariadb&logoColor=white)](https://mariadb.org)
[![Redis](https://img.shields.io/badge/Redis-7-DC382D?style=flat-square&logo=redis&logoColor=white)](https://redis.io)

</div>

---

## What is Pixelkraft?

Pixelkraft is a self-hosted **Site Operations Platform** for web agencies and solo developers. It connects to your GitHub repositories and automates the entire lifecycle of a website — from the moment you push code to the second the page goes live.

**One push. Everything happens automatically:**
- Webhook received & verified (HMAC-SHA256)
- Repository pulled via authenticated Git
- Dependencies installed (npm / pnpm / yarn / bun — auto-detected)
- Site built (`next build`, Vite, Hugo, Eleventy, etc.)
- Assets optimized (images, HTML, lazy-loading)
- Nginx config reloaded
- SEO metadata analyzed and indexed
- Analytics pipeline updated
- Team notified

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.3+ |
| Frontend routing | Inertia.js 3 + React 19 + TypeScript 6 |
| Live widgets | Livewire 4 + Flux 2 |
| UI components | shadcn/ui (Radix primitives + Tailwind v4) |
| Build tool | Vite 8 |
| Icons | Lucide React |
| Forms | React Hook Form + Zod |
| Queue / cache | Laravel Horizon + Redis 7 |
| Database | MariaDB 11 (MySQL 8+ compatible) |
| Auth | Laravel Fortify (TOTP 2FA) + Sanctum (API tokens) |
| PDF | DomPDF |
| Analytics | Google Analytics Data API v4 |
| Backup | Spatie Laravel Backup |
| Error tracking | Sentry |

---

## Feature Set

### 🚀 Git-to-Render Pipeline
- GitHub webhook receiver with HMAC-SHA256 signature verification
- Support for **static HTML, PHP, React, Vue, Svelte, Astro, Next.js, Nuxt, Hugo, Eleventy**
- Automatic package manager detection (npm / pnpm / yarn / bun)
- Build pipeline with timeout protection and full output capture
- Git conflict detection and smart rebase recovery
- One-click rollback to any previous deploy via snapshot tags

### 🎨 Visual Content Editor
- WYSIWYG editing directly on rendered pages in an iframe
- CSS-selector-based and marker-based editable region detection
- Patch-to-source: edits write back to the actual HTML / JSX / Markdown files
- Commit-on-save with full audit trail (GitOperation log)
- Source fallback preview for unbuilt framework pages (TSX/JSX/Vue/Svelte/Astro/MDX)

### 📊 Observability & Monitoring
- Built-in uptime checks with P95 response times
- Google Analytics v4 integration (organic traffic sync)
- Broken link crawler
- SEO issue tracker with per-page scoring
- Deploy log with step-by-step millisecond timing

### 💼 Agency Management Layer
- Per-site client profiles (contact, company, billing)
- Invoice management with PDF export
- Expense tracker per site
- Newsletter subscriber management & campaign editor
- Contact form submission inbox with spam filtering
- Site-scoped notification centre
- Maintenance mode management

### 🔒 Security
- All credentials encrypted at rest (`github_token`, `webhook_secret`, `inbox_inbound_secret`, `cf_api_token`, `smtp_password`, `ftp_ssh_password`)
- HMAC-SHA256 webhook signature verification (constant-time `hash_equals`)
- Role-based access control (admin / editor)
- Laravel Fortify TOTP 2FA
- Sanctum personal access tokens with scoped abilities for `/api/v1`
- Redis-backed distributed locking on all Git operations (prevents race conditions)
- Path traversal protection on asset serving

---

## Quick Start

### Prerequisites

| Requirement | Version |
|---|---|
| PHP | 8.3+ |
| Composer | 2.x |
| Node.js | 20+ |
| Redis | 7+ |
| MariaDB | 11+ (or MySQL 8+) |
| Git | 2.38+ |

### 1. Clone & Install

```bash
git clone https://github.com/your-org/pixelkraft.git
cd pixelkraft
composer run setup   # installs deps, copies .env, migrates DB, builds assets
```

The `setup` script runs:
- `composer install`
- `.env` creation from `.env.example` + app key generation
- Database migration
- `npm install && npm run build`

### 2. Configure Environment

```bash
cp .env.example .env
```

**Minimum required configuration:**

```env
APP_NAME=pixelkraft
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-dashboard-domain.com

# Database
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pixelkraft
DB_USERNAME=pixelkraft
DB_PASSWORD=<strong-password>

# Redis (required in production)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# GitHub Webhook
GITHUB_WEBHOOK_SECRET=<openssl rand -hex 32>
GITHUB_WEBHOOK_REQUIRE_SIGNATURE=true

# File paths
REPOS_PATH=/var/www/pixelkraft/repos
SITES_DEPLOY_PATH=/var/www/sites
NGINX_SITES_PATH=/etc/nginx/sites-available
```

### 3. Start the Development Server

```bash
composer run dev
```

Starts concurrently:
- `php artisan serve` — Laravel HTTP
- `php artisan queue:listen --tries=1 --timeout=0` — queue worker
- `php artisan pail --timeout=0` — real-time logs
- `npm run dev` — Vite HMR

### 4. Create the First Admin

```bash
php artisan tinker
>>> \App\Models\User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('secure-password'), 'role' => 'admin']);
```

---

## Production Deployment

### Supervisor (Horizon)

```ini
; /etc/supervisor/conf.d/pixelkraft-horizon.conf
[program:pixelkraft-horizon]
process_name=%(program_name)s
command=php /var/www/pixelkraft/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/pixelkraft-horizon.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start pixelkraft-horizon
```

### Nginx — Dashboard Vhost

```nginx
server {
    listen 443 ssl http2;
    server_name dashboard.example.com;
    root /var/www/pixelkraft/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/dashboard.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/dashboard.example.com/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known) { deny all; }
}
```

### Cron (Scheduler)

```bash
* * * * * www-data php /var/www/pixelkraft/artisan schedule:run >> /dev/null 2>&1
```

### Zero-Downtime Deploy

```bash
#!/bin/bash
set -e
APP_DIR=/var/www/pixelkraft

cd $APP_DIR
git pull origin main
composer install --no-dev --no-interaction --optimize-autoloader
npm ci && npm run build
php artisan migrate --force --graceful
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan horizon:terminate
sudo systemctl reload php8.3-fpm
sudo supervisorctl restart pixelkraft-horizon
echo "✅ Deployment complete"
```

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    GitHub Webhook (HMAC-SHA256)                  │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│              WebhookController  (/api/webhooks/github)           │
│  Signature verification · Branch filtering · Delivery recording  │
└─────────────────────────┬───────────────────────────────────────┘
                          │ dispatch
                          ▼
┌─────────────── git QUEUE (Redis / Horizon) ─────────────────────┐
│  SyncFromWebhookJob                                              │
│  GitSyncService::pull()  ·  Redis distributed lock              │
│  Conflict detection  ·  Rebase recovery                         │
└──────┬───────────────────────────────────────────────────────────┘
       │ dispatch
       ├────────────────────────────────┐
       ▼                                ▼
┌── parsing QUEUE ──┐       ┌── deploy QUEUE ──────────────────────┐
│  ParseSiteJob     │       │  DeploySiteJob chain:                │
│  Page discovery   │       │    ProvisionEnvironmentJob           │
│  Metadata index   │       │    BuildSiteJob                      │
│  SEO analysis     │       │    InjectTrackingJob                 │
│  Region detect    │       │    ActivateReleaseJob                │
└───────────────────┘       └──────────────────────────────────────┘
```

### Queue Architecture

| Queue | Workers | Timeout | Purpose |
|---|---|---|---|
| `default` | 3 | 120s | Emails, notifications |
| `git` | 2 | 300s | Clone, pull, push, tag |
| `parsing` | 2 | 600s | Page discovery, SEO, regions |
| `deploy` | 2 | 600s | Full build + activation |
| `monitoring` | 3 | 300s | Uptime, crawl, analytics |

### Supported Project Types

| Type | Build Command | Runtime |
|---|---|---|
| `static_html` | — | Nginx file serve |
| `php_site` | — | PHP-FPM |
| `react` | `npm run build` | Static or Node.js |
| `vue` | `npm run build` | Static or Node.js |
| `svelte` | `npm run build` | Static |
| `astro` | `npm run build` | Static |
| `nextjs` | `npm run build` | Node.js runtime |
| `nuxt` | `npm run build` | Node.js runtime |
| `hugo` | `hugo` | Static |
| `eleventy` | `npx @11ty/eleventy` | Static |
| `custom` | Configurable | Configurable |

---

## API Reference

### `/api/v1` — Machine Clients

Authenticate with **`Authorization: Bearer {token}`** (Sanctum personal access token). First-party Inertia sessions also authenticate on stateful domains.

| Ability | Routes |
|---|---|
| `pixelkraft:sites:read` | `GET /api/v1/sites`, `/sites/{site}`, pages, deploys, analytics, releases, git-operations |
| `pixelkraft:sites:sync` | `POST /api/v1/sites/{site}/sync` |
| `pixelkraft:sites:deploy` | `POST /api/v1/sites/{site}/deploy` |
| `pixelkraft:sites:rollback` | `POST /api/v1/sites/{site}/rollback/{logId}` |
| `pixelkraft:notifications:read` | `GET /api/v1/notifications` |
| `pixelkraft:notifications:write` | `POST /api/v1/notifications/{id}/read` |

### `/api/inbox/{slug}` — Inbound Relay

POST JSON with `subject` and `body` (plus optional `from_email`, `from_name`, `to_email`). When `INBOX_INBOUND_REQUIRE_SECRET=true`, authenticate with the site's encrypted per-site secret or the global `INBOX_INBOUND_SECRET`.

### `/api/forms/{slug}` — Public Contact Forms

Anonymous form submissions from marketing pages. Rate-limited to 10 requests/min per IP. Honeypot field `_hp` silently discards spam. Allowed fields: `email`, `name`, `first_name`, `last_name`, `phone`, `company`, `subject`, `message`, and more (see `config/pixelkraft.php`).

---

## Configuration Reference

### `config/pixelkraft.php`

| Key | Default | Description |
|---|---|---|
| `repos_path` | `storage/repos` | Cloned repository root |
| `sites_deploy_path` | `/var/www/sites` | Built site output root |
| `nginx_sites_path` | `/etc/nginx/sites-available` | Nginx vhost directory |
| `github_webhook_secret` | — | HMAC secret for webhook verification |
| `github_webhook_require_signature` | `true` | Enforce signature verification |
| `deploy.build_timeout_seconds` | `300` | Max build duration |
| `deploy.rollback_snapshots` | `10` | Rollback points to retain |
| `monitoring.uptime_interval_minutes` | `5` | Uptime check frequency |
| `monitoring.webhook_deliveries_retention_days` | `30` | Webhook delivery log retention |
| `runtime.port_start` | `4100` | First port for Node.js runtime sites |
| `runtime.startup_timeout_seconds` | `30` | Node.js server startup wait |
| `form_submission_allowed_fields` | Built-in | Per-form field allowlist for `/api/forms` |

---

## Security Checklist

Before going live:

- [ ] `APP_DEBUG=false` and `APP_ENV=production`
- [ ] `GITHUB_WEBHOOK_SECRET` set (`openssl rand -hex 32`) and `GITHUB_WEBHOOK_REQUIRE_SIGNATURE=true`
- [ ] `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`
- [ ] Strong `APP_KEY` (`php artisan key:generate`)
- [ ] Sanctum tokens use **narrow abilities** (avoid `*` in production)
- [ ] `/horizon` protected — admin-only by default via `HorizonServiceProvider` gate
- [ ] Nginx TLS via Let's Encrypt / Certbot
- [ ] `storage/` and `bootstrap/cache/` writable by `www-data` only
- [ ] PHP `expose_php = Off`
- [ ] MariaDB user: no `SUPER`; only `SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER`
- [ ] Redis bound to `127.0.0.1` only
- [ ] SSH key-based auth only; firewall allows only 80, 443, SSH

---

## Development

```bash
# Run all tests
php artisan test

# Static analysis
composer phpstan

# Code style
vendor/bin/pint

# Inspect failed jobs
php artisan horizon:list-failed

# Re-queue stalled webhook deliveries
php artisan pixelkraft:replay-webhooks --since="2 hours ago"

# Prune old webhook delivery logs
php artisan pixelkraft:prune-webhooks --days=30

# Clear all caches
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan view:clear
```

### Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Run the test suite: `php artisan test` (184 tests, 0 failures)
4. Run static analysis: `composer phpstan`
5. Run code style: `vendor/bin/pint`
6. Open a pull request

---

## License

Proprietary software. All rights reserved.

---

<div align="center">

**Built with [Laravel](https://laravel.com) · [React](https://react.dev) · [Inertia.js](https://inertiajs.com) · [Livewire](https://livewire.laravel.com) · [Tailwind CSS](https://tailwindcss.com)**

*pixelkraft — Artisanal Git-to-Render, at scale.*

</div>
