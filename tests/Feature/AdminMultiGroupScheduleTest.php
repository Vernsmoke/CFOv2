<?php

namespace Tests\Feature;

use App\Models\{AcademicOption, Registration, Schedule, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminMultiGroupScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private array $courseIds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->courseIds = [];
        foreach (['BSIT', 'BSA', 'BSCS', 'BSBA'] as $name) {
            $this->courseIds[] = AcademicOption::create(['type' => 'course', 'name' => $name])->id;
        }
        $this->actingAs($this->admin);
    }

    private function input(array $changes = []): array
    {
        return [...[
            'title' => 'One multi-group recollection', 'date' => today()->addDays(3)->toDateString(),
            'start_time' => '08:00', 'end_time' => '16:00', 'venue' => 'University Gymnasium',
            'course_ids' => array_slice($this->courseIds, 0, 2),
            'year_levels' => ['1'], 'sections' => ['A'],
            'slot_limit' => null, 'active' => true,
        ], ...$changes];
    }

    private function legacySchedule(array $changes = []): Schedule
    {
        $input = $this->input([
            'course_ids' => array_slice($this->courseIds, 0, 3),
            'year_levels' => ['1', '3'], 'sections' => ['A', 'Z'], ...$changes,
        ]);
        $courseIds = $input['course_ids'];
        unset($input['course_ids']);
        $day = Schedule::create([...$input, 'year_level' => $input['year_levels'][0],
            'section' => $input['sections'][0], 'capacity' => $input['slot_limit'] ?? 100000]);
        $day->courses()->sync($courseIds);
        foreach (['year_level' => 'year_levels', 'section' => 'sections'] as $type => $key) {
            foreach ($input[$key] as $name) AcademicOption::firstOrCreate(['type' => $type, 'name' => $name]);
        }
        return $day;
    }

    private function student(string $course, string $year, string $section): User
    {
        return User::factory()->create([
            'role' => 'Student', 'status' => 'Approved', 'course' => $course, 'year_level' => $year, 'section' => $section,
        ]);
    }

    private function registration(User $student, ?int $scheduleId = null): Registration
    {
        return Registration::create([
            'user_id' => $student->id, 'year' => now()->year, 'details' => [],
            'status' => 'Approved', 'receipt_status' => 'Approved', 'receipt_path' => 'existing.png',
            'recollection_schedule_id' => $scheduleId,
        ]);
    }

    private function academicVersion(): int
    {
        return (int) DB::table('cfo_resource_versions')->where('resource', 'academic_options')->value('version');
    }

    public function test_legacy_many_courses_years_and_sections_remain_in_one_schedule_and_editable(): void
    {
        $input = $this->input(['course_ids' => $this->courseIds, 'year_levels' => ['1', '3'], 'sections' => ['A', 'Z']]);
        $created = $this->legacySchedule($input);
        $this->putJson('/api/admin/schedules/'.$created->id, $input)->assertOk()
            ->assertJsonPath('year_levels', ['1', '3'])->assertJsonPath('sections', ['A', 'Z'])
            ->assertJsonCount(4, 'courses');
        $this->assertDatabaseCount('recollection_schedules', 1);
        $this->assertDatabaseCount('recollection_schedule_courses', 4);
        $schedule = Schedule::findOrFail($created->id);
        $this->assertSame(['1', '3'], $schedule->year_levels);
        $this->assertSame(['A', 'Z'], $schedule->sections);
        foreach ([['year_level', '1'], ['year_level', '3'], ['section', 'A'], ['section', 'Z']] as [$type, $name]) {
            $this->assertDatabaseHas('academic_options', compact('type', 'name'));
        }
        $this->assertDatabaseCount('academic_options', 8);
        $this->getJson('/api/admin/state?resources=schedules')->assertOk()
            ->assertJsonCount(1, 'schedules')->assertJsonPath('schedules.0.year_levels', ['1', '3'])
            ->assertJsonPath('schedules.0.sections', ['A', 'Z']);
    }

    public function test_students_in_every_selected_combination_see_the_same_schedule(): void
    {
        $created = $this->legacySchedule();
        foreach (['BSIT', 'BSA', 'BSCS'] as $course) {
            foreach (['1', '3'] as $year) {
                foreach (['A', 'Z'] as $section) {
                    $this->actingAs($this->student($course, $year, $section))->getJson('/api/student')->assertOk()
                        ->assertJsonCount(1, 'schedules')->assertJsonPath('schedules.0.id', $created->id);
                }
            }
        }
        foreach ([['BSBA', '3', 'Z'], ['BSIT', '2', 'Z'], ['BSIT', '3', 'B']] as [$course, $year, $section]) {
            $this->actingAs($this->student($course, $year, $section))->getJson('/api/student')->assertOk()
                ->assertJsonCount(0, 'schedules');
        }
        $this->assertDatabaseCount('recollection_schedules', 1);
    }

    public function test_student_assignment_accepts_secondary_groups_and_rejects_unselected_groups(): void
    {
        $created = $this->legacySchedule();
        $student = $this->student('BSCS', '3', 'Z');
        $registration = $this->registration($student);
        $this->actingAs($student)->postJson('/api/registrations/'.$registration->id.'/schedule', ['schedule_id' => $created->id])->assertOk();
        $this->assertSame($created->id, $registration->fresh()->recollection_schedule_id);

        $unselected = $this->student('BSIT', '2', 'Z');
        $other = $this->registration($unselected);
        $this->actingAs($unselected)->postJson('/api/registrations/'.$other->id.'/schedule', ['schedule_id' => $created->id])->assertUnprocessable();
        $this->assertNull($other->fresh()->recollection_schedule_id);
    }

    public function test_administrators_keep_the_existing_cross_group_assignment_override(): void
    {
        $created = $this->legacySchedule();
        $outside = $this->student('BSBA', '4', 'Y');
        $registration = $this->registration($outside);
        $this->postJson('/api/admin/registrations/'.$registration->id.'/schedule', ['schedule_id' => $created->id])->assertOk();
        $this->assertSame($created->id, $registration->fresh()->recollection_schedule_id);
    }

    public function test_optional_capacity_is_shared_by_the_single_schedule_across_groups(): void
    {
        $created = $this->legacySchedule(['slot_limit' => 1]);
        $first = $this->student('BSIT', '1', 'A');
        $second = $this->student('BSCS', '3', 'Z');
        $firstRegistration = $this->registration($first);
        $secondRegistration = $this->registration($second);
        $this->actingAs($first)->postJson('/api/registrations/'.$firstRegistration->id.'/schedule', ['schedule_id' => $created->id])->assertOk();
        $this->actingAs($second)->postJson('/api/registrations/'.$secondRegistration->id.'/schedule', ['schedule_id' => $created->id])->assertUnprocessable();
        $this->assertNull($secondRegistration->fresh()->recollection_schedule_id);
        $this->assertSame(1, Schedule::findOrFail($created->id)->registrations()->count());
    }

    public function test_schedule_change_requests_use_secondary_group_eligibility(): void
    {
        $target = $this->legacySchedule();
        $sourceInput = $this->input([
            'date' => today()->addDays(2)->toDateString(), 'year_levels' => ['3'], 'sections' => ['Z'],
            'course_ids' => array_slice($this->courseIds, 0, 3),
        ]);
        $source = $this->legacySchedule($sourceInput);
        $student = $this->student('BSCS', '3', 'Z');
        $registration = $this->registration($student, $source->id);
        $this->actingAs($student)->postJson('/api/change-requests', [
            'registration_id' => $registration->id, 'requested_schedule_id' => $target->id, 'reason' => 'Class schedule conflict',
        ])->assertCreated();
        $this->assertDatabaseHas('schedule_change_requests', [
            'registration_id' => $registration->id, 'requested_schedule_id' => $target->id, 'status' => 'Pending',
        ]);

        $this->actingAs($this->admin);
        $otherSource = $this->postJson('/api/admin/schedules', $this->input([
            'date' => today()->addDays(2)->toDateString(), 'year_levels' => ['2'], 'sections' => ['Z'], 'venue' => 'Secondary Hall',
        ]))->assertOk();
        $unselected = $this->student('BSIT', '2', 'Z');
        $otherRegistration = $this->registration($unselected, $otherSource->json('id'));
        $this->actingAs($unselected)->postJson('/api/change-requests', [
            'registration_id' => $otherRegistration->id, 'requested_schedule_id' => $target->id, 'reason' => 'Another request',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('schedule_change_requests', 1);
    }

    public function test_conflict_in_secondary_groups_rolls_back_and_disjoint_groups_remain_allowed(): void
    {
        $this->legacySchedule();
        $version = $this->academicVersion();
        $this->postJson('/api/admin/schedules', $this->input([
            'course_ids' => [$this->courseIds[2]], 'year_levels' => ['3'], 'sections' => ['Z'],
        ]))->assertUnprocessable()->assertSee('overlapping time');
        $this->assertDatabaseCount('recollection_schedules', 1);
        $this->assertDatabaseCount('academic_options', 8);
        $this->assertDatabaseMissing('academic_options', ['type' => 'year_level', 'name' => '4']);
        $this->assertDatabaseMissing('academic_options', ['type' => 'section', 'name' => 'Y']);
        $this->assertSame($version, $this->academicVersion());

        $this->postJson('/api/admin/schedules', $this->input([
            'course_ids' => [$this->courseIds[3]], 'year_levels' => ['3'], 'sections' => ['Z'], 'venue' => 'Business Hall',
        ]))->assertOk();
        $this->postJson('/api/admin/schedules', $this->input([
            'course_ids' => [$this->courseIds[2]], 'year_levels' => ['4'], 'sections' => ['Z'], 'venue' => 'Fourth Year Hall',
        ]))->assertOk();
        $this->postJson('/api/admin/schedules', $this->input([
            'course_ids' => [$this->courseIds[2]], 'year_levels' => ['3'], 'sections' => ['Y'], 'venue' => 'Alternative Hall',
        ]))->assertOk();
        $this->assertDatabaseCount('recollection_schedules', 4);
    }

    public function test_touching_times_are_allowed_and_inactive_overlaps_are_still_rejected(): void
    {
        $first = $this->postJson('/api/admin/schedules', $this->input(['active' => false]))->assertOk();
        $this->postJson('/api/admin/schedules', $this->input())->assertUnprocessable();
        $this->postJson('/api/admin/schedules', $this->input(['start_time' => '16:00', 'end_time' => '18:00']))->assertOk();
        $this->assertFalse(Schedule::findOrFail($first->json('id'))->active);
        $this->assertDatabaseCount('recollection_schedules', 2);
    }

    public function test_edit_cannot_remove_an_assigned_group_but_can_expand_scope_and_reorder(): void
    {
        $created = $this->legacySchedule();
        $student = $this->student('BSCS', '3', 'Z');
        $registration = $this->registration($student, $created->id);
        $version = $this->academicVersion();
        $this->putJson('/api/admin/schedules/'.$created->id, $this->input([
            'course_ids' => [$this->courseIds[0]], 'year_levels' => ['1', '4'], 'sections' => ['A', 'Y'],
        ]))->assertUnprocessable();
        $this->assertSame(['1', '3'], Schedule::findOrFail($created->id)->year_levels);
        $this->assertSame(['A', 'Z'], Schedule::findOrFail($created->id)->sections);
        $this->assertDatabaseMissing('academic_options', ['type' => 'year_level', 'name' => '4']);
        $this->assertDatabaseMissing('academic_options', ['type' => 'section', 'name' => 'Y']);
        $this->assertSame($version, $this->academicVersion());

        $expanded = $this->input([
            'course_ids' => $this->courseIds, 'year_levels' => ['1', '2', '3', '4'], 'sections' => ['A', 'B', 'Y', 'Z'],
        ]);
        $this->putJson('/api/admin/schedules/'.$created->id, $expanded)->assertOk()
            ->assertJsonCount(4, 'courses')->assertJsonPath('year_levels', ['1', '2', '3', '4'])
            ->assertJsonPath('sections', ['A', 'B', 'Y', 'Z']);
        $this->actingAs($this->student('BSBA', '4', 'Y'))->getJson('/api/student')->assertOk()
            ->assertJsonCount(1, 'schedules')->assertJsonPath('schedules.0.id', $created->id);
        $this->actingAs($this->admin);
        $version = $this->academicVersion();
        $this->putJson('/api/admin/schedules/'.$created->id, $this->input([
            'course_ids' => array_reverse(array_slice($this->courseIds, 0, 3)), 'year_levels' => ['3', '1'], 'sections' => ['Z', 'A'],
        ]))->assertOk();
        $this->assertSame($version, $this->academicVersion());
        $this->assertDatabaseCount('recollection_schedules', 1);
        $this->assertSame($created->id, $registration->fresh()->recollection_schedule_id);
    }

    public function test_new_schedule_saves_many_courses_years_and_sections_in_one_record(): void
    {
        $created = $this->postJson('/api/admin/schedules', $this->input([
            'course_ids' => $this->courseIds, 'year_levels' => ['1', '2', '3', '4'], 'sections' => range('A', 'Z'),
            'status' => 'Open',
        ]))->assertOk()->assertJsonCount(4, 'courses')
            ->assertJsonPath('year_levels', ['1', '2', '3', '4'])->assertJsonPath('sections', range('A', 'Z'))
            ->assertJsonPath('status', 'Open')->assertJsonPath('active', true);
        $this->assertDatabaseCount('recollection_schedules', 1);
        $this->assertDatabaseCount('recollection_schedule_courses', 4);
        $schedule = Schedule::findOrFail($created->json('id'));
        $this->assertSame(['1', '2', '3', '4'], $schedule->year_levels);
        $this->assertSame(range('A', 'Z'), $schedule->sections);
        $this->actingAs($this->student('BSBA', '4', 'Z'))->getJson('/api/student')->assertOk()
            ->assertJsonCount(1, 'schedules')->assertJsonPath('schedules.0.id', $schedule->id);
    }

    public function test_multi_group_schedule_can_be_saved_as_draft_and_published_without_losing_selections(): void
    {
        $input = $this->input([
            'course_ids' => array_slice($this->courseIds, 0, 3), 'year_levels' => ['1', '3'], 'sections' => ['A', 'Z'],
            'status' => 'Draft',
        ]);
        $created = $this->postJson('/api/admin/schedules', $input)->assertOk()
            ->assertJsonCount(3, 'courses')->assertJsonPath('status', 'Draft')->assertJsonPath('active', false);
        $student = $this->student('BSCS', '3', 'Z');
        $this->actingAs($student)->getJson('/api/student')->assertOk()->assertJsonCount(0, 'schedules');
        $this->actingAs($this->admin)->putJson('/api/admin/schedules/'.$created->json('id'), [...$input, 'status' => 'Open'])
            ->assertOk()->assertJsonCount(3, 'courses')->assertJsonPath('year_levels', ['1', '3'])
            ->assertJsonPath('sections', ['A', 'Z'])->assertJsonPath('status', 'Open')->assertJsonPath('active', true);
        $this->actingAs($student)->getJson('/api/student')->assertOk()
            ->assertJsonCount(1, 'schedules')->assertJsonPath('schedules.0.id', $created->json('id'));
        $this->assertDatabaseCount('recollection_schedules', 1);
    }

    public function test_reordering_does_not_reject_an_existing_admin_cross_group_assignment(): void
    {
        $created = $this->legacySchedule();
        $registration = $this->registration($this->student('BSBA', '4', 'Y'));
        $this->postJson('/api/admin/registrations/'.$registration->id.'/schedule', ['schedule_id' => $created->id])->assertOk();
        $this->putJson('/api/admin/schedules/'.$created->id, $this->input([
            'course_ids' => array_reverse(array_slice($this->courseIds, 0, 3)), 'year_levels' => ['3', '1'], 'sections' => ['Z', 'A'],
        ]))->assertOk();
        $this->assertSame($created->id, $registration->fresh()->recollection_schedule_id);
    }

    public function test_invalid_selection_arrays_do_not_create_any_schedule_or_references(): void
    {
        $version = $this->academicVersion();
        foreach ([
            ['course_ids' => []], ['course_ids' => [$this->courseIds[0], $this->courseIds[0]]], ['course_ids' => [999999]],
            ['year_levels' => []], ['year_levels' => ['1', '1']], ['year_levels' => ['5']], ['year_levels' => [1]],
            ['sections' => []], ['sections' => ['A', 'A']], ['sections' => ['AA']], ['sections' => ['z']],
            ['year_levels' => [], 'year_level' => '1'], ['sections' => [], 'section' => 'A'],
        ] as $changes) {
            $this->postJson('/api/admin/schedules', $this->input($changes))->assertUnprocessable();
        }
        $this->assertDatabaseCount('recollection_schedules', 0);
        $this->assertDatabaseCount('recollection_schedule_courses', 0);
        $this->assertDatabaseCount('academic_options', 4);
        $this->assertSame($version, $this->academicVersion());
    }

    public function test_legacy_scalar_payloads_and_null_array_records_keep_their_eligibility(): void
    {
        $legacy = $this->input();
        unset($legacy['year_levels'], $legacy['sections']);
        $legacy += ['year_level' => '2', 'section' => 'B'];
        $this->postJson('/api/admin/schedules', $legacy)->assertOk()
            ->assertJsonPath('year_levels', ['2'])->assertJsonPath('sections', ['B']);

        unset($legacy['course_ids']);
        $old = Schedule::create([...$legacy, 'date' => today()->addDays(4)->toDateString(), 'capacity' => 100000]);
        $old->courses()->attach($this->courseIds[0]);
        $this->assertNull(DB::table('recollection_schedules')->where('id', $old->id)->value('year_levels'));
        $this->assertNull(DB::table('recollection_schedules')->where('id', $old->id)->value('sections'));
        $this->assertSame(['2'], $old->fresh()->year_levels);
        $this->assertSame(['B'], $old->fresh()->sections);
        $student = $this->student('BSIT', '2', 'B');
        $this->actingAs($student)->getJson('/api/student')->assertOk()->assertJsonCount(2, 'schedules');
        $registration = $this->registration($student);
        $this->postJson('/api/registrations/'.$registration->id.'/schedule', ['schedule_id' => $old->id])->assertOk();
        $this->assertSame($old->id, $registration->fresh()->recollection_schedule_id);

        $this->actingAs($this->admin);
        AcademicOption::create(['type' => 'year_level', 'name' => '5']);
        AcademicOption::create(['type' => 'section', 'name' => 'Legacy section']);
        $custom = $this->postJson('/api/admin/schedules', $this->input([
            'year_levels' => ['5'], 'sections' => ['Legacy section'], 'venue' => 'Legacy Hall',
        ]))->assertOk();
        $this->putJson('/api/admin/schedules/'.$custom->json('id'), $this->input([
            'year_levels' => ['5'], 'sections' => ['Legacy section'], 'venue' => 'Legacy Hall',
        ]))->assertOk();
    }

    public function test_unrecorded_legacy_scopes_stay_hidden_and_explicit_empty_scopes_do_not_grant_eligibility(): void
    {
        $attributes = $this->input(['year_level' => null, 'section' => null]);
        unset($attributes['course_ids'], $attributes['year_levels'], $attributes['sections']);
        $legacy = Schedule::create([...$attributes, 'capacity' => 100000]);
        $legacy->courses()->attach($this->courseIds[0]);
        $empty = Schedule::create([...$attributes, 'year_level' => '2', 'section' => 'B',
            'year_levels' => [], 'sections' => [], 'capacity' => 100000]);
        $empty->courses()->attach($this->courseIds[0]);
        $student = $this->student('BSIT', '2', 'B');
        $this->assertTrue($legacy->allowsAcademicGroup($student), 'Legacy assignment scope remains unchanged.');
        $this->assertFalse($empty->allowsAcademicGroup($student), 'An explicitly empty new scope cannot inherit scalar eligibility.');
        $this->actingAs($student)->getJson('/api/student')->assertOk()->assertJsonCount(0, 'schedules');
        $registration = $this->registration($student);
        $this->postJson('/api/registrations/'.$registration->id.'/schedule', ['schedule_id' => $empty->id])->assertUnprocessable();
        $this->assertNull($registration->fresh()->recollection_schedule_id);
    }

    public function test_academic_options_used_only_in_secondary_groups_cannot_be_deleted(): void
    {
        $this->legacySchedule();
        $year = AcademicOption::where('type', 'year_level')->where('name', '3')->firstOrFail();
        $section = AcademicOption::where('type', 'section')->where('name', 'Z')->firstOrFail();
        $this->deleteJson('/api/admin/academic-options/'.$year->id)->assertUnprocessable();
        $this->deleteJson('/api/admin/academic-options/'.$section->id)->assertUnprocessable();
        $this->assertDatabaseHas('academic_options', ['id' => $year->id]);
        $this->assertDatabaseHas('academic_options', ['id' => $section->id]);
    }
}
