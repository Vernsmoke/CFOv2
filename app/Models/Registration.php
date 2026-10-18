<?php
namespace App\Models;

use App\Services\CfoService;
use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    // Presentation labels for the existing receipt workflow; no separate status is stored.
    public const RECEIPT_LABELS = [
        'Verified' => ['Approved'],
        'Pending' => ['Pending', 'Not uploaded'],
        'Rejected' => ['Rejected'],
    ];

    public function getReceiptLabelAttribute(): string
    {
        foreach (self::RECEIPT_LABELS as $label => $statuses) {
            if (in_array($this->receipt_status, $statuses, true)) return $label;
        }
        return $this->receipt_status;
    }

    public function getStudentDisplayNameAttribute(): string
    {
        return $this->user?->last_name && $this->user?->first_name
            ? $this->user->last_name.', '.$this->user->first_name
            : ($this->user?->name ?? '');
    }

    protected $table = 'recollection_registrations';
    protected $guarded = ['id'];
    protected $hidden = ['receipt_path'];
    protected $casts = ['year' => 'integer', 'student_snapshot' => 'array', 'details' => 'array', 'attendance_recorded_at' => 'datetime', 'attendance_verified_at' => 'datetime', 'receipt_verified_at' => 'datetime'];
    protected $appends = ['has_receipt', 'is_archived'];
    public function getIsArchivedAttribute(): bool { return $this->archived_at !== null; }
    public function scopeActive($query) { return $query->whereNull('archived_at'); }
    public function getHasReceiptAttribute(): bool { return (bool) $this->receipt_path; }
    public function user() { return $this->belongsTo(User::class); }
    public function schedule() { return $this->belongsTo(Schedule::class, 'recollection_schedule_id'); }
    public function academicGroup(): array
    {
        $details = $this->details ?? [];
        $value = static fn ($value) => is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
        $course = $value($details['course'] ?? null);
        $year = $value($details['yearLevel'] ?? null);
        $section = $value($details['section'] ?? null);
        if ($course !== null && $year !== null && $section !== null) {
            return ['course' => $course, 'year_level' => $year, 'section' => $section];
        }

        $legacy = [];
        preg_match('/^(\d+)\s*[-\/]?\s*([A-Za-z]+)$/D', $value($details['yearSection'] ?? null) ?? '', $legacy);
        $snapshot = $this->student_snapshot ?? [];
        $snapshotLegacy = [];
        preg_match('/^(\d+)\s*[-\/]?\s*([A-Za-z]+)$/D', $value($snapshot['submitted_year_section'] ?? null) ?? '', $snapshotLegacy);
        $student = $this->user;
        return ['course' => $course ?? $value($snapshot['course'] ?? null) ?? $value($student?->course),
            'year_level' => $year ?? $legacy[1] ?? $snapshotLegacy[1] ?? $value($snapshot['year_level'] ?? null) ?? $value($student?->year_level),
            'section' => $section ?? (isset($legacy[2]) ? strtoupper($legacy[2]) : null)
                ?? (isset($snapshotLegacy[2]) ? strtoupper($snapshotLegacy[2]) : null) ?? $value($snapshot['section'] ?? null) ?? $value($student?->section)];
    }
    public function academicProfile(): array
    {
        $details = $this->details ?? [];
        $snapshot = $this->student_snapshot ?? [];
        $student = $this->user;
        $value = static fn ($value) => is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;

        return [
            'department' => $value($details['department'] ?? null) ?? $value($snapshot['department'] ?? null) ?? $value($student?->department),
            ...$this->academicGroup(),
            'major' => $value($details['major'] ?? null) ?? $value($snapshot['major'] ?? null) ?? $value($student?->major),
        ];
    }
    public function certificate() { return $this->hasOne(Certificate::class)->whereNull('revoked_at'); }
    public function certificateRecord() { return $this->hasOne(Certificate::class); }
    public function changes() { return $this->hasMany(ScheduleChange::class)->with(['currentSchedule', 'requestedSchedule', 'assignedSchedule']); }
    public function scopeCurrentYear($query) { return $query->active()->where('year', now()->year); }
    public function scopeHistorical($query) { return $query->whereNotNull('archived_at'); }

    public static function archivePastYears(): void
    {
        $count = \Illuminate\Support\Facades\DB::table('recollection_registrations')
            ->whereNull('archived_at')->where('year', '<', now()->year)
            ->update(['archived_at' => now(), 'archive_reason' => 'year_rollover']);
        if ($count) {
            foreach (['registrations', 'attendance', 'schedules', 'schedule_changes'] as $resource) CfoService::touch($resource);
            CfoService::audit('registrations.year_archived', 'registration', null, ['count' => $count, 'before_year' => now()->year]);
        }
    }

    protected static function booted(): void
    {
        static::creating(function (self $registration) {
            if ($registration->student_snapshot === null) {
                $student = $registration->user()->first();
                $details = $registration->details ?? [];
                $registration->student_snapshot = $student ? [
                    'name' => $details['name'] ?? $student->name,
                    'department' => $details['department'] ?? $student->department,
                    'student_id' => $student->student_id,
                    'course' => $details['course'] ?? $student->course,
                    'major' => $details['major'] ?? $student->major,
                    'year_level' => $details['yearLevel'] ?? $student->year_level,
                    'section' => $details['section'] ?? $student->section,
                    'submitted_year_section' => $details['yearSection'] ?? null,
                ] : null;
            }
        });
        static::created(function () { CfoService::touch('registrations'); });
        static::updated(function (self $registration) {
            CfoService::touch('registrations');
            if ($registration->wasChanged(['attendance', 'attendance_verified_by', 'attendance_verified_at'])) {
                CfoService::touch('attendance');
            }
            if ($registration->wasChanged('recollection_schedule_id')) CfoService::touch('schedules');
        });
        static::deleted(function (self $registration) { CfoService::touch('registrations'); CfoService::touch('attendance'); CfoService::touch('schedules'); });
    }
}
