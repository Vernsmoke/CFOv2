<?php

namespace Tests\Feature;

use App\Models\{AcademicOption, Registration, Schedule, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminScheduleOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function input(array $changes = []): array
    {
        return [...[
            'title' => 'Standard academic group recollection',
            'date' => today()->addDays(3)->toDateString(),
            'start_time' => '08:00', 'end_time' => '16:00', 'venue' => 'University Gymnasium',
            'course_ids' => [AcademicOption::where('type', 'course')->value('id')],
            'year_level' => '4', 'section' => 'Z', 'slot_limit' => null, 'active' => true,
        ], ...$changes];
    }

    private function authenticate(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Admin', 'status' => 'Approved']));
        AcademicOption::create(['type' => 'course', 'name' => 'BSIT']);
    }

    private function academicVersion(): int
    {
        return (int) DB::table('cfo_resource_versions')->where('resource', 'academic_options')->value('version');
    }

    private function assertNoStandardOptionsAdded(int $version): void
    {
        $this->assertDatabaseMissing('academic_options', ['type' => 'year_level', 'name' => '4']);
        $this->assertDatabaseMissing('academic_options', ['type' => 'section', 'name' => 'Z']);
        $this->assertSame($version, $this->academicVersion());
    }

    public function test_standard_four_z_saves_without_references_and_creates_only_selected_options(): void
    {
        $this->authenticate();
        $version = $this->academicVersion();
        $response = $this->postJson('/api/admin/schedules', $this->input())->assertOk()
            ->assertJsonPath('year_level', '4')->assertJsonPath('section', 'Z');
        $this->assertDatabaseHas('academic_options', ['type' => 'year_level', 'name' => '4']);
        $this->assertDatabaseHas('academic_options', ['type' => 'section', 'name' => 'Z']);
        $this->assertDatabaseCount('academic_options', 3);
        $this->assertGreaterThan($version, $this->academicVersion());

        $createdVersion = $this->academicVersion();
        $this->postJson('/api/admin/schedules', $this->input(['date' => today()->addDays(4)->toDateString()]))->assertOk();
        $this->putJson('/api/admin/schedules/'.$response->json('id'), $this->input(['title' => 'Updated existing standard group']))->assertOk();
        $this->assertDatabaseCount('academic_options', 3);
        $this->assertSame($createdVersion, $this->academicVersion(), 'Reusing existing options must not signal academic-option changes.');
    }

    public function test_other_standard_years_and_sections_save_without_predefined_references(): void
    {
        $this->authenticate();
        foreach ([['1', 'A'], ['2', 'M'], ['3', 'N']] as [$year, $section]) {
            $this->postJson('/api/admin/schedules', $this->input(['year_level' => $year, 'section' => $section, 'venue' => 'University Gymnasium '.$year]))->assertOk()
                ->assertJsonPath('year_level', $year)->assertJsonPath('section', $section);
            $this->assertDatabaseHas('academic_options', ['type' => 'year_level', 'name' => $year]);
            $this->assertDatabaseHas('academic_options', ['type' => 'section', 'name' => $section]);
        }
        $this->assertDatabaseCount('academic_options', 7);
        $this->assertDatabaseCount('recollection_schedules', 3);
    }

    public function test_unknown_years_and_sections_are_rejected_without_creating_references(): void
    {
        $this->authenticate();
        $version = $this->academicVersion();
        foreach ([['year_level', '5'], ['year_level', '0'], ['section', 'AA'], ['section', 'a'], ['section', 'z']] as [$field, $value]) {
            $this->postJson('/api/admin/schedules', $this->input([$field => $value]))->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('academic_options', 1);
        $this->assertDatabaseCount('recollection_schedules', 0);
        $this->assertSame($version, $this->academicVersion());
    }

    public function test_existing_legacy_year_and_section_remain_valid_for_create_and_edit(): void
    {
        $this->authenticate();
        AcademicOption::create(['type' => 'year_level', 'name' => '5']);
        AcademicOption::create(['type' => 'section', 'name' => 'Legacy section']);
        $version = $this->academicVersion();
        $input = $this->input(['year_level' => '5', 'section' => 'Legacy section']);
        $created = $this->postJson('/api/admin/schedules', $input)->assertOk()
            ->assertJsonPath('year_level', '5')->assertJsonPath('section', 'Legacy section');
        $this->putJson('/api/admin/schedules/'.$created->json('id'), [...$input, 'title' => 'Edited legacy group'])->assertOk()
            ->assertJsonPath('year_level', '5')->assertJsonPath('section', 'Legacy section');
        $this->assertDatabaseCount('academic_options', 3);
        $this->assertSame($version, $this->academicVersion());
    }

    public function test_grouped_create_and_edit_use_standard_ranges_without_predefined_options(): void
    {
        $this->authenticate();
        $courseId = AcademicOption::where('type', 'course')->value('id');
        $input = $this->input([
            'year_levels' => ['4'], 'status' => 'Open', 'save_action' => 'publish',
            'course_sections_by_year' => [$courseId => ['4' => ['Z']]],
            'course_groups' => [['course_id' => $courseId, 'year_level' => '4', 'sections' => ['Z']]],
        ]);
        $created = $this->postJson('/api/admin/schedules', $input)->assertOk()
            ->assertJsonPath('year_levels', ['4'])->assertJsonPath('course_sections_by_year.'.$courseId.'.4', ['Z']);
        $this->assertDatabaseCount('academic_options', 3);
        $this->assertDatabaseCount('course_year_sections', 0);

        $edit = [...$input, 'year_levels' => ['4', '3'],
            'course_sections_by_year' => [$courseId => ['4' => ['A', 'Z'], '3' => ['Y']]],
        ];
        unset($edit['course_groups'], $edit['save_action']);
        $this->putJson('/api/admin/schedules/'.$created->json('id'), $edit)->assertOk()
            ->assertJsonPath('course_sections_by_year.'.$courseId.'.4', ['A', 'Z'])
            ->assertJsonPath('course_sections_by_year.'.$courseId.'.3', ['Y']);
        $this->assertDatabaseCount('academic_options', 6);
        $this->assertDatabaseCount('course_year_sections', 0);
    }

    public function test_standard_alphabet_does_not_bypass_configured_course_sections_on_create_or_edit(): void
    {
        $this->authenticate();
        $courseId = AcademicOption::where('type', 'course')->value('id');
        foreach (['A', 'B'] as $section) DB::table('course_year_sections')->insert([
            'course_id' => $courseId, 'year_level' => '3', 'section' => $section,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $input = $this->input([
            'year_levels' => ['3'], 'status' => 'Open',
            'course_sections_by_year' => [$courseId => ['3' => ['A']]],
            'course_groups' => [['course_id' => $courseId, 'year_level' => '3', 'sections' => ['A']]],
        ]);
        $invalid = [...$input,
            'course_sections_by_year' => [$courseId => ['3' => ['Z']]],
            'course_groups' => [['course_id' => $courseId, 'year_level' => '3', 'sections' => ['Z']]],
        ];
        $this->postJson('/api/admin/schedules', $invalid)->assertUnprocessable()->assertSee('BSIT 3Z');
        $created = $this->postJson('/api/admin/schedules', $input)->assertOk();
        $edit = [...$input, 'course_sections_by_year' => [$courseId => ['3' => ['A', 'Z']]]];
        unset($edit['course_groups']);
        $this->putJson('/api/admin/schedules/'.$created->json('id'), $edit)->assertUnprocessable()->assertSee('BSIT 3Z');
        $this->assertSame(['A'], Schedule::with('courses')->findOrFail($created->json('id'))->sectionsForCourse($courseId, '3'));
        $this->assertDatabaseMissing('academic_options', ['type' => 'section', 'name' => 'Z']);
        $this->assertDatabaseCount('course_year_sections', 2);
    }

    public function test_required_field_failure_does_not_create_selected_standard_options(): void
    {
        $this->authenticate();
        $version = $this->academicVersion();
        $this->postJson('/api/admin/schedules', $this->input(['venue' => '']))->assertUnprocessable()
            ->assertJsonValidationErrors('venue');
        $this->assertNoStandardOptionsAdded($version);
        $this->assertDatabaseCount('academic_options', 1);
        $this->assertDatabaseCount('recollection_schedules', 0);
    }

    public function test_overlapping_schedule_failure_does_not_create_missing_standard_options(): void
    {
        $this->authenticate();
        $input = $this->input();
        $attributes = $input;
        unset($attributes['course_ids']);
        $existing = Schedule::create([...$attributes, 'capacity' => 100000]);
        $existing->courses()->attach($input['course_ids']);
        $version = $this->academicVersion();
        $this->postJson('/api/admin/schedules', $input)->assertUnprocessable()
            ->assertSee('overlapping time');
        $this->assertNoStandardOptionsAdded($version);
        $this->assertDatabaseCount('academic_options', 1);
        $this->assertDatabaseCount('recollection_schedules', 1);
    }

    public function test_assigned_student_edit_failure_preserves_eligibility_and_adds_no_options(): void
    {
        $this->authenticate();
        AcademicOption::create(['type' => 'year_level', 'name' => '2']);
        AcademicOption::create(['type' => 'section', 'name' => 'A']);
        $student = User::factory()->create([
            'role' => 'Student', 'status' => 'Approved', 'course' => 'BSIT', 'year_level' => '2', 'section' => 'A',
        ]);
        $input = $this->input(['year_level' => '2', 'section' => 'A']);
        $created = $this->postJson('/api/admin/schedules', $input)->assertOk();
        $registration = Registration::create([
            'user_id' => $student->id, 'year' => now()->year, 'details' => [],
            'status' => 'Approved', 'receipt_status' => 'Approved', 'receipt_path' => 'existing.png',
            'recollection_schedule_id' => $created->json('id'),
        ]);
        $version = $this->academicVersion();
        $this->putJson('/api/admin/schedules/'.$created->json('id'), $this->input())->assertUnprocessable()
            ->assertSee('exclude assigned students');
        $this->assertNoStandardOptionsAdded($version);
        $this->assertDatabaseCount('academic_options', 3);
        $this->assertDatabaseHas('recollection_schedules', ['id' => $created->json('id'), 'year_level' => '2', 'section' => 'A']);
        $this->assertSame($created->json('id'), $registration->fresh()->recollection_schedule_id);
    }
}
