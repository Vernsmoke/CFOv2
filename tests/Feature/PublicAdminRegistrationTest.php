<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAdminRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function registration(string $role): array
    {
        return [
            'role' => $role, 'student_id' => '24-10724',
            'first_name' => 'New', 'last_name' => 'Office User',
            'email' => 'newoffice@gmail.com',
            'password' => 'CfoTest!234', 'password_confirmation' => 'CfoTest!234',
        ];
    }

    public function test_public_registration_is_student_only(): void
    {
        foreach ([User::ROLE_CFO_STAFF, User::ROLE_SUPER_ADMIN, User::ROLE_LEGACY_ADMIN] as $role) {
            $this->postJson('/api/register', $this->registration($role))
                ->assertUnprocessable()->assertJsonValidationErrors('role');
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_only_a_super_admin_can_create_staff(): void
    {
        $payload = ['employee_id' => 'CFO-101', 'first_name' => 'New', 'last_name' => 'Staff', 'email' => 'staff@cfo.example'];
        $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'status' => 'Approved']);
        $staff = User::factory()->create(['role' => User::ROLE_CFO_STAFF, 'status' => 'Approved']);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'status' => 'Approved']);

        $this->postJson('/api/admin/staff', $payload)->assertUnauthorized();
        $this->actingAs($student)->postJson('/api/admin/staff', $payload)->assertForbidden();
        $this->actingAs($staff)->postJson('/api/admin/staff', $payload)->assertForbidden();
        $this->actingAs($superAdmin)->postJson('/api/admin/staff', $payload)
            ->assertCreated()->assertJsonPath('user.role', User::ROLE_CFO_STAFF);
    }
}
