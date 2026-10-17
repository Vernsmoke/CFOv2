<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_entry_uses_shared_brand_assets_and_preserves_login_controls(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('data-cfo-surface="auth"', false)
            ->assertSee('cfo-design.css')->assertSee('images/cfo/chapel.webp')
            ->assertSee('id="login-form"', false)->assertSee("roleSelect('login-role', true)", false)
            ->assertDontSee('login-background.css');
    }

    public function test_admin_layouts_share_one_theme_without_loading_the_previous_dark_layers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Admin', 'status' => 'Approved']));
        foreach (['/admin', '/admin/recollection-registrations'] as $url) {
            $this->get($url)->assertOk()->assertSee('cfo-design.css')
                ->assertSee('images/cfo/cfo-logo.png')->assertSee('admin-header.css')
                ->assertDontSee('admin-theme.css')->assertDontSee('admin-background.css');
        }
    }

    public function test_student_workspace_keeps_its_modules_with_the_shared_theme(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Student', 'status' => 'Approved']));
        $this->get('/student')->assertOk()->assertSee('data-cfo-surface="student"', false)
            ->assertSee('cfo-design.css')->assertSee('student-pages.css')
            ->assertSee('id="dashboard-navigation"', false)->assertDontSee('student-theme.css');
    }

    public function test_brand_assets_exist_and_the_theme_is_screen_only(): void
    {
        foreach (['fonts/cfo/cabinet-bold.woff2', 'fonts/cfo/cabinet-regular.woff2',
            'fonts/cfo/inter-variable.woff2', 'images/cfo/cfo-logo.png', 'images/cfo/chapel.webp'] as $asset) {
            $this->assertFileExists(public_path($asset));
        }
        $this->assertStringContainsString('media="screen"', file_get_contents(resource_path('views/partials/design-system.blade.php')));
        $this->assertStringContainsString('prefers-reduced-motion:reduce', file_get_contents(public_path('cfo-design.css')));
    }
}
