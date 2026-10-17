<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RenderedPageScriptTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_registration_live_refresh_preserves_pending_work(): void
    {
        $process = new Process(['node', base_path('tests/Browser/registration-live-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_batch_workflow_controls_and_confirmations(): void
    {
        $process = new Process(['node', base_path('tests/Browser/batch-workflow-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_create_schedule_respects_the_existing_save_contract_and_restores_drafts(): void
    {
        $process = new Process(['node', base_path('tests/Browser/schedule-create-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_course_year_section_settings_validation(): void
    {
        $process = new Process(['node', base_path('tests/Browser/course-year-sections-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_filter_dropdowns_preserve_native_values_and_open_downward(): void
    {
        $process = new Process(['node', base_path('tests/Browser/filter-dropdowns-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_my_schedules_search_filters_sort_pagination_csv_calendar_and_validation(): void
    {
        $process = new Process(['node', base_path('tests/Browser/my-schedules-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_attendance_filters_bulk_saves_and_completion_use_the_existing_workflow(): void
    {
        $process = new Process(['node', base_path('tests/Browser/attendance-workflow-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_health_and_religion_share_the_selected_attendance_batch_without_duplicate_saves(): void
    {
        $process = new Process(['node', base_path('tests/Browser/health-religion-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_history_filters_details_and_live_year_rollover(): void
    {
        $process = new Process(['node', base_path('tests/Browser/yearly-history-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_student_email_privacy_controls_preserve_actual_values(): void
    {
        $process = new Process(['node', base_path('tests/Browser/student-security-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_student_change_requests_send_a_reason_and_preserve_unsaved_drafts(): void
    {
        $process = new Process(['node', base_path('tests/Browser/student-change-request-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_admin_change_request_review_assigns_eligible_schedules_and_requires_rejection_notes(): void
    {
        $process = new Process(['node', base_path('tests/Browser/admin-change-requests-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_admin_recovery_decisions_require_verification_and_prevent_duplicate_submissions(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Password reset requests')
            ->assertSee('/admin-dashboard.js?v='.filemtime(public_path('admin-dashboard.js')), false);
        $process = new Process(['node', base_path('tests/Browser/admin-recovery-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_recovery_polling_and_password_lifecycle(): void
    {
        $this->get('/login')->assertOk()->assertSee('/recovery-polling.js?v='.filemtime(public_path('recovery-polling.js')), false)
            ->assertSee('/recovery.css?v='.filemtime(public_path('recovery.css')), false)
            ->assertSee('CFO Office, Miraculous Building, near the chapel', false);
        $process = new Process(['node', base_path('tests/Browser/recovery-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_registration_prefill_edit_drafts_and_confirmation(): void
    {
        $process = new Process(['node', base_path('tests/Browser/student-registration-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_student_registration_options_follow_admin_schedules_without_losing_drafts(): void
    {
        $process = new Process(['node', base_path('tests/Browser/student-registration-options-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_certificate_editor_preserves_drafts_and_ignores_stale_previews(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $html = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<script>\s*(window\.mountCertificateEditor[\s\S]*?)<\/script>/', $html, $match));
        $process = new Process(['node', base_path('tests/Browser/certificate-editor-state.cjs')], base_path());
        $process->setInput($match[1]);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_student_certificates_use_the_saved_template_and_preserve_selection_and_modal_cleanup(): void
    {
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved']);
        $this->actingAs($student)->get('/student')->assertOk()
            ->assertSee('/assets/student/student-certificates.js?v='.filemtime(public_path('assets/student/student-certificates.js')), false)
            ->assertSee('/assets/student/student-certificates.css?v='.filemtime(public_path('assets/student/student-certificates.css')), false);
        $process = new Process(['node', base_path('tests/Browser/student-certificates-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_admin_certificates_show_verified_present_students_and_preserve_filter_export_and_print_controls(): void
    {
        $process = new Process(['node', base_path('tests/Browser/admin-certificates-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_admin_announcement_drafts_preserve_edits_and_use_the_existing_confirmation_flow(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee('/admin-announcements.js?v='.filemtime(public_path('admin-announcements.js')), false);
        $process = new Process(['node', base_path('tests/Browser/admin-announcements-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_student_notification_state_preserves_new_arrivals_and_edits(): void
    {
        $process = new Process(['node', base_path('tests/Browser/student-notification-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_live_transport_is_configurable_for_admin_and_student_pages(): void
    {
        foreach (['sse', 'polling'] as $transport) {
            config(['cfo.live_transport' => $transport]);
            foreach (['Admin', 'Student'] as $role) {
                $user = User::factory()->create(['role' => $role, 'status' => 'Approved']);
                $this->actingAs($user)->get('/'.strtolower($role))
                    ->assertOk()->assertViewHas('cfoBackend', fn ($data) => $data['live_transport'] === $transport);
            }
        }
    }

    public function test_existing_pages_render_with_parseable_inline_javascript(): void
    {
        $pages = ['login' => $this->get('/login')->assertOk()->getContent()];
        foreach (['Admin', 'Student'] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'Approved']);
            $pages[$role] = $this->actingAs($user)->get('/'.strtolower($role))->assertOk()->getContent();
        }
        $user->update(['password_change_required' => true, 'password_change_expires_at' => now()->addDay()]);
        $pages['force-password'] = $this->withSession(['force_password_change' => true])->get('/force-password')->assertOk()->getContent();

        $scripts = [];
        foreach ($pages as $page => $html) {
            preg_match_all('/<script\b([^>]*)>(.*?)<\/script\s*>/is', $html, $matches, PREG_SET_ORDER);
            foreach ($matches as $index => $match) {
                if (preg_match('/\bsrc\s*=/i', $match[1])) {
                    continue;
                }
                preg_match('/\btype\s*=\s*["\']([^"\']+)["\']/i', $match[1], $type);
                if (isset($type[1]) && ! in_array(strtolower($type[1]), ['module', 'text/javascript', 'application/javascript'])) {
                    continue;
                }
                $scripts[] = ['name' => "$page-script-$index", 'module' => ($type[1] ?? '') === 'module', 'code' => $match[2]];
            }
        }
        $this->assertNotEmpty($scripts);
        // Syntax compilation only: this does not run scripts or simulate a browser.
        $checker = <<<'JS'
const vm = require('node:vm');
const scripts = JSON.parse(require('node:fs').readFileSync(0, 'utf8'));
for (const script of scripts) {
    try {
        if (script.module) new vm.SourceTextModule(script.code, {identifier: script.name});
        else new vm.Script(script.code, {filename: script.name});
    } catch (error) { console.error(error.stack); process.exit(1); }
}
console.log(`Parsed ${scripts.length} inline scripts.`);
JS;
        $process = new Process(['node', '--experimental-vm-modules', '-e', $checker], base_path());
        $process->setInput(json_encode($scripts, JSON_THROW_ON_ERROR));
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }
}
