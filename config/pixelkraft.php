<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Repository Storage
    |--------------------------------------------------------------------------
    |
    | Path where cloned site repositories are stored on the VPS.
    |
    */
    'repos_path' => env('REPOS_PATH', storage_path('repos')),

    /*
    |--------------------------------------------------------------------------
    | Sites Deploy Path
    |--------------------------------------------------------------------------
    |
    | Base path where built sites are deployed for Nginx to serve.
    |
    */
    'sites_deploy_path' => env('SITES_DEPLOY_PATH', '/var/www/sites'),

    /*
    |--------------------------------------------------------------------------
    | Nginx Configuration
    |--------------------------------------------------------------------------
    |
    | Path where generated Nginx vhost configs are written.
    |
    */
    'nginx_sites_path' => env('NGINX_SITES_PATH', '/etc/nginx/sites-available'),

    /*
    |--------------------------------------------------------------------------
    | GitHub
    |--------------------------------------------------------------------------
    */
    'github_webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
    'github_webhook_require_signature' => env('GITHUB_WEBHOOK_REQUIRE_SIGNATURE', env('APP_ENV', 'production') !== 'local'),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare R2 (Media Storage)
    |--------------------------------------------------------------------------
    */
    'r2' => [
        'key' => env('R2_ACCESS_KEY_ID'),
        'secret' => env('R2_SECRET_ACCESS_KEY'),
        'bucket' => env('R2_BUCKET', 'pixelkraft-media'),
        'endpoint' => env('R2_ENDPOINT'),
        'url' => env('R2_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deploy Defaults
    |--------------------------------------------------------------------------
    */
    'deploy' => [
        'max_concurrent_builds' => 2,
        'build_timeout_seconds' => 300,
        'rollback_snapshots' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Runtime Sites
    |--------------------------------------------------------------------------
    |
    | Runtime-managed sites (for example non-exported Next.js apps) are served
    | from a local Node process that pixelkraft starts and health-checks.
    |
    */
    'runtime' => [
        'host' => env('SITE_RUNTIME_HOST', '127.0.0.1'),
        'port_start' => (int) env('SITE_RUNTIME_PORT_START', 4100),
        'port_span' => (int) env('SITE_RUNTIME_PORT_SPAN', 2000),
        'storage_path' => env('SITE_RUNTIME_STORAGE_PATH', storage_path('app/runtime-sites')),
        'pid_path' => env('SITE_RUNTIME_PID_PATH', storage_path('app/runtime-pids')),
        'log_path' => env('SITE_RUNTIME_LOG_PATH', storage_path('logs/runtime-sites')),
        'startup_timeout_seconds' => (int) env('SITE_RUNTIME_STARTUP_TIMEOUT_SECONDS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Defaults
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'uptime_interval_minutes' => 5,
        /** Response time above this (ms) while still HTTP-successful counts as degraded (yellow) on uptime charts */
        'uptime_degraded_after_ms' => (int) env('UPTIME_DEGRADED_AFTER_MS', 3000),
        'lighthouse_schedule' => 'weekly',
        'broken_links_schedule' => 'weekly',
        'analytics_sync_schedule' => 'daily',
        /**
         * Data retention windows for high-volume monitoring tables.
         * uptime_checks grows at up to ~288 rows/site/day; raw analytics events
         * are aggregated nightly into analytics_snapshots so can be pruned sooner.
         * Both tables are pruned by `pixelkraft:prune-monitoring` (runs weekly).
         */
        'uptime_retention_days' => (int) env('UPTIME_RETENTION_DAYS', 30),
        'events_retention_days' => (int) env('EVENTS_RETENTION_DAYS', 90),
        /**
         * Webhook delivery audit rows (pruned weekly by `pixelkraft:prune-webhooks`).
         */
        'webhook_deliveries_retention_days' => (int) env('WEBHOOK_DELIVERIES_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Analytics Data API (GA4)
    |--------------------------------------------------------------------------
    |
    | Service account JSON path. Grant the service account “Viewer” on the GA4
    | property (Admin → Property access management). The dashboard uses organic
    | search traffic only (session default channel group = Organic Search).
    |
    */
    'google_analytics_credentials_path' => env('GOOGLE_ANALYTICS_CREDENTIALS_PATH', storage_path('app/google-credentials.json')),

    /*
    |--------------------------------------------------------------------------
    | PageSpeed Insights API
    |--------------------------------------------------------------------------
    |
    | Optional Google Cloud API key for the PageSpeed Insights v5 API.
    | Without a key the API still works but is rate-limited to ~25 req/100s.
    | Create a key at console.cloud.google.com → APIs & Services → Credentials
    | and enable the "PageSpeed Insights API".
    |
    */
    'psi_api_key' => env('PSI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare API
    |--------------------------------------------------------------------------
    */
    'cloudflare_api_token' => env('CLOUDFLARE_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Backup
    |--------------------------------------------------------------------------
    */
    'backup' => [
        'disk' => env('BACKUP_DISK', 'r2'),
        'retention_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Project Types
    |--------------------------------------------------------------------------
    */
    'project_types' => [
        'static_html',
        'php_site',
        'react',
        'vue',
        'svelte',
        'astro',
        'hugo',
        'eleventy',
        'nextjs',
        'nuxt',
        'custom',
    ],

    /*
    |--------------------------------------------------------------------------
    | Project inbox (inbound webhook)
    |--------------------------------------------------------------------------
    |
    | POST /api/inbox/{site-slug} with JSON body. When set, requests must send
    | Authorization: Bearer {secret}. Leave empty in local dev to rely on throttle only.
    |
    */
    'inbox_inbound_secret' => env('INBOX_INBOUND_SECRET'),
    'inbox_inbound_require_secret' => env('INBOX_INBOUND_REQUIRE_SECRET', env('APP_ENV', 'production') !== 'local'),

];
