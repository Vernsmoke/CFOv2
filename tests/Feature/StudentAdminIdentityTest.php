<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAdminIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function student(array $changes = []): User
    {
        return User::factory()->create([...[
            'role' => 'Student', 'status' => 'Approved', 'student_id' => '24-10723',
            'first_name' => 'Juan', 'middle_name' => 'Santos', 'last_name' => 'Dela Cruz',
            'name' => 'Juan Santos Dela Cruz', 'email' => 'juan.current@gmail.com',
            'original_email' => 'Juan.DelaCruz+original@gmail.com',
            'login_email' => 'juandelacruz@cfo.office.com', 'system_username' => 'juandelacruz@cfo.office.com',
        ], ...$changes]);
    }

    private function registration(array $changes = []): array
    {
        return [...[
            'role' => 'Admin', 'student_id' => '25-00001',
            'first_name' => 'New', 'middle_name' => '', 'last_name' => 'Office Staff',
            'email' => 'newstaff@gmail.com', 'password' => 'CfoTest!234', 'password_confirmation' => 'CfoTest!234',
        ], ...$changes];
    }

    public function test_existing_student_id_and_email_cannot_be_reused_for_an_admin(): void
    {
        $this->student();
        foreach ([['student_id' => '24-10723'], ['email' => ' JUAN.CURRENT@GMAIL.COM ']] as $changes) {
            $this->postJson('/api/register', $this->registration($changes))->assertUnprocessable()
                ->assertJsonValidationErrors(array_keys($changes));
        }
        $this->assertDatabaseCount('users', 1);
    }

    public function test_gmail_aliases_and_original_email_cannot_register_as_admin_with_a_new_id_and_name(): void
    {
        $this->student();
        foreach (['Juan.DelaCruz+original@gmail.com', 'JUAN.DELA.CRUZ+admin@GMAIL.COM', 'j.u.a.n.d.e.l.a.c.r.u.z@gmail.com', 'juan.current+staff@gmail.com'] as $email) {
            $this->postJson('/api/register', $this->registration(['email' => $email]))
                ->assertUnprocessable()->assertJsonValidationErrors('role');
            $this->assertDatabaseCount('users', 1);
        }
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertDatabaseCount('cfo_notifications', 0);
    }

    public function test_matching_student_names_are_blocked_even_when_the_id_and_gmail_are_different(): void
    {
        $this->student();
        foreach ([' saNtoS ', '', null] as $middleName) {
            $this->postJson('/api/register', $this->registration([
                'first_name' => ' juAn ', 'middle_name' => $middleName, 'last_name' => 'deLa   CRUZ',
            ]))->assertUnprocessable()->assertJsonValidationErrors('role');
        }
        $this->assertDatabaseCount('users', 1);
    }

    public function test_legacy_students_with_only_a_display_name_are_detected(): void
    {
        $this->student(['first_name' => '', 'middle_name' => null, 'last_name' => '', 'name' => ' Juan   Santos DELA CRUZ ']);
        $this->postJson('/api/register', $this->registration([
            'first_name' => 'Juan', 'middle_name' => 'Santos', 'last_name' => 'Dela Cruz',
        ]))->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_student_identity_is_protected_in_every_account_status(): void
    {
        $student = $this->student();
        foreach (['Pending', 'Approved', 'Rejected', 'Inactive'] as $status) {
            $student->update(['status' => $status]);
            $this->postJson('/api/register', $this->registration(['email' => 'juandelacruz+admin@gmail.com']))
                ->assertUnprocessable()->assertJsonValidationErrors('role');
        }
        $this->assertDatabaseCount('users', 1);
    }

    public function test_a_signed_in_student_cannot_create_an_admin_under_unrelated_details(): void
    {
        $student = $this->student();
        $this->actingAs($student)->postJson('/api/register', $this->registration())
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('Student', $student->fresh()->role);
    }

    public function test_different_middle_names_and_unrelated_admin_applicants_still_require_office_approval(): void
    {
        $this->student();
        $this->postJson('/api/register', $this->registration([
            'first_name' => 'Juan', 'middle_name' => 'Miguel', 'last_name' => 'Dela Cruz',
        ]))->assertCreated();
        $applicant = User::where('email', 'newstaff@gmail.com')->firstOrFail();
        $this->assertSame('Admin', $applicant->role);
        $this->assertSame('Pending', $applicant->status);
        $this->postJson('/api/login', ['email' => 'newstaff@gmail.com', 'password' => 'CfoTest!234'])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_admin_approval_rechecks_student_identity_for_existing_pending_applicants(): void
    {
        $reviewer = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $applicant = User::factory()->create([
            'role' => 'Admin', 'status' => 'Pending', 'student_id' => '25-00001',
            'email' => 'juan.delacruz+staff@gmail.com', 'first_name' => 'New', 'last_name' => 'Staff',
        ]);
        $this->student();
        $url = '/api/admin/users/'.$applicant->id.'/review-admin';
        $this->actingAs($reviewer)->patchJson($url, ['status' => 'Approved'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertSame('Pending', $applicant->fresh()->status);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->patchJson($url, ['status' => 'Rejected', 'rejection_reason' => 'Student identity requires office review.'])->assertOk();
        $this->assertSame('Rejected', $applicant->fresh()->status);
    }
}
