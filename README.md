# pixelkraft

A self-hosted site operations platform for managing, editing, deploying, and monitoring AI-generated websites from a single dashboard.

## What is pixelkraft?

If you build websites with AI tools and manage multiple sites, pixelkraft gives you one place to control everything: edit content visually, push changes to GitHub, deploy to your VPS, monitor uptime, track SEO, handle contact forms, and send newsletters — across 10, 20, or 25+ sites.

## Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 11 (PHP 8.3) |
| Frontend | Livewire 3 + Alpine.js + Tailwind CSS v4 |
| UI Components | Livewire Flux v2 (official Livewire UI kit) |
| Database | MariaDB |
| Cache / Queue / Sessions | Redis (Valkey on AlmaLinux 10) |
| Queue Dashboard | Laravel Horizon |
| Media Storage | Cloudflare R2 (S3-compatible) |
| Email | Resend |
| Auth | Laravel Fortify (email/password + TOTP 2FA) |
| API Tokens | Laravel Sanctum |
| Headless Browser | Spatie Browsershot (Puppeteer) |
| Web Server | Nginx |
| OS | AlmaLinux 10 |

## Features

**Core**
- GitHub two-way sync (clone, pull, push, webhook listener)
- Multi-strategy parser — handles static HTML, React, Vue, Svelte, Astro, Hugo, 11ty
- Hybrid content detection (auto-detect + marker confirmation)
- Visual page editor (click on elements) + code view toggle
- Structured blog editor and product listing editor
- Content templates and global components
- Per-site configuration for heterogeneous projects

**Deployment**
- End-to-end pipeline: edit → commit → build → optimize → deploy
- Domain, SSL (Let's Encrypt), and Nginx vhost management
- Staging preview before going live
- Rollback to previous deploys

**SEO**
- Meta editor (title, description, keywords)
- Open Graph / social sharing tags
- JSON-LD structured data (Schema.org)
- robots.txt editor, canonical URLs, 301 redirect manager
- Auto-generated XML sitemaps

**Analytics & Monitoring**
- Google Analytics + Cloudflare Analytics unified dashboard
- Custom event tracking
- Google Search Console integration (keywords, indexing)
- Weekly Lighthouse audits with actionable suggestions
- Uptime monitoring (5-min intervals)
- Broken link checker

**Email**
- Contact form API endpoint for any site
- Newsletter system with templates, scheduling, and basic segmentation
- Powered by Resend

**Performance**
- Image optimization + WebP conversion on deploy
- Lazy loading injection
- HTML/CSS/JS minification

**Operations**
- Deploy and error logs in dashboard
- Public REST API with Sanctum tokens
- Automated daily database backups to R2
- Discord + in-dashboard notifications

**Auth**
- Email/password + TOTP two-factor authentication
- Role-based access (admin/editor) for future team support
- API token management

## Architecture

See [ARCHITECTURE.html](ARCHITECTURE.html) for the full blueprint — open it in a browser for a formatted, interactive view covering all 20 sections: tech stack, database schema, multi-strategy parser design, visual editor architecture, deployment pipeline, and 6 build phases.

## Requirements

- PHP 8.3+
- MariaDB 10.11+
- Redis 7+ (or Valkey on AlmaLinux 10)
- Node.js 20+
- Nginx
- Composer 2.x
- Supervisor (for Horizon queue workers)
- Certbot (for SSL, optional)

## Setup (Development)

```bash
git clone https://github.com/agve95-hub/pixelkraft.git
cd pixelkraft
composer install
npm install
cp .env.example .env
php artisan key:generate

# Configure .env with your MariaDB, Redis, and service credentials

php artisan migrate
php artisan horizon  # Start queue workers
npm run dev          # Start Vite dev server
php artisan serve    # Start Laravel dev server
```

## Deployment (Production VPS)

### 1. Server Prerequisites

```bash
# PHP 8.3 + extensions
dnf install php php-fpm php-mysqlnd php-mbstring php-xml php-curl php-zip php-bcmath php-gd php-intl php-opcache php-sodium

# Redis-compatible cache (AlmaLinux 10 uses Valkey, not Redis)
dnf install valkey
systemctl enable --now valkey

# Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Supervisor for Horizon
dnf install supervisor
systemctl enable --now supervisord
```

### 2. Application Setup

```bash
cd /var/www/pixelkraft
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate

# Configure .env:
#   APP_URL=http://YOUR_SERVER_IP
#   DB_CONNECTION=mariadb
#   CACHE_STORE=redis / QUEUE_CONNECTION=redis / SESSION_DRIVER=redis

php artisan migrate --force
php artisan storage:link
npm install
npm run build
chown -R nginx:nginx /var/www/pixelkraft
```

### 3. Flux UI v2 — Critical CSS Setup

The CSS file **must** follow the [Flux v2 installation docs](https://fluxui.dev/docs/installation) exactly:

**`resources/css/app.css`:**
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

**`postcss.config.js`** — Must use the Tailwind v4 PostCSS plugin:
```js
export default {
    plugins: {
        '@tailwindcss/postcss': {},
    },
};
```

Install the PostCSS plugin:
```bash
npm install @tailwindcss/postcss
```

**Build validation:** After `npm run build`, the CSS should be ~249 kB. If it's ~13 kB, the Flux CSS import is missing.

### 4. Nginx Configuration

```nginx
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root /var/www/pixelkraft/public;
    index index.php;
    client_max_body_size 20M;

    # Required for Flux JS/CSS assets (per fluxui.dev/docs/installation)
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

### Flux v2 vs v1 Component Names

This project uses **Flux v2.13.1**. If you encounter broken UI, check that Blade templates use v2 syntax:

| Flux v1 (wrong) | Flux v2 (correct) |
|---|---|
| `flux:sidebar sticky stashable` | `flux:sidebar sticky collapsible="mobile"` |
| `flux:brand` | `flux:sidebar.brand` |
| `flux:navlist.item` | `flux:sidebar.item` |
| `flux:navlist.group` | `flux:sidebar.group` |
| `flux:spacer` (in sidebar) | `flux:sidebar.spacer` |
| `flux:profile` (in sidebar) | `flux:sidebar.profile` |

### AlmaLinux 10 Specifics

- **No `redis` package** — use `valkey` (Redis-compatible fork, drop-in replacement)
- **php-pecl-redis6** works with Valkey on the default port 6379

### Tailwind v4 Migration

- Uses `@import "tailwindcss"` (not `@tailwind base/components/utilities`)
- PostCSS plugin is `@tailwindcss/postcss` (not `tailwindcss`)
- Content scanning uses `@source` directives in CSS (not `content` array in config)

## Build Phases

| Phase | Focus | Status |
|-------|-------|--------|
| 1 | Foundation + Auth + GitHub Sync | ✅ Complete |
| 2 | Multi-Strategy Parser + Region Detection | ✅ Complete |
| 3 | Visual Editor + Content Management | ✅ Complete |
| 4 | Deploy Pipeline + Domain/SSL Management | ✅ Complete |
| 5 | SEO + Analytics + Monitoring | ✅ Complete |
| 6 | Email + Operations + Polish | ✅ Complete |

## License

Private — not open source.
