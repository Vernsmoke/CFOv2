<?php
// Loopback browser verification only. SSE is deliberately unavailable so the
// single-worker server exercises polling fallback without blocking requests.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/api/admin/events') { http_response_code(503); exit; }
$public = realpath(__DIR__.'/../../public');
$file = realpath($public.'/'.rawurldecode($path));
if ($file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') return false;
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.browser');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite' || ! str_ends_with(config('database.connections.sqlite.database'), 'browser.sqlite')) { http_response_code(503); exit; }
$app->handleRequest(Illuminate\Http\Request::capture());
