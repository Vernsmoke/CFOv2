<?php

namespace Tests\Feature;

use App\Models\{PasswordResetRequest, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, Crypt, DB, Hash};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecoveryApprovalTest extends TestCase
{
    use RefreshDatabase;

    public static function forgotLoginIdentifiers(): array
    {
        return [
            'email' => ['email'],
            'registered Gmail' => ['original_email'],
            'office login email' => ['login_email'],
            'system username' => ['system_username'],
        ];
    }

    #[DataProvider('forgotLoginIdentifiers')]
    public function test_forgot_accepts_login_identifiers_and_lists_pending_request_for_admin(string $column): void
    {
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved',
            'email' => 'legacy.student@gmail.com', 'original_email' => 'registered.student@gmail.com',
            'login_email' => 'registered.student@cfo.office.com', 'system_username' => 'student.username@cfo.office.com',
            'password' => 'Remembered!234']);
        $nonRequester = User::factory()->create(['role' => 'Student', 'status' => 'Approved']);
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->postJson('/api/forgot-password', ['email' => ' '.strtoupper($student->{$column}).' '])->assertOk();
        $reset = PasswordResetRequest::firstOrFail();
        $this->assertSame($student->id, $reset->user_id);
        $this->assertSame('Pending', $reset->status);
        $owner = session('recovery_secret');
        $this->assertSame(hash('sha256', $owner), $reset->recovery_hash);

        $alternate = $column === 'original_email' ? $student->login_email : $student->original_email;
        $this->postJson('/api/forgot-password', ['email' => $alternate])->assertOk();
        $this->assertSame($owner, session('recovery_secret'));
        $this->assertDatabaseCount('password_reset_requests', 1);
        $this->getJson('/api/recovery')->assertOk()->assertJsonPath('status', 'Pending');
        $this->assertTrue(Hash::check('Remembered!234', $student->fresh()->password));
        $response = $this->actingAs($admin)->getJson('/api/admin/state?resources=password_resets')->assertOk()
            ->assertJsonCount(1, 'resets')->assertJsonPath('resets.0.id', $reset->id)
            ->assertJsonPath('resets.0.user_id', $student->id)->assertJsonPath('resets.0.status', 'Pending')
            ->assertJsonPath('resets.0.user.id', $student->id);
        $this->assertNotContains($nonRequester->id, array_column($response->json('resets'), 'user_id'));
    }

    public function test_pending_request_survives_wrong_login_and_is_closed_by_correct_login(): void
    {
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'password' => 'Remembered!234']);
        $this->postJson('/api/forgot-password', ['email' => $student->email])->assertOk();
        $reset = PasswordResetRequest::firstOrFail();
        $this->assertTrue(Hash::check('Remembered!234', $student->fresh()->password));
        $this->assertFalse($student->fresh()->password_change_required);
        $this->postJson('/api/login', ['email' => $student->email, 'password' => 'Incorrect!234'])->assertUnprocessable();
        $this->assertSame('Pending', $reset->fresh()->status);
        $this->getJson('/api/recovery')->assertJsonPath('status', 'Pending');
        $version = DB::table('cfo_resource_versions')->where('resource', 'password_resets')->value('version');
        $this->postJson('/api/login', ['email' => $student->email, 'password' => 'Remembered!234'])
            ->assertOk()->assertJsonPath('redirect', '/student#dashboard');
        $this->getJson('/api/student')->assertOk();
        $this->getJson('/api/recovery')->assertJsonPath('status', 'Cancelled')->assertJsonMissingPath('temporary_password');
        $this->assertSame('Cancelled', $reset->fresh()->status);
        $this->assertGreaterThan($version, DB::table('cfo_resource_versions')->where('resource', 'password_resets')->value('version'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'recovery.cancelled_after_login', 'record_id' => $reset->id]);

        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->actingAs($admin)->patchJson('/api/admin/password-resets/'.$reset->id,
            ['status' => 'Approved', 'identity_verified' => true])->assertUnprocessable();
        $this->getJson('/api/admin/state?resources=password_resets')->assertJsonPath('resets.0.status', 'Cancelled');
    }

    public static function activeRecoveryStatuses(): array
    {
        return [['Pending'], ['Approved'], ['Verified']];
    }

    #[DataProvider('activeRecoveryStatuses')]
    public function test_login_on_another_device_revokes_active_recovery_and_its_secrets(string $status): void
    {
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'password' => 'Remembered!234']);
        $reset = PasswordResetRequest::create(['user_id' => $student->id, 'status' => $status,
            'recovery_hash' => hash('sha256', 'original-device'), 'temp_password_hash' => Hash::make('Temporary!234'),
            'expires_at' => now()->addMinute(), 'verified_at' => $status === 'Verified' ? now() : null]);
        Cache::put('recovery-secret:'.$reset->id, Crypt::encryptString('Temporary!234'), now()->addMinute());
        $this->postJson('/api/login', ['email' => $student->email, 'password' => 'Remembered!234'])->assertOk();
        $this->getJson('/api/student')->assertOk();
        $this->assertNull($reset->fresh()->temp_password_hash);
        $this->assertNull(Cache::get('recovery-secret:'.$reset->id));
        $this->postJson('/api/logout')->assertOk();
        $this->withSession(['recovery_secret' => 'original-device', 'recovery_verified' => $reset->id])
            ->getJson('/api/recovery')->assertJsonPath('status', 'Cancelled')->assertJsonMissingPath('temporary_password');
        $this->postJson('/api/recovery/verify', ['temporary_password' => 'Temporary!234'])->assertUnprocessable();
        $this->postJson('/api/reset-password', ['password' => 'Replacement!234', 'password_confirmation' => 'Replacement!234'])->assertUnprocessable();
        $this->assertTrue(Hash::check('Remembered!234', $student->fresh()->password));
        $this->postJson('/api/forgot-password', ['email' => $student->email])->assertOk();
        $this->getJson('/api/recovery')->assertJsonPath('status', 'Pending');
        $this->assertSame(1, PasswordResetRequest::where('status', 'Pending')->count());
    }

    public function test_legacy_admin_reset_cannot_replace_an_active_student_request(): void
    {
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'password' => 'Remembered!234']);
        $this->postJson('/api/forgot-password', ['email' => $student->email])->assertOk();
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->actingAs($admin)->postJson('/api/admin/users/'.$student->id.'/password-reset', ['identity_verified' => true])->assertUnprocessable();
        $this->assertSame('Pending', PasswordResetRequest::firstOrFail()->status);
        $this->assertTrue(Hash::check('Remembered!234', $student->fresh()->password));
        $this->get('/admin')->assertOk()->assertDontSee('connected-reset-password')->assertDontSee('admin-temp-password');
    }

    public function test_request_approval_copy_verification_new_password_and_login_with_original_recovery_session(): void
    {
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'email' => 'recovery.owner@gmail.com', 'login_email' => 'recovery.owner@cfo.office.com', 'password' => 'OldPassword!234']);
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->postJson('/api/forgot-password', ['email' => $student->email])->assertOk();
        $owner = session('recovery_secret');
        $reset = PasswordResetRequest::firstOrFail();
        $this->getJson('/api/recovery')->assertJsonPath('status', 'Pending')->assertJsonMissingPath('temporary_password')->assertJsonMissingPath('login_email');
        $this->actingAs($admin)->patchJson('/api/admin/password-resets/'.$reset->id,
            ['status' => 'Approved', 'identity_verified' => true])->assertOk();
        $this->assertSame('Approved', $reset->fresh()->status);
        $this->postJson('/api/logout')->assertOk();
        // Restore the student's original session, not a replacement recovery token.
        $response = $this->withSession(['recovery_secret' => $owner])->getJson('/api/recovery')->assertOk()->assertJsonPath('login_email', 'recovery.owner@cfo.office.com');
        $temporary = $response->json('temporary_password');
        $this->assertNotEmpty($temporary);
        $this->postJson('/api/reset-password', ['password' => 'NewPassword!234', 'password_confirmation' => 'NewPassword!234'])->assertUnprocessable();
        $this->postJson('/api/recovery/verify', ['temporary_password' => 'wrong-password'])->assertUnprocessable();
        $this->postJson('/api/recovery/verify', ['temporary_password' => $temporary])->assertOk();
        $this->postJson('/api/recovery/verify', ['temporary_password' => $temporary])->assertUnprocessable();
        $this->postJson('/api/reset-password', ['password' => 'NewPassword!234', 'password_confirmation' => 'NewPassword!234'])->assertOk();
        $this->assertSame('Completed', $reset->fresh()->status);
        $this->assertNotNull($reset->fresh()->used_at);
        $this->assertNull($reset->fresh()->temp_password_hash);
        $this->assertNull(session('recovery_secret'));
        $this->postJson('/api/login', ['email' => $student->email, 'password' => 'OldPassword!234'])->assertUnprocessable();
        $this->postJson('/api/login', ['email' => $student->email, 'password' => 'NewPassword!234'])->assertOk();
    }

    public function test_admin_queue_keeps_older_pending_requests_and_exposes_reviewer_without_secrets(): void
    {
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved']);
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $pending = PasswordResetRequest::create(['user_id' => $student->id, 'recovery_hash' => hash('sha256', 'owner'), 'status' => 'Pending', 'created_at' => now()->subMinutes(10)]);
        for ($index = 0; $index < 101; $index++) {
            PasswordResetRequest::create(['user_id' => $student->id, 'recovery_hash' => hash('sha256', 'history-'.$index),
                'status' => 'Rejected', 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
        }
        $response = $this->actingAs($admin)->getJson('/api/admin/state?resources=password_resets')->assertOk()
            ->assertJsonCount(102, 'resets')->assertJsonPath('resets.0.reviewer.name', $admin->name)
            ->assertJsonMissingPath('resets.0.recovery_hash')->assertJsonMissingPath('resets.0.temp_password_hash');
        $this->assertContains($pending->id, array_column($response->json('resets'), 'id'));
    }

    public function test_only_in_person_approval_releases_a_hashed_password_without_expiry_to_the_requesting_session(): void
    {
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved']);
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->postJson('/api/forgot-password', ['email' => $student->email])->assertOk()
            ->assertSee('CFO Office, Miraculous Building, near the chapel');
        $owner = session('recovery_secret');
        $reset = PasswordResetRequest::firstOrFail();
        $this->getJson('/api/recovery')->assertJsonPath('status', 'Pending')->assertJsonMissingPath('temporary_password');
        $this->actingAs($admin)->patchJson('/api/admin/password-resets/'.$reset->id, ['status' => 'Approved'])->assertUnprocessable();
        $this->assertNull($reset->fresh()->temp_password_hash);
        $this->patchJson('/api/admin/password-resets/'.$reset->id, ['status' => 'Approved', 'identity_verified' => true])
            ->assertOk()->assertJsonMissingPath('temporary_password');
        $this->patchJson('/api/admin/password-resets/'.$reset->id, ['status' => 'Approved', 'identity_verified' => true])->assertUnprocessable();
        $this->postJson('/api/logout')->assertOk();
        $this->withSession(['recovery_secret' => 'another-device'])->getJson('/api/recovery')->assertJsonMissingPath('temporary_password');
        $encrypted = Cache::get('recovery-secret:'.$reset->id);
        $result = $this->withSession(['recovery_secret' => $owner])->getJson('/api/recovery')->assertOk()->assertJsonPath('status', 'Approved');
        $this->assertStringContainsString('no-store', $result->headers->get('Cache-Control'));
        $password = $result->json('temporary_password');
        $result->assertJsonPath('expires_at', null)->assertJsonPath('expires_in', null);
        $this->assertNull($reset->fresh()->expires_at);
        $this->assertGreaterThanOrEqual(6, strlen($password));
        $this->assertMatchesRegularExpression('/[0-9]/', $password);
        $this->assertSame($password, Crypt::decryptString($encrypted));
        $this->assertStringNotContainsString($password, $encrypted);
        $this->assertTrue(Hash::check($password, $reset->fresh()->temp_password_hash));
        $this->assertNull(Cache::get('recovery-secret:'.$reset->id));
        $this->getJson('/api/recovery')->assertJsonMissingPath('temporary_password');
        $this->travel(61)->seconds();
        $this->getJson('/api/recovery')->assertJsonPath('status', 'Approved');
        $this->travel(60)->seconds();
        $this->getJson('/api/recovery')->assertJsonPath('status', 'Approved')->assertJsonMissingPath('temporary_password');
        $this->postJson('/api/recovery/verify', ['temporary_password' => $password])->assertOk();
        $this->assertSame('Verified', $reset->fresh()->status);
        $this->assertNull($reset->fresh()->temp_password_hash);
    }

    public function test_recovery_has_no_waiting_approval_or_verified_time_limit(): void
    {
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'password' => 'OldPassword!234']);
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->postJson('/api/forgot-password', ['email' => $student->email])->assertOk();
        $owner = session('recovery_secret');
        $reset = PasswordResetRequest::firstOrFail();
        $this->travel(7)->days();
        $this->getJson('/api/recovery')->assertJsonPath('status', 'Pending')->assertJsonMissingPath('temporary_password');
        $this->postJson('/api/recovery/verify', ['temporary_password' => 'NotApproved!234'])->assertUnprocessable();
        $this->postJson('/api/reset-password', ['password' => 'NewPassword!234', 'password_confirmation' => 'NewPassword!234'])->assertUnprocessable();
        $this->postJson('/api/forgot-password', ['email' => $student->email])->assertOk();
        $this->assertSame($owner, session('recovery_secret'));
        $this->assertDatabaseCount('password_reset_requests', 1);
        $this->actingAs($admin)->getJson('/api/admin/state?resources=password_resets')->assertJsonPath('resets.0.status', 'Pending');
        $this->patchJson('/api/admin/password-resets/'.$reset->id, ['status' => 'Approved', 'identity_verified' => true])->assertOk();
        $this->assertNull($reset->fresh()->expires_at);
        $this->postJson('/api/logout')->assertOk();
        $this->travel(7)->days();
        $response = $this->withSession(['recovery_secret' => $owner])->getJson('/api/recovery')->assertOk()
            ->assertJsonPath('status', 'Approved')->assertJsonPath('expires_in', null);
        $temporary = $response->json('temporary_password');
        $this->assertNotEmpty($temporary);
        $this->getJson('/api/recovery')->assertJsonMissingPath('temporary_password');
        $this->postJson('/api/recovery/verify', ['temporary_password' => $temporary])->assertOk();
        $this->postJson('/api/recovery/verify', ['temporary_password' => $temporary])->assertUnprocessable();
        $this->travel(7)->days();
        $this->getJson('/api/recovery')->assertJsonPath('status', 'Verified');
        $this->postJson('/api/reset-password', ['password' => 'NewPassword!234', 'password_confirmation' => 'NewPassword!234'])->assertOk();
        $this->assertSame('Completed', $reset->fresh()->status);
        $this->assertTrue(Hash::check('NewPassword!234', $student->fresh()->password));
    }

    public function test_rejection_never_generates_a_temporary_password(): void
    {
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved']);
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->postJson('/api/forgot-password', ['email' => $student->email])->assertOk();
        $owner = session('recovery_secret');
        $reset = PasswordResetRequest::firstOrFail();
        $this->actingAs($admin)->patchJson('/api/admin/password-resets/'.$reset->id, ['status' => 'Rejected'])->assertOk();
        $this->postJson('/api/logout')->assertOk();
        $this->withSession(['recovery_secret' => $owner])->getJson('/api/recovery')
            ->assertJsonPath('status', 'Rejected')->assertJsonMissingPath('temporary_password');
        $this->assertNull($reset->fresh()->temp_password_hash);
        $this->assertNull(Cache::get('recovery-secret:'.$reset->id));
    }
}
