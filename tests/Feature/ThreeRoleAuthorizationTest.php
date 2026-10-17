<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ThreeRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $status = 'Approved'): User
    {
        return User::factory()->create([
            'role' => $role, 'status' => $status,
            'first_name' => 'Role', 'last_name' => $role,
            'password' => 'CfoTest!234',
        ]);
    }

    public function test_each_role_is_routed_to_its_workspace(): void
    {
        $student = $this->user(User::ROLE_STUDENT);
        $staff = $this->user(User::ROLE_CFO_STAFF);
        $superAdmin = $this->user(User::ROLE_SUPER_ADMIN);

        $this->actingAs($student)->get('/student')->assertOk();
        $this->actingAs($student)->get('/admin')->assertForbidden();
        foreach ([$staff, $superAdmin] as $officeUser) {
            $this->actingAs($officeUser)->get('/admin')->assertOk();
            $this->actingAs($officeUser)->get('/student')->assertForbidden();
        }
    }

    public function test_staff_can_use_daily_work_but_cannot_access_super_admin_resources(): void
    {
        $staff = $this->user(User::ROLE_CFO_STAFF);
        $student = $this->user(User::ROLE_STUDENT, 'Pending');

        $this->actingAs($staff)->getJson('/api/admin/state?resources=users')->assertOk()
            ->assertJsonCount(1, 'users')->assertJsonPath('users.0.id', $student->id);
        $this->actingAs($staff)->patchJson('/api/admin/users/'.$student->id, [
            'first_name' => 'Approved', 'last_name' => 'Student', 'status' => 'Approved',
        ])->assertOk();

        $this->actingAs($staff)->getJson('/api/admin/state?resources=activity_logs')->assertForbidden();
        $this->actingAs($staff)->postJson('/api/admin/staff', [])->assertForbidden();
        $this->actingAs($staff)->postJson('/api/admin/settings/office', [])->assertForbidden();
        $this->actingAs($staff)->postJson('/api/admin/academic-options', [])->assertForbidden();
        $this->actingAs($staff)->postJson('/api/admin/certificate-template/preview', [])->assertForbidden();
    }

    public function test_super_admin_can_create_and_disable_staff(): void
    {
        $superAdmin = $this->user(User::ROLE_SUPER_ADMIN);
        $response = $this->actingAs($superAdmin)->postJson('/api/admin/staff', [
            'employee_id' => 'CFO-001', 'first_name' => 'Maria', 'last_name' => 'Santos',
            'email' => 'maria.staff@cfo.example',
        ])->assertCreated()->assertJsonPath('user.role', User::ROLE_CFO_STAFF);

        $staff = User::findOrFail($response->json('user.id'));
        $this->assertTrue($staff->password_change_required);
        $this->assertNotEmpty($response->json('temporary_password'));
        DB::table('sessions')->insert([
            'id' => 'staff-session', 'user_id' => $staff->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'test', 'payload' => '', 'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($superAdmin)->patchJson('/api/admin/staff/'.$staff->id, ['status' => 'Inactive'])->assertOk();
        $this->assertSame('Inactive', $staff->fresh()->status);
        $this->assertDatabaseMissing('sessions', ['id' => 'staff-session']);
    }

    public function test_public_registration_cannot_create_office_accounts(): void
    {
        $payload = [
            'student_id' => '24-10723', 'first_name' => 'Public', 'last_name' => 'Applicant',
            'email' => 'public@gmail.com', 'password' => 'CfoTest!234', 'password_confirmation' => 'CfoTest!234',
        ];
        foreach ([User::ROLE_CFO_STAFF, User::ROLE_SUPER_ADMIN, User::ROLE_LEGACY_ADMIN] as $role) {
            $this->postJson('/api/register', [...$payload, 'role' => $role])
                ->assertUnprocessable()->assertJsonValidationErrors('role');
        }
        $this->assertDatabaseCount('users', 0);
    }
}
