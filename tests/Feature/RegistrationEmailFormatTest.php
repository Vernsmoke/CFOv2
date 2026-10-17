<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class RegistrationEmailFormatTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role, string $email): array
    {
        return ['role' => $role, 'student_id' => $role === 'Admin' ? '24-10724' : '24-10723',
            'first_name' => 'Gmail', 'last_name' => $role, 'email' => $email,
            'password' => 'CfoTest!234', 'password_confirmation' => 'CfoTest!234'];
    }

    public function test_student_registration_rejects_other_email_domains(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        foreach (['person@yahoo.com', 'person@outlook.com', 'person@googlemail.com', 'person@gmail.com.example', 'person@notgmail.com'] as $email) {
            $this->postJson('/api/register', $this->account('Student', $email))->assertUnprocessable()
                ->assertJsonValidationErrors('email')
                ->assertJsonPath('errors.email.0', 'Enter a valid Gmail address ending in @gmail.com.');
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_student_accepts_gmail_and_keeps_generated_office_login_email(): void
    {
        $local = 'student.example+tag';
        $this->withVerifiedRegistrationEmail($local.'@gmail.com')->postJson('/api/register', $this->account('Student', ' '.strtoupper($local).'@GMAIL.COM '))
            ->assertCreated()->assertJsonPath('login_email', $local.'@cfo.office.com');
        $this->assertDatabaseHas('users', ['role' => 'Student', 'status' => 'Pending', 'email' => $local.'@gmail.com',
            'original_email' => $local.'@gmail.com', 'login_email' => $local.'@cfo.office.com']);
    }

    public function test_gmail_suffix_does_not_bypass_email_syntax_validation(): void
    {
        $this->postJson('/api/register', $this->account('Student', 'person@@gmail.com'))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registered_gmail_still_has_to_be_unique(): void
    {
        $this->withVerifiedRegistrationEmail('person@gmail.com')->postJson('/api/register', $this->account('Student', 'person@gmail.com'))->assertCreated();
        $this->postJson('/api/register', $this->account('Admin', 'PERSON@GMAIL.COM'))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 1);
    }
}
