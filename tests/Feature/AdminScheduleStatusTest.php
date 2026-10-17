<?php

namespace Tests\Feature;

use App\Models\{AcademicOption, Registration, Schedule, ScheduleChange, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminScheduleStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private AcademicOption $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->course = AcademicOption::create(['type' => 'course', 'name' => 'BSIT']);
        $this->actingAs($this->admin);
    }

    private function input(array $changes = []): array
    {
        return [...[
            'date' => today()->addDays(3)->toDateString(),
            'start_time' => '08:00', 'end_time' => '17:00', 'venue' => 'Chapel',
            'course_ids' => [$this->course->id], 'year_level' => '3', 'section' => 'A',
            'slot_limit' => null, 'status' => 'Open',
        ], ...$changes];
    }

    private function day(array $changes = []): Schedule
    {
        $input = $this->input($changes);
        unset($input['course_ids']);
        $day = Schedule::create(['title' => 'Formation day', ...$input, 'capacity' => $input['slot_limit'] ?? 100000]);
        $day->courses()->attach($this->course->id);
        return $day;
    }

    private function student(): User
    {
        return User::factory()->create([
            'role' => 'Student', 'status' => 'Approved', 'course' => 'BSIT', 'year_level' => '3', 'section' => 'A',
        ]);
    }

    private function registration(User $student, ?Schedule $day = null, array $changes = []): Registration
    {
        return Registration::create([...[
            'user_id' => $student->id, 'year' => now()->year, 'status' => 'Approved', 'receipt_status' => 'Approved',
            'receipt_path' => 'existing-receipt.png', 'details' => ['religion' => 'Catholic', 'medical' => 'Keep this submitted value'],
            'recollection_schedule_id' => $day?->id,
        ], ...$changes]);
    }

    private function version(string $resource): int
    {
        return (int) DB::table('cfo_resource_versions')->where('resource', $resource)->value('version');
    }

    private function transition(Schedule $day, string $status)
    {
        return $this->patchJson('/api/admin/schedules/'.$day->id.'/status', compact('status'));
    }

    public function test_status_migration_backfills_only_the_new_column_and_preserves_old_records(): void
    {
        $open = $this->day();
        $closed = $this->day(['status' => 'Closed', 'date' => today()->addDays(4)->toDateString()]);
        $completed = $this->day(['status' => 'Closed', 'finalized_at' => now(), 'date' => today()->subDay()->toDateString()]);
        $this->registration($this->student(), $completed, ['archived_at' => now(), 'archive_reason' => 'batch_finalized']);
        $migration = require database_path('migrations/2026_10_09_000002_add_recollection_schedule_status.php');
        $migration->down();
        $before = DB::table('recollection_schedules')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $registrations = DB::table('recollection_registrations')->get()->toJson();
        $pivots = DB::table('recollection_schedule_courses')->get()->toJson();
        $versions = DB::table('cfo_resource_versions')->get()->toJson();
        $migration->up();
        $after = DB::table('recollection_schedules')->orderBy('id')->get()->map(function ($row) {
            $values = (array) $row;
            unset($values['status']);
            return $values;
        })->all();
        $this->assertSame($before, $after, 'No pre-existing schedule column or timestamp may change.');
        $this->assertDatabaseHas('recollection_schedules', ['id' => $open->id, 'status' => 'Open', 'active' => true]);
        foreach ([$closed, $completed] as $day) $this->assertDatabaseHas('recollection_schedules', ['id' => $day->id, 'status' => 'Closed', 'active' => false]);
        $this->assertSame($registrations, DB::table('recollection_registrations')->get()->toJson());
        $this->assertSame($pivots, DB::table('recollection_schedule_courses')->get()->toJson());
        $this->assertSame($versions, DB::table('cfo_resource_versions')->get()->toJson());
        $this->assertSame('Completed', $completed->fresh()->display_status);
    }

    public function test_save_as_draft_persists_real_fields_without_exposing_it_to_students(): void
    {
        $version = $this->version('schedules');
        $input = $this->input(['status' => 'Draft', 'theme' => 'Hope', 'facilitator' => 'CFO Team', 'description' => "A real description\nSecond line"]);
        $response = $this->postJson('/api/admin/schedules', $input)->assertOk()
            ->assertJsonPath('status', 'Draft')->assertJsonPath('display_status', 'Draft')->assertJsonPath('active', false)
            ->assertJsonPath('slot_limit', null)->assertJsonMissingPath('theme')->assertJsonMissingPath('facilitator')
            ->assertJsonMissingPath('description')->assertJsonCount(1, 'courses');
        $this->assertDatabaseHas('recollection_schedules', ['id' => $response->json('id'), 'theme' => null, 'facilitator' => null, 'description' => null]);
        $this->assertDatabaseCount('recollection_schedules', 1);
        $this->assertGreaterThan($version, $this->version('schedules'));
        $this->assertDatabaseHas('activity_logs', ['actor_id' => $this->admin->id, 'action' => 'schedule.saved', 'record_id' => $response->json('id')]);
        $this->actingAs($this->student())->getJson('/api/student?resources=schedules')->assertOk()->assertJsonCount(0, 'schedules');
    }

    public function test_publish_draft_close_and_reopen_update_availability_audit_and_live_versions(): void
    {
        $day = $this->day(['status' => 'Draft']);
        $student = $this->student();
        foreach (['Open', 'Closed', 'Open'] as $status) {
            $this->actingAs($this->admin);
            $version = $this->version('schedules');
            $auditVersion = $this->version('activity_logs');
            $this->transition($day, $status)->assertOk()->assertJsonPath('status', $status)->assertJsonPath('active', $status === 'Open');
            $this->assertGreaterThan($version, $this->version('schedules'));
            $this->assertGreaterThan($auditVersion, $this->version('activity_logs'));
            $this->actingAs($student)->getJson('/api/student?resources=schedules')->assertOk()->assertJsonCount($status === 'Open' ? 1 : 0, 'schedules');
        }
        $this->assertDatabaseCount('activity_logs', 3);
        $this->assertDatabaseCount('cfo_notifications', 0);
        $this->assertDatabaseCount('recollection_schedules', 1);
    }

    public function test_publish_button_can_create_an_open_schedule_with_optional_capacity(): void
    {
        $this->postJson('/api/admin/schedules', $this->input(['slot_limit' => 25]))->assertOk()
            ->assertJsonPath('status', 'Open')->assertJsonPath('active', true)->assertJsonPath('capacity', 25)->assertJsonPath('slot_limit', 25);
        $this->actingAs($this->student())->getJson('/api/student?resources=schedules')->assertOk()->assertJsonCount(1, 'schedules');
    }

    public function test_cancelling_preserves_registration_receipt_snapshot_and_pending_request_and_notifies_once(): void
    {
        $day = $this->day();
        $replacement = $this->day(['date' => today()->addDays(5)->toDateString()]);
        $student = $this->student();
        $registration = $this->registration($student, $day);
        $request = ScheduleChange::create([
            'registration_id' => $registration->id, 'current_schedule_id' => $day->id,
            'requested_schedule_id' => $replacement->id, 'reason' => 'Class conflict',
        ]);
        $before = $registration->fresh()->getRawOriginal();
        $requestBefore = $request->fresh()->getRawOriginal();
        $version = $this->version('notifications');
        $this->transition($day, 'Cancelled')->assertOk()->assertJsonPath('status', 'Cancelled')->assertJsonPath('active', false);
        $this->assertSame($before, $registration->fresh()->getRawOriginal());
        $this->assertSame($requestBefore, $request->fresh()->getRawOriginal());
        $this->assertDatabaseCount('recollection_schedules', 2);
        $this->assertDatabaseHas('cfo_notifications', [
            'user_id' => $student->id, 'type' => 'schedule_cancelled', 'related_type' => 'registration', 'related_id' => $registration->id,
        ]);
        $this->assertGreaterThan($version, $this->version('notifications'));
        $auditCount = DB::table('activity_logs')->count();
        $this->transition($day, 'Cancelled')->assertOk();
        $this->assertDatabaseCount('cfo_notifications', 1);
        $this->assertSame($auditCount, DB::table('activity_logs')->count());
        $state = $this->actingAs($student)->getJson('/api/student?resources=registrations,schedules,notifications')->assertOk()
            ->assertJsonPath('registrations.0.recollection_schedule_id', $day->id)
            ->assertJsonPath('notifications.0.type', 'schedule_cancelled');
        $message = $state->json('notifications.0.message');
        $this->assertStringContainsString($day->title, $message);
        $this->assertStringContainsString($day->date, $message);
        $this->assertStringContainsString('CFO', $message);
        $this->assertNotContains($day->id, array_column($state->json('schedules'), 'id'));
    }

    public function test_existing_reassign_flow_can_move_a_student_from_cancelled_schedule_without_losing_registration_data(): void
    {
        $day = $this->day();
        $replacement = $this->day(['date' => today()->addDays(5)->toDateString()]);
        $registration = $this->registration($this->student(), $day);
        $snapshot = $registration->student_snapshot;
        $details = $registration->details;
        $this->transition($day, 'Cancelled')->assertOk();
        $this->postJson('/api/admin/registrations/'.$registration->id.'/schedule', ['schedule_id' => $replacement->id])->assertOk();
        $this->assertSame($replacement->id, $registration->fresh()->recollection_schedule_id);
        $this->assertSame($snapshot, $registration->fresh()->student_snapshot);
        $this->assertSame($details, $registration->fresh()->details);
        $this->assertSame('existing-receipt.png', $registration->fresh()->receipt_path);
        $this->assertDatabaseCount('recollection_registrations', 1);
    }

    public function test_cancelled_and_finalized_schedules_cannot_be_reopened_edited_or_deleted(): void
    {
        $cancelled = $this->day(['status' => 'Cancelled']);
        $completed = $this->day(['status' => 'Closed', 'finalized_at' => now(), 'date' => today()->addDays(4)->toDateString()]);
        foreach ([$cancelled, $completed] as $day) {
            $before = $day->fresh()->getRawOriginal();
            $this->transition($day, 'Open')->assertUnprocessable();
            $this->transition($day, 'Draft')->assertUnprocessable();
            $this->putJson('/api/admin/schedules/'.$day->id, $this->input(['date' => $day->date, 'title' => 'Changed title']))->assertUnprocessable();
            $this->deleteJson('/api/admin/schedules/'.$day->id)->assertUnprocessable();
            $this->assertSame($before, $day->fresh()->getRawOriginal());
        }
        $this->assertSame('Completed', $completed->fresh()->display_status);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_only_allowed_status_transitions_are_accepted_and_cannot_write_computed_completed(): void
    {
        foreach ([['Draft', 'Closed'], ['Open', 'Draft'], ['Closed', 'Draft'], ['Open', 'Completed'], ['Open', 'Bogus']] as [$from, $to]) {
            $day = $this->day(['status' => $from]);
            $this->transition($day, $to)->assertUnprocessable();
            $this->assertSame($from, $day->fresh()->status);
            $this->assertSame($from === 'Open', $day->fresh()->active);
        }
        foreach (['Cancelled', 'Completed', 'Bogus'] as $status) $this->postJson('/api/admin/schedules', $this->input(compact('status')))->assertUnprocessable();
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_delete_protection_covers_registrations_archived_records_and_both_request_links(): void
    {
        $registered = $this->day(['status' => 'Draft']);
        $archived = $this->day(['status' => 'Draft']);
        $source = $this->day(['status' => 'Draft']);
        $target = $this->day(['status' => 'Draft']);
        $this->registration($this->student(), $registered);
        $this->registration($this->student(), $archived, ['archived_at' => now(), 'archive_reason' => 'year_rollover']);
        $unassigned = $this->registration($this->student());
        ScheduleChange::create(['registration_id' => $unassigned->id, 'current_schedule_id' => $source->id, 'requested_schedule_id' => $target->id, 'reason' => 'Existing request']);
        foreach ([$registered, $archived, $source, $target] as $day) {
            $this->deleteJson('/api/admin/schedules/'.$day->id)->assertUnprocessable();
            $this->assertDatabaseHas('recollection_schedules', ['id' => $day->id]);
        }
        $this->assertDatabaseCount('recollection_registrations', 3);
        $this->assertDatabaseCount('schedule_change_requests', 1);
        $this->getJson('/api/admin/state?resources=schedules')->assertOk()->assertJsonCount(4, 'schedules');
        foreach ([$registered, $archived, $source, $target] as $day) {
            $row = collect($this->getJson('/api/admin/state?resources=schedules')->json('schedules'))->firstWhere('id', $day->id);
            $this->assertTrue($row['has_linked_records']);
        }
    }

    public function test_unused_draft_can_be_deleted_and_advances_audit_and_schedule_versions(): void
    {
        $day = $this->day(['status' => 'Draft']);
        $version = $this->version('schedules');
        $this->deleteJson('/api/admin/schedules/'.$day->id)->assertOk();
        $this->assertDatabaseMissing('recollection_schedules', ['id' => $day->id]);
        $this->assertDatabaseCount('recollection_schedule_courses', 0);
        $this->assertGreaterThan($version, $this->version('schedules'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'schedule.deleted', 'record_id' => $day->id]);
    }

    public function test_students_cannot_choose_non_open_or_finalized_schedules_even_if_legacy_active_is_stale(): void
    {
        $student = $this->student();
        $registration = $this->registration($student);
        $days = [];
        foreach (['Draft', 'Closed', 'Cancelled'] as $status) {
            $day = $this->day(compact('status'));
            DB::table('recollection_schedules')->where('id', $day->id)->update(['active' => true]);
            $days[] = $day;
        }
        $days[] = $this->day(['status' => 'Open', 'finalized_at' => now()]);
        $this->actingAs($student)->getJson('/api/student?resources=schedules')->assertOk()->assertJsonCount(0, 'schedules');
        foreach ($days as $day) $this->postJson('/api/registrations/'.$registration->id.'/schedule', ['schedule_id' => $day->id])->assertUnprocessable();
        $this->assertNull($registration->fresh()->recollection_schedule_id);
    }

    public function test_change_requests_cannot_target_closed_draft_cancelled_or_finalized_schedules(): void
    {
        $current = $this->day();
        $student = $this->student();
        $registration = $this->registration($student, $current);
        foreach (['Draft', 'Closed', 'Cancelled', 'Completed'] as $status) {
            $target = $this->day(['status' => $status === 'Completed' ? 'Open' : $status, ...($status === 'Completed' ? ['finalized_at' => now()] : [])]);
            $this->actingAs($student)->postJson('/api/change-requests', [
                'registration_id' => $registration->id, 'requested_schedule_id' => $target->id, 'reason' => 'Class conflict',
            ])->assertUnprocessable();
        }
        $this->assertDatabaseCount('schedule_change_requests', 0);
        $this->assertSame($current->id, $registration->fresh()->recollection_schedule_id);
    }

    public function test_active_compatibility_remains_in_sync_without_overriding_explicit_status(): void
    {
        $legacy = $this->input(['active' => false]);
        unset($legacy['status']);
        $response = $this->postJson('/api/admin/schedules', $legacy)->assertOk()->assertJsonPath('status', 'Closed')->assertJsonPath('active', false);
        $day = Schedule::findOrFail($response->json('id'));
        $day->update(['active' => true]);
        $this->assertSame('Open', $day->fresh()->status);
        $day->update(['active' => false]);
        $this->assertSame('Closed', $day->fresh()->status);
        $day->update(['status' => 'Draft', 'active' => true]);
        $this->assertFalse($day->fresh()->active);
        $this->assertSame('Draft', $day->fresh()->status);
        $day->update(['status' => 'Draft', 'active' => true]);
        $this->assertFalse($day->fresh()->active, 'Explicit unchanged status still takes precedence over active.');
        $this->assertSame('Draft', $day->fresh()->status);
    }

    public function test_drafts_still_require_complete_valid_fields_and_conflicts_are_not_bypassed(): void
    {
        foreach ([['venue' => ''], ['end_time' => '07:00'], ['course_ids' => []], ['year_level' => ''], ['section' => '']] as $changes) {
            $this->postJson('/api/admin/schedules', $this->input(['status' => 'Draft', ...$changes]))->assertUnprocessable();
        }
        $this->postJson('/api/admin/schedules', $this->input(['status' => 'Draft']))->assertOk();
        $this->postJson('/api/admin/schedules', $this->input(['status' => 'Open']))->assertUnprocessable();
        $this->assertDatabaseCount('recollection_schedules', 1);
        $this->assertDatabaseCount('activity_logs', 1);
    }

    public function test_status_actions_are_admin_only(): void
    {
        $day = $this->day();
        $this->actingAs($this->student());
        $this->transition($day, 'Cancelled')->assertForbidden();
        $this->deleteJson('/api/admin/schedules/'.$day->id)->assertForbidden();
        $this->assertSame('Open', $day->fresh()->status);
    }
}
