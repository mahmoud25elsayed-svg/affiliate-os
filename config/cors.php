<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')))),

    'allowed_origins_patterns' => [
        '#^https?://.*\.'.preg_quote((string) config('tenancy.central_domains.0', 'hostingersite.com'), '#').'$#',
        '#^https?://.*\.localhost$#',
        '#^https?://localhost(:\d+)?$#',
    ],

    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'X-Tenant', 'X-Health-Token', 'Accept'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
