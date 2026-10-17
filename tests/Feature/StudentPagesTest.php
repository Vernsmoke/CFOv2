<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class StudentPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivered_pages_handle_filters_links_modals_and_profile_saves(): void
    {
        $process = new Process(['node', base_path('tests/Browser/student-pages-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_student_page_loads_versioned_assets_and_its_rendered_scripts_parse(): void
    {
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved']);
        $response = $this->actingAs($student)->get('/student')->assertOk();
        foreach (['css', 'js'] as $extension) {
            $asset = 'assets/student/student-pages.'.$extension;
            $response->assertSee(asset('assets/student/student-pages.'.$extension).'?v='.filemtime(public_path($asset)), false);
        }
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'function studentPage('));
        foreach (['recollectionHistory', 'studentAnnouncements', 'studentMyProfile'] as $function) {
            $this->assertSame(1, substr_count($html, 'function '.$function.'('));
        }
        $this->assertStringContainsString('refresh: studentMyProfile', $html);
        $this->assertStringContainsString('cleanup(); previousCleanup();', $html);
        preg_match_all('/<script\b([^>]*)>(.*?)<\/script\s*>/is', $html, $matches, PREG_SET_ORDER);
        $scripts = [];
        foreach ($matches as $match) {
            if (! preg_match('/\bsrc\s*=/i', $match[1])) $scripts[] = $match[2];
        }
        $process = new Process(['node', '-e', 'const vm=require("node:vm"),fs=require("node:fs");for(const code of JSON.parse(fs.readFileSync(0,"utf8")))new vm.Script(code);'], base_path());
        $process->setInput(json_encode($scripts, JSON_THROW_ON_ERROR));
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_extracted_image_urls_initialize_without_blocking_the_student_dashboard(): void
    {
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved']);
        $html = $this->actingAs($student)->get('/student')->assertOk()->getContent();
        $process = new Process(['node', base_path('tests/Browser/student-image-assets-state.cjs')], base_path());
        $process->setInput($html);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_profile_rejects_wrong_current_password_and_saves_a_new_name_without_changing_academic_details(): void
    {
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved',
            'first_name' => 'Juan', 'last_name' => 'Cruz', 'name' => 'Juan Cruz', 'email' => 'juan@gmail.com',
            'course' => 'BSIT', 'year_level' => '2', 'section' => 'A', 'password' => 'CurrentPassword!1']);
        $payload = ['first_name' => 'Maria', 'middle_name' => '', 'last_name' => 'Cruz', 'email' => 'juan@gmail.com',
            'current_password' => 'WrongPassword', 'password' => '', 'password_confirmation' => ''];
        $this->actingAs($student)->putJson('/api/profile', $payload)->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertSame('Juan Cruz', $student->fresh()->name);
        $payload['current_password'] = 'CurrentPassword!1';
        $this->putJson('/api/profile', $payload)->assertOk()->assertJsonPath('user.name', 'Maria Cruz')->assertJsonMissingPath('requires_login');
        $this->getJson('/api/student?resources=users')->assertOk()->assertJsonPath('user.name', 'Maria Cruz')
            ->assertJsonPath('user.course', 'BSIT')->assertJsonPath('user.year_level', '2')->assertJsonPath('user.section', 'A');
        $this->assertAuthenticatedAs($student);
    }
}
