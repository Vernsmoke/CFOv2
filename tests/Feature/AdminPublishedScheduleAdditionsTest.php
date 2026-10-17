<?php

namespace Tests\Feature;

use App\Models\{AcademicOption, Registration, Schedule, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminPublishedScheduleAdditionsTest extends TestCase
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
        $this->configure($this->it, '3', ['A', 'B']);
        $this->configure($this->nursing, '3', ['A', 'B', 'C']);
        $this->actingAs($this->admin);
    }

    private function configure(AcademicOption $course, string $year, array $sections): void
    {
        foreach ($sections as $section) DB::table('course_year_sections')->insertOrIgnore([
            'course_id' => $course->id, 'year_level' => $year, 'section' => $section,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function day(?array $mapping = null, array $changes = [], bool $legacy = false): Schedule
    {
        $mapping ??= [$this->it->id => ['A']];
        $years = $changes['year_levels'] ?? ['3'];
        $sections = $changes['sections'] ?? array_values(array_unique(array_merge(...array_values($mapping))));
        $date = today()->addDays(3)->toDateString();
        $day = Schedule::create([...[
            'title' => 'Recollection – 3rd Year – BSIT A – '.$date,
            'date' => $date, 'start_time' => '08:00', 'end_time' => '12:00', 'venue' => 'Chapel',
            'capacity' => 20, 'slot_limit' => 20, 'status' => 'Open',
            'year_levels' => $years, 'year_level' => $years[0], 'sections' => $sections, 'section' => $sections[0],
        ], ...$changes]);
        $day->courses()->sync(collect($mapping)->mapWithKeys(fn ($sections, $id) => [$id => ['sections' => $legacy ? null : $sections]])->all());

        return $day->fresh('courses');
    }

    private function input(Schedule $day, array $changes = []): array
    {
        $input = [
            'date' => $day->date, 'start_time' => $day->start_time, 'end_time' => $day->end_time,
            'venue' => $day->venue, 'slot_limit' => $day->slot_limit, 'status' => $day->status,
            'course_ids' => $day->courses->pluck('id')->all(), 'year_levels' => $day->year_levels, 'sections' => $day->sections,
        ];
        if ($day->hasCourseSections()) $input['course_sections'] = (array) $day->course_sections;

        return [...$input, ...$changes];
    }

    private function student(string $course = 'BSIT', string $year = '3', string $section = 'A'): User
    {
        return User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'course' => $course, 'year_level' => $year, 'section' => $section]);
    }

    private function registration(User $student, ?Schedule $day = null, array $changes = []): Registration
    {
        return Registration::create([...[
            'user_id' => $student->id, 'year' => now()->year, 'details' => ['religion' => 'Catholic', 'medical' => 'Keep submitted information'],
            'status' => 'Approved', 'receipt_status' => 'Approved', 'receipt_path' => 'receipts/existing.png',
            'recollection_schedule_id' => $day?->id,
        ], ...$changes]);
    }

    private function update(Schedule $day, array $changes = [])
    {
        return $this->putJson('/api/admin/schedules/'.$day->id, $this->input($day, $changes));
    }

    private function version(string $resource): int
    {
        return (int) DB::table('cfo_resource_versions')->where('resource', $resource)->value('version');
    }

    private function addedGroups(Schedule $day): array
    {
        $metadata = json_decode(DB::table('activity_logs')->where('action', 'schedule.saved')->where('record_id', $day->id)->latest('id')->value('metadata'), true);
        $this->assertSame('Added groups: '.implode(', ', $metadata['added_groups']), $metadata['added_groups_summary']);

        return $metadata['added_groups'];
    }

    public function test_adding_a_section_to_an_open_schedule_preserves_assignments_capacity_and_live_refresh(): void
    {
        $day = $this->day();
        $assigned = $this->registration($this->student(), $day);
        $before = DB::table('recollection_registrations')->where('id', $assigned->id)->first();
        $schedulesVersion = $this->version('schedules');
        $registrationsVersion = $this->version('registrations');

        $this->update($day, ['course_sections' => [$this->it->id => ['A', 'B']]])->assertOk()
            ->assertJsonPath('status', 'Open')->assertJsonPath('slot_limit', 20)
            ->assertJsonPath('course_sections.'.$this->it->id, ['A', 'B'])->assertJsonPath('sections', ['A', 'B']);

        $this->assertEquals($before, DB::table('recollection_registrations')->where('id', $assigned->id)->first());
        $this->assertSame($day->id, $assigned->fresh()->recollection_schedule_id);
        $this->assertGreaterThan($schedulesVersion, $this->version('schedules'));
        $this->assertSame($registrationsVersion, $this->version('registrations'));
        $this->assertSame(['BSIT 3B'], $this->addedGroups($day));
        $this->assertDatabaseCount('cfo_notifications', 0);
    }

    public function test_adding_a_course_preserves_each_courses_sections_and_the_legacy_union(): void
    {
        $day = $this->day();
        $this->update($day, [
            'course_ids' => [$this->it->id, $this->nursing->id],
            'course_sections' => [$this->it->id => ['A'], $this->nursing->id => ['A', 'C']],
        ])->assertOk()->assertJsonPath('sections', ['A', 'C'])
            ->assertJsonPath('course_sections.'.$this->it->id, ['A'])
            ->assertJsonPath('course_sections.'.$this->nursing->id, ['A', 'C']);

        $saved = $day->fresh('courses');
        $this->assertSame(['A'], $saved->sectionsForCourse($this->it->id));
        $this->assertSame(['A', 'C'], $saved->sectionsForCourse($this->nursing->id));
        $this->assertEqualsCanonicalizing(['BSN 3A', 'BSN 3C'], $this->addedGroups($day));
        $this->assertDatabaseCount('recollection_registrations', 0);
    }

    public function test_closed_and_draft_schedules_can_add_groups_without_changing_status(): void
    {
        foreach (['Closed', 'Draft'] as $index => $status) {
            $day = $this->day(changes: ['status' => $status, 'date' => today()->addDays(4 + $index)->toDateString()]);
            $this->update($day, ['course_sections' => [$this->it->id => ['A', 'B']]])->assertOk()
                ->assertJsonPath('status', $status)->assertJsonPath('active', false)
                ->assertJsonPath('course_sections.'.$this->it->id, ['A', 'B']);
        }
    }

    public function test_adding_a_year_applies_each_courses_sections_to_every_selected_year(): void
    {
        $this->configure($this->it, '4', ['A', 'B']);
        $this->configure($this->nursing, '4', ['A', 'B', 'C']);
        $day = $this->day([$this->it->id => ['A', 'B'], $this->nursing->id => ['C']]);
        $this->update($day, ['year_levels' => ['3', '4']])->assertOk()->assertJsonPath('year_levels', ['3', '4'])
            ->assertJsonPath('year_level', '3')->assertJsonPath('course_sections.'.$this->it->id, ['A', 'B']);

        $saved = $day->fresh('courses');
        $this->assertTrue($saved->allowsAcademicGroup($this->student('BSIT', '4', 'A')));
        $this->assertTrue($saved->allowsAcademicGroup($this->student('BSIT', '4', 'B')));
        $this->assertTrue($saved->allowsAcademicGroup($this->student('BSN', '4', 'C')));
        $this->assertFalse($saved->allowsAcademicGroup($this->student('BSIT', '4', 'C')));
        $this->assertEqualsCanonicalizing(['BSIT 4A', 'BSIT 4B', 'BSN 4C'], $this->addedGroups($day));
    }

    public function test_adding_a_year_rejects_an_invalid_combination_for_any_selected_course_without_writes(): void
    {
        $this->configure($this->it, '4', ['A']);
        $this->configure($this->nursing, '4', ['A', 'B']);
        $day = $this->day([$this->it->id => ['A'], $this->nursing->id => ['C']]);
        $before = DB::table('recollection_schedules')->where('id', $day->id)->first();
        $pivots = DB::table('recollection_schedule_courses')->where('schedule_id', $day->id)->get()->toJson();

        $this->update($day, ['year_levels' => ['3', '4']])->assertUnprocessable()->assertSee('BSN 4C');
        $this->assertEquals($before, DB::table('recollection_schedules')->where('id', $day->id)->first());
        $this->assertSame($pivots, DB::table('recollection_schedule_courses')->where('schedule_id', $day->id)->get()->toJson());
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_an_unconfigured_added_year_uses_the_existing_global_section_fallback(): void
    {
        $day = $this->day([$this->it->id => ['A', 'B']]);
        $this->update($day, ['year_levels' => ['3', '4']])->assertOk()->assertJsonPath('year_levels', ['3', '4']);
        $this->assertTrue($day->fresh('courses')->allowsAcademicGroup($this->student('BSIT', '4', 'B')));
    }

    public function test_an_invalid_added_section_is_rejected_with_its_course_year_and_section(): void
    {
        $day = $this->day();
        $this->update($day, ['course_sections' => [$this->it->id => ['A', 'C']]])->assertUnprocessable()->assertSee('BSIT 3C');
        $this->assertSame(['A'], $day->fresh('courses')->sectionsForCourse($this->it->id));
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_new_courses_must_exist_and_every_selected_course_needs_its_own_sections(): void
    {
        $day = $this->day();
        $this->update($day, ['course_ids' => [$this->it->id, 999999], 'course_sections' => [$this->it->id => ['A'], 999999 => ['A']]])
            ->assertUnprocessable()->assertJsonValidationErrors('course_ids.1');
        $this->update($day, ['course_ids' => [$this->it->id, $this->nursing->id]])->assertUnprocessable();
        $this->assertDatabaseCount('recollection_schedule_courses', 1);
    }

    public function test_adding_a_conflicting_group_names_the_group_and_other_schedule(): void
    {
        $day = $this->day();
        $other = $this->day([$this->nursing->id => ['C']], ['title' => 'Existing nursing retreat', 'start_time' => '10:00', 'end_time' => '14:00']);
        $this->update($day, [
            'course_ids' => [$this->it->id, $this->nursing->id],
            'course_sections' => [$this->it->id => ['A'], $this->nursing->id => ['C']],
        ])->assertUnprocessable()->assertSee('overlapping time')->assertSee('BSN 3C')->assertSee($other->title);
        $this->assertSame([$this->it->id], $day->fresh('courses')->courses->pluck('id')->all());
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_adding_groups_excludes_the_edited_schedule_and_ignores_existing_group_conflicts(): void
    {
        $day = $this->day();
        $this->day(changes: ['title' => 'Historical duplicate scope']);
        $this->update($day, ['course_sections' => [$this->it->id => ['A', 'B']]])->assertOk()
            ->assertJsonPath('course_sections.'.$this->it->id, ['A', 'B']);
    }

    public function test_non_additive_changes_keep_the_existing_overlap_check(): void
    {
        $day = $this->day([$this->it->id => ['A', 'B']]);
        $this->day(changes: ['title' => 'Existing IT section A']);
        $this->update($day, ['course_sections' => [$this->it->id => ['A']]])->assertUnprocessable()->assertSee('overlapping time');
        $this->assertSame(['A', 'B'], $day->fresh('courses')->sectionsForCourse($this->it->id));
    }

    public function test_adjacent_times_and_cancelled_other_schedules_do_not_block_additions(): void
    {
        $day = $this->day();
        $this->day([$this->it->id => ['B']], ['title' => 'Afternoon B', 'start_time' => '12:00', 'end_time' => '14:00']);
        $this->day([$this->it->id => ['B']], ['title' => 'Cancelled B', 'status' => 'Cancelled']);
        $this->update($day, ['course_sections' => [$this->it->id => ['A', 'B']]])->assertOk();
    }

    public function test_recorded_attendance_does_not_block_additions_or_change_registration_and_certificate_data(): void
    {
        $day = $this->day();
        $assigned = $this->registration($this->student(), $day, ['attendance' => 'Present', 'attendance_recorded_by' => $this->admin->id, 'attendance_recorded_at' => now()]);
        DB::table('certificates')->insert([
            'registration_id' => $assigned->id, 'certificate_number' => 'CFO-EXISTING-001', 'snapshot' => json_encode(['name' => 'Existing snapshot', 'schedule' => $day->title]),
            'issued_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $registration = DB::table('recollection_registrations')->where('id', $assigned->id)->first();
        $certificate = DB::table('certificates')->where('registration_id', $assigned->id)->first();

        $this->update($day, ['course_sections' => [$this->it->id => ['A', 'B']]])->assertOk();
        $this->assertEquals($registration, DB::table('recollection_registrations')->where('id', $assigned->id)->first());
        $this->assertEquals($certificate, DB::table('certificates')->where('registration_id', $assigned->id)->first());
        $this->assertSame([$day->date, $day->start_time, $day->end_time, $day->venue], array_values($day->fresh()->only(['date', 'start_time', 'end_time', 'venue'])));

        $saved = $day->fresh('courses');
        foreach ([['date' => today()->addDays(5)->toDateString()], ['start_time' => '09:00'], ['end_time' => '13:00'], ['venue' => 'Another chapel']] as $changes) {
            $this->update($saved, [...$changes, 'course_sections' => [$this->it->id => ['A', 'B'], $this->nursing->id => ['C']], 'course_ids' => [$this->it->id, $this->nursing->id]])
                ->assertUnprocessable()->assertSee('Recorded events retain');
        }
    }

    public function test_pure_additions_do_not_reject_previously_assigned_out_of_scope_profiles_but_removals_still_do(): void
    {
        $day = $this->day();
        $assigned = $this->registration($this->student('BSN', '4', 'C'), $day);
        $this->update($day, ['course_sections' => [$this->it->id => ['A', 'B']]])->assertOk();
        $this->assertSame($day->id, $assigned->fresh()->recollection_schedule_id);
        $this->update($day->fresh('courses'), ['course_sections' => [$this->it->id => ['A']]])->assertUnprocessable()->assertSee('exclude assigned students');
        $this->assertSame(['A', 'B'], $day->fresh('courses')->sectionsForCourse($this->it->id));
    }

    public function test_removing_an_assigned_courses_section_is_still_blocked_when_another_course_keeps_the_union(): void
    {
        $day = $this->day([$this->it->id => ['A', 'B'], $this->nursing->id => ['A', 'B', 'C']]);
        $assigned = $this->registration($this->student('BSIT', '3', 'B'), $day);
        $this->update($day, ['course_sections' => [$this->it->id => ['A'], $this->nursing->id => ['A', 'B', 'C']]])
            ->assertUnprocessable()->assertSee('exclude assigned students');
        $this->assertSame($day->id, $assigned->fresh()->recollection_schedule_id);
        $this->assertSame(['A', 'B'], $day->fresh('courses')->sectionsForCourse($this->it->id));
    }

    public function test_adding_groups_does_not_bypass_capacity_and_assigned_year_protections(): void
    {
        $day = $this->day();
        $this->registration($this->student(), $day);
        $this->registration($this->student(), $day);
        $groups = ['course_sections' => [$this->it->id => ['A', 'B']]];
        $this->update($day, [...$groups, 'slot_limit' => 1])->assertUnprocessable()->assertSee('assigned student count');
        $this->update($day, [...$groups, 'date' => today()->addYear()->toDateString()])->assertUnprocessable()->assertSee('different year');
        $this->assertSame(['A'], $day->fresh('courses')->sectionsForCourse($this->it->id));
    }

    public function test_completed_and_cancelled_schedules_cannot_add_groups_or_change_existing_records(): void
    {
        foreach ([['status' => 'Closed', 'finalized_at' => now()], ['status' => 'Cancelled']] as $changes) {
            $day = $this->day(changes: $changes);
            $before = DB::table('recollection_schedules')->where('id', $day->id)->first();
            $this->update($day, ['status' => 'Open', 'course_sections' => [$this->it->id => ['A', 'B']]])->assertUnprocessable()->assertSee('view only');
            $this->assertEquals($before, DB::table('recollection_schedules')->where('id', $day->id)->first());
            $this->assertSame(['A'], $day->fresh('courses')->sectionsForCourse($this->it->id));
        }
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_newly_eligible_students_can_choose_an_open_schedule_without_automatic_assignments_or_notifications(): void
    {
        $day = $this->day();
        $student = $this->student('BSIT', '3', 'B');
        $unassigned = $this->registration($student);
        $assigned = $this->registration($this->student(), $day);
        $this->actingAs($student)->getJson('/api/student?resources=schedules')->assertJsonCount(0, 'schedules');
        $this->actingAs($this->admin);
        $this->update($day, ['course_sections' => [$this->it->id => ['A', 'B']]])->assertOk();

        $this->assertNull($unassigned->fresh()->recollection_schedule_id);
        $this->assertSame($day->id, $assigned->fresh()->recollection_schedule_id);
        $this->assertDatabaseCount('recollection_registrations', 2);
        $this->assertDatabaseCount('cfo_notifications', 0);
        $this->actingAs($student)->getJson('/api/student?resources=schedules')->assertJsonCount(1, 'schedules')->assertJsonPath('schedules.0.id', $day->id);
        $this->postJson('/api/registrations/'.$unassigned->id.'/schedule', ['schedule_id' => $day->id])->assertOk();
        $this->assertSame($day->id, $unassigned->fresh()->recollection_schedule_id);
        $this->postJson('/api/registrations/'.$unassigned->id.'/schedule', ['schedule_id' => $day->id])->assertUnprocessable()->assertSee('already selected');
    }

    public function test_closed_schedule_additions_change_scope_without_allowing_student_choice_until_open(): void
    {
        $day = $this->day(changes: ['status' => 'Closed']);
        $student = $this->student('BSIT', '3', 'B');
        $registration = $this->registration($student);
        $this->update($day, ['course_sections' => [$this->it->id => ['A', 'B']]])->assertOk();
        $this->assertTrue($day->fresh('courses')->allowsAcademicGroup($student));
        $this->actingAs($student)->getJson('/api/student?resources=schedules')->assertJsonCount(0, 'schedules');
        $this->postJson('/api/registrations/'.$registration->id.'/schedule', ['schedule_id' => $day->id])->assertUnprocessable();
        $this->assertNull($registration->fresh()->recollection_schedule_id);
    }

    public function test_old_schedules_can_add_groups_without_discarding_unconfigured_existing_sections(): void
    {
        $day = $this->day([$this->it->id => ['C']], ['title' => 'Legacy IT section C'], legacy: true);
        $assigned = $this->registration($this->student('BSIT', '3', 'C'), $day);
        $this->assertTrue($day->allowsAcademicGroup($assigned->user));
        $this->update($day, [
            'course_ids' => [$this->it->id, $this->nursing->id],
            'course_sections' => [$this->it->id => ['C'], $this->nursing->id => ['A']],
        ])->assertOk()->assertJsonPath('course_sections.'.$this->it->id, ['C']);
        $saved = $day->fresh('courses');
        $this->assertTrue($saved->allowsAcademicGroup($assigned->user));
        $this->assertTrue($saved->allowsAcademicGroup($this->student('BSN', '3', 'A')));
        $this->assertSame($day->id, $assigned->fresh()->recollection_schedule_id);
        $this->assertSame(['BSN 3A'], $this->addedGroups($day));
    }

    public function test_legacy_payloads_keep_legacy_eligibility_and_null_pivot_sections(): void
    {
        $day = $this->day([$this->it->id => ['A']], ['title' => 'Old client schedule'], legacy: true);
        $this->update($day, ['sections' => ['A', 'B']])->assertOk()->assertJsonPath('sections', ['A', 'B']);
        $saved = $day->fresh('courses');
        $this->assertSame([], (array) $saved->course_sections);
        $this->assertNull($saved->courses->first()->pivot->sections);
        $this->assertTrue($saved->allowsAcademicGroup($this->student('BSIT', '3', 'B')));
    }

    public function test_backend_course_count_rule_remains_unlimited_and_new_courses_use_global_fallback(): void
    {
        $education = AcademicOption::create(['type' => 'course', 'name' => 'BSED']);
        $day = $this->day();
        $this->update($day, [
            'course_ids' => [$this->it->id, $this->nursing->id, $education->id],
            'course_sections' => [$this->it->id => ['A'], $this->nursing->id => ['C'], $education->id => ['Z']],
        ])->assertOk()->assertJsonCount(3, 'courses')->assertJsonPath('course_sections.'.$education->id, ['Z']);
        $this->assertEqualsCanonicalizing(['BSN 3C', 'BSED 3Z'], $this->addedGroups($day));
    }

    public function test_audit_lists_exact_added_cartesian_groups_and_generated_title_tracks_additions(): void
    {
        $this->configure($this->it, '4', ['A', 'B']);
        $this->configure($this->nursing, '4', ['A', 'C']);
        $day = $this->day();
        $this->update($day, [
            'course_ids' => [$this->it->id, $this->nursing->id], 'year_levels' => ['3', '4'],
            'course_sections' => [$this->it->id => ['A', 'B'], $this->nursing->id => ['A', 'C']],
        ])->assertOk()->assertJsonPath('title', 'Recollection – 3rd Year, 4th Year – BSIT A, B / BSN A, C – '.$day->date);
        $this->assertEqualsCanonicalizing(['BSIT 3B', 'BSIT 4A', 'BSIT 4B', 'BSN 3A', 'BSN 3C', 'BSN 4A', 'BSN 4C'], $this->addedGroups($day));
        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseHas('activity_logs', ['actor_id' => $this->admin->id, 'action' => 'schedule.saved', 'record_type' => 'schedule', 'record_id' => $day->id]);
    }

    public function test_titles_are_generated_for_added_sections_and_client_titles_are_ignored(): void
    {
        $generated = $this->day();
        $this->update($generated, ['course_sections' => [$this->it->id => ['A', 'B']]])->assertOk()
            ->assertJsonPath('title', 'Recollection – 3rd Year – BSIT A, B – '.$generated->date);
        $manual = $this->day(changes: ['title' => 'A manually named retreat', 'date' => today()->addDays(4)->toDateString()]);
        $this->update($manual, ['course_sections' => [$this->it->id => ['A', 'B']]])->assertOk()->assertJsonPath('title', 'Recollection – 3rd Year – BSIT A, B – '.$manual->date);
        $incomingManual = $this->day(changes: ['date' => today()->addDays(5)->toDateString()]);
        $incomingManual->update(['title' => 'Recollection – 3rd Year – BSIT A – '.$incomingManual->date]);
        $this->update($incomingManual, ['title' => 'Admin changed the title in this edit', 'course_sections' => [$this->it->id => ['A', 'B']]])
            ->assertOk()->assertJsonPath('title', 'Recollection – 3rd Year – BSIT A, B – '.$incomingManual->date);
    }
}
