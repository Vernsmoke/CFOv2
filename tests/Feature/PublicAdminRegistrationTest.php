<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAdminRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function registration(): array
    {
        return [
            'role' => 'Admin', 'student_id' => '24-10724',
            'first_name' => 'New', 'last_name' => 'Administrator',
            'email' => 'newadmin@gmail.com',
            'password' => 'CfoTest!234', 'password_confirmation' => 'CfoTest!234',
        ];
    }

    public function test_public_admin_registration_requires_review_before_admin_login(): void
    {
        $reviewer = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->postJson('/api/register', [...$this->registration(), 'status' => 'Approved'])
            ->assertCreated()->assertJsonPath('login_email', 'newadmin@cfo.office.com');
        $applicant = User::where('email', 'newadmin@gmail.com')->firstOrFail();
        $this->assertSame('Admin', $applicant->role);
        $this->assertSame('Pending', $applicant->status);
        $this->assertSame('24-10724', $applicant->student_id);
        $this->assertDatabaseHas('cfo_notifications', [
            'user_id' => $reviewer->id, 'message' => 'An administrator account is awaiting office verification.',
        ]);
        $credentials = ['email' => 'newadmin@cfo.office.com', 'password' => 'CfoTest!234'];
        $this->postJson('/api/login', $credentials)->assertUnprocessable();
        $this->assertGuest();
        $this->actingAs($reviewer)->patchJson('/api/admin/users/'.$applicant->id.'/review-admin', ['status' => 'Approved'])->assertOk();
        $this->assertSame('Approved', $applicant->fresh()->status);
        $this->postJson('/api/logout')->assertOk();
        $this->postJson('/api/login', $credentials)->assertOk()->assertJsonPath('redirect', '/admin');
        $this->getJson('/api/admin/state')->assertOk();
        $this->getJson('/api/student')->assertForbidden();
    }

    public function test_only_an_approved_admin_can_review_a_pending_admin(): void
    {
        $this->postJson('/api/register', $this->registration())->assertCreated();
        $applicant = User::where('email', 'newadmin@gmail.com')->firstOrFail();
        $url = '/api/admin/users/'.$applicant->id.'/review-admin';
        $this->patchJson($url, ['status' => 'Approved'])->assertUnauthorized();
        $this->actingAs($applicant)->patchJson($url, ['status' => 'Approved'])->assertForbidden();
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved']);
        $this->actingAs($student)->patchJson($url, ['status' => 'Approved'])->assertForbidden();
        $this->assertSame('Pending', $applicant->fresh()->status);
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->actingAs($admin)->patchJson('/api/admin/users/'.$admin->id.'/review-admin', ['status' => 'Rejected', 'rejection_reason' => 'Self review'])->assertForbidden();
        $this->patchJson('/api/admin/users/'.$student->id.'/review-admin', ['status' => 'Approved'])->assertForbidden();
        $this->patchJson($url, ['status' => 'Rejected'])->assertUnprocessable();
        $this->patchJson($url, ['status' => 'Rejected', 'rejection_reason' => 'Not office staff'])->assertOk();
        $this->assertSame('Rejected', $applicant->fresh()->status);
        $this->patchJson($url, ['status' => 'Approved'])->assertUnprocessable();
        $this->postJson('/api/logout')->assertOk();
        $this->postJson('/api/login', ['email' => 'newadmin@gmail.com', 'password' => 'CfoTest!234'])->assertUnprocessable();
    }
}
