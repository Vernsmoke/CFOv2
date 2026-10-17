<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash};
use Tests\TestCase;

class StudentAccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'email' => 'juan.cruz@gmail.com', 'password' => 'OldPassword!234']);
    }

    private function payload(): array
    {
        return ['first_name' => 'Juan', 'last_name' => 'Cruz', 'email' => 'juan.cruz@gmail.com', 'current_password' => 'OldPassword!234'];
    }

    public function test_password_change_logs_student_out_and_revokes_other_sessions(): void
    {
        $student = $this->student();
        DB::table('sessions')->insert(['id' => 'other-student-device', 'user_id' => $student->id, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($student)->putJson('/api/profile', [...$this->payload(), 'password' => 'NewPassword!234', 'password_confirmation' => 'NewPassword!234'])
            ->assertOk()->assertJsonPath('requires_login', true)->assertJsonPath('redirect', '/login?password_changed=1');
        $this->assertGuest();
        $this->assertDatabaseMissing('sessions', ['id' => 'other-student-device']);
        $this->assertTrue(Hash::check('NewPassword!234', $student->fresh()->password));
        $this->getJson('/api/student')->assertUnauthorized();
        $this->postJson('/api/login', ['email' => $student->email, 'password' => 'OldPassword!234'])->assertUnprocessable();
        $this->postJson('/api/login', ['email' => $student->email, 'password' => 'NewPassword!234'])->assertOk();
    }

    public function test_profile_only_edit_keeps_session_and_failed_password_change_does_not_log_out(): void
    {
        $student = $this->student();
        $this->actingAs($student)->putJson('/api/profile', $this->payload())->assertOk()->assertJsonMissingPath('requires_login');
        $this->assertAuthenticatedAs($student);
        $this->putJson('/api/profile', [...$this->payload(), 'password' => 'NewPassword!234', 'password_confirmation' => 'Mismatch!234'])->assertUnprocessable();
        $this->assertAuthenticatedAs($student);
        $this->assertTrue(Hash::check('OldPassword!234', $student->fresh()->password));
    }
}
