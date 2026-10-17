<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StudentIdentity
{
    private const EMAIL_FIELDS = ['email', 'original_email', 'login_email', 'system_username'];

    public static function assertCanRegisterAdmin(array $identity, ?User $actor = null): void
    {
        if ($actor?->role === 'Student' || self::matchesStudent($identity)) {
            throw ValidationException::withMessages([
                'role' => 'These details match a student identity. Students cannot register as administrators. Contact the CFO Office for verification.',
            ]);
        }
    }

    private static function matchesStudent(array $identity): bool
    {
        $id = self::normalize($identity['student_id'] ?? '');
        $emails = self::emails($identity);
        // Read only identity fields; never expose a student directory to the public form.
        $students = User::where('role', 'Student')->select([
            'student_id', ...self::EMAIL_FIELDS, 'first_name', 'middle_name', 'last_name', 'name',
        ])->cursor();

        foreach ($students as $student) {
            $record = $student->getAttributes();
            if ($id !== '' && $id === self::normalize($record['student_id'] ?? '')) return true;
            if (array_intersect($emails, self::emails($record)) !== []) return true;
            if (self::sameName($identity, $record)) return true;
        }

        return false;
    }

    private static function normalize(?string $value): string
    {
        return mb_strtolower(Str::squish($value ?? ''));
    }

    private static function emails(array $identity): array
    {
        $emails = [];
        foreach (self::EMAIL_FIELDS as $field) {
            $email = strtolower(trim($identity[$field] ?? ''));
            if ($email === '') continue;
            $emails[] = $email;
            // Personal Gmail dot/plus aliases identify the same mailbox.
            if (preg_match('/\A([^@]+)@(gmail\.com|googlemail\.com)\z/', $email, $parts)) {
                $local = str_replace('.', '', Str::before($parts[1], '+'));
                if ($local !== '') $emails[] = $local.'@gmail.com';
            }
        }

        return array_unique($emails);
    }

    private static function sameName(array $identity, array $student): bool
    {
        $first = self::normalize($identity['first_name'] ?? '');
        $last = self::normalize($identity['last_name'] ?? '');
        if ($first !== '' && $last !== '' && $first === self::normalize($student['first_name'] ?? '')
            && $last === self::normalize($student['last_name'] ?? '')) {
            $middle = self::normalize($identity['middle_name'] ?? '');
            $studentMiddle = self::normalize($student['middle_name'] ?? '');
            // Omission of a middle name must not bypass a student identity match.
            if ($middle === '' || $studentMiddle === '' || $middle === $studentMiddle) return true;
        }

        $fullName = fn (array $record) => self::normalize($record['name'] ?? implode(' ', array_filter([
            $record['first_name'] ?? '', $record['middle_name'] ?? '', $record['last_name'] ?? '',
        ])));
        $name = $fullName($identity);

        return $name !== '' && $name === $fullName($student);
    }
}
