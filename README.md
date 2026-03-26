# pixelkraft

A self-hosted site operations platform for managing, editing, deploying, and monitoring AI-generated websites from a single dashboard.

## What is pixelkraft?

If you build websites with AI tools and manage multiple sites, pixelkraft gives you one place to control everything: edit content visually, push changes to GitHub, deploy to your VPS, monitor uptime, track SEO, handle contact forms, and send newsletters — across 10, 20, or 25+ sites.

## Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 11 (PHP 8.3) |
| Frontend | Livewire 3 + Alpine.js + Tailwind CSS |
| UI Components | Livewire Flux (official Livewire UI kit) |
| Database | MariaDB |
| Cache / Queue / Sessions | Redis |
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

## Build Phases

| Phase | Focus | Status |
|-------|-------|--------|
| 1 | Foundation + Auth + GitHub Sync | ✅ Complete |
| 2 | Multi-Strategy Parser + Region Detection | ✅ Complete |
| 3 | Visual Editor + Content Management | ✅ Complete |
| 4 | Deploy Pipeline + Domain/SSL Management | ✅ Complete |
| 5 | SEO + Analytics + Monitoring | ✅ Complete |
| 6 | Email + Operations + Polish | ✅ Complete |

### Phase 1 Progress

- [x] 1.1 — Laravel scaffold + config (MariaDB, Redis, Horizon, Tailwind, Livewire)
- [x] 1.2 — Database migrations (15 tables)
- [x] 1.3 — Eloquent models (16 models with relationships)
- [x] 1.4 — Auth with 2FA (Fortify views + routing)
- [x] 1.5 — Dashboard layout + Site CRUD + Settings
- [x] 1.7 — GitSyncService + ProjectDetector
- [x] 1.8 — Webhook receiver + Public API + Notifications + Scheduled tasks

### Phase 2 Progress

- [x] 2.1 — ParserService orchestrator + StaticHtmlParser (DomCrawler)
- [x] 2.2 — RegionDetector with heuristic scoring + cross-page refinement
- [x] 2.3 — SsgOutputParser (Hugo, Astro, 11ty source-to-output mapping)
- [x] 2.4 — SpaComponentParser (React JSX, Vue SFC, Svelte, Astro)
- [x] 2.5 — ContentPatcher (edit mapping back to source files)

### Phase 3 Progress

- [x] 3.1 — RegionPanel (review/confirm auto-detected regions with filtering + confidence bars)
- [x] 3.2 — VisualEditor (iframe + injected overlay script + inline editing)
- [x] 3.3 — Code editor mode (toggle between visual and code view)
- [x] 3.4 — Editor toolbar (save → patch → commit → push flow with modal)
- [x] 3.5 — BlogEditor (structured fields: title, body, tags, SEO, scheduling, templates)
- [x] 3.6 — ProductEditor (name, price, images, attributes, output path)
- [x] 3.7 — TemplateManager (create/edit reusable templates with {{placeholders}})

### Phase 4 Progress

- [x] 4.1 — DeployService (full pipeline: pull → install deps → build → optimize → deploy → nginx reload)
- [x] 4.2 — NginxConfigService (vhost generation, symlinks, staging configs, security headers, gzip, WebP)
- [x] 4.3 — SslService (Certbot provisioning, expiry checking, renewal)
- [x] 4.4 — ImageOptimizer (jpegoptim, pngquant, svgo, WebP generation via cwebp)
- [x] 4.5 — HtmlMinifier (HTML/CSS/JS minification, lazy loading injection)
- [x] 4.6 — DeployControls Livewire (deploy button, domain setup, log viewer, rollback)
- [x] 4.7 — Artisan commands (check-uptime, check-ssl, backup-database, publish-scheduled)
- [x] 4.8 — Deploy + rollback API endpoints

### Phase 5 Progress

- [x] 5.1 — SeoAnalyzer (score computation with per-check breakdown + actionable suggestions)
- [x] 5.2 — MetaEditor Livewire (title, description, keywords, OG tags, canonical + Google/social preview)
- [x] 5.3 — SchemaEditor (JSON-LD editor with presets: Article, Product, LocalBusiness, FAQ)
- [x] 5.4 — RobotsTxtEditor (presets: allow all, block AI bots, block all)
- [x] 5.5 — RedirectManager (301/302 CRUD, auto-regenerates Nginx config on change)
- [x] 5.6 — SitemapGenerator (XML sitemap from pages + blog posts, writes to deploy + repo)
- [x] 5.7 — AnalyticsAggregator (Cloudflare GraphQL API sync, GA4 placeholder, site/page stats)
- [x] 5.8 — UnifiedDashboard Livewire (global + per-site views, uptime bars, traffic chart, top pages)
- [x] 5.9 — BrokenLinkCrawler (internal 404s, external checks, redirect chain detection)
- [x] 5.10 — RunLighthouse command (Lighthouse CLI + HTTP timing fallback)
- [x] 5.11 — SyncAnalytics + CrawlLinks artisan commands

### Phase 6 Progress

- [x] 6.1 — FormInbox Livewire (view/read/spam/delete submissions, per-site filter, integration docs)
- [x] 6.2 — SubscriberList Livewire (add/unsubscribe/delete, search, stats)
- [x] 6.3 — CampaignEditor Livewire (compose, schedule, send newsletters)
- [x] 6.4 — SendCampaigns command (Resend API integration, personalization, rate limiting)
- [x] 6.5 — FileManager Livewire (browse, view, edit, upload, delete repo files)
- [x] 6.6 — All sidebar links wired (Analytics, Uptime, Inbox, Subscribers, Newsletters)
- [x] 6.7 — All routes finalized (45+ routes covering every feature)
- [x] 6.8 — Full UI redesign with Livewire Flux (500+ component usages, zero custom CSS classes)

## Requirements

- PHP 8.3+
- MariaDB 10.11+
- Redis 7+
- Node.js 20 LTS
- Nginx
- Chromium (for Browsershot)
- Certbot (for SSL)
- Supervisor (for queue workers)

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

## License

Private — not open source.
