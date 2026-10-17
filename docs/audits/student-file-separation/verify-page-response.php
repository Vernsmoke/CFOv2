<?php

// Read-only response comparison: unsaved user, array-backed session/cache, no HTTP middleware or database calls.
$base = dirname(__DIR__, 3);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['session.driver' => 'array', 'cache.default' => 'array']);
$session = $app->make('session')->driver();
$session->start();
$session->put('_token', str_repeat('a', 40));
$student = (new App\Models\User())->forceFill([
    'id' => 1, 'name' => 'Audit Student', 'email' => 'audit.student@example.test',
    'role' => 'Student', 'status' => 'Approved',
]);
$request = Illuminate\Http\Request::create('/student', 'GET');
$request->setLaravelSession($session);
$app->instance('request', $request);
$request->setUserResolver(fn () => $student);

foreach (['sse', 'polling'] as $transport) {
    config(['cfo.live_transport' => $transport]);
    $original = $app->make(App\Http\Controllers\PageController::class)->page($request);
    $dedicated = $app->make(App\Http\Controllers\Student\StudentController::class)->page($request);
    $expected = $original->getOriginalContent()->getData()['cfoBackend'];
    $actual = $dedicated->getOriginalContent()->getData()['cfoBackend'];
    $headers = static function ($response) {
        $values = $response->headers->all();
        unset($values['date']); // Automatically generated transport timestamp.
        ksort($values);
        return $values;
    };
    if (array_keys($actual) !== ['user', 'csrf_token', 'live_transport']
        || json_encode($expected, JSON_THROW_ON_ERROR) !== json_encode($actual, JSON_THROW_ON_ERROR)
        || $actual['live_transport'] !== $transport
        || $original->getStatusCode() !== $dedicated->getStatusCode()
        || $headers($original) !== $headers($dedicated)
        || $original->getContent() !== $dedicated->getContent()) {
        fwrite(STDERR, "FAIL: dedicated student response differs for {$transport}.\n");
        exit(1);
    }
    echo "PASS: {$transport}: identical metadata, status, response headers, and rendered HTML; Cache-Control=".$dedicated->headers->get('Cache-Control').".\n";
}
