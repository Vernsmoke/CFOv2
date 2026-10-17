<?php

return [
    // Optional reachable origin for the student QR, independent of the admin URL.
    'url' => env('CFO_ACCESS_URL') ?: env('APP_URL'),
];
