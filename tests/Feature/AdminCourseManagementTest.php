<?php

namespace Tests\Feature;

use App\Models\{AcademicOption, Registration, Schedule, User};
use App\Services\StudentRegistrationOptions;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AdminCourseManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private AcademicOption $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->course = AcademicOption::create(['type' => 'course', 'name' => 'BSITT']);
        $this->actingAs($this->admin);
    }

    private function url(?int $id = null): string
    {
        return '/api/admin/academic-options/'.($id ?? $this->course->id);
    }

    private function student(string $course = 'BSITT'): User
    {
        return User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'course' => $course, 'year_level' => '3', 'section' => 'A']);
    }

    private function registration(User $user, array $changes = []): Registration
    {
        return Registration::create([...[
            'user_id' => $user->id, 'year' => now()->year, 'details' => ['course' => 'BSITT', 'yearSection' => '3A', 'notes' => 'Keep this'],
            'student_snapshot' => ['course' => 'bsitt', 'student_id' => 'saved-id', 'name' => 'Saved Name'],
        ], ...$changes]);
    }

    private function schedule(): Schedule
    {
        $schedule = Schedule::create([
            'title' => 'Course rename check', 'date' => today()->addDay()->toDateString(),
            'start_time' => '08:00', 'end_time' => '12:00', 'venue' => 'Chapel', 'capacity' => 100000,
            'active' => true, 'year_level' => '3', 'section' => 'A', 'year_levels' => ['3'], 'sections' => ['A'],
        ]);
        $schedule->courses()->attach($this->course->id);
        return $schedule;
    }

    private function versions(): array
    {
        return DB::table('cfo_resource_versions')->pluck('version', 'resource')->all();
    }

    public function test_rename_updates_profiles_and_each_matching_registration_snapshot_atomically(): void
    {
        $student = $this->student(' bsitt ');
        $current = $this->registration($student);
        $archived = $this->registration($this->student('BSN'), [
            'year' => now()->year - 1, 'archived_at' => now(), 'archive_reason' => 'year_rollover',
            'details' => ['course' => 'BSN', 'yearSection' => '2B'],
        ]);
        $mixed = $this->registration($this->student('BSN'), ['student_snapshot' => ['course' => 'BSN', 'name' => 'Another Name']]);
        $unrelated = $this->registration($this->student('BSN'), ['details' => ['course' => 'BSN'], 'student_snapshot' => null]);
        DB::table('recollection_registrations')->where('id', $unrelated->id)->update(['student_snapshot' => null]);
        $schedule = $this->schedule();
        DB::table('course_year_sections')->insert(['course_id' => $this->course->id, 'year_level' => '3', 'section' => 'A', 'created_at' => now(), 'updated_at' => now()]);
        $versions = $this->versions();

        $this->patchJson($this->url(), ['name' => '  BSIT  ', 'type' => 'section'])->assertOk()
            ->assertJsonPath('academic_option.id', $this->course->id)->assertJsonPath('academic_option.name', 'BSIT');

        $this->assertSame('BSIT', $this->course->fresh()->name);
        $this->assertSame('course', $this->course->fresh()->type);
        $this->assertSame('BSIT', $student->fresh()->course);
        $this->assertSame(['course' => 'BSIT', 'yearSection' => '3A', 'notes' => 'Keep this'], $current->fresh()->details);
        $this->assertSame(['course' => 'BSIT', 'student_id' => 'saved-id', 'name' => 'Saved Name'], $current->fresh()->student_snapshot);
        $this->assertSame(['course' => 'BSN', 'yearSection' => '2B'], $archived->fresh()->details);
        $this->assertSame('BSIT', $archived->fresh()->student_snapshot['course']);
        $this->assertNotNull($archived->fresh()->archived_at);
        $this->assertSame('BSIT', $mixed->fresh()->details['course']);
        $this->assertSame('BSN', $mixed->fresh()->student_snapshot['course']);
        $this->assertSame('BSN', $mixed->user->fresh()->course);
        $this->assertSame(['course' => 'BSN'], $unrelated->fresh()->details);
        $this->assertNull($unrelated->fresh()->student_snapshot);
        $this->assertSame($this->course->id, $schedule->fresh()->courses->sole()->id);
        $this->assertSame('BSIT', $schedule->fresh()->courses->sole()->name);
        $this->assertDatabaseHas('course_year_sections', ['course_id' => $this->course->id, 'year_level' => '3', 'section' => 'A']);
        $this->assertSame('BSIT', StudentRegistrationOptions::normalize(['course' => 'BSIT', 'yearSection' => '3A'])['course']);
        foreach (['academic_options', 'users', 'registrations', 'schedules', 'attendance', 'activity_logs'] as $resource) {
            $this->assertGreaterThan($versions[$resource], $this->versions()[$resource]);
        }
        $audit = DB::table('activity_logs')->where('action', 'academic_option.updated')->sole();
        $this->assertSame($this->admin->id, $audit->actor_id);
        $this->assertSame($this->course->id, $audit->record_id);
        $this->assertSame(['old_name' => 'BSITT', 'name' => 'BSIT'], json_decode($audit->metadata, true));
        $this->getJson('/api/admin/state?resources=academic_options')->assertOk()->assertJsonFragment(['type' => 'course', 'name' => 'BSIT']);
    }

    public function test_rename_rejects_blank_duplicate_and_invalid_names_without_changing_records(): void
    {
        AcademicOption::create(['type' => 'course', 'name' => 'BSN']);
        AcademicOption::create(['type' => 'course', 'name' => 'BSÑ']);
        $student = $this->student();
        $registration = $this->registration($student);
        $versions = $this->versions();
        foreach (['', '   ', null, ' bSn ', 'bsñ', str_repeat('A', 191), ['BSIT']] as $name) {
            $this->patchJson($this->url(), ['name' => $name])->assertUnprocessable()->assertJsonValidationErrors('name');
        }
        $this->assertSame('BSITT', $this->course->fresh()->name);
        $this->assertSame('BSITT', $student->fresh()->course);
        $this->assertSame('BSITT', $registration->fresh()->details['course']);
        $this->assertSame($versions, $this->versions());
        $this->assertDatabaseMissing('activity_logs', ['action' => 'academic_option.updated']);
    }

    public function test_case_only_rename_is_allowed_and_unchanged_name_is_a_no_op(): void
    {
        $student = $this->student();
        $registration = $this->registration($student);
        $this->patchJson($this->url(), ['name' => 'bsitt'])->assertOk();
        $this->assertSame('bsitt', $student->fresh()->course);
        $this->assertSame('bsitt', $registration->fresh()->details['course']);
        $versions = $this->versions();
        $this->patchJson($this->url(), ['name' => ' bsitt '])->assertOk();
        $this->assertSame($versions, $this->versions());
        $this->assertSame(1, DB::table('activity_logs')->where('action', 'academic_option.updated')->count());
    }

    public function test_only_courses_can_be_renamed_and_only_approved_admins_can_manage_them(): void
    {
        foreach (['year_level' => '3', 'section' => 'A'] as $type => $name) {
            $option = AcademicOption::create(compact('type', 'name'));
            $this->patchJson($this->url($option->id), ['name' => 'Changed'])->assertNotFound();
            $this->assertSame($name, $option->fresh()->name);
        }
        $this->patchJson($this->url(999999), ['name' => 'BSIT'])->assertNotFound();
        foreach ([['Student', 'Approved'], ['Admin', 'Pending'], ['Admin', 'Rejected']] as [$role, $status]) {
            $this->actingAs(User::factory()->create(compact('role', 'status')));
            $this->patchJson($this->url(), ['name' => 'BSIT'])->assertForbidden();
            $this->deleteJson($this->url())->assertForbidden();
        }
        auth()->logout();
        $this->patchJson($this->url(), ['name' => 'BSIT'])->assertUnauthorized();
        $this->deleteJson($this->url())->assertUnauthorized();
        $this->assertSame('BSITT', $this->course->fresh()->name);
    }

    public function test_failed_audit_rolls_back_the_course_profile_snapshots_and_versions(): void
    {
        $student = $this->student();
        $registration = $this->registration($student);
        $versions = $this->versions();
        DB::statement("CREATE TRIGGER reject_course_audit BEFORE INSERT ON activity_logs WHEN NEW.action = 'academic_option.updated' BEGIN SELECT RAISE(ABORT, 'Course audit failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->patchJson($this->url(), ['name' => 'BSIT']);
            $this->fail('Expected the audit failure to abort the rename.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Course audit failure', $exception->getMessage());
        }
        $this->assertSame('BSITT', $this->course->fresh()->name);
        $this->assertSame('BSITT', $student->fresh()->course);
        $this->assertSame('BSITT', $registration->fresh()->details['course']);
        $this->assertSame('bsitt', $registration->fresh()->student_snapshot['course']);
        $this->assertSame($versions, $this->versions());
        $this->assertDatabaseMissing('activity_logs', ['action' => 'academic_option.updated']);
    }

    public function test_unused_course_can_be_deleted_and_is_removed_from_refreshed_options(): void
    {
        $versions = $this->versions();
        $this->deleteJson($this->url())->assertOk();
        $this->assertDatabaseMissing('academic_options', ['id' => $this->course->id]);
        $this->assertDatabaseHas('activity_logs', ['actor_id' => $this->admin->id, 'action' => 'academic_option.deleted', 'record_id' => $this->course->id]);
        $this->assertGreaterThan($versions['academic_options'], $this->versions()['academic_options']);
        $this->getJson('/api/admin/state?resources=academic_options')->assertOk()->assertJsonMissing(['name' => 'BSITT']);
        $this->deleteJson($this->url())->assertNotFound();
    }

    public function test_delete_rejects_courses_used_by_profiles_schedules_or_configured_sections(): void
    {
        $student = $this->student(' bsitt ');
        $this->deleteJson($this->url())->assertUnprocessable()->assertSee('student profiles');
        $student->update(['course' => 'BSN']);
        $schedule = $this->schedule();
        $this->deleteJson($this->url())->assertUnprocessable()->assertSee('assigned to a Recollection Day');
        $schedule->courses()->detach();
        DB::table('course_year_sections')->insert(['course_id' => $this->course->id, 'year_level' => '3', 'section' => 'A', 'created_at' => now(), 'updated_at' => now()]);
        $versions = $this->versions();
        $this->deleteJson($this->url())->assertUnprocessable()->assertSee('configured sections');
        $this->assertDatabaseHas('academic_options', ['id' => $this->course->id]);
        $this->assertDatabaseCount('course_year_sections', 1);
        $this->assertSame($versions, $this->versions());
        $this->assertDatabaseMissing('activity_logs', ['action' => 'academic_option.deleted']);
    }

    public function test_course_dialog_edit_delete_and_schedule_dropdowns(): void
    {
        foreach (['course-manage-state.cjs', 'schedule-create-state.cjs'] as $script) {
            $process = new Process(['node', base_path('tests/Browser/'.$script)], base_path());
            $process->run();
            $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
        }
        $this->get('/admin')->assertOk()->assertSee('admin-course-manage.js', false);
    }
}
