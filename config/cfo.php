<?php

return [
    'admin' => [
        'email' => env('CFO_DEFAULT_ADMIN_EMAIL'),
        'password' => env('CFO_DEFAULT_ADMIN_PASSWORD'),
    ],
    'live_transport' => env('CFO_LIVE_TRANSPORT', 'sse') === 'polling' ? 'polling' : 'sse',
];
