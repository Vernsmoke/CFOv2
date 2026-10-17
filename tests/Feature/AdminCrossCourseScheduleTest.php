<?php

namespace Tests\Feature;

use App\Models\AcademicOption;
use App\Models\Registration;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrossCourseScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function schedule(): Schedule
    {
        $schedule = Schedule::create(['title' => 'Published BSA schedule', 'date' => today()->toDateString(), 'start_time' => '08:00', 'end_time' => '12:00',
            'venue' => 'Chapel', 'capacity' => 50, 'slot_limit' => 10, 'active' => true, 'year_level' => '1', 'section' => 'A']);
        $course = AcademicOption::firstOrCreate(['type' => 'course', 'name' => 'BSA']);
        $schedule->courses()->attach($course);

        return $schedule;
    }

    private function registration(array $profile = []): Registration
    {
        $student = User::factory()->create([...['role' => 'Student', 'status' => 'Approved', 'course' => 'BSIT', 'year_level' => '3', 'section' => 'B'], ...$profile]);

        return Registration::create(['user_id' => $student->id, 'year' => now()->year, 'details' => [], 'status' => 'Approved', 'receipt_status' => 'Approved', 'receipt_path' => 'receipts/verified.png']);
    }

    public function test_admin_can_assign_across_courses_year_levels_and_sections_using_both_endpoints(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $schedule = $this->schedule();
        foreach ([['course' => 'BSIT', 'year_level' => '1', 'section' => 'A'], ['course' => 'BSA', 'year_level' => '3', 'section' => 'A'],
            ['course' => 'BSA', 'year_level' => '1', 'section' => 'B'], []] as $index => $profile) {
            $registration = $this->registration($profile);
            $response = $this->actingAs($admin)->get('/admin/recollection-registrations/'.$registration->id)->assertOk()->assertSee($schedule->title);
            $this->assertTrue($response->viewData('schedules')->contains('id', $schedule->id));
            if ($index === 3) {
                $this->patchJson('/api/admin/registrations/'.$registration->id, ['schedule_id' => $schedule->id])->assertOk();
            } else {
                $this->postJson('/api/admin/registrations/'.$registration->id.'/schedule', ['schedule_id' => $schedule->id])->assertOk();
            }
            $this->assertSame($schedule->id, $registration->fresh()->recollection_schedule_id);
            $this->actingAs($registration->user)->getJson('/api/student')->assertJsonPath('registrations.0.schedule.id', $schedule->id);
        }
        $this->assertSame(4, $schedule->registrations()->count());
    }

    public function test_admin_academic_override_still_respects_slots_and_unavailable_schedules(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $schedule = $this->schedule();
        $schedule->update(['slot_limit' => 1]);
        $first = $this->registration();
        $second = $this->registration();
        $this->actingAs($admin)->postJson('/api/admin/registrations/'.$first->id.'/schedule', ['schedule_id' => $schedule->id])->assertOk();
        $url = '/api/admin/registrations/'.$second->id;
        $this->postJson($url.'/schedule', ['schedule_id' => $schedule->id])->assertUnprocessable();
        $this->patchJson($url, ['schedule_id' => $schedule->id])->assertUnprocessable();
        foreach ([['active' => false], ['finalized_at' => now()], ['date' => today()->subDay()->toDateString()], ['date' => now()->addYear()->startOfYear()->toDateString()]] as $unavailable) {
            $schedule->update(['slot_limit' => 10, 'active' => true, 'finalized_at' => null, 'date' => today()->toDateString(), ...$unavailable]);
            $this->postJson($url.'/schedule', ['schedule_id' => $schedule->id])->assertUnprocessable();
            $this->assertNull($second->fresh()->recollection_schedule_id);
        }
    }

    public function test_student_cannot_use_admin_override_when_choosing_a_schedule(): void
    {
        $schedule = $this->schedule();
        $registration = $this->registration();
        $this->actingAs($registration->user)->postJson('/api/registrations/'.$registration->id.'/schedule', [
            'schedule_id' => $schedule->id, 'adminAssignment' => true, 'adminCorrection' => true,
        ])->assertUnprocessable();
        $this->postJson('/api/admin/registrations/'.$registration->id.'/schedule', ['schedule_id' => $schedule->id])->assertForbidden();
        $this->assertNull($registration->fresh()->recollection_schedule_id);
    }
}
