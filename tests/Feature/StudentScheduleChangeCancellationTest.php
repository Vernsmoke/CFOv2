<?php

namespace Tests\Feature;

use App\Models\{Registration, Schedule, ScheduleChange, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentScheduleChangeCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'Student', string $status = 'Approved'): User
    {
        return User::factory()->create(compact('role', 'status'));
    }

    private function day(string $title = 'Current Recollection'): Schedule
    {
        return Schedule::create(['title' => $title, 'date' => today()->addDay()->toDateString(),
            'start_time' => '08:00', 'end_time' => '16:00', 'venue' => 'Chapel', 'capacity' => 50, 'active' => true]);
    }

    private function registration(User $student, Schedule $schedule): Registration
    {
        return Registration::create(['user_id' => $student->id, 'year' => now()->year, 'details' => [],
            'status' => 'Approved', 'receipt_status' => 'Approved', 'recollection_schedule_id' => $schedule->id]);
    }

    private function pending(Registration $registration): ScheduleChange
    {
        return ScheduleChange::create(['registration_id' => $registration->id,
            'current_schedule_id' => $registration->recollection_schedule_id, 'reason' => 'Class schedule conflict']);
    }

    private function cancellationCount(): int
    {
        return DB::table('activity_logs')->where('action', 'schedule_change.cancelled')->count();
    }

    public function test_owner_cancels_once_with_audit_live_state_and_admin_notice_without_changing_assignment(): void
    {
        $student = $this->account();
        $admin = $this->account('Admin');
        $this->account('Admin', 'Pending');
        $current = $this->day();
        $registration = $this->registration($student, $current);
        $change = $this->pending($registration);
        $before = DB::table('cfo_resource_versions')->where('resource', 'schedule_changes')->value('version');
        $url = '/api/change-requests/'.$change->id.'/cancel';

        $this->actingAs($student)->patchJson($url)->assertOk()
            ->assertJsonPath('message', 'Your schedule change request was cancelled.')
            ->assertJsonPath('request.status', 'Cancelled')
            ->assertJsonPath('request.student_status', 'Cancelled')
            ->assertJsonPath('request.student_message', 'You cancelled this request.')
            ->assertJsonPath('request.current_schedule.id', $current->id);
        $this->assertSame($current->id, $registration->fresh()->recollection_schedule_id);
        $this->assertSame('Not Yet Recorded', $registration->fresh()->attendance);
        $this->assertNull($change->fresh()->assigned_schedule_id);
        $this->assertNull($change->fresh()->reviewed_by);
        $this->assertNull($change->fresh()->reviewed_at);
        $this->assertDatabaseHas('activity_logs', ['actor_id' => $student->id, 'action' => 'schedule_change.cancelled',
            'record_type' => 'schedule_change', 'record_id' => $change->id]);
        $this->assertSame(1, $this->cancellationCount());
        $version = DB::table('cfo_resource_versions')->where('resource', 'schedule_changes')->value('version');
        $this->assertGreaterThan($before, $version);
        $this->assertDatabaseHas('cfo_notifications', ['user_id' => $admin->id, 'message' => 'A student cancelled a schedule change request.']);
        $this->assertDatabaseCount('cfo_notifications', 1);
        $this->getJson('/api/student?resources=schedule_changes')->assertOk()
            ->assertJsonPath('requests.0.status', 'Cancelled')
            ->assertJsonPath('requests.0.student_message', 'You cancelled this request.')
            ->assertJsonPath('requests.0.current_schedule.id', $current->id);
        $this->actingAs($admin)->getJson('/api/admin/state?resources=schedule_changes')->assertOk()
            ->assertJsonPath('requests.0.status', 'Cancelled')->assertJsonCount(0, 'requests.0.eligible_schedules');

        $this->actingAs($student)->patchJson($url)->assertUnprocessable()->assertSee('Only a pending');
        $this->assertSame(1, $this->cancellationCount());
        $this->assertDatabaseCount('cfo_notifications', 1);
        $this->assertSame($version, DB::table('cfo_resource_versions')->where('resource', 'schedule_changes')->value('version'));
        $this->postJson('/api/change-requests', ['registration_id' => $registration->id, 'reason' => 'Revised academic conflict'])
            ->assertCreated()->assertJsonPath('status', 'Pending');
        $this->assertDatabaseCount('schedule_change_requests', 2);
    }

    public function test_cancel_is_student_authenticated_and_owner_only(): void
    {
        $owner = $this->account();
        $registration = $this->registration($owner, $this->day());
        $change = $this->pending($registration);
        $url = '/api/change-requests/'.$change->id.'/cancel';
        $this->patchJson($url)->assertUnauthorized();
        $this->actingAs($this->account('Admin'))->patchJson($url)->assertForbidden();
        $this->actingAs($this->account())->patchJson($url)->assertNotFound();
        $this->actingAs($this->account('Student', 'Pending'))->patchJson($url)->assertForbidden();
        $this->assertSame('Pending', $change->fresh()->status);
        $this->assertSame(0, $this->cancellationCount());
    }

    public function test_non_pending_requests_cannot_be_cancelled_and_cancelled_requests_cannot_be_reviewed(): void
    {
        $student = $this->account();
        $admin = $this->account('Admin');
        $current = $this->day();
        $target = $this->day('Available Recollection');
        $registration = $this->registration($student, $current);
        foreach (['Approved', 'Rejected', 'Closed', 'Cancelled'] as $status) {
            $change = $this->pending($registration);
            $change->update(['status' => $status]);
            $this->actingAs($student)->patchJson('/api/change-requests/'.$change->id.'/cancel')
                ->assertUnprocessable()->assertSee('Only a pending');
            $this->assertSame($status, $change->fresh()->status);
            if ($status === 'Cancelled') {
                $url = '/api/admin/change-requests/'.$change->id;
                $this->actingAs($admin)->patchJson($url, ['status' => 'Approved', 'assigned_schedule_id' => $target->id])
                    ->assertUnprocessable()->assertSee('already reviewed');
                $this->patchJson($url, ['status' => 'Rejected', 'review_reason' => 'Review from a stale page'])
                    ->assertUnprocessable()->assertSee('already reviewed');
                $this->assertSame('Cancelled', $change->fresh()->status);
                $this->assertNull($change->fresh()->assigned_schedule_id);
                $this->assertNull($change->fresh()->reviewed_by);
            }
        }
        $this->assertSame($current->id, $registration->fresh()->recollection_schedule_id);
        $this->assertSame(0, $this->cancellationCount());
        $this->assertDatabaseCount('cfo_notifications', 0);
    }

    public function test_create_enforces_exact_reason_boundaries_and_does_not_create_invalid_requests(): void
    {
        $student = $this->account();
        $registration = $this->registration($student, $this->day());
        $this->actingAs($student);
        foreach (['', str_repeat('a', 9), str_repeat('a', 1001), ['Invalid array']] as $reason) {
            $this->postJson('/api/change-requests', ['registration_id' => $registration->id, 'reason' => $reason])
                ->assertUnprocessable()->assertJsonValidationErrors('reason');
        }
        $this->assertDatabaseCount('schedule_change_requests', 0);
        foreach ([10, 1000] as $length) {
            $reason = str_repeat('a', $length);
            $created = $this->postJson('/api/change-requests', ['registration_id' => $registration->id, 'reason' => $reason])
                ->assertCreated()->assertJsonPath('reason', $reason)->assertJsonPath('status', 'Pending');
            $this->assertSame($length, mb_strlen($created->json('reason')));
            $this->patchJson('/api/change-requests/'.$created->json('id').'/cancel')->assertOk();
        }
        $this->assertDatabaseCount('schedule_change_requests', 2);
    }

    public function test_cancel_rechecks_the_locked_request_after_an_admin_decision_changes_the_bound_model(): void
    {
        $student = $this->account();
        $registration = $this->registration($student, $this->day());
        $change = $this->pending($registration);
        $changed = false;
        // The route binding reads Pending; an intervening decision is committed before the transaction.
        DB::listen(function ($query) use ($change, &$changed) {
            if (! $changed && str_starts_with($query->sql, 'select') && str_contains($query->sql, '"schedule_change_requests"')) {
                $changed = true;
                $change->update(['status' => 'Approved']);
            }
        });
        $this->actingAs($student)->patchJson('/api/change-requests/'.$change->id.'/cancel')
            ->assertUnprocessable()->assertSee('Only a pending');
        $this->assertTrue($changed);
        $this->assertSame('Approved', $change->fresh()->status);
        $this->assertSame(0, $this->cancellationCount());
        $this->assertDatabaseCount('cfo_notifications', 0);
    }

    public function test_admin_rechecks_the_locked_request_after_student_cancellation_changes_the_bound_model(): void
    {
        $admin = $this->account('Admin');
        $registration = $this->registration($this->account(), $this->day());
        $change = $this->pending($registration);
        $target = $this->day('Alternative Recollection');
        $changed = false;
        DB::listen(function ($query) use ($change, &$changed) {
            if (! $changed && str_starts_with($query->sql, 'select') && str_contains($query->sql, '"schedule_change_requests"')) {
                $changed = true;
                $change->update(['status' => 'Cancelled']);
            }
        });
        $this->actingAs($admin)->patchJson('/api/admin/change-requests/'.$change->id,
            ['status' => 'Approved', 'assigned_schedule_id' => $target->id])->assertUnprocessable()->assertSee('already reviewed');
        $this->assertTrue($changed);
        $this->assertSame('Cancelled', $change->fresh()->status);
        $this->assertSame($change->current_schedule_id, $registration->fresh()->recollection_schedule_id);
        $this->assertNull($change->fresh()->reviewed_by);
        $this->assertDatabaseCount('cfo_notifications', 0);
    }

    public function test_student_state_retains_the_original_schedule_after_admin_reassignment(): void
    {
        $student = $this->account();
        $current = $this->day('Original Recollection');
        $target = $this->day('Assigned Alternative');
        $registration = $this->registration($student, $current);
        $change = $this->pending($registration);
        $this->actingAs($this->account('Admin'))->patchJson('/api/admin/change-requests/'.$change->id,
            ['status' => 'Approved', 'assigned_schedule_id' => $target->id, 'review_reason' => 'Internal approval note'])->assertOk();
        $this->actingAs($student)->getJson('/api/student?resources=schedule_changes')->assertOk()
            ->assertJsonPath('requests.0.current_schedule.id', $current->id)
            ->assertJsonPath('requests.0.current_schedule.title', 'Original Recollection')
            ->assertJsonPath('requests.0.assigned_schedule.id', $target->id)
            ->assertJsonPath('requests.0.student_status', 'Approved')
            ->assertJsonMissingPath('requests.0.review_reason');
    }
}
