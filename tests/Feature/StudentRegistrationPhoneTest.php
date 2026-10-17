<?php

namespace Tests\Feature;

use App\Models\{Registration, Schedule, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Support\ConfiguredRegistrationChoices;

class StudentRegistrationPhoneTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguredRegistrationChoices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuredRegistrationSchedule();
    }

    private const PHONE_ERROR = 'Enter a valid mobile number like 09123456789.';

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

        $attributes = ['user_id' => $student->id, 'year' => now()->year,
            'details' => [...$this->details(), 'phone' => '+63 912 345 6789', 'emergencyPhone' => '9123456780'],
            'student_snapshot' => ['name' => 'Original snapshot', 'phone' => '+63 912 345 6789', 'emergencyPhone' => '9123456780']];
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

    public static function convertibleNumbers(): array
    {
        return [
            'canonical' => ['09123456789', '09123456780'],
            'letters removed' => ['09a123b456c789', '09x123y456z780'],
            'plus 63' => ['+639123456789', '+639123456780'],
            'plus 63 with spaces' => ['+63 912 345 6789', '+63 912 345 6780'],
            '63 country code' => ['639123456789', '639123456780'],
            'ten digits starting with 9' => ['9123456789', '9123456780'],
            'punctuation removed' => ['(0912).345-6789', '(0912).345-6780'],
        ];
    }

    #[DataProvider('convertibleNumbers')]
    public function test_all_registration_paths_clean_and_save_both_phone_numbers(string $phone, string $emergencyPhone): void
    {
        foreach (['new', 'edit', 'retry'] as $mode) {
            $student = $this->student();
            $record = $this->record($student, $mode);
            $oldDetails = $record?->details;
            $oldSnapshot = $record?->student_snapshot;
            $this->actingAs($student);
            $response = $this->submit($mode, $record, [...$this->details(), 'phone' => $phone, 'emergencyPhone' => $emergencyPhone]);
            if ($mode === 'edit') $response->assertOk();
            else $response->assertCreated();

            $saved = Registration::findOrFail($response->json($mode === 'new' ? 'id' : 'registration.id'));
            $this->assertSame('09123456789', $saved->details['phone']);
            $this->assertSame('09123456780', $saved->details['emergencyPhone']);
            if ($mode === 'retry') {
                $this->assertSame($oldDetails, $record->fresh()->details);
                $this->assertSame($oldSnapshot, $record->fresh()->student_snapshot);
                $this->getJson('/api/history/'.$record->id)->assertOk()
                    ->assertJsonPath('registration.details.phone', '+63 912 345 6789')
                    ->assertJsonPath('registration.details.emergencyPhone', '9123456780');
            }
        }
    }

    public static function invalidNumbers(): array
    {
        return [
            'twelfth digit is rejected rather than truncated' => ['091234567890'],
            'incomplete' => ['0912345678'],
            'wrong mobile prefix' => ['08123456789'],
            'wrong country code' => ['649123456789'],
            'letters without digits' => ['abcdefghijk'],
            'empty' => [''],
            'missing value' => [null],
            'all ones after 09' => ['09111111111'],
            'all zeroes after 09' => ['09000000000'],
            'all nines after 09' => ['09999999999'],
            'fake country-code number' => ['+63 911 111 1111'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_all_registration_paths_reject_invalid_numbers_in_either_field_without_writing(?string $value): void
    {
        foreach (['phone', 'emergencyPhone'] as $field) {
            foreach (['new', 'edit', 'retry'] as $mode) {
                $student = $this->student();
                $record = $this->record($student, $mode);
                $before = $record?->fresh()->getAttributes();
                $count = Registration::count();
                $this->actingAs($student);
                $response = $this->submit($mode, $record, [...$this->details(), $field => $value])
                    ->assertUnprocessable()->assertJsonValidationErrors('details.'.$field);
                $this->assertSame(self::PHONE_ERROR, $response->json('errors')['details.'.$field][0]);
                $this->assertSame($count, Registration::count());
                if ($record) $this->assertSame($before, $record->fresh()->getAttributes());
            }
        }
    }

    public function test_all_registration_paths_reject_emergency_number_equal_to_normalized_contact_number(): void
    {
        foreach (['new', 'edit', 'retry'] as $mode) {
            $student = $this->student();
            $record = $this->record($student, $mode);
            $before = $record?->fresh()->getAttributes();
            $count = Registration::count();
            $this->actingAs($student);
            $response = $this->submit($mode, $record, [...$this->details(), 'phone' => '+63 912 345 6789', 'emergencyPhone' => '9123456789'])
                ->assertUnprocessable()->assertJsonValidationErrors('details.emergencyPhone');
            $this->assertSame('Emergency contact number must be different from your own number.', $response->json('errors')['details.emergencyPhone'][0]);
            $this->assertArrayNotHasKey('details.phone', $response->json('errors'));
            $this->assertSame($count, Registration::count());
            if ($record) $this->assertSame($before, $record->fresh()->getAttributes());
        }
    }

    public function test_new_request_normalization_does_not_rewrite_legacy_history_or_snapshot(): void
    {
        Storage::fake('local');
        $student = $this->student();
        Storage::disk('local')->put('receipts/history.png', 'historical receipt');
        $legacyDetails = [...$this->details(), 'phone' => '+63 912 345 6789', 'emergencyPhone' => '(0912)-345-6780'];
        $old = Registration::create(['user_id' => $student->id, 'year' => now()->year - 1, 'status' => 'Approved',
            'details' => $legacyDetails, 'student_snapshot' => ['name' => 'Legacy student', 'phone' => $legacyDetails['phone']],
            'archived_at' => now(), 'archive_reason' => 'batch_completed', 'receipt_path' => 'receipts/history.png', 'receipt_status' => 'Approved']);
        $before = $old->fresh()->getAttributes();
        $this->actingAs($student)->getJson('/api/student')->assertOk()
            ->assertJsonPath('registrations.0.details.phone', '+63 912 345 6789')
            ->assertJsonPath('registrations.0.details.emergencyPhone', '(0912)-345-6780');

        $upload = UploadedFile::fake()->createWithContent('new.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));
        $this->post('/api/registrations', ['details' => $legacyDetails, 'receipt' => $upload], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('details.phone', '09123456789')->assertJsonPath('details.emergencyPhone', '09123456780');
        $this->getJson('/api/history/'.$old->id)->assertOk()
            ->assertJsonPath('registration.details.phone', '+63 912 345 6789')
            ->assertJsonPath('registration.details.emergencyPhone', '(0912)-345-6780');
        $this->assertSame($before, $old->fresh()->getAttributes());
        $this->assertSame('historical receipt', Storage::disk('local')->get('receipts/history.png'));
        $this->assertDatabaseCount('recollection_registrations', 2);
    }
}
