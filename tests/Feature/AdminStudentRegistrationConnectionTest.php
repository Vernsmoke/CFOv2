<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Support\ConfiguredRegistrationChoices;

class AdminStudentRegistrationConnectionTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguredRegistrationChoices;

    private function receipt(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));
    }

    public function test_student_submission_review_resubmission_and_assignment_share_one_record(): void
    {
        Storage::fake('local');
        $schedule = $this->configuredRegistrationSchedule(['BSIT' => ['2' => ['A']]], ['title' => 'Assigned Recollection', 'venue' => 'Chapel', 'end_time' => '12:00']);
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'course' => null, 'year_level' => '', 'section' => null]);
        $other = User::factory()->create(['role' => 'Student', 'status' => 'Approved']);
        $details = ['name' => $student->name, 'age' => 20, 'sex' => 'Male', 'course' => 'BSIT', 'yearSection' => '2A', 'email' => $student->email,
            'phone' => '09123456789', 'emergencyName' => 'Parent', 'relationship' => 'Mother', 'emergencyPhone' => '09123456780', 'participation' => true, 'photos' => true,
            'medical' => 'Submitted medical information', 'allergies' => 'Submitted allergy information', 'religion' => 'Submitted religion'];
        $url = '/admin/recollection-registrations';
        $baseline = $this->actingAs($admin)->get($url)->assertOk()->assertSee('/registration-live.js', false)->viewData('liveVersions');

        $id = $this->actingAs($student)->post('/api/registrations', ['details' => $details, 'receipt' => $this->receipt()], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $this->getJson('/api/student')->assertJsonCount(1, 'registrations')->assertJsonPath('registrations.0.id', $id)
            ->assertJsonPath('registrations.0.receipt_status', 'Pending')->assertJsonPath('registrations.0.schedule', null);
        $this->assertSame('BSIT', $student->fresh()->course);
        $this->assertSame('2', $student->fresh()->year_level);
        $this->assertSame('A', $student->fresh()->section);
        $this->actingAs($student->fresh())->getJson('/api/student?resources=users')->assertJsonPath('user.course', 'BSIT')->assertJsonPath('user.year_level', '2')->assertJsonPath('user.section', 'A');
        $versions = $this->actingAs($admin)->getJson('/api/live/versions')->assertOk()->json();
        $this->get($url.'?course=BSIT&year_section=2A&search=BSIT+2A')->assertOk()
            ->assertViewHas('registrations', fn ($rows) => $rows->pluck('id')->all() === [$id])
            ->assertViewHas('courses', fn ($courses) => $courses->all() === ['BSIT'])
            ->assertViewHas('yearSections', fn ($sections) => $sections->all() === ['2A'])->assertSee('BSIT')->assertSee('2A');
        $this->assertStringContainsString('BSIT,2A', $this->get($url.'/export?course=BSIT&year_section=2A')->assertOk()->streamedContent());
        $this->assertGreaterThan((int) $baseline['registrations'], $versions['registrations']);
        $this->get($url.'?schedule_status=not_yet_scheduled&status=Pending')->assertViewHas('registrations', fn ($rows) => $rows->pluck('id')->all() === [$id]);
        $this->get($url.'/'.$id)->assertOk()->assertSee($student->name)->assertSee('Receipt Uploaded');
        $this->get('/api/receipts/'.$id)->assertOk();

        $review = '/api/admin/registrations/'.$id;
        $this->patchJson($review, ['receipt_status' => 'Rejected', 'receipt_reason' => 'Please upload a clear receipt.'])->assertOk();
        $this->actingAs($student)->getJson('/api/student?resources=registrations')->assertJsonPath('registrations.0.receipt_status', 'Rejected')
            ->assertJsonPath('registrations.0.receipt_reason', 'Please upload a clear receipt.');
        $oldPath = Registration::findOrFail($id)->receipt_path;
        $this->post('/api/registrations/'.$id.'/receipt', ['receipt' => $this->receipt()], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('local')->assertMissing($oldPath);
        $this->actingAs($admin)->get($url.'?status=Pending')->assertViewHas('registrations', fn ($rows) => $rows->pluck('id')->all() === [$id]);
        $this->patchJson($review, ['receipt_status' => 'Approved'])->assertOk();
        $this->actingAs($student)->getJson('/api/student?resources=registrations')->assertJsonPath('registrations.0.receipt_status', 'Approved')
            ->assertJsonPath('registrations.0.receipt_reason', null)->assertJsonPath('registrations.0.schedule', null);

        $this->actingAs($admin)->postJson($review.'/schedule', ['schedule_id' => $schedule->id])->assertOk();
        $this->get($url.'?schedule_status=scheduled')->assertViewHas('registrations', fn ($rows) => $rows->isEmpty());
        $this->get($url)->assertViewHas('registrations', fn ($rows) => $rows->isEmpty())->assertViewHas('stats', fn ($stats) => $stats['Total Registrations'] === 0);
        $this->get($url.'?schedule_status=not_yet_scheduled')->assertViewHas('registrations', fn ($rows) => $rows->isEmpty());
        $this->getJson('/api/admin/state')->assertJsonPath('registrations.0.id', $id)->assertJsonPath('registrations.0.status', 'Approved')
            ->assertJsonPath('registrations.0.receipt_status', 'Approved')->assertJsonPath('registrations.0.schedule.id', $schedule->id)
            ->assertJsonPath('registrations.0.attendance', 'Not Yet Recorded')->assertJsonPath('registrations.0.is_archived', false)
            ->assertJsonPath('registrations.0.details.medical', $details['medical'])->assertJsonPath('registrations.0.details.allergies', $details['allergies'])
            ->assertJsonPath('registrations.0.details.religion', $details['religion']);
        $this->patchJson($review, ['receipt_status' => 'Rejected', 'receipt_reason' => 'Cannot downgrade an assigned receipt'])->assertUnprocessable();
        $this->actingAs($student)->getJson('/api/student?resources=registrations,schedules,notifications')->assertJsonPath('registrations.0.status', 'Approved')
            ->assertJsonPath('registrations.0.schedule.id', $schedule->id)->assertJsonPath('registrations.0.schedule.venue', 'Chapel')
            ->assertJsonPath('notifications.0.type', 'registration_approved');
        $this->patchJson('/api/registrations/'.$id, ['details' => $details])->assertUnprocessable();
        $this->actingAs($other)->getJson('/api/student')->assertJsonCount(0, 'registrations')->assertJsonCount(0, 'notifications');
        $this->get('/api/receipts/'.$id)->assertForbidden();
        $this->get($url.'/'.$id)->assertForbidden();

        $archiveUrl = '/api/admin/schedules/'.$schedule->id.'/archive';
        $archive = ['confirmed' => true, 'registration_ids' => [$id]];
        $this->actingAs($admin)->postJson($archiveUrl, $archive)->assertUnprocessable();
        $beforeAttendance = $this->getJson('/api/live/versions')->json('attendance');
        $this->postJson($review.'/attendance', ['attendance' => 'Present'])->assertOk();
        $certificate = Certificate::where('registration_id', $id)->firstOrFail();
        $this->assertNull($certificate->revoked_at);
        $this->assertGreaterThan($beforeAttendance, $this->getJson('/api/live/versions')->json('attendance'));
        $this->assertSame(1, DB::table('cfo_notifications')->where('user_id', $student->id)->where('type', 'attendance_present')->count());
        $this->postJson($review.'/attendance', ['attendance' => 'Absent'])->assertUnprocessable();
        $this->postJson($review.'/attendance', ['attendance' => 'Absent', 'confirm_edit' => true])->assertOk();
        $this->assertNotNull($certificate->fresh()->revoked_at);
        $this->postJson($review.'/attendance', ['attendance' => 'Present', 'confirm_edit' => true])->assertOk();
        $this->assertNull($certificate->fresh()->revoked_at);
        $this->assertSame($certificate->id, Registration::findOrFail($id)->certificate->id);
        $this->postJson($archiveUrl, $archive)->assertOk();
        $this->getJson('/api/admin/state?include_history=1')->assertJsonCount(0, 'registrations')->assertJsonCount(1, 'history')
            ->assertJsonPath('history.0.id', $id)->assertJsonPath('history.0.attendance', 'Present');
        $this->get($url)->assertViewHas('registrations', fn ($rows) => $rows->isEmpty());
        $this->assertNotNull($schedule->fresh()->finalized_at);
        $this->assertFalse($schedule->fresh()->active);
        Storage::disk('local')->assertExists(Registration::findOrFail($id)->receipt_path);
        $this->actingAs($student)->getJson('/api/student?resources=registrations,notifications')->assertJsonPath('registrations.0.is_archived', true)
            ->assertJsonPath('registrations.0.attendance', 'Present');
        $this->get('/api/receipts/'.$id)->assertOk();
        $this->get('/certificates/'.$certificate->id)->assertOk();
        $this->assertDatabaseCount('recollection_registrations', 1);
        $this->assertDatabaseCount('certificates', 1);
    }
}
