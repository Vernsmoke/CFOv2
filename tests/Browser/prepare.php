<?php
// Isolated browser fixtures only. This script refuses to touch the application database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.browser');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite' || ! str_ends_with(config('database.connections.sqlite.database'), 'browser.sqlite')) {
    throw new RuntimeException('Browser fixtures require the isolated testing SQLite database.');
}
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
foreach (['Admin', 'Student'] as $role) {
    App\Models\User::firstOrCreate(['email' => strtolower($role).'@browser.test'], ['name' => 'Browser '.$role, 'first_name' => 'Browser', 'last_name' => $role, 'student_id' => $role === 'Student' ? 'BROWSER-001' : null, 'system_username' => strtolower($role).'@cfo.office.com', 'role' => $role, 'status' => 'Approved', 'password' => 'BrowserTest!2026']);
}
echo "Isolated browser database ready.\n";
