<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Support\ConfiguredRegistrationChoices;

class AbsentRegistrationRetryTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguredRegistrationChoices;

    private function fixtures(): array
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(12, 0));
        $this->configuredRegistrationSchedule(['BSIT' => ['2' => ['A']]], ['date' => '2026-10-06']);
        Storage::fake('local');
        Storage::disk('local')->put('receipts/original.png', 'original receipt');
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'course' => 'BSIT', 'year_level' => '2', 'section' => 'A']);
        $details = ['name' => $student->name, 'age' => 20, 'sex' => 'Male', 'course' => 'BSIT', 'yearSection' => '2A', 'email' => $student->email, 'phone' => '09123456789', 'religion' => 'Roman Catholic',
            'emergencyName' => 'Parent', 'relationship' => 'Mother', 'emergencyPhone' => '09123456780', 'participation' => true, 'photos' => true];
        $schedule = Schedule::create(['title' => 'Original recollection', 'date' => '2026-10-05', 'start_time' => '08:00', 'end_time' => '10:00', 'venue' => 'Chapel', 'capacity' => 50, 'active' => true]);
        $r = Registration::create(['user_id' => $student->id, 'year' => 2026, 'details' => $details, 'status' => 'Approved', 'receipt_status' => 'Approved', 'receipt_path' => 'receipts/original.png', 'recollection_schedule_id' => $schedule->id]);

        return [$admin, $student, $schedule, $r, $details];
    }

    public function test_absent_student_can_resubmit_and_admin_can_assign_another_published_schedule(): void
    {
        [$admin, $student, $schedule, $old, $details] = $this->fixtures();
        $this->actingAs($student)->getJson('/api/student')->assertJsonPath('registrations.0.can_reregister', false);
        $this->actingAs($admin)->postJson('/api/admin/registrations/'.$old->id.'/attendance', ['attendance' => 'Absent'])->assertOk();
        $this->actingAs($student)->getJson('/api/student')->assertJsonPath('registrations.0.can_reregister', true)
            ->assertJsonPath('notifications.0.type', 'attendance_absent');
        $payload = ['details' => [...$details, 'phone' => '09123456700']];
        $response = $this->postJson('/api/registrations/'.$old->id.'/retry', $payload)->assertCreated()
            ->assertJsonPath('registration.status', 'Pending')->assertJsonPath('registration.receipt_status', 'Pending');
        $new = Registration::findOrFail($response->json('registration.id'));
        $this->assertSame(2, (int) $new->attempt_no);
        $this->assertNull($new->recollection_schedule_id);
        $this->assertSame('Not Yet Recorded', $new->attendance);
        $this->assertNull($new->attendance_verified_at);
        $this->assertNull($new->receipt_verified_at);
        $this->assertNull($new->archived_at);
        $this->assertSame('Absent', $old->fresh()->attendance);
        $this->assertSame($schedule->id, $old->fresh()->recollection_schedule_id);
        $this->assertSame($details, $old->fresh()->details);
        $this->assertSame('absent_reregistered', $old->fresh()->archive_reason);
        $this->assertNotSame($old->receipt_path, $new->receipt_path);
        Storage::disk('local')->assertExists($old->receipt_path);
        Storage::disk('local')->assertExists($new->receipt_path);
        $this->getJson('/api/student')->assertJsonPath('registrations.0.id', $new->id)->assertJsonPath('registrations.0.can_reregister', false)
            ->assertJsonPath('registrations.1.id', $old->id)->assertJsonPath('registrations.1.can_reregister', false);
        $this->getJson('/api/history/'.$old->id)->assertOk()->assertJsonPath('registration.attendance', 'Absent');
        $this->postJson('/api/registrations/'.$old->id.'/retry', $payload)->assertUnprocessable();
        $this->postJson('/api/registrations', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('recollection_registrations', 2);

        $this->actingAs($admin)->get('/admin/recollection-registrations?schedule_status=not_yet_scheduled')->assertViewHas('registrations', fn ($rows) => $rows->pluck('id')->all() === [$new->id]);
        $this->getJson('/api/admin/state?include_history=1')->assertJsonCount(1, 'registrations')->assertJsonCount(1, 'history');
        $next = $schedule->replicate();
        $next->title = 'New published recollection';
        $next->date = '2026-10-06';
        $next->capacity = 0;
        $next->save();
        $this->patchJson('/api/admin/registrations/'.$new->id, ['receipt_status' => 'Approved'])->assertOk();
        $this->postJson('/api/admin/registrations/'.$new->id.'/schedule', ['schedule_id' => $next->id])->assertUnprocessable();
        $next->update(['capacity' => 10]);
        $this->postJson('/api/admin/registrations/'.$new->id.'/schedule', ['schedule_id' => $next->id])->assertOk();
        $this->actingAs($student)->getJson('/api/student')->assertJsonPath('registrations.0.schedule.id', $next->id)->assertJsonPath('registrations.0.status', 'Approved');
        $this->assertSame('Absent', $old->fresh()->attendance);
    }

    public function test_archived_absent_attempt_can_retry_and_new_receipt_does_not_overwrite_history(): void
    {
        [$admin, $student, $schedule, $old, $details] = $this->fixtures();
        $this->actingAs($admin)->postJson('/api/admin/registrations/'.$old->id.'/attendance', ['attendance' => 'Absent'])->assertOk();
        $this->postJson('/api/admin/schedules/'.$schedule->id.'/archive', ['confirmed' => true, 'registration_ids' => [$old->id]])->assertOk();
        $this->actingAs($student)->getJson('/api/student')->assertJsonPath('registrations.0.can_reregister', true);
        $upload = UploadedFile::fake()->createWithContent('new.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));
        $this->post('/api/registrations/'.$old->id.'/retry', ['details' => $details, 'receipt' => $upload], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame('batch_completed', $old->fresh()->archive_reason);
        $this->assertSame('original receipt', Storage::disk('local')->get($old->receipt_path));
        $this->assertCount(2, Storage::disk('local')->allFiles('receipts'));
    }

    public function test_retry_is_denied_for_other_students_present_unmarked_prior_year_and_corrected_attendance(): void
    {
        [$admin, $student, $schedule, $old, $details] = $this->fixtures();
        $url = '/api/registrations/'.$old->id.'/retry';
        $this->actingAs($admin)->postJson($url, ['details' => $details])->assertForbidden();
        $other = User::factory()->create(['role' => 'Student', 'status' => 'Approved']);
        $this->actingAs($other)->postJson($url, ['details' => $details])->assertForbidden();
        foreach (['Not Yet Recorded', 'Present', 'Late'] as $attendance) {
            $old->update(['attendance' => $attendance]);
            $this->actingAs($student)->postJson($url, ['details' => $details])->assertUnprocessable();
        }
        $old->update(['attendance' => 'Absent', 'year' => 2025]);
        $this->postJson($url, ['details' => $details])->assertUnprocessable();
        $old->update(['year' => 2026]);
        $this->getJson('/api/student')->assertJsonPath('registrations.0.can_reregister', true);
        $this->actingAs($admin)->postJson('/api/admin/registrations/'.$old->id.'/attendance', ['attendance' => 'Present', 'confirm_edit' => true])->assertOk();
        $this->actingAs($student)->postJson($url, ['details' => $details])->assertUnprocessable();
        $this->assertDatabaseCount('recollection_registrations', 1);
    }

    public function test_retry_requires_valid_form_and_renewed_consent_without_altering_old_record(): void
    {
        [$admin, $student, $schedule, $old, $details] = $this->fixtures();
        $old->update(['attendance' => 'Absent']);
        $this->actingAs($student)->postJson('/api/registrations/'.$old->id.'/retry', ['details' => [...$details, 'participation' => false]])
            ->assertUnprocessable()->assertJsonValidationErrors('details.participation');
        $this->assertNull($old->fresh()->archived_at);
        $this->assertDatabaseCount('recollection_registrations', 1);
    }
}
