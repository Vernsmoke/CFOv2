<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CfoService
{
    public const STUDENT_NOTIFICATION_TYPES = [
        'schedule_change_approved', 'schedule_change_rejected', 'registration_approved',
        'certificate_ready', 'attendance_present', 'attendance_absent', 'announcement', 'schedule_cancelled',
    ];

    public static function scheduleChangeRejectionMessage(?string $reason): string
    {
        return 'Your schedule change request was not approved. Reason: '.($reason ?: 'Please ask the CFO for the review details.').' Please visit the CFO office, Miraculous Building, near the chapel, for follow-up.';
    }

    public static function touch(string $resource): void
    {
        if (! DB::getSchemaBuilder()->hasTable('cfo_resource_versions')) {
            return;
        }

        DB::table('cfo_resource_versions')->where('resource', $resource)->increment('version', 1, [
            'updated_at' => now(),
        ]);
    }

    public static function audit(string $action, string $type, ?int $id, array $metadata = []): void
    {
        DB::table('activity_logs')->insert(['actor_id' => auth()->id(), 'action' => $action, 'record_type' => $type, 'record_id' => $id, 'metadata' => json_encode($metadata), 'created_at' => now()]);
        self::touch('activity_logs');
    }

    public static function notify(int $userId, string $message): void
    {
        DB::table('cfo_notifications')->insert(['user_id' => $userId, 'message' => $message, 'created_at' => now()]);
        self::touch('notifications');
    }

    public static function notifyStudent(int $userId, string $type, string $message, string $relatedType, int $relatedId): void
    {
        if (! in_array($type, self::STUDENT_NOTIFICATION_TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported student notification type.');
        }

        DB::table('cfo_notifications')->insert([
            'user_id' => $userId,
            'message' => $message,
            'type' => $type,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'created_at' => now(),
        ]);
        self::touch('notifications');
    }

    public static function notifyAdmins(string $message): void
    {
        foreach (User::whereIn('role', [User::ROLE_CFO_STAFF, User::ROLE_SUPER_ADMIN, User::ROLE_LEGACY_ADMIN])
            ->where('status', 'Approved')->pluck('id') as $id) {
            self::notify($id, $message);
        }
    }

    public static function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['request' => $message]);
        }
    }

    // First reason a registration cannot be assigned to a schedule, or null when it can.
    // assign() enforces this; the change-request screen uses it to list eligible schedules.
    public static function assignmentProblem(Registration $registration, Schedule $schedule, bool $adminCorrection = false, bool $adminAssignment = false): ?string
    {
        if ($registration->is_archived || $schedule->finalized_at) {
            return 'Archived records and finalized batches cannot be assigned.';
        }
        if (! ($registration->status === 'Approved' && $registration->receipt_status === 'Approved')) {
            return 'Approve the registration and receipt before assigning a schedule.';
        }
        if (! ($adminCorrection || ($registration->attendance === 'Not Yet Recorded' && ! $registration->certificate()->exists()))) {
            return 'Recorded attendance or an issued certificate prevents reassignment.';
        }
        if (! ($schedule->isOpen() && $schedule->date >= today()->toDateString())) {
            return 'Choose an Open upcoming schedule.';
        }
        if ($schedule->year !== $registration->year) {
            return 'The schedule must belong to the registration year.';
        }
        // Preserve the legacy administrator override only for legacy schedules.
        // A configured per-course scope must never manufacture a nonexistent group.
        $courseNames = $adminAssignment && ! $schedule->hasCourseSections() ? collect() : $schedule->courses->pluck('name');
        if ($courseNames->isNotEmpty()) {
            $group = $registration->academicGroup();
            if (! $courseNames->contains($group['course'])) {
                return 'This Recollection Day is not assigned to your course.';
            }
            if (! $schedule->includesYearLevel($group['year_level'])) {
                return 'This Recollection Day is not assigned to your year level.';
            }
            if (! $schedule->includesSectionForCourse($group['course'], $group['section'], $group['year_level'])) {
                return 'This Recollection Day is not assigned to your section.';
            }
        }
        $limit = $schedule->slot_limit ?? $schedule->capacity;
        if (! ($schedule->registrations()->active()->where('id', '!=', $registration->id)->count() < $limit)) {
            return 'This Recollection Day is full.';
        }

        return null;
    }

    // Open upcoming schedules an administrator may move this registration to.
    public static function eligibleChangeSchedules(Registration $registration)
    {
        return Schedule::withScheduleLinks()->open()
            ->where('id', '!=', $registration->recollection_schedule_id)
            ->where('date', '>=', today()->toDateString())
            ->orderBy('date')->orderBy('start_time')->get()
            ->filter(fn (Schedule $schedule) => self::assignmentProblem($registration, $schedule, false, true) === null)
            ->values();
    }

    // Caller holds the registration lock and a transaction. Lock schedules in ID order.
    public static function assign(Registration $registration, int $scheduleId, bool $adminCorrection = false, bool $adminAssignment = false): void
    {
        if ($adminAssignment) {
            self::require(auth()->user()?->hasOfficeAccess() === true, 'Only CFO staff can assign students across academic groups.');
        }
        $ids = array_filter([$registration->recollection_schedule_id, $scheduleId]);
        $schedules = Schedule::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        $schedule = $schedules->firstWhere('id', $scheduleId);
        self::require((bool) $schedule, 'Schedule not found.');
        $problem = self::assignmentProblem($registration, $schedule, $adminCorrection, $adminAssignment);
        self::require($problem === null, $problem ?? '');
        $old = $registration->recollection_schedule_id;
        $registration->update(['recollection_schedule_id' => $schedule->id, ...($adminCorrection ? [
            'attendance' => 'Not Yet Recorded', 'attendance_recorded_by' => null, 'attendance_recorded_at' => null,
            'attendance_verified_by' => null, 'attendance_verified_at' => null,
        ] : [])]);
        if ($adminCorrection) {
            AutomaticCertificate::sync($registration);
        }
        self::audit('schedule.assigned', 'registration', $registration->id, ['from' => $old, 'to' => $schedule->id]);
    }

    public static function settings(string $key, array $defaults = []): array
    {
        $value = DB::table('cfo_settings')->where('key', $key)->value('value');

        return $value ? json_decode($value, true) : $defaults;
    }
}
