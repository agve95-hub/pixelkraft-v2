<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Only the PUBLIC endpoints that are embedded in third-party websites need
    | wildcard CORS:
    |   - tracking collect  (analytics beacon from client sites)
    |   - form submissions  (contact forms embedded in client sites)
    |   - project inbox     (inbound POST from external clients)
    |
    | The authenticated /api/v1/* routes are called only from the same-origin
    | dashboard and do NOT need CORS; they are excluded from the paths below.
    | Webhook receivers are server-to-server and also excluded.
    |
    */

    'paths' => [
        'api/tracking/*/collect',
        'api/forms/*',
        'api/inbox/*',
        'api/sites/*/active-campaigns',   // consumed by client-side JS on managed sites
    ],

    /*
    | Allowed request methods. OPTIONS is required for CORS preflight.
    */
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    /*
    | Wildcard origin is intentional: these endpoints serve arbitrary
    | third-party websites and cannot enumerate all valid origins.
    | Credentials (cookies/auth headers) are NOT allowed with wildcard origin.
    */
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    /*
    | Only the headers that these endpoints actually need.
    */
    'allowed_headers' => ['Content-Type', 'Accept', 'X-Requested-With'],

    'exposed_headers' => [],

    /*
    | Cache the preflight response for 1 hour to reduce OPTIONS round-trips
    | from high-traffic client sites.
    */
    'max_age' => 3600,

    /*
    | Must stay false when allowed_origins is '*'. Browsers reject the
    | combination of wildcard origin + credentials.
    */
    'supports_credentials' => false,

];
