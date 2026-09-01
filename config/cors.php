<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configura CORS untuk aplikasi yang saat ini same-origin (Inertia.js).
    | Default ketat: tidak ada wildcard origin; hanya FRONTEND_URL yang
    | diperbolehkan. Path API dibatasi ke api/* & sanctum/csrf-cookie agar
    | scanner tidak flag missing config. supports_credentials = false untuk
    | minimalkan permukaan serangan hingga API publik diperlukan.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [env('FRONTEND_URL', env('APP_URL', 'http://localhost'))],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
