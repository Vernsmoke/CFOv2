<?php

namespace Tests\Feature;

use App\Models\{AcademicOption, Registration, Schedule, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminScheduleEditBlocksTest extends TestCase
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
        foreach ([[$this->it, '3', ['A', 'B']], [$this->it, '4', ['C']], [$this->nursing, '3', ['A', 'C']], [$this->nursing, '4', ['B', 'C']]] as [$course, $year, $sections]) {
            foreach ($sections as $section) DB::table('course_year_sections')->insert([
                'course_id' => $course->id, 'year_level' => $year, 'section' => $section,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->actingAs($this->admin);
    }

    private function day(array $changes = [], ?array $mapping = null, bool $legacy = false): Schedule
    {
        $date = today()->addDays(3)->toDateString();
        $day = Schedule::create([...[
            'title' => 'Recollection – 3rd Year – BSIT A – '.$date, 'date' => $date,
            'start_time' => '08:00', 'end_time' => '12:00', 'venue' => 'Chapel',
            'capacity' => 20, 'slot_limit' => 20, 'status' => 'Open',
            'year_levels' => ['3'], 'year_level' => '3', 'sections' => ['A'], 'section' => 'A',
        ], ...$changes]);
        $mapping ??= [$this->it->id => ['A']];
        $day->courses()->sync(collect($mapping)->mapWithKeys(fn ($sections, $id) => [$id => ['sections' => $legacy ? null : $sections]])->all());
        return $day->fresh('courses');
    }

    private function student(string $course = 'BSIT', string $year = '3', string $section = 'A'): User
    {
        return User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'course' => $course, 'year_level' => $year, 'section' => $section]);
    }

    private function registration(User $student, ?Schedule $day = null, array $changes = []): Registration
    {
        return Registration::create([...[
            'user_id' => $student->id, 'year' => now()->year, 'details' => ['religion' => 'Catholic', 'medical' => 'Preserved information'],
            'status' => 'Approved', 'receipt_status' => 'Approved', 'receipt_path' => 'receipts/existing.png',
            'recollection_schedule_id' => $day?->id,
        ], ...$changes]);
    }

    private function input(Schedule $day, array $mapping, array $changes = []): array
    {
        $years = [];
        foreach ($mapping as $blocks) foreach (array_keys($blocks) as $year) $years[] = (string) $year;
        return [...[
            'date' => $day->date, 'start_time' => $day->start_time, 'end_time' => $day->end_time,
            'venue' => $day->venue, 'slot_limit' => $day->slot_limit, 'status' => $day->status,
            'course_ids' => array_keys($mapping), 'year_levels' => array_values(array_unique($years)),
            'course_sections_by_year' => $mapping,
        ], ...$changes];
    }

    private function update(Schedule $day, array $mapping, array $changes = [])
    {
        return $this->putJson('/api/admin/schedules/'.$day->id, $this->input($day, $mapping, $changes));
    }

    public function test_edit_route_is_admin_only_and_prefills_saved_schedule_and_assigned_groups(): void
    {
        $day = $this->day(['theme' => 'Faith', 'facilitator' => 'Campus ministry', 'description' => 'Saved notes']);
        $assigned = $this->registration($this->student(), $day, ['attendance' => 'Present']);
        $this->get(route('admin.schedules.edit', $day->id))->assertOk()->assertViewIs('admin')
            ->assertViewHas('cfoBackend', function ($backend) use ($day) {
                $edit = $backend['edit_schedule'];
                return $edit->id === $day->id && $edit->venue === 'Chapel' && ! array_key_exists('theme', $edit->toArray())
                    && $edit->registrations_recorded_attendance_count === 1
                    && $edit->assigned_academic_groups->all() === [[
                        'course_id' => $this->it->id, 'year_level' => '3', 'section' => 'A', 'count' => 1,
                    ]];
            });
        $this->actingAs($assigned->user)->get(route('admin.schedules.edit', $day->id))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->get(route('admin.schedules.edit', $day->id))->assertRedirect('/login');
    }

    public function test_edit_ignores_removed_fields_and_preserves_existing_database_values(): void
    {
        $day = $this->day(['theme' => 'Saved theme', 'facilitator' => 'Saved facilitator', 'description' => 'Saved description']);
        $this->update($day, [$this->it->id => ['3' => ['A']]], [
            'title' => 'Ignored client name', 'theme' => 'Changed theme', 'facilitator' => 'Changed facilitator', 'description' => 'Changed description',
        ])->assertOk()->assertJsonPath('title', 'Recollection – 3rd Year – BSIT A – '.$day->date)
            ->assertJsonMissingPath('theme')->assertJsonMissingPath('facilitator')->assertJsonMissingPath('description');
        $this->assertDatabaseHas('recollection_schedules', [
            'id' => $day->id, 'theme' => 'Saved theme', 'facilitator' => 'Saved facilitator', 'description' => 'Saved description',
        ]);
    }

    public function test_missing_and_terminal_edit_urls_return_to_schedules_with_clear_messages(): void
    {
        $this->get('/admin/schedules/999999/edit')->assertRedirect('/admin#admin-recollection-schedule')
            ->assertSessionHas('schedule_notice', 'The schedule was not found.');
        foreach ([['status' => 'Closed', 'finalized_at' => now()], ['status' => 'Cancelled']] as $changes) {
            $day = $this->day($changes);
            $reason = $day->finalized_at ? 'Completed schedules are view only.' : 'Cancelled schedules are view only.';
            $this->get(route('admin.schedules.edit', $day->id))->assertRedirect('/admin#admin-recollection-schedule')->assertSessionHas('schedule_notice', $reason);
            $this->update($day, [$this->it->id => ['3' => ['A', 'B']]], ['status' => 'Open'])->assertUnprocessable()->assertSee('view only');
        }
    }

    public function test_adding_sections_and_an_independent_year_block_keeps_assigned_students_and_other_records(): void
    {
        $day = $this->day();
        $assigned = $this->registration($this->student(), $day);
        $before = DB::table('recollection_registrations')->where('id', $assigned->id)->first();
        $version = (int) DB::table('cfo_resource_versions')->where('resource', 'schedules')->value('version');
        $this->update($day, [$this->it->id => ['3' => ['A', 'B'], '4' => ['C']]])->assertOk()
            ->assertJsonPath('year_levels', ['3', '4'])->assertJsonPath('year_level', '3')->assertJsonPath('slot_limit', 20)
            ->assertJsonPath('course_sections_by_year.'.$this->it->id.'.3', ['A', 'B'])
            ->assertJsonPath('course_sections_by_year.'.$this->it->id.'.4', ['C'])
            ->assertJsonPath('course_sections.'.$this->it->id, ['A', 'B', 'C'])
            ->assertJsonPath('sections', ['A', 'B', 'C']);
        $saved = $day->fresh('courses');
        $this->assertSame(['3' => ['A', 'B'], '4' => ['C']], $saved->courses->first()->pivot->sections);
        $this->assertSame(['C'], $saved->sectionsForCourse($this->it->id, '4'));
        $this->assertFalse($saved->allowsAcademicGroup($this->student('BSIT', '4', 'A')));
        $this->assertTrue($saved->allowsAcademicGroup($this->student('BSIT', '4', 'C')));
        $this->assertEquals($before, DB::table('recollection_registrations')->where('id', $assigned->id)->first());
        $this->assertGreaterThan($version, (int) DB::table('cfo_resource_versions')->where('resource', 'schedules')->value('version'));
        $this->assertDatabaseCount('cfo_notifications', 0);
    }

    public function test_courses_can_differ_between_year_blocks_without_manufacturing_cross_product_groups(): void
    {
        $day = $this->day();
        $this->update($day, [$this->it->id => ['3' => ['A']], $this->nursing->id => ['4' => ['B', 'C']]])->assertOk();
        $saved = $day->fresh('courses');
        $this->assertTrue($saved->allowsAcademicGroup($this->student('BSN', '4', 'C')));
        $this->assertFalse($saved->allowsAcademicGroup($this->student('BSN', '3', 'C')));
        $this->assertFalse($saved->allowsAcademicGroup($this->student('BSIT', '4', 'A')));
        $this->assertSame([], $saved->sectionsForCourse($this->nursing->id, '3'));
        $this->getJson('/api/admin/state?resources=schedules')->assertJsonPath('schedules.0.course_sections_by_year.'.$this->nursing->id.'.4', ['B', 'C']);
    }

    public function test_duplicate_years_unconfigured_sections_missing_blocks_and_unknown_courses_are_rejected(): void
    {
        $day = $this->day();
        $map = [$this->it->id => ['3' => ['A'], '4' => ['C']]];
        $this->update($day, $map, ['year_levels' => ['3', '3', '4']])->assertUnprocessable()->assertJsonValidationErrors('year_levels.0');
        $this->update($day, [$this->it->id => ['3' => ['A'], '4' => ['A']]])->assertUnprocessable()->assertSee('BSIT 4A');
        $this->update($day, [$this->it->id => ['3' => ['A', 'A']]])->assertUnprocessable()->assertSee('distinct section');
        $this->update($day, [$this->it->id => ['3' => ['A']]], ['year_levels' => ['3', '4']])->assertUnprocessable()->assertSee('Each selected year-level block');
        $this->update($day, [$this->it->id => ['3' => []]])->assertUnprocessable();
        $this->update($day, [$this->it->id => ['3' => ['A']], 999999 => ['3' => ['A']]])->assertUnprocessable()->assertJsonValidationErrors('course_ids.1');
        $this->assertSame(['A'], $day->fresh('courses')->sectionsForCourse($this->it->id));
    }

    public function test_published_schedule_accepts_more_than_three_courses_and_four_configured_year_blocks(): void
    {
        $day = $this->day(['title' => 'Published schedule with additional groups']);
        $assigned = $this->registration($this->student(), $day, ['attendance' => 'Present', 'attendance_recorded_at' => now()]);
        $before = DB::table('recollection_registrations')->where('id', $assigned->id)->first();
        AcademicOption::create(['type' => 'year_level', 'name' => '5']);
        $extraCourses = [];
        foreach ([['BSBA', '1', ['A', 'B']], ['BSED', '2', ['A', 'B', 'C']], ['BSCE', '5', ['A', 'B']]] as [$name, $year, $sections]) {
            $course = AcademicOption::create(['type' => 'course', 'name' => $name]);
            $extraCourses[$course->id] = [$year => $sections];
            foreach ($sections as $section) DB::table('course_year_sections')->insert([
                'course_id' => $course->id, 'year_level' => $year, 'section' => $section,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $map = [
            $this->it->id => ['3' => ['A', 'B'], '4' => ['C']],
            $this->nursing->id => ['4' => ['B', 'C']],
        ] + $extraCourses;
        $this->update($day, $map)->assertOk()->assertJsonCount(5, 'courses')->assertJsonCount(5, 'year_levels')
            ->assertJsonPath('slot_limit', 20)->assertJsonPath('date', $day->date)
            ->assertJsonPath('start_time', $day->start_time)->assertJsonPath('end_time', $day->end_time)
            ->assertJsonPath('venue', $day->venue);
        $this->assertStringStartsWith('Recollection – ', $day->fresh()->title);
        $this->assertStringEndsWith(' – '.$day->date, $day->fresh()->title);
        $saved = $day->fresh('courses');
        foreach ($map as $courseId => $blocks) foreach ($blocks as $year => $sections) {
            $this->assertSame($sections, $saved->sectionsForCourse($courseId, (string) $year));
        }
        $this->assertTrue($saved->allowsAcademicGroup($this->student('BSED', '2', 'C')));
        $this->assertTrue($saved->allowsAcademicGroup($this->student('BSCE', '5', 'B')));
        $this->assertFalse($saved->allowsAcademicGroup($this->student('BSED', '5', 'B')));
        $this->assertEquals($before, DB::table('recollection_registrations')->where('id', $assigned->id)->first());
        $this->assertDatabaseCount('recollection_registrations', 1);
        $this->assertDatabaseCount('cfo_notifications', 0);
    }

    public function test_unconfigured_course_year_uses_global_section_fallback_and_existing_groups_are_grandfathered(): void
    {
        $day = $this->day(['sections' => ['Z'], 'section' => 'Z'], [$this->it->id => ['Z']], legacy: true);
        $assigned = $this->registration($this->student('BSIT', '3', 'Z'), $day);
        $this->update($day, [$this->it->id => ['3' => ['Z'], '2' => ['Y']]])->assertOk();
        $this->assertTrue($day->fresh('courses')->allowsAcademicGroup($this->student('BSIT', '2', 'Y')));
        $this->assertSame($day->id, $assigned->fresh()->recollection_schedule_id);
    }

    public function test_overlap_names_new_exact_group_and_other_schedule_and_excludes_self(): void
    {
        $day = $this->day();
        $other = $this->day(['title' => 'Nursing fourth-year retreat', 'year_levels' => ['4'], 'year_level' => '4', 'sections' => ['C'], 'section' => 'C'], [$this->nursing->id => ['4' => ['C']]]);
        $this->update($day, [$this->it->id => ['3' => ['A']], $this->nursing->id => ['4' => ['C']]])->assertUnprocessable()
            ->assertSee('BSN 4C')->assertSee($other->title)->assertSee('overlapping time');
        $this->update($day, [$this->it->id => ['3' => ['A', 'B']], $this->nursing->id => ['3' => ['C']]])->assertOk();
        $this->assertDatabaseCount('recollection_registrations', 0);
    }

    public function test_adding_groups_with_attendance_keeps_calendar_locked_and_removing_assigned_groups_fails(): void
    {
        $day = $this->day();
        $assigned = $this->registration($this->student(), $day, ['attendance' => 'Present', 'attendance_recorded_at' => now()]);
        $map = [$this->it->id => ['3' => ['A', 'B'], '4' => ['C']]];
        $this->update($day, $map)->assertOk();
        $saved = $day->fresh('courses');
        foreach ([['date' => today()->addDays(4)->toDateString()], ['start_time' => '09:00'], ['end_time' => '13:00'], ['venue' => 'Other venue']] as $changes) {
            $this->update($saved, $map, $changes)->assertUnprocessable()->assertSee('Recorded events retain');
        }
        $this->update($saved, [$this->it->id => ['3' => ['B'], '4' => ['C']]])->assertUnprocessable()->assertSee('exclude assigned students');
        $this->update($saved, $map, ['slot_limit' => 0])->assertUnprocessable();
        $this->assertSame($day->id, $assigned->fresh()->recollection_schedule_id);
    }

    public function test_newly_eligible_students_can_choose_but_are_not_assigned_or_notified_automatically(): void
    {
        $day = $this->day();
        $student = $this->student('BSN', '4', 'C');
        $wrongYear = $this->student('BSN', '3', 'C');
        $registration = $this->registration($student);
        $otherRegistration = $this->registration($wrongYear);
        $this->update($day, [$this->it->id => ['3' => ['A']], $this->nursing->id => ['4' => ['C']]])->assertOk();
        $this->assertNull($registration->fresh()->recollection_schedule_id);
        $this->assertDatabaseCount('recollection_registrations', 2);
        $this->assertDatabaseCount('cfo_notifications', 0);
        $this->actingAs($wrongYear)->getJson('/api/student?resources=schedules')->assertJsonCount(0, 'schedules');
        $this->postJson('/api/registrations/'.$otherRegistration->id.'/schedule', ['schedule_id' => $day->id])->assertUnprocessable();
        $this->actingAs($student)->getJson('/api/student?resources=schedules')->assertJsonCount(1, 'schedules');
        $this->postJson('/api/registrations/'.$registration->id.'/schedule', ['schedule_id' => $day->id])->assertOk();
        $this->assertSame($day->id, $registration->fresh()->recollection_schedule_id);
    }

    public function test_schedule_change_and_admin_assignment_respect_exact_course_year_sections(): void
    {
        $target = $this->day();
        $this->update($target, [$this->it->id => ['3' => ['A']], $this->nursing->id => ['4' => ['C']]])->assertOk();
        $source = $this->day(['date' => today()->addDays(6)->toDateString()], [$this->nursing->id => ['3' => ['C'], '4' => ['C']]]);
        $wrong = $this->student('BSN', '3', 'C');
        $registration = $this->registration($wrong, $source);
        $this->actingAs($wrong)->postJson('/api/change-requests', ['registration_id' => $registration->id, 'requested_schedule_id' => $target->id, 'reason' => 'Request change'])->assertUnprocessable()->assertSee('assigned to your section');
        $this->actingAs($this->admin)->postJson('/api/admin/registrations/'.$registration->id.'/schedule', ['schedule_id' => $target->id])->assertUnprocessable()->assertSee('assigned to your section');
        $this->assertSame($source->id, $registration->fresh()->recollection_schedule_id);
    }

    public function test_old_clients_can_update_nested_schedule_metadata_without_flattening_its_scope(): void
    {
        $day = $this->day();
        $this->update($day, [$this->it->id => ['3' => ['A'], '4' => ['C']]])->assertOk();
        $saved = $day->fresh('courses');
        $input = $this->input($saved, [$this->it->id => ['3' => ['A'], '4' => ['C']]], ['title' => 'Updated metadata']);
        unset($input['course_sections_by_year']);
        $input['course_sections'] = (array) $saved->course_sections;
        $this->putJson('/api/admin/schedules/'.$day->id, $input)->assertOk()->assertJsonPath('course_sections_by_year.'.$this->it->id.'.4', ['C']);
        $input['course_sections'][$this->it->id][] = 'B';
        $this->putJson('/api/admin/schedules/'.$day->id, $input)->assertUnprocessable()->assertSee('without losing their year-level sections');
        $this->assertFalse($day->fresh('courses')->allowsAcademicGroup($this->student('BSIT', '4', 'B')));
    }

    public function test_old_flat_and_null_pivots_keep_their_previous_year_section_cross_product(): void
    {
        foreach ([false, true] as $legacy) {
            $day = $this->day(['date' => today()->addDays($legacy ? 7 : 6)->toDateString(), 'year_levels' => ['3', '4'], 'year_level' => '3'], legacy: $legacy);
            $this->assertSame([], (array) $day->course_sections_by_year);
            $this->assertTrue($day->allowsAcademicGroup($this->student('BSIT', '4', 'A')));
            $before = $day->courses->first()->pivot->sections;
            $input = $this->input($day, [$this->it->id => ['3' => ['A'], '4' => ['A']]], ['title' => 'Legacy metadata']);
            unset($input['course_sections_by_year']);
            $input['sections'] = ['A'];
            $this->putJson('/api/admin/schedules/'.$day->id, $input)->assertOk();
            $this->assertSame($before, $day->fresh('courses')->courses->first()->pivot->sections);
        }
    }

    public function test_reopening_and_create_overlap_checks_read_nested_groups_without_cross_year_leakage(): void
    {
        $day = $this->day(['status' => 'Closed']);
        $this->update($day, [$this->it->id => ['3' => ['A']], $this->nursing->id => ['4' => ['C']]])->assertOk();
        $other = $this->day(['title' => 'Third year nursing', 'year_level' => '3'], [$this->nursing->id => ['C']]);
        $this->patchJson('/api/admin/schedules/'.$day->id.'/status', ['status' => 'Open'])->assertOk();
        $input = $this->input($other, [$this->nursing->id => ['4' => ['C']]], ['title' => 'Conflicting fourth year', 'year_levels' => ['4']]);
        unset($input['course_sections_by_year']);
        $input['course_sections'] = [$this->nursing->id => ['C']];
        $this->postJson('/api/admin/schedules', $input)->assertUnprocessable()->assertSee('overlapping time');
    }

    public function test_edit_block_statuses_use_existing_transitions_and_draft_publish_behavior(): void
    {
        $day = $this->day();
        $map = [$this->it->id => ['3' => ['A', 'B']]];
        $this->update($day, $map, ['status' => 'Draft'])->assertUnprocessable()->assertSee('status cannot');
        $this->update($day, $map, ['status' => 'Closed'])->assertOk()->assertJsonPath('status', 'Closed')->assertJsonPath('active', false);
        $this->update($day->fresh('courses'), $map, ['status' => 'Open'])->assertOk()->assertJsonPath('active', true);
        $draft = $this->day(['status' => 'Draft', 'date' => today()->addDays(8)->toDateString()]);
        $this->update($draft, $map)->assertOk()->assertJsonPath('status', 'Draft');
        $this->update($draft->fresh('courses'), $map, ['status' => 'Open'])->assertOk()->assertJsonPath('status', 'Open');
    }

    public function test_added_groups_are_audited_and_titles_are_generated_for_all_edits(): void
    {
        $day = $this->day();
        $map = [$this->it->id => ['3' => ['A'], '4' => ['C']], $this->nursing->id => ['4' => ['B']]];
        $this->update($day, $map)->assertOk()->assertJsonPath('title', 'Recollection – 3rd Year, 4th Year – BSIT 3A, 4C / BSN 4B – '.$day->date);
        $log = json_decode(DB::table('activity_logs')->where('action', 'schedule.saved')->where('record_id', $day->id)->latest('id')->value('metadata'), true);
        $this->assertSame(['BSIT 4C', 'BSN 4B'], $log['added_groups']);
        $this->assertSame('Added groups: BSIT 4C, BSN 4B', $log['added_groups_summary']);
        $manual = $this->day(['title' => 'A custom schedule name', 'date' => today()->addDays(9)->toDateString()]);
        $this->update($manual, $map)->assertOk()->assertJsonPath('title', 'Recollection – 3rd Year, 4th Year – BSIT 3A, 4C / BSN 4B – '.$manual->date);
    }

    public function test_an_unchanged_edit_preserves_old_storage_and_future_generated_title_updates(): void
    {
        foreach ([false, true] as $legacy) {
            $day = $this->day(['date' => today()->addDays($legacy ? 11 : 10)->toDateString()], legacy: $legacy);
            $day->update(['title' => 'Recollection – 3rd Year – BSIT A – '.$day->date]);
            $before = $day->courses->first()->pivot->sections;
            $this->update($day, [$this->it->id => ['3' => ['A']]])->assertOk()->assertJsonPath('title', $day->title);
            $this->assertSame($before, $day->fresh('courses')->courses->first()->pivot->sections);
            $this->update($day->fresh('courses'), [$this->it->id => ['3' => ['A', 'B']]])->assertOk()
                ->assertJsonPath('title', 'Recollection – 3rd Year – BSIT 3A, 3B – '.$day->date);
        }
    }

    public function test_configured_section_delete_protection_reads_the_correct_course_year_block(): void
    {
        $day = $this->day();
        $this->update($day, [$this->it->id => ['3' => ['A'], '4' => ['C']]])->assertOk();
        $used = DB::table('course_year_sections')->where('course_id', $this->it->id)->where('year_level', '4')->where('section', 'C')->value('id');
        $unused = DB::table('course_year_sections')->where('course_id', $this->it->id)->where('year_level', '3')->where('section', 'B')->value('id');
        $this->deleteJson('/api/admin/course-year-sections/'.$used)->assertUnprocessable()->assertSee('assigned to a Recollection Day');
        $this->deleteJson('/api/admin/course-year-sections/'.$unused)->assertOk();
    }
}
