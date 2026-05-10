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
    | Registration
    |--------------------------------------------------------------------------
    |
    | Open user registration is disabled by default for self-hosted deployments.
    | Enable only when you need to onboard new users (e.g., during initial setup).
    | Set REGISTRATION_ENABLED=true in .env to allow sign-ups.
    |
    */
    'registration_enabled' => env('REGISTRATION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Two-factor authentication enforcement
    |--------------------------------------------------------------------------
    |
    | When true, admin users without a confirmed TOTP device are redirected to
    | the settings page before they can access the dashboard.  Defaults to true
    | in production, false elsewhere so development and test environments are
    | not blocked.  Set ENFORCE_2FA=true to enable in any environment.
    |
    */
    'enforce_two_factor' => env('ENFORCE_2FA', env('APP_ENV') === 'production'),

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
        'bucket' => env('R2_BUCKET', 'platform-media'),
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
    | from a local Node process that platform starts and health-checks.
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
        // STRONGLY RECOMMENDED in production: set SITE_RUNTIME_SUPERVISOR_ENABLED=true
        // so platform writes a Supervisor .conf on every runtime deploy and Node.js
        // processes (Next.js, Nuxt) survive server reboots automatically.
        // Without this, runtime sites return 502 after every reboot until redeployed.
        // Requires supervisord + the web-server user to have write access to
        // SUPERVISOR_CONF_PATH (default: /etc/supervisor/conf.d/).
        'supervisor_enabled' => env('SITE_RUNTIME_SUPERVISOR_ENABLED', false),
        'supervisor_conf_path' => env('SUPERVISOR_CONF_PATH', '/etc/supervisor/conf.d'),
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
         * Both tables are pruned by `platform:prune-monitoring` (runs weekly).
         */
        'uptime_retention_days' => (int) env('UPTIME_RETENTION_DAYS', 30),
        'events_retention_days' => (int) env('EVENTS_RETENTION_DAYS', 90),
        /**
         * Webhook delivery audit rows (pruned weekly by `platform:prune-webhooks`).
         */
        'webhook_deliveries_retention_days' => (int) env('WEBHOOK_DELIVERIES_RETENTION_DAYS', 30),

        /**
         * Closed edit sessions, content revisions, git operations, read notifications,
         * and snapshot-less deploy logs (all pruned weekly by `platform:prune-monitoring`).
         */
        // ?: rather than env(VAR, default) so an empty string in .env falls back to the default.
        'sessions_retention_days' => (int) (env('SESSIONS_RETENTION_DAYS') ?: 60),
        'revisions_retention_days' => (int) (env('REVISIONS_RETENTION_DAYS') ?: 90),
        'git_ops_retention_days' => (int) (env('GIT_OPS_RETENTION_DAYS') ?: 60),
        'notifications_retention_days' => (int) (env('NOTIFICATIONS_RETENTION_DAYS') ?: 30),
        'deploy_logs_retention_days' => (int) (env('DEPLOY_LOGS_RETENTION_DAYS') ?: 90),
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
    // Store the service account JSON in storage/app/private/ so it is never
    // web-accessible and is excluded from the default R2 backup path.
    // Add `location ~* \.json$ { deny all; }` to Nginx as a defence-in-depth measure.
    'google_analytics_credentials_path' => env('GOOGLE_ANALYTICS_CREDENTIALS_PATH', storage_path('app/private/google-credentials.json')),

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
    | POST /api/inbox/{site-slug} with JSON body. When a site has
    | inbox_inbound_secret set (encrypted), Bearer must match that value;
    | otherwise the global secret below is used. Leave empty in local dev to
    | rely on throttle only when INBOX_INBOUND_REQUIRE_SECRET is false.
    |
    */
    'inbox_inbound_secret' => env('INBOX_INBOUND_SECRET'),
    'inbox_inbound_require_secret' => env('INBOX_INBOUND_REQUIRE_SECRET', env('APP_ENV', 'production') !== 'local'),

    /*
    |--------------------------------------------------------------------------
    | Allowed Git Hosts
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of additional git hosts beyond the built-in defaults
    | (github.com, gitlab.com, bitbucket.org). Used by the GitRemoteUrl
    | validation rule. Example: "github.mycompany.com,git.example.com"
    |
    */
    'allowed_git_hosts' => array_filter(array_map(
        'trim',
        explode(',', (string) env('ALLOWED_GIT_HOSTS', ''))
    )),

    /*
    |--------------------------------------------------------------------------
    | Public form submissions (/api/forms/{slug})
    |--------------------------------------------------------------------------
    |
    | Keys under '*' are the global maximum stored on form_submissions.data
    | (after validation). Optional entries keyed by _form_name restrict to a
    | subset of '*' for that form. Unknown form names use '*' only. Values must
    | be a subset of the built-in field list; anything else is ignored. An
    | empty per-form list falls back to '*'.
    |
    */
    'form_submission_allowed_fields' => [
        '*' => [
            '_hp',
            'email',
            'name',
            'first_name',
            'last_name',
            'message',
            'body',
            'content',
            'inquiry',
            'subject',
            'title',
            'topic',
            'comments',
            'details',
            'phone',
            'company',
            'website',
            'url',
            'department',
            'to_email',
        ],
    ],

];
