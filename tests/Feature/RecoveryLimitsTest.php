<?php

namespace Tests\Feature;

use App\Models\{PasswordResetRequest, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecoveryLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fifth_request_is_accepted_then_a_full_hour_is_required_across_sessions(): void
    {
        $this->travelTo(now()->startOfSecond());
        $student = User::factory()->create(['status' => 'Approved']);
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson('/api/forgot-password', ['email' => $student->email])->assertOk()
                ->assertJsonPath('recovery_limit.remaining_requests', 5 - $attempt)
                ->assertJsonPath('recovery_limit.retry_after', 0);
        }
        $this->travel(10)->minutes();
        $this->postJson('/api/forgot-password', ['email' => $student->email])->assertOk()
            ->assertJsonPath('recovery_limit.remaining_requests', 0)
            ->assertJsonPath('recovery_limit.retry_after', 3600);
        $this->assertDatabaseCount('password_reset_requests', 1);
        $owner = session('recovery_secret');
        $this->getJson('/api/recovery')->assertOk()->assertJsonPath('recovery_limit.retry_after', 3600);

        $this->postJson('/api/logout')->assertOk();
        $this->postJson('/api/forgot-password', ['email' => ' '.strtoupper($student->email).' '])
            ->assertTooManyRequests()->assertHeader('Retry-After', '3600')
            ->assertJsonPath('recovery_limit.retry_after', 3600);
        $this->assertSame(hash('sha256', $owner), PasswordResetRequest::firstOrFail()->recovery_hash);

        // Passing one hour since the first request must not end the fifth-request cooldown.
        $this->travel(50)->minutes();
        $this->postJson('/api/forgot-password', ['email' => $student->email])->assertTooManyRequests()
            ->assertHeader('Retry-After', '600');
        $this->travel(599)->seconds();
        $this->postJson('/api/forgot-password', ['email' => $student->email])->assertTooManyRequests()
            ->assertHeader('Retry-After', '1');
        $this->travel(1)->seconds();
        $this->postJson('/api/forgot-password', ['email' => $student->email])->assertOk()
            ->assertJsonPath('recovery_limit.remaining_requests', 4)
            ->assertJsonPath('recovery_limit.retry_after', 0);
    }

    public function test_limits_are_per_email_and_apply_equally_to_unknown_accounts(): void
    {
        $student = User::factory()->create(['status' => 'Approved']);
        foreach ([$student->email, 'unknown@example.test'] as $email) {
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $this->postJson('/api/forgot-password', ['email' => $email])->assertOk()
                    ->assertJsonPath('recovery_limit.remaining_requests', 5 - $attempt);
            }
            $this->postJson('/api/forgot-password', ['email' => $email])->assertTooManyRequests()
                ->assertJsonPath('recovery_limit.remaining_requests', 0);
        }
        $this->assertDatabaseCount('password_reset_requests', 1);
    }

    public function test_requests_below_the_limit_reset_after_an_hour_and_invalid_input_does_not_count(): void
    {
        $this->travelTo(now()->startOfSecond());
        $this->postJson('/api/forgot-password', ['email' => 'invalid'])->assertUnprocessable();
        $email = 'unknown@example.test';
        $this->postJson('/api/forgot-password', ['email' => $email])->assertOk()
            ->assertJsonPath('recovery_limit.remaining_requests', 4);
        $this->travel(1)->hours();
        $this->postJson('/api/forgot-password', ['email' => $email])->assertOk()
            ->assertJsonPath('recovery_limit.remaining_requests', 4)
            ->assertJsonPath('recovery_limit.retry_after', 0);
    }

    public function test_fifth_request_can_be_approved_and_verified_during_cooldown_without_expiry(): void
    {
        [$reset, $owner, $temporary] = $this->approveFifthRequest();
        $this->travel(119)->seconds();
        $this->withSession(['recovery_secret' => $owner])->getJson('/api/recovery')->assertOk()
            ->assertJsonPath('status', 'Approved')->assertJsonPath('expires_in', null);
        $this->postJson('/api/recovery/verify', ['temporary_password' => $temporary])->assertOk();
        $this->postJson('/api/reset-password', ['password' => 'NewPassword!234', 'password_confirmation' => 'NewPassword!234'])->assertOk();
        $this->assertSame('Completed', $reset->fresh()->status);
    }

    public function test_temporary_password_remains_valid_after_days(): void
    {
        [$reset, $owner, $temporary] = $this->approveFifthRequest();
        $this->travel(7)->days();
        $this->withSession(['recovery_secret' => $owner])->postJson('/api/recovery/verify', ['temporary_password' => $temporary])->assertOk();
        $this->getJson('/api/recovery')->assertJsonPath('status', 'Verified')->assertJsonMissingPath('temporary_password');
        $this->assertNull($reset->fresh()->temp_password_hash);
    }

    private function approveFifthRequest(): array
    {
        $this->travelTo(now()->startOfSecond());
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved']);
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/forgot-password', ['email' => $student->email])->assertOk();
        }
        $owner = session('recovery_secret');
        $reset = PasswordResetRequest::firstOrFail();
        $this->actingAs($admin)->patchJson('/api/admin/password-resets/'.$reset->id,
            ['status' => 'Approved', 'identity_verified' => true])->assertOk();
        $this->assertNull($reset->fresh()->expires_at);
        $this->postJson('/api/logout')->assertOk();
        $response = $this->withSession(['recovery_secret' => $owner, 'recovery_email' => $student->email])
            ->getJson('/api/recovery')->assertOk()->assertJsonPath('expires_in', null)
            ->assertJsonPath('recovery_limit.remaining_requests', 0);

        return [$reset, $owner, $response->json('temporary_password')];
    }
}
