<?php

namespace Tests\Feature;

use App\Models\{Certificate, Registration, Schedule, User};
use App\Services\AutomaticCertificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BatchWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'name' => 'Original Student']);
        $schedule = Schedule::create(['title' => 'Batch One', 'date' => today()->toDateString(), 'start_time' => '08:00',
            'end_time' => '12:00', 'venue' => 'Chapel', 'capacity' => 50, 'active' => true]);
        $registration = Registration::create(['user_id' => $student->id, 'year' => now()->year,
            'details' => ['name' => $student->name, 'medical' => 'Saved information'], 'receipt_path' => 'receipts/original.png']);
        return [$admin, $student, $schedule, $registration];
    }

    public function test_schedule_attendance_and_full_batch_archive_without_deleting_records(): void
    {
        [$admin, $student, $day, $r] = $this->fixtures();
        $otherStudent = User::factory()->create(['role' => 'Student', 'status' => 'Approved']);
        $other = Registration::create(['user_id' => $otherStudent->id, 'year' => now()->year, 'details' => [], 'receipt_path' => 'receipts/other.png']);
        $this->actingAs($admin);
        foreach ([$r, $other] as $row) $this->postJson('/api/admin/registrations/'.$row->id.'/schedule', ['schedule_id' => $day->id])->assertOk();
        $this->assertSame('Approved', $r->fresh()->status);
        $this->assertSame('Approved', $r->fresh()->receipt_status);
        $this->assertSame($day->id, $r->fresh()->recollection_schedule_id);
        $payload = ['confirmed' => true, 'registration_ids' => [$r->id, $other->id]];
        $url = '/api/admin/schedules/'.$day->id.'/archive';
        $this->postJson($url, $payload)->assertUnprocessable();
        $this->assertNull($r->fresh()->archived_at);
        $this->postJson('/api/admin/registrations/'.$r->id.'/attendance', ['attendance' => 'Present'])->assertOk();
        $certificate = Certificate::where('registration_id', $r->id)->firstOrFail();
        $this->assertNull($certificate->revoked_at);
        $this->postJson($url, $payload)->assertUnprocessable();
        $this->postJson('/api/admin/registrations/'.$other->id.'/attendance', ['attendance' => 'Absent'])->assertOk();
        $this->postJson($url, ['registration_ids' => $payload['registration_ids']])->assertUnprocessable();
        $this->postJson($url, ['confirmed' => true, 'registration_ids' => [$r->id]])->assertUnprocessable();
        $this->postJson($url, $payload)->assertOk();
        $this->assertNotNull($day->fresh()->finalized_at);
        $this->assertSame('batch_completed', $r->fresh()->archive_reason);
        $this->assertSame($admin->id, $r->fresh()->archived_by);
        $this->getJson('/api/admin/state?include_history=1')->assertJsonCount(0, 'registrations')->assertJsonCount(2, 'history');
        $this->getJson('/api/history/'.$r->id)->assertOk()->assertJsonPath('registration.is_archived', true)
            ->assertJsonPath('registration.details.medical', 'Saved information')->assertJsonPath('registration.has_receipt', true);
        $this->postJson($url, $payload)->assertUnprocessable();
        $this->assertDatabaseCount('recollection_registrations', 2);
        $this->assertDatabaseCount('certificates', 1);
        $this->actingAs($student)->getJson('/api/student')->assertJsonPath('registrations.0.is_archived', true);
        $this->get('/certificates/'.$certificate->id)->assertOk();
        $this->getJson('/api/history/'.$r->id)->assertOk();
        $this->getJson('/api/history/'.$other->id)->assertForbidden();
    }

    public function test_schedule_correction_resets_attendance_and_certificate_and_rejects_archived_changes(): void
    {
        [$admin, $student, $day, $r] = $this->fixtures();
        $next = $day->replicate(); $next->title = 'Batch Two'; $next->save();
        $url = '/api/admin/registrations/'.$r->id;
        $this->actingAs($admin)->postJson($url.'/schedule', ['schedule_id' => $day->id])->assertOk();
        $this->postJson($url.'/attendance', ['attendance' => 'Present'])->assertOk();
        $certificate = $r->fresh()->certificate;
        $this->postJson($url.'/schedule', ['schedule_id' => $next->id])->assertUnprocessable();
        $this->postJson($url.'/schedule', ['schedule_id' => $next->id, 'confirm_attendance_reset' => true])->assertOk();
        $this->assertSame('Not Yet Recorded', $r->fresh()->attendance);
        $this->assertNull($r->fresh()->attendance_verified_at);
        $this->assertNotNull($certificate->fresh()->revoked_at);
        $this->get('/certificates/'.$certificate->id)->assertNotFound();
        $this->postJson($url.'/attendance', ['attendance' => 'Present'])->assertOk();
        $this->assertSame($certificate->id, $r->fresh()->certificate->id);
        $this->postJson('/api/admin/schedules/'.$next->id.'/archive', ['confirmed' => true, 'registration_ids' => [$r->id]])->assertOk();
        $this->postJson($url.'/schedule', ['schedule_id' => $day->id, 'confirm_attendance_reset' => true])->assertUnprocessable();
        $this->postJson($url.'/attendance', ['attendance' => 'Absent', 'confirm_edit' => true])->assertOk();
        $this->assertTrue($r->fresh()->is_archived);
        $this->get('/certificates/'.$certificate->id)->assertNotFound();
    }

    public function test_certificate_is_automatic_idempotent_and_renders_latest_content_without_reissue(): void
    {
        [$admin, $student, $day, $r] = $this->fixtures();
        $url = '/api/admin/registrations/'.$r->id;
        $this->actingAs($admin)->postJson($url.'/schedule', ['schedule_id' => $day->id])->assertOk();
        $this->postJson($url.'/attendance', ['attendance' => 'Present'])->assertOk();
        $certificate = $r->fresh()->certificate;
        $snapshot = $certificate->snapshot;
        $this->postJson($url.'/attendance', ['attendance' => 'Present'])->assertOk();
        $this->assertDatabaseCount('certificates', 1);
        $this->assertSame(1, DB::table('cfo_notifications')->where('type', 'certificate_ready')->count());
        $before = $this->actingAs($student)->getJson('/api/student')->json('certificate_revision');
        $this->get('/certificates/'.$certificate->id)->assertOk()->assertSee('Original Student');
        $this->actingAs($admin)->postJson('/api/admin/settings/certificate', ['title' => 'Updated Design', 'description' => 'Completed recollection', 'signatory' => 'CFO Director'])->assertOk();
        $student->update(['name' => 'Corrected Student']);
        $day->update(['title' => 'Corrected Recollection']);
        $this->actingAs($student)->get('/certificates/'.$certificate->id)->assertOk()->assertSee('Updated Design')
            ->assertSee('Corrected Student')->assertSee('Corrected Recollection')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertNotSame($before, $this->getJson('/api/student?resources=settings')->json('certificate_revision'));
        $this->getJson('/api/live/versions')->assertJsonStructure(['settings']);
        $this->assertSame($snapshot, $certificate->fresh()->snapshot);
        $this->assertDatabaseCount('certificates', 1);
    }

    public function test_automatic_certificate_uses_only_the_students_group_for_issuance_view_and_download(): void
    {
        [$admin, $student, $day, $r] = $this->fixtures();
        $student->update(['course' => 'BSN', 'year_level' => '2nd Year', 'section' => 'B']);
        $r->update(['details' => ['course' => ' BSIT ', 'yearLevel' => '3rd Year', 'section' => ' A '],
            'student_snapshot' => ['course' => 'BSMT', 'year_level' => '1st Year', 'section' => 'C']]);
        $date = $day->date;
        $day->update(['title' => 'Recollection – 2nd Year, 3rd Year – BSIT 2A / BSN 3B – '.$date]);
        $url = '/api/admin/registrations/'.$r->id;
        $this->actingAs($admin)->postJson($url.'/schedule', ['schedule_id' => $day->id])->assertOk();
        $this->postJson($url.'/attendance', ['attendance' => 'Present'])->assertOk();
        $certificate = $r->fresh()->certificate;
        $event = 'Recollection - BSIT 3A - '.$date;
        $this->assertSame($event, $certificate->snapshot['event']);
        $saved = $certificate->getAttributes();
        $this->actingAs($student);
        foreach (['' => 'inline', '?download=1' => 'attachment'] as $query => $disposition) {
            $this->get('/certificates/'.$certificate->id.$query)->assertOk()->assertSee($event, false)
                ->assertDontSee('BSIT 2A')->assertDontSee('BSN 3B')->assertDontSee('BSMT')
                ->assertDontSee('2nd Year, 3rd Year')
                ->assertHeader('Content-Disposition', $disposition.'; filename="'.$certificate->certificate_number.'.svg"');
        }
        $this->assertSame($saved, $certificate->fresh()->getAttributes());
        $this->assertDatabaseCount('certificates', 1);
        $this->assertSame(1, DB::table('cfo_notifications')->where('type', 'certificate_ready')->count());
    }

    public function test_legacy_issued_certificate_renders_its_own_group_without_changing_issuance(): void
    {
        [$admin, $student, $day, $r] = $this->fixtures();
        $student->update(['course' => 'BSN', 'year_level' => '4th Year', 'section' => 'C']);
        $date = $day->date;
        $day->update(['title' => 'Recollection - 2nd Year, 3rd Year - BSIT 2A / BSN 3B - '.$date]);
        $r->update(['details' => [], 'student_snapshot' => ['course' => 'BSIT', 'year_level' => '3rd Year', 'section' => 'A'],
            'recollection_schedule_id' => $day->id, 'attendance' => 'Present', 'attendance_verified_at' => now(),
            'attendance_verified_by' => $admin->id, 'archived_at' => now(), 'archive_reason' => 'batch_completed']);
        $certificate = Certificate::create(['registration_id' => $r->id, 'certificate_number' => 'CFO-LEGACY-GROUP',
            'snapshot' => ['title' => 'Original Certificate', 'name' => 'Original Student', 'event' => $day->title, 'date' => $date],
            'issued_by' => $admin->id, 'created_at' => now()->subMonth(), 'updated_at' => now()->subMonth()]);
        $saved = $certificate->fresh()->getAttributes();
        $event = 'Recollection - BSIT 3A - '.$date;
        $this->actingAs($student);
        $view = $this->get('/certificates/'.$certificate->id)->assertOk()->assertSee($event, false)
            ->assertDontSee('BSIT 2A')->assertDontSee('BSN 3B')->assertDontSee('2nd Year, 3rd Year');
        $download = $this->get('/certificates/'.$certificate->id.'?download=1')->assertOk()->assertSee($event, false)
            ->assertDontSee('BSIT 2A')->assertDontSee('BSN 3B')
            ->assertHeader('Content-Disposition', 'attachment; filename="'.$certificate->certificate_number.'.svg"');
        $this->assertSame($view->getContent(), $download->getContent());
        $this->assertSame($saved, $certificate->fresh()->getAttributes());
        $this->assertSame($day->title, $certificate->fresh()->snapshot['event']);
        $this->assertDatabaseCount('certificates', 1);
    }

    public function test_certificate_group_uses_nonblank_details_then_snapshot_then_profile(): void
    {
        [, $student, $day, $r] = $this->fixtures();
        $student->fill(['course' => 'BSMT', 'year_level' => '1st Year', 'section' => 'C']);
        $day->title = 'Recollection – 2nd Year, 3rd Year – BSIT 2A / BSN 3B – '.$day->date;
        $r->setRelation('user', $student)->setRelation('schedule', $day);
        $cases = [
            'details' => [['course' => ' BSIT ', 'yearLevel' => '3rd Year', 'section' => ' A '],
                ['course' => 'BSN', 'year_level' => '4th Year', 'section' => 'B'], 'BSIT 3A'],
            'blank details' => [['course' => ' ', 'yearLevel' => "\t", 'section' => null],
                ['course' => 'BSIT', 'year_level' => '3rd Year', 'section' => 'A'], 'BSIT 3A'],
            'non-scalar details' => [['course' => ['BSN'], 'yearLevel' => [], 'section' => []],
                ['course' => 'BSIT', 'year_level' => '3rd Year', 'section' => 'A'], 'BSIT 3A'],
            'mixed sources' => [['course' => 'BSIT', 'yearLevel' => '', 'section' => ' '],
                ['course' => 'BSN', 'year_level' => '3rd Year', 'section' => ''], 'BSIT 3C'],
            'legacy details' => [['course' => 'BSIT', 'yearSection' => '3-a'],
                ['course' => 'BSN', 'year_level' => '4th Year', 'section' => 'B'], 'BSIT 3A'],
            'legacy snapshot' => [[], ['course' => 'BSIT', 'year_level' => '', 'section' => [],
                'submitted_year_section' => '3/A'], 'BSIT 3A'],
            'submitted snapshot before profile fallback' => [[], ['course' => 'BSIT', 'year_level' => '3rd Year', 'section' => 'A',
                'submitted_year_section' => '4-b'], 'BSIT 4B'],
            'blank snapshot' => [[], ['course' => ' ', 'year_level' => '', 'section' => null], 'BSMT 1C'],
            'profile only' => [[], [], 'BSMT 1C'],
        ];
        foreach ($cases as $label => [$details, $snapshot, $group]) {
            $r->details = $details;
            $r->student_snapshot = $snapshot;
            $this->assertSame('Recollection - '.$group.' - '.$day->date, AutomaticCertificate::content($r)['event'], $label);
        }
    }

    public function test_certificate_event_handles_missing_titles_and_partial_student_groups(): void
    {
        [, , $day, $r] = $this->fixtures();
        $day->title = ' ';
        $r->student_snapshot = [];
        $r->setRelation('user', null)->setRelation('schedule', $day);
        $cases = [
            [['course' => 'BSIT', 'yearLevel' => '3rd Year', 'section' => 'A'], 'Recollection - BSIT 3A'],
            [['course' => 'BSIT', 'yearLevel' => '', 'section' => 'A'], 'Recollection - BSIT A'],
            [['course' => '', 'yearLevel' => '3rd Year', 'section' => 'A'], 'Recollection - 3A'],
            [['course' => 'BSIT', 'yearLevel' => '3rd Year', 'section' => ''], 'Recollection - BSIT 3'],
            [['course' => 'BSIT'], 'Recollection - BSIT'],
            [['yearLevel' => '3rd Year'], 'Recollection - 3'],
            [['section' => 'A'], 'Recollection - A'],
            [['course' => ' ', 'yearLevel' => null, 'section' => []], 'Recollection'],
        ];
        foreach ($cases as [$details, $event]) {
            $r->details = $details;
            $this->assertSame($event, AutomaticCertificate::content($r)['event']);
        }
        $r->details = ['course' => 'BSIT', 'yearLevel' => '3rd Year', 'section' => 'A'];
        $r->setRelation('schedule', null);
        $this->assertSame('Recollection - BSIT 3A', AutomaticCertificate::content($r)['event']);
        $day->title = 'Corrected Recollection';
        $r->setRelation('schedule', $day);
        $this->assertSame('Corrected Recollection - BSIT 3A', AutomaticCertificate::content($r)['event']);
    }

    public function test_authorization_and_unavailable_schedule_guards(): void
    {
        [$admin, $student, $day, $r] = $this->fixtures();
        $url = '/api/admin/registrations/'.$r->id;
        $this->actingAs($student)->postJson($url.'/schedule', ['schedule_id' => $day->id])->assertForbidden();
        $this->postJson($url.'/attendance', ['attendance' => 'Present'])->assertForbidden();
        $this->postJson('/api/admin/schedules/'.$day->id.'/archive', ['confirmed' => true, 'registration_ids' => [$r->id]])->assertForbidden();
        $this->actingAs($admin)->postJson($url.'/attendance', ['attendance' => 'Present'])->assertUnprocessable();
        $day->update(['finalized_at' => now()]);
        $this->postJson($url.'/schedule', ['schedule_id' => $day->id])->assertUnprocessable();
        $this->assertSame('Pending', $r->fresh()->status);
        $day->update(['finalized_at' => null, 'capacity' => 0]);
        $this->postJson($url.'/schedule', ['schedule_id' => $day->id])->assertUnprocessable();
        $this->assertNull($r->fresh()->recollection_schedule_id);
    }
}
