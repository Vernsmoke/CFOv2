<?php

namespace Tests\Feature;

use App\Mail\RegistrationVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function registration(string $email = 'verified.student@gmail.com'): array
    {
        return [
            'student_id' => '24-10723',
            'first_name' => 'Verified',
            'last_name' => 'Student',
            'email' => $email,
            'password' => 'CfoTest!234',
            'password_confirmation' => 'CfoTest!234',
        ];
    }

    public function test_registration_cannot_bypass_email_verification(): void
    {
        $this->postJson('/api/register', $this->registration())
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Verify this Gmail address before creating your account.');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_code_is_emailed_and_verified_before_registration(): void
    {
        Mail::fake();
        $email = ' Verified.Student@GMAIL.COM ';
        $code = null;

        $this->postJson('/api/register/email/send-code', ['email' => $email])
            ->assertOk()->assertJsonPath('expires_in', 600);
        Mail::assertSent(RegistrationVerificationCode::class, function (RegistrationVerificationCode $mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('verified.student@gmail.com');
        });
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $code);

        $this->postJson('/api/register/email/verify-code', ['email' => $email, 'code' => $code])
            ->assertOk();
        $this->postJson('/api/register', $this->registration($email))->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'verified.student@gmail.com', 'status' => 'Pending']);

        $this->postJson('/api/register', [...$this->registration('verified.student+again@gmail.com'), 'student_id' => '24-10724'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_wrong_or_expired_code_cannot_verify_an_email(): void
    {
        Mail::fake();
        $email = 'wrong.code@gmail.com';
        $this->postJson('/api/register/email/send-code', ['email' => $email])->assertOk();
        $this->postJson('/api/register/email/verify-code', ['email' => $email, 'code' => '999999'])
            ->assertUnprocessable()->assertJsonPath('errors.code.0', 'The verification code is incorrect.');

        $this->travel(11)->minutes();
        $this->postJson('/api/register/email/verify-code', ['email' => $email, 'code' => '000000'])
            ->assertUnprocessable()->assertJsonPath('errors.code.0', 'This verification code has expired. Request a new code.');
    }

    public function test_resend_has_a_cooldown_and_non_gmail_is_rejected(): void
    {
        Mail::fake();
        $this->postJson('/api/register/email/send-code', ['email' => 'person@yahoo.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/register/email/send-code', ['email' => 'person@gmail.com'])->assertOk();
        $this->postJson('/api/register/email/send-code', ['email' => 'person@gmail.com'])
            ->assertStatus(429)->assertHeader('Retry-After');
        Mail::assertSentCount(1);
    }
}
