<?php

namespace Tests\Feature;

use App\Models\{AcademicOption, Registration, Schedule, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class AdminCourseYearSectionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private AcademicOption $it;
    private AcademicOption $nursing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->it = AcademicOption::create(['type' => 'course', 'name' => 'BSIT']);
        $this->nursing = AcademicOption::create(['type' => 'course', 'name' => 'BSN']);
        $this->actingAs($this->admin);
    }

    private function configure(): void
    {
        foreach ([[$this->it->id, ['A', 'B']], [$this->nursing->id, ['A', 'B', 'C']]] as [$id, $sections]) {
            foreach ($sections as $section) $this->postJson('/api/admin/course-year-sections', ['course_id' => $id, 'year_level' => '3', 'section' => $section])->assertCreated();
        }
    }

    private function input(array $changes = []): array
    {
        return [...[
            'title' => 'Third year recollection', 'date' => today()->addDays(2)->toDateString(),
            'start_time' => '08:00', 'end_time' => '12:00', 'venue' => 'Chapel', 'slot_limit' => null, 'status' => 'Open',
            'course_ids' => [$this->it->id, $this->nursing->id], 'year_levels' => ['3'],
            'course_sections' => [$this->it->id => ['A', 'B'], $this->nursing->id => ['A', 'B', 'C']],
        ], ...$changes];
    }

    private function legacy(array $changes = [], ?AcademicOption $course = null): Schedule
    {
        $schedule = Schedule::create([...[
            'title' => 'Legacy recollection', 'date' => today()->addDays(2)->toDateString(),
            'start_time' => '08:00', 'end_time' => '12:00', 'venue' => 'Chapel', 'capacity' => 100000,
            'slot_limit' => null, 'active' => true, 'year_level' => '3', 'section' => 'C',
            'year_levels' => ['3'], 'sections' => ['C'],
        ], ...$changes]);
        $schedule->courses()->attach(($course ?? $this->it)->id);
        return $schedule;
    }

    private function student(string $course, string $section, string $year = '3'): User
    {
        return User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'course' => $course, 'year_level' => $year, 'section' => $section]);
    }

    private function registration(User $student, ?int $scheduleId = null): Registration
    {
        return Registration::create(['user_id' => $student->id, 'year' => now()->year, 'details' => [],
            'status' => 'Approved', 'receipt_status' => 'Approved', 'receipt_path' => 'receipts/approved.png', 'recollection_schedule_id' => $scheduleId]);
    }

    public function test_admin_configures_per_course_sections_with_live_refresh_audit_and_global_compatibility(): void
    {
        $version = DB::table('cfo_resource_versions')->where('resource', 'academic_options')->value('version');
        $this->configure();
        $this->getJson('/api/admin/state?resources=academic_options')->assertOk()->assertJsonCount(5, 'course_year_sections');
        $this->assertDatabaseCount('course_year_sections', 5);
        $this->assertDatabaseHas('academic_options', ['type' => 'section', 'name' => 'C']);
        $this->assertDatabaseHas('academic_options', ['type' => 'year_level', 'name' => '3']);
        $this->assertGreaterThan($version, DB::table('cfo_resource_versions')->where('resource', 'academic_options')->value('version'));
        $this->assertSame(5, DB::table('activity_logs')->where('action', 'course_year_section.created')->count());
        $this->postJson('/api/admin/course-year-sections', ['course_id' => $this->it->id, 'year_level' => '3', 'section' => ' a '])->assertUnprocessable();
        $this->postJson('/api/admin/course-year-sections', ['course_id' => $this->it->id, 'year_level' => '3', 'section' => '   '])->assertUnprocessable();
        $this->postJson('/api/admin/course-year-sections', ['course_id' => $this->it->id, 'year_level' => '3', 'section' => str_repeat('A', 191)])->assertUnprocessable();
        $this->assertDatabaseCount('course_year_sections', 5);
        $year = AcademicOption::where('type', 'year_level')->first();
        $this->postJson('/api/admin/course-year-sections', ['course_id' => $year->id, 'year_level' => '3', 'section' => 'D'])->assertUnprocessable();
    }

    public function test_course_section_endpoints_are_admin_only(): void
    {
        $this->configure();
        $id = DB::table('course_year_sections')->value('id');
        $this->actingAs($this->student('BSIT', 'A'));
        $this->postJson('/api/admin/course-year-sections', ['course_id' => $this->it->id, 'year_level' => '3', 'section' => 'D'])->assertForbidden();
        $this->deleteJson('/api/admin/course-year-sections/'.$id)->assertForbidden();
        $this->assertDatabaseCount('course_year_sections', 5);
    }

    public function test_unicode_section_case_variants_are_duplicates_and_cannot_bypass_student_usage_protection(): void
    {
        $saved = $this->postJson('/api/admin/course-year-sections', ['course_id' => $this->it->id, 'year_level' => '3', 'section' => 'Ñ'])->assertCreated();
        $this->postJson('/api/admin/course-year-sections', ['course_id' => $this->it->id, 'year_level' => '3', 'section' => 'ñ'])->assertUnprocessable()->assertSee('already configured');
        $this->assertDatabaseCount('course_year_sections', 1);
        $this->student('BSIT', 'ñ');
        $this->deleteJson('/api/admin/course-year-sections/'.$saved->json('course_year_section.id'))->assertUnprocessable()->assertSee('student profiles');
        $this->assertDatabaseCount('course_year_sections', 1);
    }

    public function test_global_references_used_only_by_configuration_cannot_be_deleted(): void
    {
        $configured = $this->postJson('/api/admin/course-year-sections', ['course_id' => $this->it->id, 'year_level' => '4', 'section' => 'Special Section'])->assertCreated();
        $year = AcademicOption::where('type', 'year_level')->where('name', '4')->firstOrFail();
        $section = AcademicOption::where('type', 'section')->where('name', 'Special Section')->firstOrFail();
        foreach ([$this->it->id, $year->id, $section->id] as $id) $this->deleteJson('/api/admin/academic-options/'.$id)->assertUnprocessable();
        $this->deleteJson('/api/admin/course-year-sections/'.$configured->json('course_year_section.id'))->assertOk();
        $this->deleteJson('/api/admin/academic-options/'.$section->id)->assertOk();
        $this->deleteJson('/api/admin/academic-options/'.$year->id)->assertOk();
        $this->assertDatabaseHas('academic_options', ['id' => $this->it->id]);
    }

    public function test_one_year_schedule_stores_each_course_sections_and_the_legacy_union(): void
    {
        $this->configure();
        $saved = $this->postJson('/api/admin/schedules', $this->input())->assertOk()
            ->assertJsonPath('year_levels', ['3'])->assertJsonPath('sections', ['A', 'B', 'C'])
            ->assertJsonPath('year_level', '3')->assertJsonPath('section', 'A')
            ->assertJsonPath('course_sections.'.$this->it->id, ['A', 'B'])
            ->assertJsonPath('course_sections.'.$this->nursing->id, ['A', 'B', 'C']);
        $schedule = Schedule::with('courses')->findOrFail($saved->json('id'));
        $this->assertSame(['A', 'B'], $schedule->courses->firstWhere('id', $this->it->id)->pivot->sections);
        $this->assertSame(['A', 'B', 'C'], $schedule->courses->firstWhere('id', $this->nursing->id)->pivot->sections);
        $this->getJson('/api/admin/state?resources=schedules')->assertJsonPath('schedules.0.course_sections.'.$this->it->id, ['A', 'B']);
    }

    public function test_unknown_sections_missing_courses_and_multiple_years_are_rejected_without_writes(): void
    {
        $this->configure();
        foreach ([
            ['year_levels' => ['2', '3']],
            ['course_sections' => [$this->it->id => ['A', 'C'], $this->nursing->id => ['A']]],
            ['course_sections' => [$this->it->id => ['A']]],
            ['course_sections' => [$this->it->id => [], $this->nursing->id => ['A']]],
            ['course_sections' => [$this->it->id => ['A', 'A'], $this->nursing->id => ['A']]],
            ['course_sections' => [$this->it->id => ['A'], $this->nursing->id => ['A'], 999999 => ['A']]],
        ] as $changes) $this->postJson('/api/admin/schedules', $this->input($changes))->assertUnprocessable();
        $this->assertDatabaseCount('recollection_schedules', 0);
        $this->assertDatabaseCount('recollection_schedule_courses', 0);
    }

    public function test_unconfigured_course_year_uses_current_global_sections_and_custom_sections_work(): void
    {
        $this->postJson('/api/admin/schedules', $this->input(['course_sections' => [$this->it->id => ['Z'], $this->nursing->id => ['C']]]))->assertOk();
        $this->postJson('/api/admin/course-year-sections', ['course_id' => $this->it->id, 'year_level' => '2', 'section' => 'Special Group'])->assertCreated();
        $this->postJson('/api/admin/schedules', $this->input(['year_levels' => ['2'], 'course_ids' => [$this->it->id], 'course_sections' => [$this->it->id => ['Special Group']], 'venue' => 'Retreat House']))->assertOk()
            ->assertJsonPath('sections', ['Special Group']);
        $this->assertDatabaseHas('academic_options', ['type' => 'section', 'name' => 'Special Group']);
    }

    public function test_student_choices_and_admin_assignment_use_the_students_own_course_sections(): void
    {
        $this->configure();
        $saved = $this->postJson('/api/admin/schedules', $this->input())->assertOk();
        $schedule = Schedule::findOrFail($saved->json('id'));
        $itC = $this->student('BSIT', 'C');
        $nursingC = $this->student('BSN', 'C');
        $this->assertFalse($schedule->allowsAcademicGroup($itC));
        $this->assertTrue($schedule->allowsAcademicGroup($nursingC));
        $this->assertFalse($schedule->allowsAcademicGroup($this->student('BSN', 'C', '2')));
        $itRegistration = $this->registration($itC);
        $nursingRegistration = $this->registration($nursingC);
        $this->actingAs($itC)->getJson('/api/student?resources=schedules')->assertOk()->assertJsonCount(0, 'schedules');
        $this->postJson('/api/registrations/'.$itRegistration->id.'/schedule', ['schedule_id' => $schedule->id])->assertUnprocessable();
        $this->actingAs($nursingC)->getJson('/api/student?resources=schedules')->assertOk()->assertJsonCount(1, 'schedules');
        $this->postJson('/api/registrations/'.$nursingRegistration->id.'/schedule', ['schedule_id' => $schedule->id])->assertOk();
        $this->actingAs($this->admin)->postJson('/api/admin/registrations/'.$itRegistration->id.'/schedule', ['schedule_id' => $schedule->id])->assertUnprocessable();
        $this->patchJson('/api/admin/registrations/'.$itRegistration->id, ['schedule_id' => $schedule->id])->assertUnprocessable();
        $this->assertNull($itRegistration->fresh()->recollection_schedule_id);
        $this->assertSame($schedule->id, $nursingRegistration->fresh()->recollection_schedule_id);
    }

    public function test_change_requests_cannot_use_another_courses_section(): void
    {
        $this->configure();
        $target = $this->postJson('/api/admin/schedules', $this->input())->assertOk();
        $source = $this->legacy(['date' => today()->addDay()->toDateString()]);
        $student = $this->student('BSIT', 'C');
        $registration = $this->registration($student, $source->id);
        $this->actingAs($student)->postJson('/api/change-requests', ['registration_id' => $registration->id, 'requested_schedule_id' => $target->json('id'), 'reason' => 'Different date'])->assertUnprocessable();
        $this->assertDatabaseCount('schedule_change_requests', 0);
    }

    public function test_overlap_checks_sections_for_the_shared_course_instead_of_the_union(): void
    {
        $this->configure();
        $this->legacy(); // BSIT 3C remains valid historical scope, not BSIT 3A/B.
        $this->postJson('/api/admin/schedules', $this->input(['venue' => 'Retreat House']))->assertOk();
        $this->postJson('/api/admin/schedules', $this->input(['course_ids' => [$this->it->id], 'course_sections' => [$this->it->id => ['A']]]))->assertUnprocessable()->assertSee('overlapping time');
        $this->postJson('/api/admin/schedules', $this->input(['course_ids' => [$this->nursing->id], 'course_sections' => [$this->nursing->id => ['C']], 'start_time' => '12:00', 'end_time' => '14:00']))->assertOk();
        $this->assertDatabaseCount('recollection_schedules', 3);
    }

    public function test_different_courses_at_same_time_in_different_venues_are_allowed_and_reopening_uses_per_course_scope(): void
    {
        $this->configure();
        $this->postJson('/api/admin/schedules', $this->input(['course_ids' => [$this->it->id], 'course_sections' => [$this->it->id => ['A']]]))->assertOk();
        $this->postJson('/api/admin/schedules', $this->input(['course_ids' => [$this->nursing->id], 'course_sections' => [$this->nursing->id => ['C']], 'venue' => 'Nursing Hall']))->assertOk();
        $draft = $this->postJson('/api/admin/schedules', $this->input(['date' => today()->addDays(3)->toDateString(), 'status' => 'Draft']))->assertOk();
        $this->legacy(['date' => today()->addDays(3)->toDateString()]);
        $url = '/api/admin/schedules/'.$draft->json('id').'/status';
        $this->patchJson($url, ['status' => 'Open'])->assertOk();
        $this->patchJson($url, ['status' => 'Closed'])->assertOk();
        $this->legacy(['date' => today()->addDays(3)->toDateString(), 'sections' => ['B'], 'section' => 'B']);
        $this->patchJson($url, ['status' => 'Open'])->assertUnprocessable();
    }

    public function test_edit_keeps_per_course_sections_in_new_and_legacy_client_payloads(): void
    {
        $this->configure();
        $saved = $this->postJson('/api/admin/schedules', $this->input())->assertOk();
        $url = '/api/admin/schedules/'.$saved->json('id');
        $this->putJson($url, $this->input(['title' => 'Edited title']))->assertOk()->assertJsonPath('course_sections.'.$this->it->id, ['A', 'B']);
        $oldPayload = $this->input(['title' => 'Legacy client edit', 'sections' => ['A', 'B', 'C']]);
        unset($oldPayload['course_sections']);
        $this->putJson($url, $oldPayload)->assertOk()->assertJsonPath('course_sections.'.$this->it->id, ['A', 'B'])
            ->assertJsonPath('course_sections.'.$this->nursing->id, ['A', 'B', 'C']);
        $this->assertFalse(Schedule::findOrFail($saved->json('id'))->allowsAcademicGroup($this->student('BSIT', 'C')));
    }

    public function test_edit_cannot_silently_exclude_an_assigned_student_of_one_course(): void
    {
        $this->configure();
        $saved = $this->postJson('/api/admin/schedules', $this->input())->assertOk();
        $registration = $this->registration($this->student('BSN', 'C'), $saved->json('id'));
        $this->putJson('/api/admin/schedules/'.$saved->json('id'), $this->input(['course_sections' => [$this->it->id => ['A', 'B'], $this->nursing->id => ['A', 'B']]]))
            ->assertUnprocessable()->assertSee('exclude assigned students');
        $this->assertSame(['A', 'B', 'C'], Schedule::findOrFail($saved->json('id'))->sectionsForCourse($this->nursing->id));
        $this->assertSame($saved->json('id'), $registration->fresh()->recollection_schedule_id);
    }

    public function test_removing_used_sections_is_blocked_for_students_mapped_schedules_and_legacy_schedules(): void
    {
        $this->configure();
        $sectionId = fn ($course, $section) => DB::table('course_year_sections')->where('course_id', $course)->where('section', $section)->value('id');
        $this->student('BSIT', 'A');
        $this->deleteJson('/api/admin/course-year-sections/'.$sectionId($this->it->id, 'A'))->assertUnprocessable()->assertSee('student profiles');
        $this->legacy(['section' => 'B', 'sections' => ['B']]);
        $this->deleteJson('/api/admin/course-year-sections/'.$sectionId($this->it->id, 'B'))->assertUnprocessable()->assertSee('Recollection Day');
        $this->postJson('/api/admin/schedules', $this->input(['course_ids' => [$this->nursing->id], 'course_sections' => [$this->nursing->id => ['C']], 'venue' => 'Nursing Hall']))->assertOk();
        $this->deleteJson('/api/admin/course-year-sections/'.$sectionId($this->nursing->id, 'C'))->assertUnprocessable()->assertSee('Recollection Day');
        $unused = $sectionId($this->nursing->id, 'B');
        $this->deleteJson('/api/admin/course-year-sections/'.$unused)->assertOk();
        $this->assertDatabaseMissing('course_year_sections', ['id' => $unused]);
        $this->assertDatabaseHas('academic_options', ['type' => 'section', 'name' => 'B']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'course_year_section.deleted', 'record_id' => $unused]);
    }

    public function test_edit_cannot_drop_an_assigned_section_even_when_another_course_keeps_it_in_the_union(): void
    {
        $this->configure();
        $saved = $this->postJson('/api/admin/schedules', $this->input())->assertOk();
        $registration = $this->registration($this->student('BSIT', 'B'), $saved->json('id'));
        $this->putJson('/api/admin/schedules/'.$saved->json('id'), $this->input([
            'course_sections' => [$this->it->id => ['A'], $this->nursing->id => ['A', 'B', 'C']],
        ]))->assertUnprocessable()->assertSee('exclude assigned students');
        $schedule = Schedule::findOrFail($saved->json('id'));
        $this->assertSame(['A', 'B', 'C'], $schedule->sections);
        $this->assertSame(['A', 'B'], $schedule->sectionsForCourse($this->it->id));
        $this->assertSame($schedule->id, $registration->fresh()->recollection_schedule_id);
    }

    public function test_mapped_schedules_keep_capacity_attendance_and_terminal_edit_protections(): void
    {
        $this->configure();
        $saved = $this->postJson('/api/admin/schedules', $this->input())->assertOk();
        $id = $saved->json('id');
        $first = $this->registration($this->student('BSIT', 'A'), $id);
        $this->registration($this->student('BSN', 'C'), $id);
        $this->putJson('/api/admin/schedules/'.$id, $this->input(['slot_limit' => 1]))->assertUnprocessable()->assertSee('assigned student count');
        DB::table('recollection_registrations')->where('id', $first->id)->update(['attendance' => 'Present']);
        foreach ([['date' => today()->addDays(4)->toDateString()], ['start_time' => '09:00'], ['end_time' => '13:00'], ['venue' => 'Another venue']] as $changes) {
            $this->putJson('/api/admin/schedules/'.$id, $this->input($changes))->assertUnprocessable()->assertSee('Recorded events retain');
        }
        $this->putJson('/api/admin/schedules/'.$id, $this->input(['slot_limit' => 25]))->assertOk();
        $this->deleteJson('/api/admin/schedules/'.$id)->assertUnprocessable()->assertSee('linked records');
        $this->patchJson('/api/admin/schedules/'.$id.'/status', ['status' => 'Cancelled'])->assertOk();
        $this->putJson('/api/admin/schedules/'.$id, $this->input())->assertUnprocessable()->assertSee('view only');
        $this->assertSame(['A', 'B'], Schedule::findOrFail($id)->sectionsForCourse($this->it->id));
        $this->assertDatabaseCount('recollection_registrations', 2);
    }

    public function test_legacy_schedules_and_old_payloads_ignore_new_configuration_and_keep_their_existing_scope(): void
    {
        $this->configure();
        $old = $this->legacy();
        $student = $this->student('BSIT', 'C');
        $this->assertSame([], (array) $old->course_sections);
        $this->assertTrue($old->allowsAcademicGroup($student));
        $this->actingAs($student)->getJson('/api/student?resources=schedules')->assertJsonCount(1, 'schedules');
        $this->actingAs($this->admin);
        $input = $this->input(['date' => today()->addDays(4)->toDateString(), 'year_levels' => ['2', '3'], 'sections' => ['A', 'C']]);
        unset($input['course_sections']);
        $saved = $this->postJson('/api/admin/schedules', $input)->assertOk()->assertJsonPath('year_levels', ['2', '3'])->assertJsonPath('sections', ['A', 'C']);
        $this->assertSame([], (array) Schedule::findOrFail($saved->json('id'))->course_sections);
    }

    public function test_additive_migration_is_idempotent_reversible_and_does_not_rewrite_existing_data(): void
    {
        $old = $this->legacy();
        $before = DB::table('recollection_schedules')->where('id', $old->id)->first();
        $migration = require database_path('migrations/2026_10_09_000003_add_course_year_sections.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseCount('course_year_sections', 0);
        $this->assertNull(DB::table('recollection_schedule_courses')->where('schedule_id', $old->id)->value('sections'));
        $migration->down();
        $this->assertFalse(Schema::hasTable('course_year_sections'));
        $this->assertFalse(Schema::hasColumn('recollection_schedule_courses', 'sections'));
        $migration->up();
        $this->assertEquals($before, DB::table('recollection_schedules')->where('id', $old->id)->first());
        $this->assertDatabaseHas('recollection_schedule_courses', ['schedule_id' => $old->id, 'academic_option_id' => $this->it->id, 'sections' => null]);
    }
}
