<?php

namespace Tests\Feature;

use App\Models\{Registration, Schedule, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Support\ConfiguredRegistrationChoices;

class StudentRegistrationFieldsTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguredRegistrationChoices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuredRegistrationSchedule(['BSIT' => ['1' => ['A'], '2' => ['A', 'Q'], '3' => ['A', 'B'], '4' => ['Z']], 'BSN' => ['3' => ['B']]]);
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'course' => 'BSIT', 'year_level' => '2', 'section' => 'A']);
    }

    private function details(): array
    {
        return ['name' => 'Student Name', 'age' => 20, 'sex' => 'Female', 'course' => 'BSIT', 'yearSection' => '2A',
            'email' => 'student@example.test', 'phone' => '09123456789', 'religion' => 'Roman Catholic', 'emergencyName' => 'Parent',
            'relationship' => 'Mother', 'emergencyPhone' => '09123456780', 'participation' => true, 'photos' => true];
    }

    private function record(User $student, string $mode): ?Registration
    {
        if ($mode === 'new') return null;

        $attributes = ['user_id' => $student->id, 'year' => now()->year, 'details' => $this->details()];
        if ($mode === 'retry') {
            $schedule = Schedule::create(['title' => 'Published recollection', 'date' => today()->toDateString(),
                'start_time' => '08:00', 'end_time' => '16:00', 'venue' => 'Chapel', 'capacity' => 50, 'active' => true]);
            $attributes += ['status' => 'Approved', 'attendance' => 'Absent', 'receipt_status' => 'Approved', 'recollection_schedule_id' => $schedule->id];
        }

        return Registration::create($attributes);
    }

    private function submit(string $mode, ?Registration $record, array $details)
    {
        return $mode === 'edit'
            ? $this->patchJson('/api/registrations/'.$record->id, ['details' => $details])
            : $this->postJson($mode === 'retry' ? '/api/registrations/'.$record->id.'/retry' : '/api/registrations', ['details' => $details]);
    }

    public static function invalidOrUnconfiguredFields(): array
    {
        return [
            'course numbers' => ['course', 'BSIT1'],
            'course spaces' => ['course', 'BS IT'],
            'course symbol' => ['course', 'BS-IT'],
            'course non-ASCII letter' => ['course', 'BSÍT'],
            'course newline' => ['course', "BS\nIT"],
            'course array' => ['course', ['BSIT']],
            'course empty' => ['course', ''],
            'course too long' => ['course', str_repeat('A', 191)],
            'year outside range' => ['yearSection', '5A'],
            'year zero' => ['yearSection', '0A'],
            'year dash' => ['yearSection', '3-A'],
            'year space' => ['yearSection', '3 A'],
            'year third character' => ['yearSection', '3AB'],
            'year incomplete' => ['yearSection', '3'],
            'year reversed' => ['yearSection', 'A3'],
            'year second number' => ['yearSection', '33'],
            'year non-ASCII letter' => ['yearSection', '3É'],
            'year array' => ['yearSection', ['3A']],
            'year empty' => ['yearSection', ''],
        ];
    }

    #[DataProvider('invalidOrUnconfiguredFields')]
    public function test_all_registration_paths_reject_invalid_or_unconfigured_academic_fields_without_writing(string $field, mixed $value): void
    {
        foreach (['new', 'edit', 'retry'] as $mode) {
            $student = $this->student();
            $record = $this->record($student, $mode);
            $before = $record?->fresh()->getAttributes();
            $count = Registration::count();
            $this->actingAs($student);
            $this->submit($mode, $record, [...$this->details(), $field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors('details.'.$field);
            $this->assertSame($count, Registration::count());
            if ($record) $this->assertSame($before, $record->fresh()->getAttributes());
        }
    }

    public function test_all_registration_paths_sync_canonical_academic_choices_and_preserve_old_attempt_details(): void
    {
        foreach (['new', 'edit', 'retry'] as $mode) {
            $student = $this->student();
            $record = $this->record($student, $mode);
            $beforeDetails = $record?->details;
            $beforeSnapshot = $record?->student_snapshot;
            $this->actingAs($student);
            $response = $this->submit($mode, $record, [...$this->details(), 'course' => 'bsn', 'yearSection' => '3b']);
            if ($mode === 'edit') $response->assertOk();
            else $response->assertCreated();
            $saved = Registration::findOrFail($response->json($mode === 'new' ? 'id' : 'registration.id'));
            $this->assertSame('BSN', $saved->details['course']);
            $this->assertSame('3B', $saved->details['yearSection']);
            $this->assertSame('3', $saved->details['yearLevel']);
            $this->assertSame('B', $saved->details['section']);
            $this->assertSame('BSN', $student->fresh()->course);
            $this->assertSame('3', $student->fresh()->year_level);
            $this->assertSame('B', $student->fresh()->section);
            if ($mode === 'retry') {
                $this->assertSame($beforeDetails, $record->fresh()->details);
                $this->assertSame($beforeSnapshot, $record->fresh()->student_snapshot);
            }
        }
    }

    public function test_each_configured_year_and_section_is_accepted_and_capitalized(): void
    {
        $student = $this->student();
        $record = $this->record($student, 'edit');
        $this->actingAs($student);
        foreach (['1a', '2q', '3b', '4z'] as $selection) {
            $this->submit('edit', $record, [...$this->details(), 'yearSection' => $selection])
                ->assertOk()->assertJsonPath('registration.details.yearSection', strtoupper($selection));
        }
    }

    public function test_new_year_requires_new_receipt_and_preserves_legacy_history_and_snapshot(): void
    {
        Storage::fake('local');
        $student = $this->student();
        Storage::disk('local')->put('receipts/history.png', 'historical receipt');
        $old = Registration::create(['user_id' => $student->id, 'year' => now()->year - 1, 'status' => 'Approved',
            'details' => [...$this->details(), 'course' => 'bsit', 'yearSection' => '3-A'], 'archived_at' => now(),
            'archive_reason' => 'batch_completed', 'receipt_path' => 'receipts/history.png', 'receipt_status' => 'Approved']);
        $before = $old->fresh()->getAttributes();
        $this->actingAs($student);
        $this->postJson('/api/registrations', ['details' => $this->details()])
            ->assertUnprocessable()->assertJsonValidationErrors('receipt')
            ->assertJsonPath('errors.receipt.0', 'Upload a new receipt for this registration.');
        $this->assertDatabaseCount('recollection_registrations', 1);
        $this->assertSame($before, $old->fresh()->getAttributes());

        $upload = UploadedFile::fake()->createWithContent('new.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));
        $response = $this->post('/api/registrations', ['details' => [...$this->details(), 'course' => 'bsit', 'yearSection' => '3a'], 'receipt' => $upload], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('details.course', 'BSIT')->assertJsonPath('details.yearSection', '3A')->assertJsonPath('receipt_status', 'Pending');
        $new = Registration::findOrFail($response->json('id'));
        $this->assertNotSame($old->receipt_path, $new->receipt_path);
        Storage::disk('local')->assertExists($new->receipt_path);
        $this->assertSame('historical receipt', Storage::disk('local')->get($old->receipt_path));
        $this->assertSame($before, $old->fresh()->getAttributes());
        $this->assertDatabaseCount('recollection_registrations', 2);
    }

    public function test_academic_backfill_uses_latest_active_record_fills_only_blanks_and_is_idempotent(): void
    {
        $blank = $this->student();
        $blank->update(['course' => null, 'year_level' => '', 'section' => ' ']);
        $older = Registration::create(['user_id' => $blank->id, 'year' => now()->year, 'attempt_no' => 1,
            'details' => [...$this->details(), 'course' => 'BSIT', 'yearLevel' => '2', 'section' => 'A']]);
        $latest = Registration::create(['user_id' => $blank->id, 'year' => now()->year, 'attempt_no' => 2,
            'details' => [...$this->details(), 'course' => 'BSN', 'yearLevel' => '3', 'section' => 'B', 'yearSection' => '3B']]);
        $archived = Registration::create(['user_id' => $blank->id, 'year' => now()->year, 'attempt_no' => 3,
            'archived_at' => now(), 'details' => [...$this->details(), 'course' => 'ARCHIVED', 'yearLevel' => '4', 'section' => 'Z']]);
        $partial = $this->student();
        $partial->update(['course' => 'KEEP', 'year_level' => null, 'section' => '']);
        Registration::create(['user_id' => $partial->id, 'year' => now()->year, 'details' => [],
            'student_snapshot' => ['course' => 'BSN', 'year_level' => '3', 'section' => 'C']]);
        $legacy = $this->student();
        $legacy->update(['course' => '', 'year_level' => '', 'section' => '']);
        Registration::create(['user_id' => $legacy->id, 'year' => now()->year,
            'details' => ['course' => 'BSIT', 'yearSection' => '4-z']]);
        $archivedOnly = $this->student();
        $archivedOnly->update(['course' => '', 'year_level' => '', 'section' => '']);
        Registration::create(['user_id' => $archivedOnly->id, 'year' => now()->year, 'archived_at' => now(),
            'details' => [...$this->details(), 'yearLevel' => '2', 'section' => 'A']]);
        $before = [$older->fresh()->getAttributes(), $latest->fresh()->getAttributes(), $archived->fresh()->getAttributes()];
        $version = DB::table('cfo_resource_versions')->where('resource', 'registrations')->value('version');
        $migration = require database_path('migrations/2026_10_11_000002_sync_student_academic_fields_from_registration.php');
        $migration->up();
        $this->assertSame(['BSN', '3', 'B'], [$blank->fresh()->course, $blank->fresh()->year_level, $blank->fresh()->section]);
        $this->assertSame(['KEEP', '3', 'C'], [$partial->fresh()->course, $partial->fresh()->year_level, $partial->fresh()->section]);
        $this->assertSame(['BSIT', '4', 'Z'], [$legacy->fresh()->course, $legacy->fresh()->year_level, $legacy->fresh()->section]);
        $this->assertSame(['', '', ''], [$archivedOnly->fresh()->course, $archivedOnly->fresh()->year_level, $archivedOnly->fresh()->section]);
        $this->assertSame($before, [$older->fresh()->getAttributes(), $latest->fresh()->getAttributes(), $archived->fresh()->getAttributes()]);
        $this->assertGreaterThan($version, DB::table('cfo_resource_versions')->where('resource', 'registrations')->value('version'));
        $after = $blank->fresh()->getAttributes();
        $versions = DB::table('cfo_resource_versions')->orderBy('resource')->pluck('version', 'resource')->all();
        $migration->up();
        $this->assertSame($after, $blank->fresh()->getAttributes());
        $this->assertSame($versions, DB::table('cfo_resource_versions')->orderBy('resource')->pluck('version', 'resource')->all());
        $blank->update(['course' => 'STALE', 'year_level' => '1', 'section' => 'X']);
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->actingAs($admin)->get('/admin/recollection-registrations')->assertOk()->assertSee('BSN')->assertSee('3B');
        $this->get('/admin/recollection-registrations/'.$latest->id)->assertOk()->assertSee('BSN')->assertSee('3B');
        $this->assertStringContainsString('BSN,3B', $this->get('/admin/recollection-registrations/export')->assertOk()->streamedContent());
    }

    public function test_validation_messages_explain_admin_configured_academic_choices(): void
    {
        $this->actingAs($this->student());
        $response = $this->postJson('/api/registrations', ['details' => [...$this->details(), 'course' => 'BS-IT', 'yearSection' => '3']])
            ->assertUnprocessable();
        $this->assertSame('Select a course/program currently available in an admin-configured recollection schedule.', $response->json('errors')['details.course'][0]);
        $this->assertSame('Select an available year level and section for your chosen course/program.', $response->json('errors')['details.yearSection'][0]);
    }
}
