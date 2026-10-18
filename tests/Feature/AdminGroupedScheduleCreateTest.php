<?php

namespace Tests\Feature;

use App\Models\{AcademicOption, Registration, Schedule, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AdminGroupedScheduleCreateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private AcademicOption $it;
    private AcademicOption $nursing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->actingAs($this->admin);
        $this->it = AcademicOption::create(['type' => 'course', 'name' => 'BSIT']);
        $this->nursing = AcademicOption::create(['type' => 'course', 'name' => 'BSN']);
        foreach (['1', '2', '3', '4'] as $name) AcademicOption::create(['type' => 'year_level', 'name' => $name]);
        foreach (['A', 'B', 'C'] as $name) AcademicOption::create(['type' => 'section', 'name' => $name]);
        foreach ([[$this->it->id, '2', ['A', 'B']], [$this->nursing->id, '3', ['A', 'B', 'C']]] as [$course, $year, $sections]) {
            foreach ($sections as $section) DB::table('course_year_sections')->insert([
                'course_id' => $course, 'year_level' => $year, 'section' => $section,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function input(array $changes = [], ?array $mapping = null): array
    {
        $mapping ??= [$this->it->id => ['2' => ['A', 'B']], $this->nursing->id => ['3' => ['A', 'B', 'C']]];
        $cards = [];
        $years = [];
        $sections = [];
        foreach ($mapping as $courseId => $blocks) foreach ($blocks as $year => $selected) {
            $cards[] = ['course_id' => $courseId, 'year_level' => (string) $year, 'sections' => $selected];
            $years[] = (string) $year;
            $sections = [...$sections, ...$selected];
        }

        return [...[
            'date' => today()->addDays(3)->toDateString(), 'start_time' => '08:00', 'end_time' => '17:00',
            'venue' => 'CFO Hall', 'slot_limit' => null, 'status' => 'Open', 'save_action' => 'publish',
            'course_ids' => array_keys($mapping), 'year_levels' => array_values(array_unique($years)),
            'sections' => array_values(array_unique($sections)), 'course_sections_by_year' => $mapping, 'course_groups' => $cards,
        ], ...$changes];
    }

    private function existing(array $changes = []): Schedule
    {
        $schedule = Schedule::create([...[
            'title' => 'Reserved hall schedule', 'date' => today()->addDays(3)->toDateString(),
            'start_time' => '08:00', 'end_time' => '17:00', 'venue' => 'CFO Hall',
            'year_level' => '4', 'year_levels' => ['4'], 'section' => 'C', 'sections' => ['C'],
            'capacity' => 100000, 'slot_limit' => null, 'status' => 'Open',
        ], ...$changes]);
        $schedule->courses()->sync([$this->nursing->id => ['sections' => ['4' => ['C']]]]);

        return $schedule;
    }

    private function student(string $course, string $year, string $section): User
    {
        return User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'course' => $course, 'year_level' => $year, 'section' => $section]);
    }

    public function test_all_course_cards_persist_as_exact_groups_in_one_schedule_transaction(): void
    {
        $response = $this->postJson('/api/admin/schedules', $this->input(['slot_limit' => 45, 'theme' => 'Faith', 'facilitator' => 'CFO Team', 'description' => 'A reflection day']))
            ->assertOk()->assertJsonCount(2, 'courses')->assertJsonPath('year_levels', ['2', '3'])
            ->assertJsonPath('sections', ['A', 'B', 'C'])->assertJsonPath('slot_limit', 45)
            ->assertJsonPath('capacity', 45)->assertJsonPath('status', 'Open')->assertJsonPath('active', true)
            ->assertJsonPath('title', 'Recollection – 2nd Year, 3rd Year – BSIT 2A, 2B / BSN 3A, 3B, 3C – '.$this->input()['date'])
            ->assertJsonMissingPath('theme')->assertJsonMissingPath('facilitator')->assertJsonMissingPath('description')
            ->assertJsonPath('course_sections_by_year.'.$this->it->id.'.2', ['A', 'B'])
            ->assertJsonPath('course_sections_by_year.'.$this->nursing->id.'.3', ['A', 'B', 'C']);
        $schedule = Schedule::with('courses')->findOrFail($response->json('id'));
        $this->assertSame(['2' => ['A', 'B']], $schedule->courses->firstWhere('id', $this->it->id)->pivot->sections);
        $this->assertTrue($schedule->allowsAcademicGroup($this->student('BSIT', '2', 'B')));
        $this->assertTrue($schedule->allowsAcademicGroup($this->student('BSN', '3', 'C')));
        $this->assertFalse($schedule->allowsAcademicGroup($this->student('BSIT', '3', 'C')));
        $this->assertDatabaseCount('recollection_schedules', 1);
        $this->assertDatabaseCount('recollection_schedule_courses', 2);
        $this->assertDatabaseHas('recollection_schedules', ['id' => $schedule->id, 'theme' => null, 'facilitator' => null, 'description' => null]);
        $this->assertDatabaseCount('recollection_registrations', 0);
        $this->assertDatabaseCount('cfo_notifications', 0);
        $this->assertDatabaseHas('activity_logs', ['action' => 'schedule.saved', 'record_id' => $schedule->id]);
    }

    public function test_catalog_major_is_validated_saved_and_limits_student_eligibility(): void
    {
        $business = AcademicOption::create([
            'type' => 'course',
            'name' => 'Bachelor of Science in Business Administration',
        ]);
        $mapping = [$business->id => ['3' => ['A']]];
        $input = $this->input([
            'course_majors' => [(string) $business->id => 'Marketing Management'],
        ], $mapping);

        $response = $this->postJson('/api/admin/schedules', $input)
            ->assertOk()
            ->assertJsonPath('course_majors.'.(string) $business->id, 'Marketing Management');

        $schedule = Schedule::with('courses')->findOrFail($response->json('id'));
        $this->assertSame('Marketing Management', $schedule->majorForCourse($business->id));
        $this->assertTrue($schedule->allowsAcademicGroup(User::factory()->create([
            'role' => 'Student', 'status' => 'Approved', 'course' => $business->name,
            'major' => 'Marketing Management', 'year_level' => '3', 'section' => 'A',
        ])));
        $this->assertFalse($schedule->allowsAcademicGroup(User::factory()->create([
            'role' => 'Student', 'status' => 'Approved', 'course' => $business->name,
            'major' => 'Financial Management', 'year_level' => '3', 'section' => 'A',
        ])));

        $this->postJson('/api/admin/schedules', [...$input,
            'venue' => 'Second Hall',
            'date' => today()->addDays(4)->toDateString(),
            'course_majors' => [(string) $business->id => 'Invalid Major'],
        ])->assertUnprocessable()->assertJsonValidationErrors('course_majors.'.$business->id);
    }

    public function test_generated_titles_fit_the_database_and_follow_date_changes_without_client_titles(): void
    {
        $mapping = [$this->it->id => ['2' => ['A', 'B']], $this->nursing->id => ['3' => ['C']]];
        for ($index = 1; $index <= 5; $index++) {
            $course = AcademicOption::create(['type' => 'course', 'name' => 'Program '.str_repeat('LongName ', 18).$index]);
            $mapping[$course->id] = ['4' => ['A', 'B', 'C']];
        }
        $input = $this->input(['title' => str_repeat('Ignored client title', 20)], $mapping);
        $response = $this->postJson('/api/admin/schedules', $input)->assertOk();
        $title = $response->json('title');
        $this->assertStringStartsWith('Recollection – ', $title);
        $this->assertStringEndsWith(' – '.$input['date'], $title);
        $this->assertLessThanOrEqual(190, strlen(mb_convert_encoding($title, 'UTF-16LE', 'UTF-8')) / 2);
        unset($input['title']);
        $input['date'] = today()->addDays(4)->toDateString();
        $edited = $this->putJson('/api/admin/schedules/'.$response->json('id'), $input)->assertOk();
        $this->assertNotSame($title, $edited->json('title'));
        $this->assertStringEndsWith(' – '.$input['date'], $edited->json('title'));
    }

    public function test_schedule_forms_and_lists_preserve_create_edit_filter_export_and_calendar_behaviour(): void
    {
        foreach (['schedule-create-state.cjs', 'my-schedules-state.cjs'] as $script) {
            $process = new Process(['node', base_path('tests/Browser/'.$script)], base_path());
            $process->run();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        }
    }

    public function test_same_course_can_have_distinct_year_cards_and_no_artificial_course_limit(): void
    {
        $mapping = [$this->it->id => ['2' => ['A'], '3' => ['B']], $this->nursing->id => ['3' => ['C']]];
        foreach (['BSBA', 'BSED', 'BSCE'] as $name) {
            $course = AcademicOption::create(['type' => 'course', 'name' => $name]);
            $mapping[$course->id] = ['4' => ['A', 'B', 'C']];
        }
        $this->postJson('/api/admin/schedules', $this->input([], $mapping))->assertOk()->assertJsonCount(5, 'courses')
            ->assertJsonPath('course_sections_by_year.'.$this->it->id.'.2', ['A'])
            ->assertJsonPath('course_sections_by_year.'.$this->it->id.'.3', ['B']);
        $this->assertDatabaseCount('recollection_schedules', 1);
    }

    public function test_save_as_draft_overrides_selected_status_but_requires_complete_fields(): void
    {
        foreach (['venue' => '', 'date' => '', 'start_time' => '', 'course_ids' => []] as $field => $value) {
            $this->postJson('/api/admin/schedules', $this->input(['save_action' => 'draft', $field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $created = $this->postJson('/api/admin/schedules', $this->input(['save_action' => 'draft', 'status' => 'Closed']))
            ->assertOk()->assertJsonPath('status', 'Draft')->assertJsonPath('active', false);
        $this->actingAs($this->student('BSIT', '2', 'A'))->getJson('/api/student?resources=schedules')->assertJsonCount(0, 'schedules');
        $this->assertDatabaseHas('recollection_schedules', ['id' => $created->json('id'), 'status' => 'Draft']);
    }

    public function test_publish_rejects_draft_and_preserves_open_or_closed_selection(): void
    {
        $this->postJson('/api/admin/schedules', $this->input(['status' => 'Draft']))->assertUnprocessable()->assertJsonValidationErrors('status');
        foreach (['Open', 'Closed'] as $index => $status) {
            $this->postJson('/api/admin/schedules', $this->input(['status' => $status, 'date' => today()->addDays(3 + $index)->toDateString()]))
                ->assertOk()->assertJsonPath('status', $status)->assertJsonPath('active', $status === 'Open');
        }
        $this->assertDatabaseCount('recollection_schedules', 2);
    }

    public function test_save_and_create_another_persists_chosen_status_and_optional_unlimited_capacity(): void
    {
        $this->postJson('/api/admin/schedules', $this->input(['save_action' => 'another', 'status' => 'Draft']))
            ->assertOk()->assertJsonPath('status', 'Draft')->assertJsonPath('slot_limit', null)->assertJsonPath('capacity', 100000);
        $this->postJson('/api/admin/schedules', $this->input(['save_action' => 'another', 'status' => 'Closed', 'date' => today()->addDays(4)->toDateString()]))
            ->assertOk()->assertJsonPath('status', 'Closed');
    }

    public function test_invalid_time_capacity_and_missing_groups_leave_no_partial_schedule(): void
    {
        foreach ([['end_time' => '07:00'], ['end_time' => '08:00'], ['slot_limit' => 0], ['slot_limit' => -5], ['course_groups' => []], ['course_sections_by_year' => []]] as $changes) {
            $this->postJson('/api/admin/schedules', $this->input($changes))->assertUnprocessable();
        }
        $this->assertDatabaseCount('recollection_schedules', 0);
        $this->assertDatabaseCount('recollection_schedule_courses', 0);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'schedule.saved']);
    }

    public function test_database_configured_sections_and_global_fallback_are_enforced_for_every_card(): void
    {
        $this->postJson('/api/admin/schedules', $this->input([], [$this->it->id => ['2' => ['C']]]))
            ->assertUnprocessable()->assertSee('BSIT 2C')->assertSee('not a configured section');
        $this->postJson('/api/admin/schedules', $this->input([], [$this->nursing->id => ['4' => ['Z']]]))
            ->assertOk()->assertJsonPath('course_sections_by_year.'.$this->nursing->id.'.4', ['Z']);
        $this->postJson('/api/admin/schedules', $this->input(['date' => today()->addDays(4)->toDateString()], [$this->nursing->id => ['4' => ['C']]]))->assertOk();
        $this->postJson('/api/admin/schedules', $this->input([], [$this->it->id => ['9' => ['A']]]))->assertUnprocessable()->assertJsonValidationErrors('year_levels.0');
        $this->assertDatabaseHas('academic_options', ['type' => 'section', 'name' => 'Z']);
        $this->assertDatabaseMissing('academic_options', ['type' => 'year_level', 'name' => '9']);
    }

    public function test_duplicate_cards_and_inconsistent_map_or_unions_are_rejected(): void
    {
        $input = $this->input();
        $input['course_groups'][] = $input['course_groups'][0];
        $this->postJson('/api/admin/schedules', $input)->assertUnprocessable()->assertJsonValidationErrors('course_groups.2.year_level');
        $input = $this->input();
        $input['course_groups'][0]['sections'] = ['A'];
        $this->postJson('/api/admin/schedules', $input)->assertUnprocessable()->assertJsonValidationErrors('course_groups');
        $this->postJson('/api/admin/schedules', $this->input(['course_ids' => [$this->it->id]]))->assertUnprocessable()->assertSee('every selected course');
        $this->postJson('/api/admin/schedules', $this->input(['year_levels' => ['2']]))->assertUnprocessable()->assertSee('selected year level');
        $this->postJson('/api/admin/schedules', $this->input([], [$this->it->id => ['2' => ['A', 'A']]]))->assertUnprocessable()->assertSee('distinct section');
        $this->assertDatabaseCount('recollection_schedules', 0);
    }

    public function test_every_non_cancelled_status_including_completed_reserves_venue(): void
    {
        foreach (['Open', 'Draft', 'Closed', 'Completed'] as $index => $status) {
            $date = today()->addDays(3 + $index)->toDateString();
            $other = $this->existing(['date' => $date, 'status' => $status === 'Completed' ? 'Closed' : $status,
                'finalized_at' => $status === 'Completed' ? now() : null]);
            $this->postJson('/api/admin/schedules', $this->input(['date' => $date]))->assertUnprocessable()
                ->assertJsonValidationErrors('venue')->assertSee($other->title)->assertSee($date)->assertSee('08:00')->assertSee('17:00');
        }
        $this->assertDatabaseCount('recollection_schedules', 4);
    }

    public function test_venue_is_trimmed_and_case_insensitive_and_cancelled_does_not_reserve_it(): void
    {
        $other = $this->existing(['venue' => '  CFO HALL  ']);
        $this->postJson('/api/admin/schedules', $this->input(['venue' => 'cfo hall']))->assertUnprocessable()->assertJsonValidationErrors('venue');
        $other->update(['status' => 'Cancelled']);
        $this->postJson('/api/admin/schedules', $this->input(['venue' => 'cfo hall']))->assertOk();
        $this->assertSame('Cancelled', $other->fresh()->status);
    }

    public function test_touching_times_different_dates_and_different_venues_are_allowed(): void
    {
        $this->existing();
        $this->postJson('/api/admin/schedules', $this->input(['start_time' => '17:00', 'end_time' => '18:00']))->assertOk();
        $this->postJson('/api/admin/schedules', $this->input(['date' => today()->addDays(4)->toDateString()]))->assertOk();
        $this->postJson('/api/admin/schedules', $this->input(['venue' => 'Retreat House']))->assertOk();
        $this->assertDatabaseCount('recollection_schedules', 4);
    }

    public function test_unchanged_edit_can_add_groups_without_rejecting_legacy_shared_venue_but_calendar_changes_are_checked(): void
    {
        $day = $this->existing(['year_level' => '2', 'year_levels' => ['2'], 'sections' => ['A'], 'section' => 'A']);
        $day->courses()->sync([$this->it->id => ['sections' => ['2' => ['A']]]]);
        $other = $this->existing();
        $input = $this->input(['title' => $day->title], [$this->it->id => ['2' => ['A', 'B']]]);
        $this->putJson('/api/admin/schedules/'.$day->id, $input)->assertOk();
        $this->putJson('/api/admin/schedules/'.$day->id, [...$input, 'start_time' => '09:00'])->assertUnprocessable()->assertJsonValidationErrors('venue')->assertSee($other->title);
        $this->assertSame('08:00', $day->fresh()->start_time);
    }

    public function test_generated_grouped_title_tracks_additions_and_ignores_client_titles(): void
    {
        $date = today()->addDays(3)->toDateString();
        $mapping = [$this->it->id => ['2' => ['A']], $this->nursing->id => ['3' => ['C']]];
        $title = 'Recollection – 2nd Year, 3rd Year – BSIT 2A / BSN 3C – '.$date;
        $created = $this->postJson('/api/admin/schedules', $this->input([], $mapping))->assertOk()->assertJsonPath('title', $title);
        $mapping[$this->it->id]['2'][] = 'B';
        $this->putJson('/api/admin/schedules/'.$created->json('id'), $this->input([], $mapping))->assertOk()
            ->assertJsonPath('title', 'Recollection – 2nd Year, 3rd Year – BSIT 2A, 2B / BSN 3C – '.$date);
        $this->putJson('/api/admin/schedules/'.$created->json('id'), $this->input(['title' => 'Manually named retreat'], $mapping))->assertOk()->assertJsonPath('title', 'Recollection – 2nd Year, 3rd Year – BSIT 2A, 2B / BSN 3C – '.$date);
    }

    public function test_published_groups_are_selectable_draft_and_closed_are_not_and_saving_does_not_assign_students(): void
    {
        $open = $this->postJson('/api/admin/schedules', $this->input())->assertOk();
        foreach (['Draft', 'Closed'] as $index => $status) {
            $this->postJson('/api/admin/schedules', $this->input(['status' => $status, 'save_action' => 'another', 'date' => today()->addDays(4 + $index)->toDateString()]))->assertOk();
        }
        $student = $this->student('BSIT', '2', 'A');
        $registration = Registration::create(['user_id' => $student->id, 'year' => now()->year, 'details' => [], 'status' => 'Approved', 'receipt_status' => 'Approved', 'receipt_path' => 'verified.png']);
        $this->assertNull($registration->fresh()->recollection_schedule_id);
        $this->get('/admin/recollection-registrations/'.$registration->id)->assertOk()->assertViewHas('schedules', fn ($schedules) => $schedules->pluck('id')->all() === [$open->json('id')]);
        $this->actingAs($student)->getJson('/api/student?resources=schedules')->assertOk()->assertJsonCount(1, 'schedules');
        $this->postJson('/api/registrations/'.$registration->id.'/schedule', ['schedule_id' => $open->json('id')])->assertOk();
    }

    public function test_student_and_guest_cannot_create_schedules(): void
    {
        $this->actingAs($this->student('BSIT', '2', 'A'))->postJson('/api/admin/schedules', $this->input())->assertForbidden();
        auth()->forgetGuards();
        $this->postJson('/api/admin/schedules', $this->input())->assertUnauthorized();
        $this->assertDatabaseCount('recollection_schedules', 0);
    }
}
