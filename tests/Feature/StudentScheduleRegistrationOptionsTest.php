<?php

namespace Tests\Feature;

use App\Models\{AcademicOption, Registration, Schedule, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentScheduleRegistrationOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'course' => 'BSCE', 'year_level' => '4', 'section' => 'Z']);
    }

    private function course(string $name): AcademicOption
    {
        return AcademicOption::firstOrCreate(['type' => 'course', 'name' => $name]);
    }

    private function details(array $changes = []): array
    {
        return [...[
            'name' => 'Student Name', 'age' => 20, 'sex' => 'Female', 'course' => 'BSIT',
            'yearLevel' => '1', 'section' => 'A', 'yearSection' => '1A', 'email' => 'student@example.test',
            'phone' => '09123456789', 'religion' => 'Roman Catholic', 'emergencyName' => 'Parent',
            'relationship' => 'Mother', 'emergencyPhone' => '09123456780', 'participation' => true, 'photos' => true,
        ], ...$changes];
    }

    private function schedule(array $mapping, array $changes = []): Schedule
    {
        $years = $sections = [];
        foreach ($mapping as $blocks) {
            foreach ($blocks as $year => $selected) {
                $years[] = (string) $year;
                $sections = [...$sections, ...$selected];
            }
        }
        $schedule = Schedule::create([...[
            'title' => 'Configured Recollection', 'date' => today()->toDateString(), 'start_time' => '08:00',
            'end_time' => '16:00', 'venue' => 'Chapel', 'capacity' => 50, 'slot_limit' => 50, 'status' => 'Open',
            'year_levels' => array_values(array_unique($years)), 'sections' => array_values(array_unique($sections)),
        ], ...$changes]);
        $schedule->courses()->sync(collect($mapping)->mapWithKeys(fn ($blocks, $id) => [$id => ['sections' => $blocks]])->all());

        return $schedule->fresh('courses');
    }

    public function test_student_options_include_all_courses_and_only_eligible_schedule_groups(): void
    {
        $it = $this->course('BSIT');
        $nursing = $this->course('BSN');
        $day = $this->schedule([$it->id => ['1' => ['A']], $nursing->id => ['3' => ['B']]], ['slot_limit' => 1]);
        Registration::create(['user_id' => $this->student()->id, 'year' => now()->year,
            'details' => $this->details(), 'recollection_schedule_id' => $day->id]);
        foreach (['Closed', 'Cancelled', 'Draft'] as $status) $this->schedule([$it->id => ['2' => ['C']]], ['status' => $status]);
        $this->schedule([$it->id => ['2' => ['C']]], ['finalized_at' => now()]);
        $this->schedule([$it->id => ['2' => ['C']]], ['date' => today()->subDay()]);
        $this->schedule([$it->id => ['2' => ['C']]], ['date' => today()->addYear()]);
        $this->actingAs($this->student());
        foreach (['registration_options', 'academic_options', 'schedules'] as $resource) {
            $this->getJson('/api/student?resources='.$resource)->assertOk()
                ->assertJsonPath('registration_courses', ['BSIT', 'BSN'])
                ->assertJsonCount(1, 'registration_schedules')->assertJsonCount(2, 'registration_schedules.0.courses')
                ->assertJsonPath('registration_schedules.0.course_sections_by_year.'.$nursing->id.'.3', ['B'])
                ->assertJsonMissingPath('registration_schedules.0.title')->assertJsonMissingPath('registration_schedules.0.registrations');
        }
        $this->getJson('/api/student?resources=registration_options')->assertJsonMissingPath('registrations')->assertJsonMissingPath('schedules');
        $this->getJson('/api/student?resources=notifications')->assertJsonMissingPath('registration_courses');
        $this->postJson('/api/registrations', ['details' => $this->details(['yearLevel' => '2', 'section' => 'C', 'yearSection' => '2C'])])
            ->assertUnprocessable()->assertJsonValidationErrors('details.yearSection');
        $this->postJson('/api/registrations', ['details' => $this->details(['course' => ' bSiT ', 'section' => 'a', 'yearSection' => '1a'])])
            ->assertCreated()->assertJsonPath('details.course', 'BSIT');
    }

    public function test_new_admin_course_refreshes_without_a_schedule_and_cannot_register(): void
    {
        $it = $this->course('BSIT');
        $this->schedule([$it->id => ['1' => ['A']]]);
        $student = $this->student();
        $this->actingAs($student)->getJson('/api/student?resources=registration_options')->assertJsonPath('registration_courses', ['BSIT']);
        $version = $this->getJson('/api/live/versions')->json('academic_options');
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->actingAs($admin)->postJson('/api/admin/academic-options', ['type' => 'course', 'name' => 'BSA'])->assertCreated();
        $this->actingAs($student);
        $this->assertGreaterThan($version, $this->getJson('/api/live/versions')->json('academic_options'));
        $this->getJson('/api/student?resources=registration_options')->assertJsonPath('registration_courses', ['BSA', 'BSIT']);
        $this->postJson('/api/registrations', ['details' => $this->details(['course' => 'BSA'])])
            ->assertUnprocessable()->assertJsonValidationErrors(['details.course', 'details.yearSection']);
        $this->assertDatabaseCount('recollection_registrations', 0);
    }

    public function test_new_edit_and_retry_require_an_exact_configured_group_and_preserve_records_on_invalid_choices(): void
    {
        $it = $this->course('BSIT');
        $nursing = $this->course('BSN');
        $this->course('BSA');
        $day = $this->schedule([$it->id => ['1' => ['A'], '3' => ['C']], $nursing->id => ['3' => ['B']]]);
        foreach (['new', 'edit', 'retry'] as $mode) {
            $student = $this->student();
            $record = $mode === 'new' ? null : Registration::create(['user_id' => $student->id, 'year' => now()->year,
                'details' => $this->details(), ...($mode === 'retry' ? ['recollection_schedule_id' => $day->id, 'attendance' => 'Absent'] : [])]);
            $before = $record?->fresh()->getAttributes();
            $count = Registration::count();
            $this->actingAs($student);
            foreach ([
                ['course' => 'BSA'],
                ['course' => 'BSN', 'yearLevel' => '1', 'section' => 'A', 'yearSection' => '1A'],
                ['yearLevel' => '1', 'section' => 'C', 'yearSection' => '1C'],
                ['course' => ['BSIT']],
                ['yearLevel' => ['1']],
                ['section' => ['A']],
                ['yearSection' => '3C'],
            ] as $invalid) {
                $payload = ['details' => $this->details($invalid)];
                $response = ($mode === 'edit' ? $this->patchJson('/api/registrations/'.$record->id, $payload)
                    : $this->postJson($mode === 'new' ? '/api/registrations' : '/api/registrations/'.$record->id.'/retry', $payload))->assertUnprocessable();
                if (($invalid['course'] ?? null) === 'BSA') $response->assertJsonValidationErrors(['details.course', 'details.yearSection']);
                $this->assertSame($count, Registration::count());
                if ($record) $this->assertSame($before, $record->fresh()->getAttributes());
            }
            $payload = ['details' => $this->details(['course' => 'bsn', 'yearLevel' => '3', 'section' => 'b', 'yearSection' => '3b'])];
            $response = $mode === 'edit' ? $this->patchJson('/api/registrations/'.$record->id, $payload)
                : $this->postJson($mode === 'new' ? '/api/registrations' : '/api/registrations/'.$record->id.'/retry', $payload);
            $response->assertStatus($mode === 'edit' ? 200 : 201);
            $prefix = $mode === 'new' ? '' : 'registration.';
            $response->assertJsonPath($prefix.'details.course', 'BSN')->assertJsonPath($prefix.'details.yearLevel', '3')
                ->assertJsonPath($prefix.'details.section', 'B')->assertJsonPath($prefix.'details.yearSection', '3B');
            $this->assertSame('BSN', $student->fresh()->course);
            $this->assertSame('3', $student->fresh()->year_level);
            $this->assertSame('B', $student->fresh()->section);
            if ($mode === 'retry') $this->assertSame($before['details'], $record->fresh()->getAttributes()['details']);
        }
    }

    public function test_no_published_groups_blocks_registration_and_removed_groups_block_pending_edits(): void
    {
        $student = $this->student();
        $this->actingAs($student)->postJson('/api/registrations', ['details' => $this->details()])->assertUnprocessable()
            ->assertJsonValidationErrors(['details.course', 'details.yearSection']);
        $this->assertDatabaseCount('recollection_registrations', 0);
        $it = $this->course('BSIT');
        $day = $this->schedule([$it->id => ['1' => ['A']]]);
        $id = $this->postJson('/api/registrations', ['details' => $this->details()])->assertCreated()->json('id');
        $record = Registration::findOrFail($id);
        $before = $record->getAttributes();
        $day->update(['status' => 'Closed']);
        $this->patchJson('/api/registrations/'.$id, ['details' => $this->details(['name' => 'Edited name'])])->assertUnprocessable()
            ->assertJsonValidationErrors('details.course');
        $this->assertSame($before, $record->fresh()->getAttributes());
    }

    public function test_custom_admin_labels_sync_the_student_profile_and_preserve_schedule_eligibility(): void
    {
        $course = $this->course('BS IT');
        foreach (['5', '6'] as $year) AcademicOption::create(['type' => 'year_level', 'name' => $year]);
        foreach (['AA', 'AB'] as $section) AcademicOption::create(['type' => 'section', 'name' => $section]);
        foreach ([['5', 'AA'], ['5', 'AB'], ['6', 'B']] as [$year, $section]) {
            DB::table('course_year_sections')->insert(['course_id' => $course->id, 'year_level' => $year, 'section' => $section,
                'created_at' => now(), 'updated_at' => now()]);
        }
        $day = $this->schedule([$course->id => ['5' => ['AA', 'AB'], '6' => ['B']]]);
        $student = $this->student();
        $selected = $this->details(['course' => 'BS IT', 'yearLevel' => '5', 'section' => 'AA', 'yearSection' => '5AA']);
        $id = $this->actingAs($student)->postJson('/api/registrations', ['details' => $selected])->assertCreated()
            ->assertJsonPath('student_snapshot.year_level', '5')->assertJsonPath('student_snapshot.section', 'AA')->json('id');
        $registration = Registration::findOrFail($id);
        $registration->update(['status' => 'Approved', 'receipt_status' => 'Approved']);
        $this->getJson('/api/student')->assertJsonCount(1, 'schedules')->assertJsonPath('schedules.0.id', $day->id);
        $this->getJson('/api/student?resources=users')->assertJsonCount(1, 'schedules')->assertJsonPath('schedules.0.id', $day->id);
        $this->postJson('/api/registrations/'.$id.'/schedule', ['schedule_id' => $day->id])->assertOk();
        $this->assertSame('BS IT', $student->fresh()->course);
        $this->assertSame('5', $student->fresh()->year_level);
        $this->assertSame('AA', $student->fresh()->section);
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->actingAs($admin)->get(route('admin.schedules.edit', $day->id))->assertOk()->assertViewHas('cfoBackend',
            fn ($backend) => $backend['edit_schedule']->assigned_academic_groups->all() === [
                ['course_id' => $course->id, 'year_level' => '5', 'section' => 'AA', 'count' => 1],
            ]);
        $input = ['title' => $day->title, 'date' => $day->date, 'start_time' => '08:00', 'end_time' => '16:00',
            'venue' => $day->venue, 'status' => 'Open', 'slot_limit' => 50, 'course_ids' => [$course->id],
            'year_levels' => ['5'], 'course_sections_by_year' => [$course->id => ['5' => ['AA']]]];
        $this->actingAs($admin)->putJson('/api/admin/schedules/'.$day->id, $input)->assertOk();
        $this->putJson('/api/admin/schedules/'.$day->id, [...$input, 'course_sections_by_year' => [$course->id => ['5' => ['AB']]]])
            ->assertUnprocessable()->assertSee('exclude assigned students');
        $this->assertSame(['AA'], $day->fresh('courses')->sectionsForCourse($course->id, '5'));
    }

    public function test_legacy_flat_scopes_remain_available_and_ambiguous_combined_fields_require_explicit_year_and_section(): void
    {
        $it = $this->course('BSIT');
        $legacy = $this->schedule([$it->id => ['1' => ['A'], '2' => ['B']]]);
        $legacy->courses()->sync([$it->id => ['sections' => ['A', 'B']]]);
        $details = $this->details(['yearSection' => '2A']);
        unset($details['yearLevel'], $details['section']);
        $this->actingAs($this->student())->postJson('/api/registrations', ['details' => $details])->assertCreated()
            ->assertJsonPath('details.yearLevel', '2')->assertJsonPath('details.section', 'A');

        $ambiguous = $this->course('Custom');
        $this->schedule([$ambiguous->id => ['1' => ['2A'], '12' => ['A']]]);
        $this->actingAs($this->student())->postJson('/api/registrations', ['details' => [...$details, 'course' => 'Custom', 'yearSection' => '12A']])
            ->assertUnprocessable()->assertJsonValidationErrors('details.yearSection');
        $this->postJson('/api/registrations', ['details' => $this->details(['course' => 'Custom', 'yearLevel' => '12', 'section' => 'A', 'yearSection' => '12A'])])
            ->assertCreated()->assertJsonPath('details.yearLevel', '12');
    }

    public function test_a_schedule_changed_after_initial_validation_is_rechecked_before_writing_records_or_receipts(): void
    {
        Storage::fake('local');
        $it = $this->course('BSIT');
        $day = $this->schedule([$it->id => ['1' => ['A']]]);
        $changed = false;
        // Simulate an admin change at the boundary between early validation and the protected recheck.
        DB::listen(function ($query) use ($day, &$changed) {
            if (! $changed && str_contains($query->sql, 'academic_options') && in_array('course', $query->bindings, true)) {
                $changed = true;
                $day->update(['status' => 'Closed']);
            }
        });
        $receipt = UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));
        $this->actingAs($this->student())->post('/api/registrations', ['details' => $this->details(), 'receipt' => $receipt], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors(['details.course', 'details.yearSection']);
        $this->assertTrue($changed);
        $this->assertDatabaseCount('recollection_registrations', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('receipts'));
    }
}
