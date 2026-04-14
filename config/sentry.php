<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sentry DSN
    |--------------------------------------------------------------------------
    |
    | The Data Source Name that tells the Sentry SDK where to send events.
    | Find it in your Sentry project under Settings → Client Keys (DSN).
    | Leave empty to disable Sentry reporting (e.g. in local development).
    |
    */

    'dsn' => env('SENTRY_DSN'),

    /*
    |--------------------------------------------------------------------------
    | Tracing / Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | traces_sample_rate: 0.0 = off, 1.0 = 100% of requests traced.
    | For production start at 0.05–0.1 (5–10%) to stay within the free quota.
    |
    */

    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.05),

    /*
    |--------------------------------------------------------------------------
    | Release Tracking
    |--------------------------------------------------------------------------
    |
    | Tag events with the current Git commit SHA so you can correlate errors
    | to specific deploys in the Sentry UI.
    |
    */

    'release' => env('SENTRY_RELEASE', env('APP_VERSION')),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    */

    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    /*
    |--------------------------------------------------------------------------
    | Breadcrumbs
    |--------------------------------------------------------------------------
    */

    'breadcrumbs' => [
        'logs'            => true,
        'cache'           => true,
        'livewire'        => true,
        'sql_queries'     => true,
        'sql_bindings'    => false, // keep false — bindings may contain PII
        'queue_info'      => true,
        'command_info'    => true,
        'http_client_requests' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Send Default PII
    |--------------------------------------------------------------------------
    |
    | Disabled by default to avoid accidentally leaking user email / IP.
    | Enable only if you have explicit user consent or a legitimate interest
    | basis under GDPR.
    |
    */

    'send_default_pii' => false,

];
