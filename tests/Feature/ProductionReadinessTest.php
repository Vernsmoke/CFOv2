<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrated_database_supports_sessions_cache_and_jobs(): void
    {
        foreach (['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }

        $cache = Cache::store('database');
        $cache->put('production-migration-check', 'ready', 60);
        $this->assertSame('ready', $cache->get('production-migration-check'));
        $lock = $cache->lock('production-migration-check', 60);
        $this->assertTrue($lock->get());
        $this->assertTrue($lock->release());

        $handler = app('session')->driver('database')->getHandler();
        $payload = serialize(['migration' => 'ready']);
        $this->assertTrue($handler->write('production-migration-check', $payload));
        $this->assertSame($payload, $handler->read('production-migration-check'));

        $jobId = Queue::connection('database')->pushRaw(json_encode(['job' => 'migration-check', 'data' => []]));
        $this->assertDatabaseHas('jobs', ['id' => $jobId]);
    }

    public function test_admin_seed_requires_an_explicit_valid_email(): void
    {
        config(['cfo.admin.email' => null, 'cfo.admin.password' => null]);

        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail('Seeding without administrator credentials must fail.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('CFO_DEFAULT_ADMIN_EMAIL', $error->getMessage());
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_admin_seed_requires_an_explicit_password(): void
    {
        config(['cfo.admin.email' => 'admin@example.com', 'cfo.admin.password' => null]);

        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail('Seeding without an administrator password must fail.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('CFO_DEFAULT_ADMIN_PASSWORD', $error->getMessage());
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_admin_seed_uses_configured_credentials_and_preserves_an_existing_account(): void
    {
        $password = bin2hex(random_bytes(16));
        config(['cfo.admin.email' => 'admin@example.com', 'cfo.admin.password' => $password]);
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertSame('Admin', $admin->role);
        $this->assertTrue(Hash::check($password, $admin->password));

        config(['cfo.admin.password' => null]);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($admin->password, $admin->fresh()->password);
    }
}
