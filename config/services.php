<?php
return [
    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'api_base' => env('TELEGRAM_API_BASE', 'https://api.telegram.org'),
    ],
    'max' => [
        'token' => env('MAX_BOT_TOKEN'),
    ],
    'sync' => [
        'token'       => env('SYNC_TOKEN'),
        'allowed_ips' => env('SYNC_ALLOWED_IPS', ''),
    ],
    'pbx' => [
        'token' => env('PBX_TOKEN'),
    ],
    'website_intake' => [
        'token'       => env('WEBSITE_INTAKE_TOKEN'),
        'allowed_ips' => env('WEBSITE_INTAKE_ALLOWED_IPS', ''),
    ],
    'speedtest_intake' => [
        'token'       => env('SPEEDTEST_INTAKE_TOKEN'),
        'allowed_ips' => env('SPEEDTEST_INTAKE_ALLOWED_IPS', ''),
    ],
];
