# pixelkraft

A self-hosted site operations platform for managing, editing, deploying, and monitoring Git-backed websites from one dashboard.

## What Is pixelkraft?

If you build sites with AI tools and manage more than a few repos, pixelkraft gives you one place to:

- sync with GitHub
- parse pages and editable regions
- edit content and code
- deploy sites to your VPS
- manage SEO, forms, and operations
- monitor logs, uptime, and site health

## Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 13 (PHP 8.3+) |
| Frontend | Livewire 4 + Alpine.js + Tailwind CSS v4 |
| UI Components | Livewire Flux v2 |
| Database | MariaDB |
| Cache / Queue / Sessions | Redis or Valkey |
| Queue Dashboard | Laravel Horizon |
| Media Storage | Cloudflare R2 |
| Email | Resend |
| Auth | Laravel Fortify |
| API Tokens | Laravel Sanctum |
| Web Server | Nginx |
| OS Target | AlmaLinux 10 |

## Features

**Core**

- GitHub two-way sync: clone, pull, commit, push
- Multi-strategy parser for static HTML, rendered SSG output, and component-based apps
- Region detection from rendered DOM output with manual confirmation controls
- Visual preview plus code editor with Git-backed save and push
- Blog, product, template, redirect, and file-management surfaces
- Per-site configuration for mixed project types

**Deployment**

- End-to-end pipeline: edit -> commit -> build -> deploy
- Static-site deploys
- Runtime-managed Next.js deploys behind Nginx
- Package-manager-aware builds for npm, pnpm, yarn, and bun
- Domain, SSL, and Nginx vhost management
- Deploy history and rollback support

**SEO**

- Meta editor
- Open Graph fields
- JSON-LD structured data editor
- robots.txt editor
- Canonical URLs
- Redirect manager
- Sitemap generation

**Analytics And Monitoring**

