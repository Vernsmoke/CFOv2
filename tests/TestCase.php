<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function withVerifiedRegistrationEmail(string $email): static
    {
        $email = strtolower(trim($email));

        return $this->withSession(['registration_email_verification' => [
            'email' => $email,
            'code_hash' => 'test-only',
            'expires_at' => now()->addMinutes(10)->timestamp,
            'sent_at' => now()->timestamp,
            'attempts' => 0,
            'verified_at' => now()->timestamp,
        ]]);
    }
}
