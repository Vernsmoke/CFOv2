<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountIdFormatTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role, mixed $id, string $email = 'format@gmail.com'): array
    {
        return ['role' => $role, 'student_id' => $id, 'first_name' => 'Format', 'last_name' => $role,
            'email' => $email, 'password' => 'CfoTest!234', 'password_confirmation' => 'CfoTest!234'];
    }

    public function test_student_and_admin_creation_require_exact_id_format(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        foreach (['Student', 'Admin'] as $role) {
            foreach (['2410723', '2-10723', '2024-10723', '24-1072', '24-107234', '24/10723', 'AA-10723', '24-10A23', "24-\n10723", '24 -10723', '24–10723', 2410723, ['24-10723']] as $id) {
                $this->postJson('/api/register', $this->account($role, $id))
                    ->assertUnprocessable()->assertJsonValidationErrors('student_id');
                $this->assertDatabaseCount('users', 0);
            }
        }
    }

    public function test_valid_ids_and_leading_zeroes_are_saved_for_both_roles(): void
    {
        foreach (['Student' => ['24-10723', '05-00123'], 'Admin' => ['24-10724', '05-00124']] as $role => $ids) {
            foreach ($ids as $index => $id) {
                $email = strtolower($role).$index.'@gmail.com';
                $this->postJson('/api/register', $this->account($role, $id, $email))->assertCreated();
                $this->assertDatabaseHas('users', ['email' => $email, 'student_id' => $id, 'role' => $role, 'status' => 'Pending']);
            }
        }
    }

    public function test_surrounding_whitespace_is_normalized_without_changing_the_id(): void
    {
        foreach (['Student' => '24-10723', 'Admin' => '24-10724'] as $role => $id) {
            $email = strtolower($role).'trim@gmail.com';
            $this->postJson('/api/register', $this->account($role, " ".$id."\n", $email))->assertCreated();
            $this->assertDatabaseHas('users', ['email' => $email, 'student_id' => $id]);
        }
    }

    public function test_ids_remain_unique_across_student_and_admin_accounts(): void
    {
        $this->postJson('/api/register', $this->account('Student', '24-10723', 'student@gmail.com'))->assertCreated();
        $this->postJson('/api/register', $this->account('Admin', '24-10723', 'admin@gmail.com'))
            ->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_can_review_legacy_ids_but_changed_ids_must_match_the_format(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $student = User::factory()->create(['role' => 'Student', 'student_id' => 'LEGACY-001', 'status' => 'Pending']);
        $url = '/api/admin/users/'.$student->id;
        $values = ['first_name' => 'Updated', 'last_name' => 'Student', 'status' => 'Approved'];
        $this->actingAs($admin)->patchJson($url, [...$values, 'student_id' => 'LEGACY-001'])->assertOk();
        $this->assertSame('LEGACY-001', $student->fresh()->student_id);
        $this->patchJson($url, [...$values, 'student_id' => 'INVALID-002'])
            ->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->assertSame('LEGACY-001', $student->fresh()->student_id);
        $this->patchJson($url, [...$values, 'student_id' => '05-00123'])->assertOk();
        $this->assertSame('05-00123', $student->fresh()->student_id);
    }
}