- **GA4 (organic / SEO)**: `pixelkraft:sync-analytics` pulls **Organic Search** traffic only (`sessionDefaultChannelGroup = Organic Search`) via the [Analytics Data API](https://developers.google.com/analytics/devguides/reporting/data/v1), matched to pages by `pagePath`. Requires a Google Cloud service account JSON and **Viewer** access on each GA4 property.
- Cloudflare analytics aggregation (fallback when GA4 organic rows are absent)
- Custom event tracking
- Scheduled HTTP uptime checks with optional **degraded** state when responses are slow but still successful
- Lighthouse and uptime monitoring hooks
- Broken-link checking
- **External uptime (free tiers)**: [UptimeRobot](https://uptimerobot.com/) (50 monitors) and [Better Stack Uptime](https://betterstack.com/uptime) (10 monitors) expose APIs and status badges; pixelkraft can keep using the built-in `pixelkraft:check-uptime` command, or you can mirror external results into `uptime_checks` with a small custom sync if you outgrow ping-from-one-server limits.

**Operations**

- Deploy logs and error logs in the dashboard
- Sanctum-backed API surface
- Notification hooks
- Queue diagnostics and stuck-job visibility

## Framework Support

pixelkraft does not treat every project type the same. The current support model is:

- `static_html`: direct HTML parsing, visual editing, and static deployment
- `hugo`, `eleventy`, static `astro`: parse rendered output and deploy static build artifacts
- `nextjs`: supports static export or a runtime-managed Node process behind Nginx
- `react`, `vue`, `svelte`, `nuxt`: parsing works best from rendered output or preview HTML when available

For component-based frameworks, the editor currently uses this safety model:

- preview is generated from rendered HTML or a local runtime server
- regions are detected from the rendered DOM
- code mode is the reliable editing path for component source files
- full direct visual source patching is still more limited than raw HTML editing

## Architecture

See [ARCHITECTURE.html](ARCHITECTURE.html) for the full blueprint. It covers the parser strategies, editor flow, deployment pipeline, database schema, and the intended phase breakdown.

## Requirements

- PHP 8.3+
- MariaDB 10.11+
- Redis 7+ or Valkey
- Node.js 20+
- Nginx
- Composer 2.x
- Supervisor
- Certbot for SSL automation, optional

## Setup (Development)

### Quick start

```bash
git clone https://github.com/agve95-hub/pixelkraft.git
cd pixelkraft
composer setup
```

`composer setup` installs PHP and Node dependencies, ensures `.env` exists, runs migrations, and builds assets.

### Run the development stack

```bash
composer dev
```

This starts Laravel, queue worker, logs (`pail`), and Vite in one command.

### Configure environment values

Set `.env` values for your database/cache/services before running full workflows:

- `DB_CONNECTION` + DB credentials (MariaDB recommended)
- `CACHE_STORE`, `QUEUE_CONNECTION`, and `SESSION_DRIVER` (`redis` / Valkey)
- Optional external services (GitHub, Cloudflare, GA4, Resend, R2)

If you prefer running services individually:

```bash
php artisan serve
php artisan queue:listen --tries=1 --timeout=0
php artisan pail --timeout=0
npm run dev
```

### Run tests

```bash
composer test
```

## Deployment (Production VPS)

### 1. Server prerequisites

```bash
dnf install php php-fpm php-mysqlnd php-mbstring php-xml php-curl php-zip php-bcmath php-gd php-intl php-opcache php-sodium
dnf install valkey
systemctl enable --now valkey

curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

dnf install supervisor
systemctl enable --now supervisord
```

### 2. Application setup

```bash
cd /var/www/pixelkraft
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate

# Configure .env:
# APP_URL=http://YOUR_SERVER_IP
# DB_CONNECTION=mariadb
# CACHE_STORE=redis
# QUEUE_CONNECTION=redis
# SESSION_DRIVER=redis

php artisan migrate --force
php artisan storage:link
npm install
npm run build
chown -R nginx:nginx /var/www/pixelkraft
```

After each pixelkraft application update:

```bash
php artisan optimize:clear
php artisan horizon:terminate
```

### 2b. Optional: GitHub Actions auto-deploy

This repository includes `.github/workflows/deploy-production.yml` to publish changes automatically when pushing to `main`.

Configure these GitHub repository secrets:

- `PROD_HOST` (example: `187.124.26.127`)
- `PROD_USER` (recommended non-root deploy user)
- `PROD_SSH_KEY` (private key for the deploy user)
- `PROD_APP_DIR` (example: `/var/www/pixelkraft`)

The workflow runs:

- `git pull --ff-only`
- `composer install --no-dev --optimize-autoloader`
- `php artisan migrate --force`
- `npm ci && npm run build`
- `php artisan optimize:clear`
- `php artisan horizon:terminate`

> Release policy: because deploy runs automatically from `main`, only merge to `main` when a release is ready for production.

### 3. Flux UI CSS setup

Follow the Flux v2 installation rules exactly.

`resources/css/app.css`

```css
@import 'tailwindcss';
@import '../../vendor/livewire/flux/dist/flux.css';

@custom-variant dark (&:where(.dark, .dark *));

@source "../../resources/views";
@source "../../vendor/livewire/flux/stubs";

@theme {
    --font-sans: 'Inter', sans-serif;
}
```

`postcss.config.js`

```js
export default {
    plugins: {
        '@tailwindcss/postcss': {},
    },
};
```

Install the Tailwind PostCSS plugin:

```bash
npm install @tailwindcss/postcss
```

### 4. Nginx configuration

```nginx
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root /var/www/pixelkraft/public;
    index index.php;
    client_max_body_size 20M;

    location ~* ^/flux/flux(\.min)?\.(js|css)$ {
        expires off;
        try_files $uri $uri/ /index.php?$query_string;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
```

### 5. Supervisor (Horizon)

Create `/etc/supervisord.d/horizon.ini`:

```ini
[program:horizon]
process_name=%(program_name)s
command=php /var/www/pixelkraft/artisan horizon
autostart=true
autorestart=true
user=nginx
redirect_stderr=true
stdout_logfile=/var/www/pixelkraft/storage/logs/horizon.log
stopwaitsecs=3600
```

### 6. Cron

```bash
* * * * * cd /var/www/pixelkraft && php artisan schedule:run >> /dev/null 2>&1
```

## Key Notes

### Managed site builds

- Frontend site builds install dev dependencies when the target framework needs them to compile successfully
- Runtime-managed Next.js sites run on an internal port and are proxied through Nginx
- Static optimization steps are skipped for runtime-managed output

### Flux v2 component names

This project uses Flux v2. If you hit broken UI after refactors, double-check Blade component names:

| Flux v1 (wrong) | Flux v2 (correct) |
|---|---|
| `flux:sidebar sticky stashable` | `flux:sidebar sticky collapsible="mobile"` |
| `flux:brand` | `flux:sidebar.brand` |
| `flux:navlist.item` | `flux:sidebar.item` |
| `flux:navlist.group` | `flux:sidebar.group` |
| `flux:spacer` | `flux:sidebar.spacer` |
| `flux:profile` | `flux:sidebar.profile` |

### AlmaLinux 10 specifics

- Use `valkey` instead of a `redis` package
- `php-pecl-redis6` works with Valkey on the default port `6379`

### Tailwind v4 migration

- Use `@import "tailwindcss"` instead of the old Tailwind layers
- Use `@tailwindcss/postcss` as the PostCSS plugin
- Use `@source` directives in CSS instead of an old `content` array

## Build Phases

| Phase | Focus | Status |
|-------|-------|--------|
| 1 | Foundation + Auth + GitHub Sync | Complete |
| 2 | Multi-Strategy Parser + Region Detection | Complete |
| 3 | Visual Editor + Content Management | Complete |
| 4 | Deploy Pipeline + Domain / SSL Management | Complete |
| 5 | SEO + Analytics + Monitoring | Complete |
| 6 | Email + Operations + Polish | Complete |

## License

Private - not open source.
