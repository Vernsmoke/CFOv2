<?php

namespace Tests\Feature;

use App\Models\{Announcement, Registration, Schedule, ScheduleChange, User};
use App\Services\CfoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'Student'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'Approved']);
    }

    private function day(): Schedule
    {
        return Schedule::create(['title' => 'Retreat', 'date' => today()->addDay()->toDateString(), 'start_time' => '08:00', 'end_time' => '12:00', 'venue' => 'Chapel', 'capacity' => 50, 'active' => true]);
    }

    private function registration(User $student, array $extra = []): Registration
    {
        return Registration::create(['user_id' => $student->id, 'year' => now()->year, 'details' => [], ...$extra]);
    }

    public function test_unviewed_notifications_stack_beyond_fifty_and_only_displayed_owned_ids_are_viewed(): void
    {
        $student = $this->account(); $other = $this->account();
        for ($i = 0; $i < 65; $i++) CfoService::notifyStudent($student->id, 'announcement', 'News '.$i, 'announcement', $i + 1);
        CfoService::notify($student->id, 'Legacy generic account message');
        CfoService::notifyStudent($other->id, 'announcement', 'Other student notice', 'announcement', 1);
        $response = $this->actingAs($student)->getJson('/api/student?resources=notifications')
            ->assertOk()->assertJsonCount(65, 'notifications')->assertJsonPath('notification_unviewed_count', 65);
        $ids = array_column($response->json('notifications'), 'id');
        $this->assertCount(65, array_unique($ids));
        $this->assertStringNotContainsString('Legacy generic', $response->getContent());

        // This message arrives after the student opened the rendered list.
        CfoService::notifyStudent($student->id, 'announcement', 'Arrived during viewing', 'announcement', 100);
        $otherId = DB::table('cfo_notifications')->where('user_id', $other->id)->value('id');
        $this->postJson('/api/student/notifications/viewed', ['ids' => [...$ids, $otherId]])->assertOk()->assertJsonPath('viewed', 65);
        $this->assertNull(DB::table('cfo_notifications')->where('id', $otherId)->value('read_at'));
        $this->getJson('/api/student?resources=notifications')->assertJsonPath('notification_unviewed_count', 1);
        $this->postJson('/api/student/notifications/viewed', ['ids' => $ids])->assertJsonPath('viewed', 0);
        $this->postJson('/api/student/notifications/viewed', [])->assertUnprocessable();
        $this->assertDatabaseCount('cfo_notifications', 68);
        $this->getJson('/api/live/versions')->assertJsonStructure(['notifications', 'announcements']);
        $this->actingAs($this->account('Admin'))->postJson('/api/student/notifications/viewed', ['ids' => $ids])->assertForbidden();
    }

    public function test_only_meaningful_registration_attendance_and_certificate_transitions_notify(): void
    {
        $student = $this->account(); $admin = $this->account('Admin');
        $r = $this->registration($student);
        $url = '/api/admin/registrations/'.$r->id;
        $this->actingAs($admin)->patchJson($url, ['status' => 'Approved'])->assertOk();
        $this->patchJson($url, ['status' => 'Approved'])->assertOk();
        $this->assertSame(1, DB::table('cfo_notifications')->where('type', 'registration_approved')->count());

        $r->update(['recollection_schedule_id' => $this->day()->id]);
        $this->patchJson($url, ['attendance' => 'Present', 'verify_attendance' => true])->assertOk();
        $this->patchJson($url, ['attendance' => 'Present', 'verify_attendance' => true, 'confirm_edit' => true])->assertOk();
        $this->assertSame(1, DB::table('cfo_notifications')->where('type', 'attendance_present')->count());
        $this->postJson('/api/admin/settings/certificate', ['title' => 'Certificate', 'description' => 'Participation', 'signatory' => 'CFO'])->assertOk();
        $this->postJson($url.'/certificate')->assertOk();
        $this->postJson($url.'/certificate')->assertOk();
        $this->assertSame(1, DB::table('cfo_notifications')->where('type', 'certificate_ready')->count());
        $this->patchJson($url, ['attendance' => 'Absent', 'confirm_edit' => true])->assertOk();
        $this->assertSame(1, DB::table('cfo_notifications')->where('type', 'attendance_absent')->count());
        $this->patchJson($url, ['attendance' => 'Late', 'confirm_edit' => true])->assertOk();
        $this->assertDatabaseCount('cfo_notifications', 4);
    }

    public function test_rejected_schedule_request_requires_and_preserves_full_reason_without_duplicate_notifications(): void
    {
        $student = $this->account(); $admin = $this->account('Admin'); $current = $this->day(); $target = $this->day();
        $r = $this->registration($student, ['recollection_schedule_id' => $current->id]);
        $change = ScheduleChange::create(['registration_id' => $r->id, 'current_schedule_id' => $current->id, 'requested_schedule_id' => $target->id, 'reason' => 'Class conflict']);
        $url = '/api/admin/change-requests/'.$change->id;
        $this->actingAs($admin)->patchJson($url, ['status' => 'Rejected'])->assertUnprocessable();
        $this->patchJson($url, ['status' => 'Rejected', 'review_reason' => '   '])->assertUnprocessable();
        $reason = str_repeat('The requested alternative conflicts with your course schedule. ', 20);
        $this->patchJson($url, ['status' => 'Rejected', 'review_reason' => $reason])->assertOk();
        $this->patchJson($url, ['status' => 'Rejected', 'review_reason' => $reason])->assertUnprocessable();
        $this->assertDatabaseCount('cfo_notifications', 1);
        $this->assertSame(trim($reason), $change->fresh()->review_reason);
        $notice = $this->actingAs($student)->getJson('/api/student?resources=notifications')->assertOk()->json('notifications.0');
        $this->assertSame('schedule_change_rejected', $notice['type']);
        $this->assertStringContainsString(trim($reason), $notice['message']);
        $this->assertStringContainsString('visit the CFO office', $notice['message']);
        $this->assertSame('#schedule-change-request', $notice['target']);
        $request = $this->getJson('/api/student?resources=schedule_changes')->assertOk()->json('requests.0');
        $this->assertSame('Not Approved', $request['student_status']);
        $this->assertSame($notice['message'], $request['student_message']);
        $this->assertStringContainsString('Miraculous Building, near the chapel', $request['student_message']);
    }

    public function test_approved_schedule_request_has_one_typed_notification_and_no_assignment_notice(): void
    {
        $student = $this->account(); $current = $this->day(); $target = $this->day();
        $r = $this->registration($student, ['recollection_schedule_id' => $current->id, 'status' => 'Approved', 'receipt_status' => 'Approved']);
        $change = ScheduleChange::create(['registration_id' => $r->id, 'current_schedule_id' => $current->id, 'requested_schedule_id' => $target->id, 'reason' => 'Conflict']);
        $this->actingAs($this->account('Admin'))->patchJson('/api/admin/change-requests/'.$change->id, ['status' => 'Approved'])->assertOk();
        $this->assertDatabaseCount('cfo_notifications', 1);
        $this->assertDatabaseHas('cfo_notifications', ['type' => 'schedule_change_approved', 'user_id' => $student->id]);
    }

    public function test_announcement_publish_retries_do_not_duplicate_rows_or_notifications_and_full_body_is_separate(): void
    {
        $student = $this->account(); $other = $this->account(); $pending = $this->account(); $pending->update(['status' => 'Pending']);
        $payload = ['publication_key' => (string) Str::uuid(), 'title' => 'Retreat reminders', 'preview' => 'Bring your ID.', 'body' => 'Full details visible only on the announcements page.'];
        $this->actingAs($student)->postJson('/api/admin/announcements', $payload)->assertForbidden();
        $this->actingAs($this->account('Admin'))->postJson('/api/admin/announcements', $payload)->assertCreated();
        $this->postJson('/api/admin/announcements', $payload)->assertCreated();
        $this->postJson('/api/admin/announcements', [...$payload, 'title' => 'Changed title'])->assertUnprocessable();
        $this->assertDatabaseCount('cfo_announcements', 1);
        $this->assertDatabaseCount('cfo_notifications', 2);
        $this->assertDatabaseMissing('cfo_notifications', ['user_id' => $pending->id]);
        $response = $this->actingAs($student)->getJson('/api/student?resources=notifications')->assertOk();
        $this->assertStringNotContainsString($payload['body'], $response->getContent());
        $response->assertJsonPath('notifications.0.type', 'announcement')
            ->assertJsonPath('notifications.0.target', '#announcements?announcement='.Announcement::first()->id);
        $this->getJson('/api/student?resources=announcements')->assertJsonPath('announcements.0.body', $payload['body']);
    }

    public function test_historical_notifications_navigate_to_history_and_unrelated_updates_are_excluded(): void
    {
        $student = $this->account(); $r = $this->registration($student, ['year' => now()->year - 1]);
        CfoService::notifyStudent($student->id, 'certificate_ready', 'Your old certificate is ready.', 'registration', $r->id);
        $this->actingAs($this->account('Admin'))->patchJson('/api/admin/users/'.$student->id, ['first_name' => 'Student', 'last_name' => 'Updated', 'status' => 'Approved'])->assertOk();
        $this->assertDatabaseCount('cfo_notifications', 1);
        $this->actingAs($student)->getJson('/api/student?resources=notifications')->assertJsonPath('notifications.0.target', '#recollection-history');
    }
}
