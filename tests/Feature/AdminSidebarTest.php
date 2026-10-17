<?php

namespace Tests\Feature;

use App\Models\{PasswordResetRequest, Registration, Schedule, ScheduleChange, User};
use App\Services\AdminNavigation;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AdminSidebarTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'Student', string $status = 'Approved'): User
    {
        return User::factory()->create(['first_name' => 'Test', 'last_name' => $role, 'role' => $role, 'status' => $status, 'password' => 'CfoTest!234']);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    public function test_navigation_config_is_complete_and_matches_the_admin_pages(): void
    {
        $dashboard = file_get_contents(public_path('admin-dashboard.js'));
        $icons = file_get_contents(resource_path('views/partials/admin-icon.blade.php'));
        $badgeKeys = array_keys(AdminNavigation::badges());
        $seen = [];

        foreach (config('admin_navigation') as $group => $items) {
            $this->assertIsString($group);
            foreach ($items as $key => $item) {
                $this->assertArrayNotHasKey($key, $seen, $key.' appears in the sidebar twice');
                $seen[$key] = true;
                [$label, $icon] = $item;
                $options = $item[2] ?? [];
                $this->assertNotSame('', trim($label), $key.' needs a label');
                $this->assertStringContainsString("'{$key}':[", $dashboard, $key.' must exist in the admin page map');
                $this->assertStringContainsString("'{$icon}' =>", $icons, $icon.' must exist in the server-rendered icon set');
                $this->assertTrue(str_contains($dashboard, "'{$icon}':") || str_contains($dashboard, "{$icon}:'"), $icon.' must exist in the admin script icon set');
                if (isset($options['badge'])) {
                    $this->assertContains($options['badge'], $badgeKeys, $key.' uses an unknown badge');
                }
            }
        }

        $icons = array_map(fn ($item) => $item[1], array_merge(...array_values(config('admin_navigation'))));
        $this->assertSame(count($icons), count(array_unique($icons)), 'Every sidebar link has its own icon');
    }

    public function test_registration_pages_render_the_shared_sidebar_with_active_state_and_badges(): void
    {
        $admin = $this->account('Admin');
        $this->account('Student', 'Pending');
        $this->account('Student', 'Pending');

        $html = $this->actingAs($admin)->get('/admin/recollection-registrations')->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $current = $xpath->query('//aside[@id="sidebar"]//a[@aria-current="page"]');
        $this->assertCount(1, $current, 'Exactly one sidebar link is current');
        $this->assertSame('admin-register-this-year', $current->item(0)->getAttribute('data-route'));

        $brand = $xpath->query('//aside[@id="sidebar"]//a[contains(@class,"brand")]')->item(0);
        $this->assertStringEndsWith('/admin#admin-dashboard', $brand->getAttribute('href'));

        $this->assertCount(0, $xpath->query('//aside//*[contains(@class,"nav-group")][normalize-space()=""]'), 'Groups without a name render no heading');
        $this->assertSame('2', trim($xpath->query('//aside//span[@data-nav-badge="accounts"]')->item(0)->textContent));
        $this->assertTrue($xpath->query('//aside//span[@data-nav-badge="change_requests"]')->item(0)->hasAttribute('hidden'), 'A zero count stays hidden');
        $this->assertStringEndsWith('/admin#admin-settings', $xpath->query('//aside//a[contains(@class,"sidebar-profile-link")]')->item(0)->getAttribute('href'));
    }

    public function test_admin_page_receives_the_same_navigation_and_initial_badges(): void
    {
        $admin = $this->account('Admin');
        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee('Password reset requests')->assertSee('Daily work')->assertSee('Activity log')
            ->assertSee('data-nav-badges', false)->assertSee('/admin-nav-badges.js?v=', false);
    }

    public function test_badges_count_only_items_waiting_for_an_administrator_and_stay_admin_only(): void
    {
        $admin = $this->account('Admin');
        $owner = $this->account();
        $this->account('Student', 'Pending');
        $this->account('Student', 'Pending');
        $schedule = Schedule::create(['title' => 'Badge Recollection', 'date' => today()->toDateString(), 'start_time' => '08:00', 'end_time' => '16:00', 'venue' => 'Test Hall', 'capacity' => 5, 'active' => true]);

        Registration::create(['user_id' => $owner->id, 'year' => now()->year, 'details' => [], 'status' => 'Pending', 'receipt_status' => 'Pending']);
        $scheduled = Registration::create(['user_id' => $this->account()->id, 'year' => now()->year, 'details' => [], 'status' => 'Approved', 'receipt_status' => 'Approved', 'recollection_schedule_id' => $schedule->id]);
        ScheduleChange::create(['registration_id' => $scheduled->id, 'current_schedule_id' => $schedule->id, 'reason' => 'Needs a different day']);
        ScheduleChange::create(['registration_id' => $scheduled->id, 'current_schedule_id' => $schedule->id, 'reason' => 'Already handled', 'status' => 'Rejected']);
        PasswordResetRequest::create(['user_id' => $owner->id, 'status' => 'Pending', 'recovery_hash' => str_repeat('a', 64)]);
        PasswordResetRequest::create(['user_id' => $owner->id, 'status' => 'Cancelled', 'recovery_hash' => str_repeat('b', 64)]);

        $this->actingAs($admin)->getJson('/api/admin/nav-badges')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['registrations' => 1, 'change_requests' => 1, 'password_resets' => 1, 'accounts' => 2]);

        $this->actingAs($owner)->getJson('/api/admin/nav-badges')->assertForbidden();
    }

    public function test_badges_require_a_signed_in_user(): void
    {
        $this->getJson('/api/admin/nav-badges')->assertUnauthorized();
    }

    public function test_sidebar_badge_script_state(): void
    {
        $process = new Process(['node', base_path('tests/Browser/admin-sidebar-state.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }
}
