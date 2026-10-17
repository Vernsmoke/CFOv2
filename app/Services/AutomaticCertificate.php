<?php

namespace App\Services;

use App\Models\{Certificate, Registration};
use Illuminate\Support\Facades\DB;

class AutomaticCertificate
{
    public static function content(Registration $registration): array
    {
        $registration->loadMissing(['user', 'schedule']);
        $group = $registration->academicGroup();
        $title = trim((string) ($registration->schedule?->title ?? ''));
        $suffix = '';
        // Generated titles list every eligible group between the event label and date.
        if (preg_match('/^(Recollection)\s+[-\x{2013}\x{2014}]\s+.+\s+[-\x{2013}\x{2014}]\s+(\d{4}-\d{2}-\d{2})$/u', $title, $parts)) {
            $title = $parts[1];
            $suffix = $parts[2];
        }
        $year = preg_replace('/^(\d+)(?:st|nd|rd|th)?(?:\s+Year)?$/i', '$1', trim((string) ($group['year_level'] ?? '')));
        $studentGroup = trim(trim((string) ($group['course'] ?? '')).' '.trim($year.trim((string) ($group['section'] ?? ''))));
        $event = implode(' - ', array_filter([$title ?: 'Recollection', $studentGroup, $suffix], fn ($part) => $part !== ''));
        return [...CertificateTemplate::current(), 'year' => $registration->year,
            'name' => $registration->user?->name ?? $registration->student_snapshot['name'] ?? '',
            'student_id' => $registration->user?->student_id ?? $registration->student_snapshot['student_id'] ?? '',
            'date' => $registration->schedule?->date ?? '', 'venue' => $registration->schedule?->venue ?? '',
            'event' => $event];
    }

    public static function sync(Registration $registration): ?Certificate
    {
        // Caller holds the registration lock; issuance keeps its original snapshot for audit.
        $certificate = $registration->certificateRecord()->first();
        $eligible = $registration->attendance === 'Present' && $registration->attendance_verified_at && $registration->recollection_schedule_id;
        if (! $eligible) {
            if ($certificate && ! $certificate->revoked_at) {
                $certificate->update(['revoked_at' => now(), 'revoked_by' => auth()->id(), 'revocation_reason' => 'Attendance no longer qualifies.']);
                CfoService::audit('certificate.revoked', 'certificate', $certificate->id);
            }
            return null;
        }
        if ($certificate && ! $certificate->revoked_at) return $certificate;
        $certificate = Certificate::updateOrCreate(['registration_id' => $registration->id], [
            'certificate_number' => $certificate?->certificate_number ?? sprintf('CFO-%d-%06d', $registration->year, $registration->id),
            'snapshot' => self::content($registration), 'issued_by' => $registration->attendance_verified_by ?? auth()->id(),
            'revoked_at' => null, 'revoked_by' => null, 'revocation_reason' => null,
        ]);
        CfoService::audit('certificate.auto_issued', 'certificate', $certificate->id);
        CfoService::notifyStudent($registration->user_id, 'certificate_ready', 'Your '.$registration->year.' Recollection certificate is ready.', 'registration', $registration->id);
        return $certificate;
    }

    public static function syncEligible(?int $userId): void
    {
        Registration::where('attendance', 'Present')->whereNotNull('attendance_verified_at')->whereNotNull('recollection_schedule_id')
            ->when($userId, fn ($query) => $query->where('user_id', $userId))->whereDoesntHave('certificate')
            ->select('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) DB::transaction(fn () => self::sync(Registration::lockForUpdate()->findOrFail($row->id)), 3);
            });
    }
}
