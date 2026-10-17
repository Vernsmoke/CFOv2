<?php

namespace App\Http\Controllers;

use App\Models\{Registration, Schedule, ScheduleChange};
use App\Services\{AutomaticCertificate, CfoService as Cfo};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BatchWorkflowController extends Controller
{
    public function assign(Request $request, Registration $registration)
    {
        $input = $request->validate(['schedule_id' => 'required|integer|exists:recollection_schedules,id', 'confirm_attendance_reset' => 'sometimes|boolean']);
        DB::transaction(function () use ($input, $registration, $request) {
            $r = Registration::lockForUpdate()->findOrFail($registration->id);
            Cfo::require(! $r->is_archived, 'This registration is already in History.');
            Cfo::require((bool) $r->receipt_path, 'A receipt must be uploaded before scheduling.');
            if ($r->recollection_schedule_id === (int) $input['schedule_id']) return;
            Cfo::require($r->attendance === 'Not Yet Recorded' || ($input['confirm_attendance_reset'] ?? false), 'Confirm that changing the schedule clears this student\'s attendance and certificate eligibility.');
            $r->fill(['status' => 'Approved', 'rejection_reason' => null, 'receipt_status' => 'Approved',
                'receipt_reason' => null, 'receipt_verified_by' => $request->user()->id, 'receipt_verified_at' => now()]);
            Cfo::assign($r, (int) $input['schedule_id'], adminCorrection: true, adminAssignment: true);
            ScheduleChange::where('registration_id', $r->id)->where('status', 'Pending')
                ->update(['status' => 'Closed', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            Cfo::touch('schedule_changes');
            Cfo::notifyStudent($r->user_id, 'registration_approved', 'Your '.$r->year.' Recollection registration is approved and scheduled.', 'registration', $r->id);
        }, 3);
        return response()->json(['message' => 'Matagumpay na na-set ang schedule ng student. Makikita na siya sa Student Attendance.']);
    }

    public function attendance(Request $request, Registration $registration)
    {
        $input = $request->validate(['attendance' => 'required|in:Present,Absent', 'confirm_edit' => 'sometimes|boolean']);
        DB::transaction(function () use ($input, $registration, $request) {
            $r = Registration::lockForUpdate()->findOrFail($registration->id);
            Cfo::require((bool) $r->recollection_schedule_id && $r->status === 'Approved', 'Schedule this registration first.');
            Cfo::require($r->attendance === 'Not Yet Recorded' || $r->attendance === $input['attendance'] || ($input['confirm_edit'] ?? false), 'Confirm this attendance correction.');
            $changed = $r->attendance !== $input['attendance'];
            if ($changed || ! $r->attendance_verified_at) $r->update([
                'attendance' => $input['attendance'], 'attendance_recorded_by' => $request->user()->id,
                'attendance_recorded_at' => now(), 'attendance_verified_by' => $request->user()->id, 'attendance_verified_at' => now(),
            ]);
            AutomaticCertificate::sync($r);
            if ($changed) {
                Cfo::audit('attendance.marked', 'registration', $r->id, ['attendance' => $input['attendance']]);
                Cfo::notifyStudent($r->user_id, $input['attendance'] === 'Present' ? 'attendance_present' : 'attendance_absent',
                    'Your attendance was marked '.$input['attendance'].' for the '.$r->year.' Recollection.'.($input['attendance'] === 'Absent' && $r->year === now()->year ? ' Your registration form is open again. Resubmit it for CFO review and a new schedule.' : ''), 'registration', $r->id);
            }
        }, 3);
        return response()->json(['message' => 'Attendance saved. Certificate availability is updated automatically.']);
    }

    public function archive(Request $request, Schedule $schedule)
    {
        $input = $request->validate(['confirmed' => 'required|accepted', 'registration_ids' => 'required|array|min:1', 'registration_ids.*' => 'required|integer|distinct']);
        DB::transaction(function () use ($input, $schedule, $request) {
            $batch = Schedule::lockForUpdate()->findOrFail($schedule->id);
            Cfo::require(! $batch->finalized_at, 'This batch has already been moved to History.');
            $rows = $batch->registrations()->active()->orderBy('id')->lockForUpdate()->get();
            $expected = array_map('intval', $input['registration_ids']); sort($expected);
            Cfo::require($rows->isNotEmpty() && $rows->pluck('id')->all() === $expected, 'The batch membership changed. Refresh and review the complete batch before moving it.');
            Cfo::require($rows->every(fn ($r) => in_array($r->attendance, ['Present', 'Absent'], true)), 'Mark every student Present or Absent before moving this batch to History.');
            foreach ($rows as $r) {
                if (! $r->attendance_verified_at) $r->fill(['attendance_verified_at' => now(), 'attendance_verified_by' => $request->user()->id]);
                $r->fill(['archived_at' => now(), 'archived_by' => $request->user()->id, 'archive_reason' => 'batch_completed'])->save();
                AutomaticCertificate::sync($r);
            }
            $batch->update(['finalized_at' => now(), 'active' => false]);
            Cfo::touch('schedule_changes');
            Cfo::audit('batch.archived', 'schedule', $batch->id, ['registration_ids' => $expected]);
        }, 3);
        return response()->json(['message' => 'Nailipat na ang buong batch sa Recollection History. Lahat ng records ay retained.']);
    }
}
