<?php

return [
    'rp_id' => env('WEBAUTHN_RP_ID', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),
    'rp_name' => env('WEBAUTHN_RP_NAME', env('APP_NAME', 'MyChurchAdmin')),
    'origin' => env('WEBAUTHN_ORIGIN', env('APP_URL', 'http://localhost')),
    'timeout' => env('WEBAUTHN_TIMEOUT', 60000),
];
