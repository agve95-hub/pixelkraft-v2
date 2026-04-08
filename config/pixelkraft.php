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
    'github_webhook_require_signature' => env('GITHUB_WEBHOOK_REQUIRE_SIGNATURE', ! app()->environment('local')),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare R2 (Media Storage)
    |--------------------------------------------------------------------------
    */
    'r2' => [
        'key'      => env('R2_ACCESS_KEY_ID'),
        'secret'   => env('R2_SECRET_ACCESS_KEY'),
        'bucket'   => env('R2_BUCKET', 'pixelkraft-media'),
        'endpoint' => env('R2_ENDPOINT'),
        'url'      => env('R2_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deploy Defaults
    |--------------------------------------------------------------------------
    */
    'deploy' => [
        'max_concurrent_builds' => 2,
        'build_timeout_seconds' => 300,
        'rollback_snapshots'    => 10,
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
        'host'                    => env('SITE_RUNTIME_HOST', '127.0.0.1'),
        'port_start'              => (int) env('SITE_RUNTIME_PORT_START', 4100),
        'port_span'               => (int) env('SITE_RUNTIME_PORT_SPAN', 2000),
        'storage_path'            => env('SITE_RUNTIME_STORAGE_PATH', storage_path('app/runtime-sites')),
        'pid_path'                => env('SITE_RUNTIME_PID_PATH', storage_path('app/runtime-pids')),
        'log_path'                => env('SITE_RUNTIME_LOG_PATH', storage_path('logs/runtime-sites')),
        'startup_timeout_seconds' => (int) env('SITE_RUNTIME_STARTUP_TIMEOUT_SECONDS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Defaults
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'uptime_interval_minutes'  => 5,
        'lighthouse_schedule'      => 'weekly',
        'broken_links_schedule'    => 'weekly',
        'analytics_sync_schedule'  => 'daily',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
    'discord_webhook_url' => env('DISCORD_WEBHOOK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Backup
    |--------------------------------------------------------------------------
    */
    'backup' => [
        'disk'           => env('BACKUP_DISK', 'r2'),
        'retention_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Project Types
    |--------------------------------------------------------------------------
    */
    'project_types' => [
        'static_html',
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

];
