<div align="center">

# ⚡ pixelkraft

**The Git-to-Render Site Operations Platform**

*Push to Git. Pixelkraft handles everything else.*

[![PHP](https://img.shields.io/badge/PHP-8.3+-8892BF?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12%2F13-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-v4-FB70A9?style=flat-square)](https://livewire.laravel.com)
[![Redis](https://img.shields.io/badge/Redis-7-DC382D?style=flat-square&logo=redis&logoColor=white)](https://redis.io)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-v4-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![MariaDB](https://img.shields.io/badge/MariaDB-11-003545?style=flat-square&logo=mariadb&logoColor=white)](https://mariadb.org)

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

## Feature Set

### 🚀 Git-to-Render Pipeline
- GitHub webhook receiver with signature verification
- Support for **static HTML, PHP, React, Vue, Svelte, Astro, Next.js, Nuxt, Hugo, Eleventy**
- Automatic package manager detection (npm / pnpm / yarn / bun)
- Build pipeline with timeout protection and output capture
- Git conflict detection and smart rebase recovery
- One-click rollback to any previous deploy (snapshot tags)

### 🎨 Visual Content Editor
- WYSIWYG editing directly on rendered pages in an iframe
- CSS-selector-based and marker-based region detection
- Patch-to-source: edits flow back to the actual HTML/JSX/Markdown files
- Commit-on-save with full audit trail (GitOperation log)
- Live source fallback preview for non-built framework files

### 📊 Observability & Monitoring
- Built-in uptime checks with P95 response times
- Google Analytics v4 integration (organic traffic sync)
- Broken link crawler
- SEO issue tracker with per-page scoring
- Deploy log with step-by-step millisecond timing

### 💼 Agency Management Layer
- Per-site client profiles (contact, company, billing)
- Invoice management with PDF generation
- Expense tracker per site
- Newsletter subscriber management
- Contact form submission inbox
- Site-scoped notifications

### 🔒 Security Architecture
- All credentials encrypted at rest (`github_token`, `cf_api_token`, `smtp_password`, `ftp_ssh_password`)
- HMAC-SHA256 webhook signature verification (constant-time `hash_equals`)
- Role-based access control (admin / user)
- Laravel Sanctum API tokens + Fortify 2FA
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
composer run setup   # Installs deps, sets up .env, migrates DB, builds assets
```

The `setup` script handles:
- `composer install`
- `.env` creation from `.env.example`
- App key generation
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

# Redis (REQUIRED for production — do not use file/database drivers in prod)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# GitHub Webhook (REQUIRED — never leave blank in production)
GITHUB_WEBHOOK_SECRET=<cryptographically-random-secret>
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

This starts concurrently:
- `php artisan serve` — Laravel HTTP
- `php artisan queue:listen --tries=1 --timeout=0` — Queue worker
- `php artisan pail --timeout=0` — Real-time logs
- `npm run dev` — Vite HMR

### 4. Register the First User

```bash
php artisan tinker
>>> \App\Models\User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('secure-password'), 'role' => 'admin']);
```

---

## Production Deployment

### Supervisor Configuration (Horizon)

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
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start pixelkraft-horizon
```

### Nginx — Dashboard Vhost

```nginx
server {
    listen 443 ssl http2;
    server_name dashboard.pixelkraft.io;
    root /var/www/pixelkraft/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/dashboard.pixelkraft.io/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/dashboard.pixelkraft.io/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src * data: blob:; font-src 'self' https://fonts.bunny.net;" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

### Cron (Scheduler)

```bash
* * * * * www-data php /var/www/pixelkraft/artisan schedule:run >> /dev/null 2>&1
```

### Zero-Downtime Deploy Script

```bash
#!/bin/bash
set -e

APP_DIR=/var/www/pixelkraft

cd $APP_DIR
git pull origin main

composer install --no-dev --no-interaction --optimize-autoloader

npm ci
npm run build

php artisan migrate --force --graceful

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

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
│  - Signature verification       - Duplicate check               │
│  - Branch filtering             - Delivery recording            │
└─────────────────────────┬───────────────────────────────────────┘
                          │ dispatch
                          ▼
┌──────────────────── git QUEUE ──────────────────────────────────┐
│  SyncFromWebhookJob                                             │
│  - GitSyncService::pull()   (with Redis distributed lock)       │
│  - Conflict detection       - Rebase recovery                   │
└──────┬──────────────────────────────────────────────────────────┘
       │ dispatch
       ├─────────────────────────────────────────────┐
       ▼                                             ▼
┌── parsing QUEUE ──┐                    ┌── deploy QUEUE ──────────┐
│  ParseSiteJob     │                    │  DeploySiteJob + chain:  │
│  - Page discovery │                    │  ProvisionEnvironmentJob  │
│  - Metadata index │                    │  BuildSiteJob            │
│  - SEO analysis   │                    │  InjectTrackingJob       │
│  - Region detect  │                    │  ActivateReleaseJob      │
└───────────────────┘                    └──────────────────────────┘
```

### Queue Architecture

| Queue | Supervisor | Workers | Timeout | Purpose |
|---|---|---|---|---|
| `default` | supervisor-default | 3 | 120s | Emails, notifications |
| `git` | supervisor-git | 2 | 300s | Clone, pull, push, tag |
| `parsing` | supervisor-parsing | 2 | 600s | Page discovery, SEO, regions |
| `deploy` | supervisor-deploy | 2 | 600s | Full build + activation |
| `monitoring` | supervisor-monitoring | 3 | 300s | Uptime, crawl, analytics |

### Supported Project Types

| Type | Build | Parser | Runtime |
|---|---|---|---|
| `static_html` | None | StaticHtmlParser | Nginx file serve |
| `php_site` | None | RenderedPhpParser | PHP-FPM |
| `react` | npm run build | SpaComponentParser | Static or runtime |
| `vue` | npm run build | SpaComponentParser | Static or runtime |
| `svelte` | npm run build | SpaComponentParser | Static |
| `astro` | npm run build | SsgOutputParser | Static |
| `nextjs` | npm run build | SpaComponentParser | Node.js runtime |
| `nuxt` | npm run build | SpaComponentParser | Node.js runtime |
| `hugo` | hugo | SsgOutputParser | Static |
| `eleventy` | npx @11ty/eleventy | SsgOutputParser | Static |
| `custom` | Configurable | StaticHtmlParser | Configurable |

---

## Public contact forms API

Sites can accept **anonymous** form posts from the marketing site or static pages.

| Item | Detail |
|---|---|
| **Endpoint** | `POST /api/forms/{slug}` where `{slug}` is the site’s `slug` (active sites only) |
| **Rate limit** | 10 requests per minute per client IP and slug (returns `429` when exceeded) |
| **Anti-spam** | Optional honeypot field **`_hp`** — if it has any value, the submission is stored as spam and skipped for inbox/notifications |
| **Form name** | Optional **`_form_name`** (default `contact`) — stored on the submission record |

### Allowed fields (allowlisted)

Only these JSON keys are validated and stored on `form_submissions.data`. Any other keys are ignored.

| Field | Notes |
|---|---|
| `email` | Valid email, optional |
| `name` | Display name; optional if `first_name` / `last_name` used |
| `first_name`, `last_name` | Combined for inbox **From** name when `name` is empty |
| `phone` | Short string |
| `company`, `department` | Short strings |
| `website`, `url` | Optional URLs or site strings (max 500 chars) |
| `to_email` | Optional routed destination; stored on inbox row only |
| `subject`, `title`, `topic` | First non-empty is used as the inbox **subject** |
| `message`, `body`, `content`, `inquiry`, `comments`, `details` | First non-empty is used as the inbox **body**; all are scanned for basic spam patterns |

### Spam handling

Submissions matching simple patterns (e.g. obvious spam phrases or many URLs in the combined text) are marked **`is_spam`** and do not create inbox messages or notifications.

---

## Configuration Reference

### `config/pixelkraft.php`

| Key | Default | Description |
|---|---|---|
| `repos_path` | `storage/repos` | Where cloned repositories are stored |
| `sites_deploy_path` | `/var/www/sites` | Where built sites are deployed |
| `nginx_sites_path` | `/etc/nginx/sites-available` | Nginx vhost config directory |
| `github_webhook_secret` | — | HMAC secret for webhook verification |
| `github_webhook_require_signature` | `true` | Enforce signature verification |
| `deploy.build_timeout_seconds` | `300` | Max build command duration |
| `deploy.rollback_snapshots` | `10` | How many rollback points to keep |
| `monitoring.uptime_interval_minutes` | `5` | Uptime check frequency |
| `monitoring.webhook_deliveries_retention_days` | `30` | `WEBHOOK_DELIVERIES_RETENTION_DAYS` — age of `webhook_deliveries` rows kept before prune |
| `runtime.port_start` | `4100` | First port for runtime Node.js sites |
| `runtime.startup_timeout_seconds` | `30` | Wait time for Node.js server to start |

---

## Security Checklist for Production

Before going live, verify:

- [ ] `APP_DEBUG=false` in production `.env`
- [ ] `APP_ENV=production` in production `.env`
- [ ] `GITHUB_WEBHOOK_SECRET` is set and cryptographically random (`openssl rand -hex 32`)
- [ ] `GITHUB_WEBHOOK_REQUIRE_SIGNATURE=true`
- [ ] `CACHE_STORE=redis` (never `file` or `database` in production)
- [ ] `SESSION_DRIVER=redis`
- [ ] `QUEUE_CONNECTION=redis`
- [ ] All encrypted fields use a strong `APP_KEY` (`php artisan key:generate`)
- [ ] `/horizon` dashboard is protected (`web` + `auth` middleware; **admin-only** outside `local` — `HorizonServiceProvider` gate; in `local`, Horizon allows access for developer convenience)
- [ ] Nginx TLS configured with certificate via Let's Encrypt / Certbot
- [ ] `storage/` and `bootstrap/cache/` are writable by `www-data` only
- [ ] PHP `expose_php = Off` in `php.ini`
- [ ] MariaDB user has no `SUPER` privileges; only `SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER` on the pixelkraft DB
- [ ] Redis is not publicly accessible (bind `127.0.0.1` only)
- [ ] SSH access uses key-based authentication only; no password auth
- [ ] Firewall: only ports 80, 443, and your SSH port are open

---

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Run the full test suite: `composer test`
4. Run static analysis: `vendor/bin/phpstan analyse`
5. Run code style: `vendor/bin/pint`
6. Open a pull request

### Development Utilities

```bash
# Run all tests
php artisan test --parallel

# Static analysis (requires phpstan/phpstan)
vendor/bin/phpstan analyse

# Code style (Pint)
vendor/bin/pint

# Inspect failed jobs
php artisan horizon:list-failed

# Re-queue stalled webhook deliveries
php artisan pixelkraft:replay-webhooks --since="2 hours ago"

# Prune old webhook deliveries
php artisan pixelkraft:prune-webhooks --days=30

# Clear all application caches
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan view:clear
```

---

## License

This project is proprietary software. All rights reserved.

---

<div align="center">

**Built with ❤️ using [Laravel](https://laravel.com) · [Livewire](https://livewire.laravel.com) · [Tailwind CSS](https://tailwindcss.com)**

*pixelkraft — Artisanal Git-to-Render, at scale.*

</div>
