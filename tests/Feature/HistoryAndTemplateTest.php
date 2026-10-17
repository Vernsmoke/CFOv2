<?php

namespace Tests\Feature;

use App\Models\{Certificate, Registration, Schedule, ScheduleChange, User};
use App\Services\CertificateTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;
use Tests\Support\ConfiguredRegistrationChoices;

class HistoryAndTemplateTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguredRegistrationChoices;

    private function account(string $role = 'Student'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'Approved', 'name' => 'Juan Dela Cruz', 'course' => 'BSIT', 'year_level' => '2', 'section' => 'A']);
    }

    private function registration(User $student, int $year): Registration
    {
        return Registration::create(['user_id' => $student->id, 'year' => $year, 'details' => ['name' => $student->name, 'medical' => 'Original health information']]);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('background.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));
    }

    public function test_year_boundary_moves_records_out_of_active_state_without_changing_or_deleting_them(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-12-31 23:59:59', config('app.timezone')));
        $admin = $this->account('Admin'); $student = $this->account();
        $old = $this->registration($student, 2025); $current = $this->registration($student, 2026);
        $snapshot = $current->fresh()->getAttributes();
        $this->actingAs($admin)->getJson('/api/admin/state?include_history=1')->assertOk()->assertJsonPath('current_year', 2026)->assertJsonPath('registrations.0.id', $current->id)->assertJsonCount(1, 'history');
        $this->travelTo(\Carbon\Carbon::parse('2027-01-01 00:00:00', config('app.timezone')));
        $this->getJson('/api/live/versions')->assertJsonPath('year', 2027);
        $this->getJson('/api/admin/state?resources=year&include_history=1')->assertOk()->assertJsonCount(0, 'registrations')->assertJsonCount(2, 'history')->assertJsonPath('history.0.id', $current->id);
        $this->assertSame([...$snapshot, 'archived_at' => '2027-01-01 00:00:00', 'archive_reason' => 'year_rollover'], $current->fresh()->getAttributes());
        $this->getJson('/api/admin/state')->assertJsonMissingPath('history')->assertJsonCount(0, 'registrations');
        $details = ['name' => $student->name, 'age' => 20, 'sex' => 'Male', 'course' => 'BSIT', 'yearSection' => '2A', 'email' => 'student@example.test', 'phone' => '09123456789', 'religion' => 'Roman Catholic', 'emergencyName' => 'Parent', 'relationship' => 'Parent', 'emergencyPhone' => '09123456780', 'participation' => true, 'photos' => true];
        Storage::fake('local');
        $this->configuredRegistrationSchedule();
        $this->actingAs($student)->postJson('/api/registrations', ['details' => $details, 'receipt' => $this->image()])->assertCreated()->assertJsonPath('year', 2027);
        $this->postJson('/api/registrations', ['details' => $details])->assertUnprocessable();
        $this->actingAs($admin);
        $this->travelTo(\Carbon\Carbon::parse('2028-01-01 00:00:00', config('app.timezone')));
        $this->getJson('/api/admin/state?resources=year&include_history=1')->assertJsonCount(0, 'registrations')->assertJsonCount(3, 'history');
        $this->assertDatabaseCount('recollection_registrations', 3);
        $this->actingAs($student)->getJson('/api/student?resources=year')->assertJsonPath('current_year', 2028)->assertJsonCount(3, 'registrations');
        $this->getJson('/api/live/versions')->assertJsonPath('year', 2028);
    }

    public function test_history_details_retain_academic_snapshot_and_enforce_ownership(): void
    {
        $student = $this->account(); $admin = $this->account('Admin');
        $r = $this->registration($student, now()->year - 1);
        $student->update(['course' => 'BSN', 'year_level' => '3', 'section' => 'B']);
        $this->actingAs($admin)->getJson('/api/history/'.$r->id)->assertOk()->assertJsonPath('registration.student_snapshot.course', 'BSIT')->assertJsonPath('registration.student_snapshot.section', 'A')->assertJsonPath('registration.details.medical', 'Original health information');
        $this->actingAs($student)->getJson('/api/history/'.$r->id)->assertOk();
        $this->actingAs($this->account())->getJson('/api/history/'.$r->id)->assertForbidden();
        $active = $this->registration($student, now()->year);
        $this->actingAs($admin)->getJson('/api/history/'.$active->id)->assertNotFound();
    }

    public function test_archive_after_server_downtime_preserves_related_records_and_private_files(): void
    {
        Storage::fake('local');
        $this->travelTo(\Carbon\Carbon::parse('2026-12-31 23:59:59', 'Asia/Manila'));
        $student = $this->account(); $admin = $this->account('Admin');
        $schedule = Schedule::create(['title' => 'Year-end recollection', 'date' => '2026-12-20',
            'start_time' => '08:00', 'end_time' => '12:00', 'venue' => 'Chapel', 'capacity' => 50]);
        $r = $this->registration($student, 2026);
        Storage::disk('local')->put('receipts/history.png', 'retained receipt');
        $r->update(['status' => 'Approved', 'recollection_schedule_id' => $schedule->id,
            'receipt_path' => 'receipts/history.png', 'receipt_status' => 'Approved',
            'attendance' => 'Present', 'attendance_verified_at' => now(), 'attendance_verified_by' => $admin->id]);
        $certificate = Certificate::create(['registration_id' => $r->id, 'certificate_number' => 'CFO-2026-HISTORY',
            'snapshot' => [...CertificateTemplate::defaults(), 'name' => $student->name, 'year' => 2026,
                'date' => $schedule->date, 'venue' => $schedule->venue, 'event' => $schedule->title], 'issued_by' => $admin->id]);
        $change = ScheduleChange::create(['registration_id' => $r->id, 'current_schedule_id' => $schedule->id,
            'requested_schedule_id' => $schedule->id, 'reason' => 'Original request', 'status' => 'Rejected']);
        $saved = [$r->fresh()->getAttributes(), $certificate->fresh()->getAttributes(), $change->fresh()->getAttributes()];
        $originalCertificate = $this->actingAs($student)->get('/certificates/'.$certificate->id)->assertOk()->getContent();

        // No request, scheduler or batch job is run at midnight, or even during 2027.
        $this->travelTo(\Carbon\Carbon::parse('2028-01-05 09:00:00', 'Asia/Manila'));
        $this->actingAs($admin)->getJson('/api/admin/state?include_history=1')->assertOk()
            ->assertJsonPath('current_year', 2028)->assertJsonPath('archive_timezone', 'Asia/Manila')
            ->assertJsonCount(0, 'registrations')->assertJsonCount(0, 'requests')->assertJsonCount(1, 'history');
        $this->getJson('/api/history/'.$r->id)->assertOk()
            ->assertJsonPath('registration.schedule.venue', 'Chapel')
            ->assertJsonPath('registration.attendance', 'Present')
            ->assertJsonPath('registration.certificate.certificate_number', 'CFO-2026-HISTORY')
            ->assertJsonPath('registration.changes.0.reason', 'Original request');
        $this->get('/api/receipts/'.$r->id)->assertOk();
        $this->actingAs($student)->getJson('/api/history/'.$r->id)->assertOk();
        $this->get('/api/receipts/'.$r->id)->assertOk();
        $this->assertSame($originalCertificate, $this->get('/certificates/'.$certificate->id)->assertOk()->getContent());
        $saved[0]['archived_at'] = '2028-01-05 09:00:00';
        $saved[0]['archive_reason'] = 'year_rollover';
        $this->assertSame($saved, [$r->fresh()->getAttributes(), $certificate->fresh()->getAttributes(), $change->fresh()->getAttributes()]);
        Storage::disk('local')->assertExists('receipts/history.png');
        $this->assertDatabaseCount('recollection_registrations', 1);
        $this->assertDatabaseCount('certificates', 1);
        $this->assertDatabaseCount('schedule_change_requests', 1);
        $this->actingAs($this->account())->get('/api/receipts/'.$r->id)->assertForbidden();
        $this->get('/certificates/'.$certificate->id)->assertForbidden();
    }

    public function test_pending_requests_from_an_old_year_do_not_block_new_year_requests(): void
    {
        $student = $this->account(); $admin = $this->account('Admin');
        $day = Schedule::create(['title' => 'Recollection', 'date' => today()->toDateString(), 'start_time' => '08:00', 'end_time' => '12:00', 'venue' => 'Chapel', 'capacity' => 50, 'active' => true]);
        $target = $day->replicate(); $target->date = today()->addDay()->toDateString(); $target->save();
        $past = $this->registration($student, now()->year - 1);
        ScheduleChange::create(['registration_id' => $past->id, 'current_schedule_id' => $day->id, 'requested_schedule_id' => $target->id, 'reason' => 'Old request']);
        $r = $this->registration($student, now()->year); $r->update(['recollection_schedule_id' => $day->id]);
        $this->actingAs($admin)->getJson('/api/admin/state')->assertJsonCount(0, 'requests');
        $this->actingAs($student)->postJson('/api/change-requests', ['registration_id' => $r->id, 'requested_schedule_id' => $target->id, 'reason' => 'New year request'])->assertCreated();
        $this->getJson('/api/student')->assertJsonCount(1, 'requests');
        $this->getJson('/api/history/'.$past->id)->assertJsonCount(1, 'registration.changes');
    }

    public function test_preview_and_existing_certificate_follow_the_current_template_and_student(): void
    {
        Storage::fake('local');
        $admin = $this->account('Admin'); $student = $this->account();
        $r = $this->registration($student, now()->year);
        $day = Schedule::create(['title' => 'Recollection', 'date' => today()->toDateString(), 'start_time' => '08:00', 'end_time' => '12:00', 'venue' => 'Chapel', 'capacity' => 50]);
        $r->update(['recollection_schedule_id' => $day->id, 'attendance' => 'Present', 'attendance_verified_at' => now()]);
        $settings = [...CertificateTemplate::defaults(), 'font_family' => 'Arial', 'font_size' => 24, 'background_color' => '#eaf4ff'];
        unset($settings['image_path']);
        $preview = $this->actingAs($admin)->postJson('/api/admin/certificate-template/preview', [...$settings, 'image' => $this->image()])->assertOk()->json('svg');
        $this->assertStringContainsString('font-family="Arial,Helvetica,sans-serif"', $preview);
        $this->assertStringContainsString('font-size="24"', $preview);
        $this->assertStringContainsString('fill="#eaf4ff"', $preview);
        $this->assertStringContainsString('data:image/png;base64,', $preview);
        $this->assertDatabaseMissing('cfo_settings', ['key' => 'certificate']);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->postJson('/api/admin/settings/certificate', [...$settings, 'image' => $this->image()])->assertOk();
        $student->update(['name' => 'Corrected Student']);
        $cert = $this->postJson('/api/admin/registrations/'.$r->id.'/certificate')->assertOk()->json();
        $this->assertSame('Juan Dela Cruz', $cert['snapshot']['name']); // Original issuance is retained for audit.
        $before = $this->get('/certificates/'.$cert['id'])->assertOk()->getContent();
        $this->assertStringContainsString('font-family="Arial,Helvetica,sans-serif"', $before);
        $this->assertStringContainsString('fill="#eaf4ff"', $before);
        $this->assertStringContainsString('data:image/png;base64,', $before);
        $this->assertStringContainsString('Corrected Student', $before);
        $originalImage = CertificateTemplate::current()['image_path'];
        $this->postJson('/api/admin/settings/certificate', [...$settings, 'font_family' => 'Verdana', 'font_size' => 16, 'background_color' => '#ffffff', 'remove_image' => true])->assertOk();
        $after = $this->get('/certificates/'.$cert['id'])->assertOk()->getContent();
        $this->assertNotSame($before, $after);
        $this->assertStringContainsString('Verdana', $after);
        $this->assertStringNotContainsString('data:image/png;base64,', $after);
        Storage::disk('local')->assertExists($originalImage);
        $this->actingAs($student)->get('/certificates/'.$cert['id'].'?download=1')->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="'.$cert['certificate_number'].'.svg"');
        $saved = CertificateTemplate::current();
        $this->assertSame('Verdana', $saved['font_family']); $this->assertNull($saved['image_path']);
    }

    public function test_template_validation_and_admin_access_are_enforced(): void
    {
        $settings = CertificateTemplate::defaults();
        $this->actingAs($this->account())->postJson('/api/admin/certificate-template/preview', $settings)->assertForbidden();
        $this->postJson('/api/admin/settings/certificate', $settings)->assertForbidden();
        $this->actingAs($this->account('Admin'));
        foreach ([['font_family' => 'Injected font'], ['font_size' => 100], ['background_color' => 'url(https://invalid.test)'], ['image' => UploadedFile::fake()->create('unsafe.svg', 1, 'image/svg+xml')]] as $invalid) {
            $this->postJson('/api/admin/settings/certificate', [...$settings, ...$invalid])->assertUnprocessable();
            $this->postJson('/api/admin/certificate-template/preview', [...$settings, ...$invalid])->assertUnprocessable();
        }
    }

    public function test_legacy_certificate_defaults_and_xml_escaping_remain_compatible(): void
    {
        $svg = CertificateTemplate::render(['title' => 'Certificate', 'description' => '<script>alert("x")</script>', 'signatory' => 'CFO', 'name' => 'Niño & María'], 'CFO-2025-000001');
        $this->assertStringContainsString('font-family="Georgia,serif"', $svg);
        $this->assertStringContainsString('fill="#fffdf4"', $svg);
        $this->assertStringContainsString('y="715"', $svg);
        $this->assertStringNotContainsString('<script>', $svg);
        $this->assertStringContainsString('Niño &amp; María', $svg);
        $this->assertNotFalse(simplexml_load_string($svg));
        $long = CertificateTemplate::render([...CertificateTemplate::defaults(), 'name' => str_repeat('Niño María ', 20), 'description' => str_repeat('María ', 80)], 'Preview');
        $this->assertNotFalse(simplexml_load_string($long));
    }

    public function test_snapshot_migration_preserves_legacy_details_and_marks_profile_fallback(): void
    {
        $student = $this->account();
        $r = $this->registration($student, now()->year - 1);
        $r->update(['details' => ['name' => 'Original name', 'course' => 'Original course', 'yearSection' => '1-C']]);
        $details = $r->details;
        $migration = require database_path('migrations/2026_09_29_000001_preserve_registration_student_snapshots.php');
        $migration->down();
        $migration->up();
        $r->refresh();
        $this->assertSame($details, $r->details);
        $this->assertSame('Original name', $r->student_snapshot['name']);
        $this->assertSame('Original course', $r->student_snapshot['course']);
        $this->assertSame('1-C', $r->student_snapshot['submitted_year_section']);
        $this->assertTrue($r->student_snapshot['legacy']);
        $this->assertDatabaseCount('recollection_registrations', 1);
    }
}
