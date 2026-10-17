<?php
namespace Tests\Feature;

use App\Models\{AcademicOption, Registration, Schedule, ScheduleChange, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class AdminAssignsChangeRequestScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'Student'): User
    {
        return User::factory()->create(['first_name' => 'Test', 'last_name' => $role, 'role' => $role, 'status' => 'Approved', 'password' => 'CfoTest!234']);
    }

    private function schedule(array $extra = []): Schedule
    {
        return Schedule::create([...['title' => 'Test Recollection', 'date' => today()->toDateString(), 'start_time' => '08:00', 'end_time' => '16:00', 'venue' => 'Test Hall', 'capacity' => 2, 'active' => true], ...$extra]);
    }

    public function test_student_sends_only_a_reason_and_admin_assigns_the_new_schedule(): void
    {
        $admin = $this->account('Admin');
        $student = $this->account();
        $current = $this->schedule(['title' => 'Recollection 1']);
        $other = $this->schedule(['title' => 'Recollection 2']);
        $registration = Registration::create(['user_id' => $student->id, 'year' => now()->year, 'details' => [], 'status' => 'Approved', 'receipt_status' => 'Approved', 'recollection_schedule_id' => $current->id]);

        $this->actingAs($student)->postJson('/api/change-requests', ['registration_id' => $registration->id, 'reason' => 'Exam on the same day'])->assertCreated();
        $change = ScheduleChange::firstOrFail();
        $this->assertNull($change->requested_schedule_id);
        $this->assertSame('Pending', $change->status);

        // The admin screen is told which schedules may be assigned.
        $eligible = $this->actingAs($admin)->getJson('/api/admin/state')->assertOk()->json('requests.0.eligible_schedules');
        $this->assertSame([$other->id], array_column($eligible, 'id'));

        $url = '/api/admin/change-requests/'.$change->id;
        $this->patchJson($url, ['status' => 'Approved'])->assertUnprocessable();
        $this->patchJson($url, ['status' => 'Approved', 'assigned_schedule_id' => $current->id])->assertUnprocessable();
        $this->assertSame($current->id, $registration->fresh()->recollection_schedule_id);

        $this->patchJson($url, ['status' => 'Approved', 'assigned_schedule_id' => $other->id, 'review_reason' => 'Verified conflict'])->assertOk();
        $this->assertSame($other->id, $registration->fresh()->recollection_schedule_id);
        $this->assertSame($other->id, $change->fresh()->assigned_schedule_id);
        $this->assertSame('Approved', $change->fresh()->status);
        $this->getJson('/api/admin/state?resources=schedule_changes')->assertOk()
            ->assertJsonPath('requests.0.review_reason', 'Verified conflict');
        $this->actingAs($student)->getJson('/api/student?resources=schedule_changes')->assertOk()
            ->assertJsonMissingPath('requests.0.review_reason')
            ->assertJsonPath('requests.0.assigned_schedule.id', $other->id);
    }

    public function test_rejecting_needs_a_reason_and_keeps_the_current_schedule(): void
    {
        $admin = $this->account('Admin');
        $student = $this->account();
        $current = $this->schedule();
        $registration = Registration::create(['user_id' => $student->id, 'year' => now()->year, 'details' => [], 'status' => 'Approved', 'receipt_status' => 'Approved', 'recollection_schedule_id' => $current->id]);
        $this->actingAs($student)->postJson('/api/change-requests', ['registration_id' => $registration->id, 'reason' => 'Family event'])->assertCreated();
        $url = '/api/admin/change-requests/'.ScheduleChange::firstOrFail()->id;

        $this->actingAs($admin)->patchJson($url, ['status' => 'Rejected'])->assertUnprocessable();
        $this->patchJson($url, ['status' => 'Rejected', 'review_reason' => 'Please visit the CFO office.'])->assertOk();
        $this->assertSame($current->id, $registration->fresh()->recollection_schedule_id);
        $this->assertNull(ScheduleChange::firstOrFail()->assigned_schedule_id);
        $this->actingAs($student)->getJson('/api/student?resources=schedule_changes')->assertOk()
            ->assertJsonPath('requests.0.review_reason', 'Please visit the CFO office.');
    }

    private function assignedRegistration(User $student, Schedule $schedule, array $details = []): Registration
    {
        return Registration::create(['user_id' => $student->id, 'year' => now()->year, 'details' => $details,
            'status' => 'Approved', 'receipt_status' => 'Approved', 'recollection_schedule_id' => $schedule->id]);
    }

    private function scopedSchedule(AcademicOption $course, array $extra = [], array $sections = ['3' => ['B']]): Schedule
    {
        $schedule = $this->schedule(['year_levels' => array_map('strval', array_keys($sections)),
            'sections' => array_values(array_unique(array_merge(...array_values($sections)))), ...$extra]);
        $schedule->courses()->attach($course->id, ['sections' => $sections]);
        return $schedule;
    }

    public function test_admin_eligibility_and_approval_use_submitted_academic_group_and_recheck_availability(): void
    {
        $admin = $this->account('Admin');
        $student = $this->account();
        $student->update(['course' => 'BSA', 'year_level' => '1', 'section' => 'A']);
        $course = AcademicOption::firstOrCreate(['type' => 'course', 'name' => 'BSIT']);
        $profileCourse = AcademicOption::firstOrCreate(['type' => 'course', 'name' => 'BSA']);
        $current = $this->scopedSchedule($course);
        $target = $this->scopedSchedule($course, ['title' => 'Submitted BSIT 3B group']);
        $wrongSection = $this->scopedSchedule($course, [], ['3' => ['A']]);
        $wrongProfile = $this->scopedSchedule($profileCourse, [], ['1' => ['A']]);
        $unavailable = [
            $this->scopedSchedule($course, ['status' => 'Draft']),
            $this->scopedSchedule($course, ['status' => 'Closed']),
            $this->scopedSchedule($course, ['status' => 'Cancelled']),
            $this->scopedSchedule($course, ['date' => today()->subDay()->toDateString()]),
            $this->scopedSchedule($course, ['date' => today()->addYear()->startOfYear()->toDateString()]),
            $this->scopedSchedule($course, ['finalized_at' => now()]),
        ];
        $full = $this->scopedSchedule($course, ['slot_limit' => 1]);
        $this->assignedRegistration($this->account(), $full);
        $registration = $this->assignedRegistration($student, $current,
            ['course' => 'BSIT', 'yearLevel' => '3', 'section' => 'B', 'yearSection' => '3B']);
        $this->actingAs($student)->postJson('/api/change-requests',
            ['registration_id' => $registration->id, 'reason' => 'Exam conflict'])->assertCreated();
        $change = ScheduleChange::firstOrFail();

        $eligible = $this->actingAs($admin)->getJson('/api/admin/state?resources=schedule_changes')->assertOk()->json('requests.0.eligible_schedules');
        $this->assertSame([$target->id], array_column($eligible, 'id'));
        $this->assertSame(2, $eligible[0]['slots_left']);
        $url = '/api/admin/change-requests/'.$change->id;
        foreach ([$wrongSection, $wrongProfile, $full, ...$unavailable] as $invalid) {
            $this->patchJson($url, ['status' => 'Approved', 'assigned_schedule_id' => $invalid->id])->assertUnprocessable();
            $this->assertSame('Pending', $change->fresh()->status);
            $this->assertSame($current->id, $registration->fresh()->recollection_schedule_id);
        }

        // The selection displayed to the admin can become unavailable before review.
        $target->update(['status' => 'Closed']);
        $this->patchJson($url, ['status' => 'Approved', 'assigned_schedule_id' => $target->id])->assertUnprocessable();
        $target->update(['status' => 'Open', 'slot_limit' => 1]);
        $occupant = $this->assignedRegistration($this->account(), $target);
        $this->patchJson($url, ['status' => 'Approved', 'assigned_schedule_id' => $target->id])->assertUnprocessable();
        $occupant->update(['archived_at' => now(), 'archive_reason' => 'year_rollover']);
        $this->patchJson($url, ['status' => 'Approved', 'assigned_schedule_id' => $target->id])->assertOk();
        $this->assertSame($target->id, $registration->fresh()->recollection_schedule_id);
        $this->assertSame('BSA', $student->fresh()->course);
    }

    public function test_legacy_requests_allow_fallback_or_a_different_admin_assignment_and_notify_once(): void
    {
        $admin = $this->account('Admin');
        $current = $this->schedule(['title' => 'Original assignment']);
        $requested = $this->schedule(['title' => 'Student preferred day']);
        $assigned = $this->schedule(['title' => 'Admin selected day']);
        foreach ([false, true] as $adminChooses) {
            $student = $this->account();
            $registration = $this->assignedRegistration($student, $current);
            $this->actingAs($student)->postJson('/api/change-requests', ['registration_id' => $registration->id,
                'requested_schedule_id' => $requested->id, 'reason' => 'Legacy client conflict'])->assertCreated();
            $change = ScheduleChange::where('registration_id', $registration->id)->firstOrFail();
            $url = '/api/admin/change-requests/'.$change->id;
            $payload = ['status' => 'Approved', ...($adminChooses ? ['assigned_schedule_id' => $assigned->id] : [])];
            $this->actingAs($admin)->patchJson($url, $payload)->assertOk();
            $chosen = $adminChooses ? $assigned : $requested;
            $this->assertSame($requested->id, $change->fresh()->requested_schedule_id);
            $this->assertSame($chosen->id, $change->fresh()->assigned_schedule_id);
            $this->assertSame($chosen->id, $registration->fresh()->recollection_schedule_id);
            $notice = DB::table('cfo_notifications')->where('user_id', $student->id)->where('type', 'schedule_change_approved')->first();
            $this->assertStringContainsString($chosen->title, $notice->message);
            $this->assertStringContainsString($chosen->date, $notice->message);
            $this->patchJson($url, $payload)->assertUnprocessable();
            $this->assertSame(1, DB::table('cfo_notifications')->where('user_id', $student->id)->where('type', 'schedule_change_approved')->count());
            $this->actingAs($student)->getJson('/api/student?resources=schedule_changes')->assertOk()
                ->assertJsonPath('requests.0.assigned_schedule.id', $chosen->id)
                ->assertJsonPath('requests.0.student_message', $notice->message);
        }
    }

    public function test_assigned_request_link_is_retained_in_history_and_prevents_schedule_deletion(): void
    {
        $admin = $this->account('Admin');
        $student = $this->account();
        $current = $this->schedule();
        $assigned = $this->schedule(['title' => 'Approved change assignment']);
        $later = $this->schedule();
        $registration = $this->assignedRegistration($student, $current);
        $this->actingAs($student)->postJson('/api/change-requests',
            ['registration_id' => $registration->id, 'reason' => 'Class conflict'])->assertCreated();
        $change = ScheduleChange::firstOrFail();
        $this->actingAs($admin)->patchJson('/api/admin/change-requests/'.$change->id,
            ['status' => 'Approved', 'assigned_schedule_id' => $assigned->id, 'review_reason' => 'Internal approval note'])->assertOk();
        $this->patchJson('/api/admin/registrations/'.$registration->id, ['schedule_id' => $later->id])->assertOk();

        // The approved request is now the only link to this schedule.
        $this->assertSame(0, $assigned->registrations()->count());
        $this->assertTrue($assigned->fresh()->has_linked_records);
        $this->assertTrue(Schedule::withScheduleLinks()->findOrFail($assigned->id)->has_linked_records);
        $this->deleteJson('/api/admin/schedules/'.$assigned->id)->assertUnprocessable();
        $this->assertDatabaseHas('recollection_schedules', ['id' => $assigned->id]);
        $registration->update(['archived_at' => now(), 'archive_reason' => 'year_rollover']);
        $this->actingAs($student)->getJson('/api/history/'.$registration->id)->assertOk()
            ->assertJsonPath('registration.changes.0.current_schedule.id', $current->id)
            ->assertJsonPath('registration.changes.0.assigned_schedule.id', $assigned->id)
            ->assertJsonMissingPath('registration.changes.0.review_reason');
        $this->actingAs($admin)->getJson('/api/history/'.$registration->id)->assertOk()
            ->assertJsonPath('registration.changes.0.review_reason', 'Internal approval note');
        $this->artisan('cfo:audit-database')->assertSuccessful();
    }

    public function test_migration_preserves_legacy_approvals_and_assignments_when_reversed(): void
    {
        $current = $this->schedule();
        $requested = $this->schedule();
        $assigned = $this->schedule();
        $legacy = ScheduleChange::create(['registration_id' => $this->assignedRegistration($this->account(), $requested)->id,
            'current_schedule_id' => $current->id, 'requested_schedule_id' => $requested->id,
            'status' => 'Approved', 'reason' => 'Legacy approved request']);
        $approved = ScheduleChange::create(['registration_id' => $this->assignedRegistration($this->account(), $assigned)->id,
            'current_schedule_id' => $current->id, 'assigned_schedule_id' => $assigned->id,
            'status' => 'Approved', 'reason' => 'Admin approved request']);
        $pending = ScheduleChange::create(['registration_id' => $this->assignedRegistration($this->account(), $current)->id,
            'current_schedule_id' => $current->id, 'reason' => 'Reason-only pending request']);
        $migration = require database_path('migrations/2026_10_10_000001_let_admin_assign_change_request_schedule.php');

        $migration->up();
        $this->assertSame($requested->id, $legacy->fresh()->assigned_schedule_id);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('schedule_change_requests', 'assigned_schedule_id'));
        $this->assertSame($assigned->id, $approved->fresh()->requested_schedule_id);
        $this->assertSame($current->id, $pending->fresh()->requested_schedule_id);

        $migration->up();
        $this->assertSame($requested->id, $legacy->fresh()->assigned_schedule_id);
        $this->assertSame($assigned->id, $approved->fresh()->assigned_schedule_id);
        $this->assertNull($pending->fresh()->assigned_schedule_id);
        $this->assertDatabaseCount('schedule_change_requests', 3);
    }
}
