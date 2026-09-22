<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'build/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [
        '#\Ahttps?://(localhost|127\.0\.0\.1|\[::1\])(:\d+)?\z#',
        '#\Ahttps?://(10|127|192\.168|169\.254)\.\d{1,3}\.\d{1,3}\.\d{1,3}(:\d+)?\z#',
        '#\Ahttps?://172\.(1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3}(:\d+)?\z#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
