<?php

namespace Tests\Feature;

use App\Models\{Registration, Schedule, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Support\ConfiguredRegistrationChoices;

class StudentRegistrationReligionTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguredRegistrationChoices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuredRegistrationSchedule();
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'course' => 'BSIT', 'year_level' => '2', 'section' => 'A']);
    }

    private function details(): array
    {
        return ['name' => 'Student Name', 'age' => 20, 'sex' => 'Female', 'course' => 'BSIT', 'yearSection' => '2A',
            'email' => 'student@example.test', 'phone' => '09123456789', 'emergencyName' => 'Parent',
            'relationship' => 'Mother', 'emergencyPhone' => '09123456780', 'religion' => 'Roman Catholic',
            'participation' => true, 'photos' => true];
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

    public static function emptyReligions(): array
    {
        return [
            'missing' => [[]],
            'empty string' => [['religion' => '']],
            'null' => [['religion' => null]],
            'whitespace' => [['religion' => " \t\n "]],
        ];
    }

    #[DataProvider('emptyReligions')]
    public function test_all_registration_paths_reject_empty_religion_without_writing(array $religion): void
    {
        foreach (['new', 'edit', 'retry'] as $mode) {
            $student = $this->student();
            $record = $this->record($student, $mode);
            $before = $record?->fresh()->getAttributes();
            $count = Registration::count();
            $details = array_diff_key($this->details(), ['religion' => true]);
            $response = $this->actingAs($student)->submit($mode, $record, [...$details, ...$religion])
                ->assertUnprocessable()->assertJsonValidationErrors('details.religion');

            $this->assertSame('Please select your religion.', $response->json('errors')['details.religion'][0]);
            $this->assertSame($count, Registration::count());
            if ($record) $this->assertSame($before, $record->fresh()->getAttributes());
        }
    }

    public static function selectedReligions(): array
    {
        return [
            'Roman Catholic' => ['Roman Catholic'],
            'Prefer not to say' => ['Prefer not to say'],
        ];
    }

    #[DataProvider('selectedReligions')]
    public function test_all_registration_paths_accept_selected_religion(string $religion): void
    {
        foreach (['new', 'edit', 'retry'] as $mode) {
            $student = $this->student();
            $record = $this->record($student, $mode);
            $oldDetails = $record?->details;
            $oldSnapshot = $record?->student_snapshot;
            $response = $this->actingAs($student)->submit($mode, $record, [...$this->details(), 'religion' => $religion]);
            if ($mode === 'edit') $response->assertOk();
            else $response->assertCreated();

            $saved = Registration::findOrFail($response->json($mode === 'new' ? 'id' : 'registration.id'));
            $this->assertSame($religion, $saved->details['religion']);
            if ($mode === 'retry') {
                $this->assertSame($oldDetails, $record->fresh()->details);
                $this->assertSame($oldSnapshot, $record->fresh()->student_snapshot);
            }
        }
    }
}
