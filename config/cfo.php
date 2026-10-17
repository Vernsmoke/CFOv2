<?php

return [
    'admin' => [
        'email' => env('CFO_DEFAULT_ADMIN_EMAIL'),
        'password' => env('CFO_DEFAULT_ADMIN_PASSWORD'),
    ],
    'demo_accounts' => [
        'enabled' => env('CFO_DEMO_ACCOUNTS_ENABLED', false),
        'password' => env('CFO_DEMO_ACCOUNTS_PASSWORD', 'ChangeMe123!'),
    ],
    'live_transport' => env('CFO_LIVE_TRANSPORT', 'sse') === 'polling' ? 'polling' : 'sse',
];
