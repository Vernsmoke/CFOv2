<?php

namespace Tests\Feature;

use App\Models\{AcademicOption, Certificate, PasswordResetRequest, Registration, Schedule, ScheduleChange, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash};
use Tests\TestCase;

class ExistingWorkflowIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'email' => 'audit@gmail.com', 'course' => 'BSIT', 'year_level' => '2', 'section' => 'A', 'password' => 'ExistingPass!234']);
        foreach (['course' => 'BSIT', 'year_level' => '2', 'section' => 'A'] as $type => $name) $options[$type] = AcademicOption::create(compact('type', 'name'));
        $day = Schedule::create(['title' => 'Integrity day', 'date' => today()->toDateString(), 'start_time' => '08:00', 'end_time' => '10:00', 'venue' => 'Hall', 'capacity' => 10, 'slot_limit' => 10, 'active' => true, 'year_level' => '2', 'section' => 'A']);
        $day->courses()->attach($options['course']);
        $registration = Registration::create(['user_id' => $student->id, 'year' => now()->year, 'details' => [], 'status' => 'Approved', 'receipt_status' => 'Approved', 'receipt_path' => 'audit.png', 'recollection_schedule_id' => $day->id]);
        return [$admin, $student, $day, $registration, $options];
    }

    public function test_partial_live_state_refreshes_related_users_and_schedules(): void
    {
        [$admin, $student, $day, $registration] = $this->fixtures();
        $this->actingAs($admin)->getJson('/api/admin/state?resources=users')->assertOk()->assertJsonPath('registrations.0.user.id', $student->id);
        $this->getJson('/api/admin/state?resources=schedules')->assertOk()->assertJsonPath('registrations.0.schedule.id', $day->id);
        $this->actingAs($student)->getJson('/api/student?resources=users')->assertOk()->assertJsonPath('schedules.0.id', $day->id);
        $this->getJson('/api/student?resources=schedules')->assertOk()->assertJsonPath('registrations.0.schedule.id', $day->id);
    }

    public function test_archiving_refreshes_student_notification_links_with_the_registration(): void
    {
        [$admin, $student, $day, $registration] = $this->fixtures();
        $this->actingAs($admin)->postJson('/api/admin/registrations/'.$registration->id.'/attendance', ['attendance' => 'Absent'])->assertOk();
        $this->actingAs($student)->getJson('/api/student?resources=notifications')->assertOk()
            ->assertJsonPath('notifications.0.target', '#dashboard?focus=attendance');
        $notificationVersion = DB::table('cfo_resource_versions')->where('resource', 'notifications')->value('version');
        $this->actingAs($admin)->postJson('/api/admin/schedules/'.$day->id.'/archive', ['confirmed' => true, 'registration_ids' => [$registration->id]])->assertOk();
        $this->assertSame($notificationVersion, DB::table('cfo_resource_versions')->where('resource', 'notifications')->value('version'));
        $this->actingAs($student)->getJson('/api/student?resources=registrations')->assertOk()
            ->assertJsonPath('registrations.0.is_archived', true)
            ->assertJsonPath('notifications.0.target', '#recollection-history')->assertJsonCount(0, 'schedules');
        $this->getJson('/api/history/'.$registration->id)->assertOk();
        $this->assertDatabaseHas('recollection_registrations', ['id' => $registration->id]);
    }

    public function test_available_schedule_slots_refresh_when_registration_membership_changes(): void
    {
        [, $student, $day, $registration] = $this->fixtures();
        $day->update(['capacity' => 2, 'slot_limit' => 2]);
        $other = User::factory()->create(['role' => 'Student', 'status' => 'Approved']);
        $second = Registration::create(['user_id' => $other->id, 'year' => now()->year, 'details' => [], 'recollection_schedule_id' => $day->id]);
        $this->actingAs($student)->getJson('/api/student?resources=registrations')->assertOk()->assertJsonCount(0, 'schedules');
        // Retained archived records do not consume slots in active lists.
        $second->update(['archived_at' => now(), 'archive_reason' => 'year_rollover']);
        $this->getJson('/api/student?resources=registrations')->assertOk()->assertJsonCount(1, 'schedules')
            ->assertJsonPath('schedules.0.registrations_count', 1)->assertJsonPath('server_date', today()->toDateString());
    }

    public function test_student_cannot_request_a_finalized_batch_even_from_a_stale_page(): void
    {
        [, $student, $day, $registration] = $this->fixtures();
        $target = $day->replicate(); $target->date = today()->addDay()->toDateString(); $target->finalized_at = now(); $target->save();
        $target->courses()->sync($day->courses->modelKeys());
        $this->actingAs($student)->postJson('/api/change-requests', ['registration_id' => $registration->id, 'requested_schedule_id' => $target->id, 'reason' => 'Class conflict'])->assertUnprocessable()->assertSee('unfinalized schedule');
        $this->assertDatabaseCount('schedule_change_requests', 0);
        $this->getJson('/api/student?resources=schedules')->assertOk()->assertJsonCount(1, 'schedules')->assertJsonPath('schedules.0.id', $day->id);
    }

    public function test_an_unchanged_schedule_does_not_block_review_of_recorded_attendance(): void
    {
        [$admin, $student, $day, $registration] = $this->fixtures();
        $registration->update(['attendance' => 'Present']);
        $this->actingAs($admin)->patchJson('/api/admin/registrations/'.$registration->id, ['status' => 'Approved', 'schedule_id' => $day->id])->assertOk();
    }

    public function test_admin_reassignment_advances_change_request_version(): void
    {
        [$admin, $student, $day, $registration] = $this->fixtures();
        $other = $day->replicate(); $other->date = today()->addDay()->toDateString(); $other->save(); $other->courses()->sync($day->courses->modelKeys());
        $request = ScheduleChange::create(['registration_id' => $registration->id, 'current_schedule_id' => $day->id, 'requested_schedule_id' => $other->id, 'reason' => 'Conflict']);
        $version = DB::table('cfo_resource_versions')->where('resource', 'schedule_changes')->value('version');
        $this->actingAs($admin)->patchJson('/api/admin/registrations/'.$registration->id, ['schedule_id' => $other->id])->assertOk();
        $this->assertSame('Closed', $request->fresh()->status);
        $this->assertGreaterThan($version, DB::table('cfo_resource_versions')->where('resource', 'schedule_changes')->value('version'));
    }

    public function test_old_recovery_deadline_does_not_block_login_and_success_cancels_recovery(): void
    {
        [$admin, $student] = $this->fixtures();
        $reset = PasswordResetRequest::create(['user_id' => $student->id, 'recovery_hash' => hash('sha256', 'expired'), 'status' => 'Approved', 'expires_at' => now()->subMinute(), 'temp_password_hash' => Hash::make('Temp123!')]);
        $this->postJson('/api/login', ['email' => $student->email, 'password' => 'ExistingPass!234'])->assertOk();
        $this->assertSame('Cancelled', $reset->fresh()->status);
        $this->assertNull($reset->fresh()->temp_password_hash);
    }

    public function test_repeated_recovery_from_the_same_browser_preserves_its_token(): void
    {
        [$admin, $student] = $this->fixtures();
        $this->postJson('/api/forgot-password', ['email' => $student->email])->assertOk();
        $reset = PasswordResetRequest::firstOrFail();
        $this->postJson('/api/forgot-password', ['email' => $student->email])->assertOk();
        $this->assertSame($reset->recovery_hash, hash('sha256', session('recovery_secret')));
        $this->assertSame(1, PasswordResetRequest::count());
    }

    public function test_office_recovery_clears_admin_temporary_password_restrictions(): void
    {
        [$admin, $student] = $this->fixtures();
        $student->update(['password_change_required' => true, 'password_change_expires_at' => now()->subDay()]);
        $reset = PasswordResetRequest::create(['user_id' => $student->id, 'recovery_hash' => hash('sha256', 'verified'), 'status' => 'Verified', 'verified_at' => now()]);
        $this->withSession(['recovery_secret' => 'verified', 'recovery_verified' => $reset->id])->postJson('/api/reset-password', ['password' => 'NewPermanent!234', 'password_confirmation' => 'NewPermanent!234'])->assertOk();
        $this->assertFalse($student->fresh()->password_change_required);
        $this->postJson('/api/login', ['email' => $student->email, 'password' => 'NewPermanent!234'])->assertOk();
    }

    public function test_attendance_correction_revokes_old_certificate_without_deleting_its_record(): void
    {
        [$admin, $student, $day, $registration] = $this->fixtures();
        $registration->update(['attendance' => 'Present', 'attendance_verified_by' => $admin->id, 'attendance_verified_at' => now()]);
        $certificate = Certificate::create(['registration_id' => $registration->id, 'issued_by' => $admin->id, 'certificate_number' => 'CFO-AUDIT', 'snapshot' => ['name' => 'Snapshot']]);
        $this->actingAs($admin)->patchJson('/api/admin/registrations/'.$registration->id, ['attendance' => 'Absent', 'confirm_edit' => true])->assertOk();
        $this->assertNotNull($certificate->fresh()->revoked_at);
        $this->assertNull($registration->fresh()->certificate);
        $this->get('/certificates/'.$certificate->id)->assertNotFound();
        $this->assertDatabaseHas('certificates', ['id' => $certificate->id]);
    }

    public function test_invalid_academic_option_is_rejected_and_schema_audit_passes(): void
    {
        [$admin, $student] = $this->fixtures();
        $this->actingAs($admin)->patchJson('/api/admin/users/'.$student->id, ['first_name' => 'Student', 'last_name' => 'Audit', 'status' => 'Approved', 'course' => 'Nonexistent course'])->assertUnprocessable();
        $this->artisan('cfo:audit-database')->assertSuccessful();
    }

    public function test_corrected_attendance_requires_reverification_before_certificate_reissue(): void
    {
        [$admin, $student, $day, $registration] = $this->fixtures();
        $registration->update(['attendance' => 'Present', 'attendance_verified_by' => $admin->id, 'attendance_verified_at' => now()]);
        $this->actingAs($admin)->postJson('/api/admin/settings/certificate', ['title' => 'Certificate', 'description' => 'Participated.', 'signatory' => 'CFO'])->assertOk();
        $url = '/api/admin/registrations/'.$registration->id;
        $this->postJson($url.'/certificate')->assertOk();
        $original = Certificate::firstOrFail();
        $this->patchJson($url, ['attendance' => 'Absent', 'confirm_edit' => true])->assertOk();
        $this->postJson($url.'/certificate')->assertUnprocessable();
        $this->patchJson($url, ['attendance' => 'Present', 'confirm_edit' => true])->assertOk();
        $this->assertNotNull($registration->fresh()->attendance_recorded_at);
        $this->postJson($url.'/certificate')->assertUnprocessable();
        $this->patchJson($url, ['verify_attendance' => true])->assertOk();
        $this->postJson($url.'/certificate')->assertOk()->assertJsonPath('id', $original->id);
        $this->assertDatabaseCount('certificates', 1);
        $this->assertNull($original->fresh()->revoked_at);
        $this->actingAs($student)->get('/certificates/'.$original->id)->assertOk();
    }

    public function test_database_rejects_registration_with_a_missing_student(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('recollection_registrations')->insert(['user_id' => 987654, 'year' => now()->year, 'details' => '{}']);
    }

    public function test_schedule_edit_cannot_exclude_its_already_assigned_students(): void
    {
        [$admin, , $day, , $options] = $this->fixtures();
        $otherCourse = AcademicOption::create(['type' => 'course', 'name' => 'BSCS']);
        $input = ['title' => $day->title, 'date' => $day->date, 'start_time' => '08:00', 'end_time' => '10:00', 'venue' => 'Hall', 'slot_limit' => 10, 'active' => true, 'year_level' => '2', 'section' => 'A', 'course_ids' => [$otherCourse->id]];
        $this->actingAs($admin)->putJson('/api/admin/schedules/'.$day->id, $input)->assertUnprocessable();
        $this->assertSame([$options['course']->id], $day->fresh()->courses->modelKeys());
        $this->putJson('/api/admin/schedules/'.$day->id, [...$input, 'course_ids' => [$options['course']->id, $otherCourse->id]])->assertOk();
        $this->putJson('/api/admin/schedules/'.$day->id, [...$input, 'course_ids' => [$options['course']->id, $options['course']->id]])->assertUnprocessable();
    }

    public function test_database_rejects_duplicate_registration_year(): void
    {
        [, $student] = $this->fixtures();
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('recollection_registrations')->insert(['user_id' => $student->id, 'year' => now()->year, 'details' => '{}']);
    }

    public function test_database_rejects_duplicate_course_links(): void
    {
        [, , $day, , $options] = $this->fixtures();
        $this->expectException(\Illuminate\Database\QueryException::class);
        $day->courses()->attach($options['course']);
    }

    public function test_schema_audit_reports_a_missing_parent_table_without_crashing(): void
    {
        \Illuminate\Support\Facades\Schema::drop('academic_options');
        $this->artisan('cfo:audit-database')->expectsOutput('Missing table: academic_options')->assertFailed();
    }
}
