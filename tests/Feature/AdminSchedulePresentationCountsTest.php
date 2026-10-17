<?php

namespace Tests\Feature;

use App\Models\{AcademicOption, Registration, Schedule, ScheduleChange, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSchedulePresentationCountsTest extends TestCase
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
            'title' => 'Schedule presentation', 'date' => today()->addDays(3)->toDateString(),
            'start_time' => '08:00', 'end_time' => '17:00', 'venue' => 'Chapel',
            'course_ids' => [$this->course->id], 'year_level' => '3', 'section' => 'A',
            'slot_limit' => 10, 'status' => 'Draft',
        ], ...$changes];
    }

    private function day(array $changes = []): Schedule
    {
        $input = $this->input($changes);
        unset($input['course_ids']);
        $day = Schedule::create([...$input, 'capacity' => $input['slot_limit'] ?? 100000]);
        $day->courses()->attach($this->course->id);
        return $day;
    }

    private function registration(Schedule $day, array $changes = []): Registration
    {
        $student = User::factory()->create([
            'role' => 'Student', 'status' => 'Approved', 'course' => 'BSIT', 'year_level' => '3', 'section' => 'A',
        ]);
        return Registration::create([...[
            'user_id' => $student->id, 'year' => now()->year, 'details' => ['religion' => 'Catholic'],
            'recollection_schedule_id' => $day->id, 'status' => 'Approved', 'attendance' => 'Not Yet Recorded',
        ], ...$changes]);
    }

    private function change(Registration $registration, Schedule $current, Schedule $requested, string $status): void
    {
        ScheduleChange::create([
            'registration_id' => $registration->id, 'current_schedule_id' => $current->id,
            'requested_schedule_id' => $requested->id, 'reason' => 'Existing request', 'status' => $status,
        ]);
    }

    private function recordSnapshot(): array
    {
        return collect([
            'recollection_schedules', 'recollection_schedule_courses', 'recollection_registrations',
            'schedule_change_requests', 'cfo_resource_versions', 'activity_logs', 'cfo_notifications',
        ])->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->toJson()])->all();
    }

    public function test_state_returns_separate_active_total_attendance_and_pending_counts_without_changing_records(): void
    {
        $target = $this->day();
        $other = $this->day(['date' => today()->addDays(4)->toDateString()]);
        $empty = $this->day(['date' => today()->addDays(5)->toDateString()]);
        $active = $this->registration($target);
        $this->registration($target, ['attendance' => 'Present']);
        $this->registration($target, ['attendance' => 'Absent', 'archived_at' => now(), 'archive_reason' => 'batch_finalized']);
        $this->registration($target, ['archived_at' => now(), 'archive_reason' => 'batch_finalized']);
        $this->registration($other, ['attendance' => 'Present']);
        foreach (['Pending', 'Approved', 'Rejected', 'Closed'] as $status) {
            $this->change($active, $target, $other, $status);
            $this->change($active, $other, $target, $status);
        }
        $before = $this->recordSnapshot();

        foreach (['schedules', 'registrations', 'schedule_changes'] as $resource) {
            $response = $this->getJson('/api/admin/state?resources='.$resource)->assertOk();
            $days = collect($response->json('schedules'))->keyBy('id');
            $day = $days->get($target->id);
            $this->assertSame(2, $day['registrations_count']);
            $this->assertSame(4, $day['registrations_total_count']);
            $this->assertSame(2, $day['registrations_recorded_attendance_count'], 'Archived recorded attendance still locks existing events.');
            $this->assertSame(4, $day['current_change_requests_count'], 'Existing all-request count must remain unchanged.');
            $this->assertSame(4, $day['requested_change_requests_count']);
            $this->assertSame(1, $day['current_pending_change_requests_count']);
            $this->assertSame(1, $day['requested_pending_change_requests_count']);
            $this->assertTrue($day['has_linked_records']);
            foreach (['registrations_count', 'registrations_total_count', 'registrations_recorded_attendance_count', 'current_change_requests_count', 'requested_change_requests_count', 'current_pending_change_requests_count', 'requested_pending_change_requests_count'] as $field) {
                $this->assertSame(0, $days->get($empty->id)[$field]);
            }
            $this->assertFalse($days->get($empty->id)['has_linked_records']);
        }

        $this->assertSame($before, $this->recordSnapshot(), 'Loading presentation counts must not edit schedules, registrations, requests, versions, audits, or notifications.');
    }

    public function test_resolved_request_links_still_protect_deletion_without_being_counted_as_pending(): void
    {
        $source = $this->day();
        $target = $this->day(['date' => today()->addDays(4)->toDateString()]);
        $registration = $this->registration($source);
        foreach (['Approved', 'Rejected', 'Closed'] as $status) $this->change($registration, $source, $target, $status);
        $before = $this->recordSnapshot();
        $days = collect($this->getJson('/api/admin/state?resources=schedules')->assertOk()->json('schedules'))->keyBy('id');
        $this->assertSame(0, $days[$target->id]['registrations_total_count']);
        $this->assertSame(3, $days[$target->id]['requested_change_requests_count']);
        $this->assertSame(0, $days[$target->id]['requested_pending_change_requests_count']);
        $this->assertTrue($days[$target->id]['has_linked_records']);
        $this->assertSame($before, $this->recordSnapshot());
    }

    public function test_new_presentation_counts_are_available_in_create_update_and_status_responses(): void
    {
        $response = $this->postJson('/api/admin/schedules', $this->input())->assertOk();
        foreach (['registrations_count', 'registrations_total_count', 'registrations_recorded_attendance_count', 'current_change_requests_count', 'requested_change_requests_count', 'current_pending_change_requests_count', 'requested_pending_change_requests_count'] as $field) {
            $response->assertJsonPath($field, 0);
        }
        $response->assertJsonPath('has_linked_records', false);
        $day = Schedule::findOrFail($response->json('id'));
        $other = $this->day(['date' => today()->addDays(4)->toDateString()]);
        $registration = $this->registration($day);
        $this->registration($day, ['attendance' => 'Present', 'archived_at' => now(), 'archive_reason' => 'year_rollover']);
        $this->change($registration, $day, $other, 'Pending');
        $this->change($registration, $other, $day, 'Approved');
        $linkedRecords = collect(['recollection_registrations', 'schedule_change_requests'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->toJson()])->all();

        $responses = [
            $this->putJson('/api/admin/schedules/'.$day->id, $this->input(['title' => 'Presentation-only revised name'])),
            $this->patchJson('/api/admin/schedules/'.$day->id.'/status', ['status' => 'Open']),
            $this->patchJson('/api/admin/schedules/'.$day->id.'/status', ['status' => 'Closed']),
        ];
        foreach ($responses as $saved) {
            $saved->assertOk()->assertJsonPath('registrations_count', 1)->assertJsonPath('registrations_total_count', 2)
                ->assertJsonPath('registrations_recorded_attendance_count', 1)
                ->assertJsonPath('current_change_requests_count', 1)->assertJsonPath('requested_change_requests_count', 1)
                ->assertJsonPath('current_pending_change_requests_count', 1)->assertJsonPath('requested_pending_change_requests_count', 0)
                ->assertJsonPath('has_linked_records', true);
        }
        foreach ($linkedRecords as $table => $snapshot) $this->assertSame($snapshot, DB::table($table)->get()->toJson());
    }
}
