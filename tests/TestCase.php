<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function studentAcademicSelection(array $changes = []): array
    {
        return [...[
            'department' => 'College of Information Technology',
            'course' => 'Bachelor of Science in Information Technology',
            'major' => null,
            'year_level' => '1',
            'section' => 'A',
        ], ...$changes];
    }

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
