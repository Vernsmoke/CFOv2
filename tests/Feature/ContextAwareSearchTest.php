<?php

namespace Tests\Feature;

use App\Models\{AcademicOption, Registration, Schedule, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ContextAwareSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_modules_and_interaction_state_use_actual_shared_implementation(): void
    {
        $process = new Process(['node', base_path('tests/Browser/context-search-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_discovery_assets_are_loaded_by_both_authenticated_panels(): void
    {
        foreach (['Admin', 'Student'] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'Approved']);
            $this->actingAs($user)->get('/'.strtolower($role))->assertOk()
                ->assertSee('cfo-search.js', false)->assertSee('cfo-usability.css', false)->assertSee('cfo-usability.js', false);
        }
    }

    public function test_student_search_source_cannot_be_expanded_with_admin_resources_or_other_user_parameters(): void
    {
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'course' => 'BSIT', 'year_level' => '3', 'section' => 'A']);
        $other = User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'name' => 'Hidden Other Student']);
        $mine = Registration::create(['user_id' => $student->id, 'year' => now()->year, 'details' => []]);
        $foreign = Registration::create(['user_id' => $other->id, 'year' => now()->year - 1, 'details' => [], 'archived_at' => now()]);
        $course = AcademicOption::create(['type' => 'course', 'name' => 'BSIT']);
        $otherCourse = AcademicOption::create(['type' => 'course', 'name' => 'BSN']);
        foreach ([$course, $otherCourse] as $option) {
            $day = Schedule::create(['title' => $option->name.' formation', 'date' => today()->addDay()->toDateString(), 'start_time' => '08:00', 'end_time' => '12:00', 'venue' => 'Chapel', 'capacity' => 20, 'active' => true, 'year_level' => '3', 'section' => 'A']);
            $day->courses()->attach($option->id);
        }
        $response = $this->actingAs($student)->getJson('/api/student?include_history=1&role=Admin&user_id='.$other->id)
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(1, 'registrations')->assertJsonPath('registrations.0.id', $mine->id)
            ->assertJsonCount(1, 'schedules')->assertJsonPath('schedules.0.title', 'BSIT formation');
        $this->assertStringNotContainsString('Hidden Other Student', $response->getContent());
        $this->assertArrayNotHasKey('users', $response->json());
        $this->getJson('/api/student?resources=users,password_resets,activity_logs')->assertOk()
            ->assertJsonMissingPath('resets')->assertJsonMissingPath('logs')->assertJsonPath('user.id', $student->id);
        $this->getJson('/api/admin/state?include_history=1')->assertForbidden();
        $this->get('/admin')->assertForbidden();
        $this->getJson('/api/history/'.$foreign->id)->assertForbidden();
        $this->getJson('/api/receipts/'.$foreign->id)->assertForbidden();
    }

    public function test_search_data_reuses_approved_account_and_password_gates(): void
    {
        $this->getJson('/api/student')->assertUnauthorized();
        $this->getJson('/api/admin/state')->assertUnauthorized();
        foreach (['Pending', 'Rejected', 'Inactive'] as $status) {
            $user = User::factory()->create(['role' => 'Student', 'status' => $status]);
            $this->actingAs($user)->getJson('/api/student')->assertForbidden();
        }
        $user = User::factory()->create(['role' => 'Admin', 'status' => 'Approved', 'password_change_required' => true]);
        $this->actingAs($user)->getJson('/api/admin/state')->assertForbidden();
    }
}
