<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OptimizationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_polling_does_not_repeat_daily_maintenance_queries(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);

        $this->actingAs($admin)->getJson('/api/live/versions')->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/live/versions')->assertOk();

        $queries = collect(DB::getQueryLog())->pluck('query');
        $this->assertLessThanOrEqual(2, $queries->count(), 'A warm live-version poll should execute only access and version reads.');
        $this->assertFalse($queries->contains(fn (string $query) => str_contains($query, 'update "recollection_registrations"')));
        $this->assertFalse($queries->contains(fn (string $query) => str_contains($query, 'not exists') && str_contains($query, 'certificates')));
    }

    public function test_security_headers_are_sent_and_hsts_requires_https(): void
    {
        $this->get('/login')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeaderMissing('Strict-Transport-Security');

        config(['trustedproxy.proxies' => ['10.20.0.10']]);
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.0.10'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('http://app.example.com/login')->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
