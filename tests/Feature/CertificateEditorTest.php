<?php

namespace Tests\Feature;

use App\Models\{Registration, Schedule, User};
use App\Services\CertificateTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateEditorTest extends TestCase
{
    use RefreshDatabase;

    private function image(string $name = 'logo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
    }

    public function test_multipart_positions_remain_numbers_when_the_editor_reopens(): void
    {
        $this->actingAs($this->admin());
        $settings = CertificateTemplate::editorDefaults();
        array_walk_recursive($settings, function (&$value) { if (is_numeric($value)) $value = (string) $value; });
        $this->post('/api/admin/settings/certificate', $settings, ['Accept' => 'application/json'])->assertOk();
        $saved = $this->getJson('/api/admin/state?resources=settings')->assertOk()->json('template');
        $this->assertSame(2, $saved['layout_version']);
        $this->assertSame(180, $saved['elements']['name']['x']);
        $this->assertSame(46, $saved['elements']['name']['font_size']);
        $this->assertSame(70, $saved['logo']['x']);
    }

    public function test_visual_design_preview_matches_issued_design_and_reset_preserves_old_issues(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $settings = CertificateTemplate::editorDefaults();
        $settings['background_mode'] = 'gradient';
        $settings['gradient_direction'] = 'horizontal';
        $settings['elements']['name'] = [...$settings['elements']['name'], 'font_family' => 'Arial', 'font_size' => 36, 'color' => '#a12345', 'align' => 'right', 'x' => 210, 'width' => 700];
        $settings['logo'] = ['x' => 70, 'y' => 70, 'width' => 90, 'height' => 100];
        $uploads = ['image' => $this->image('background.png'), 'logo_image' => $this->image()];
        $preview = $this->postJson('/api/admin/certificate-template/preview', [...$settings, ...$uploads])->assertOk()->json('svg');
        foreach (['Juan Dela Cruz', 'linearGradient', 'stop-color="#f0e4c8"', 'fill="url(#certificate-background)"', 'font-family="Arial,Helvetica,sans-serif"', 'font-size="36"', 'text-anchor="end"', 'fill="#a12345"', '<text x="910"', '<image x="70" y="70" width="90" height="100"'] as $expected) {
            $this->assertStringContainsString($expected, $preview);
        }
        $this->assertSame(2, substr_count($preview, 'data:image/png;base64,'));
        $this->assertNotFalse(simplexml_load_string($preview));
        $this->assertDatabaseMissing('cfo_settings', ['key' => 'certificate']);
        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->postJson('/api/admin/settings/certificate', [...$settings, ...$uploads])->assertOk();
        $saved = CertificateTemplate::current();
        $this->assertEquals($settings['elements'], $saved['elements']);
        $this->getJson('/api/admin/state?resources=settings')->assertJsonPath('template.elements.name.color', '#a12345');
        $student = User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'name' => 'Maria Santos']);
        $day = Schedule::create(['title' => 'Annual Recollection', 'date' => today()->toDateString(), 'start_time' => '08:00', 'end_time' => '12:00', 'venue' => 'Chapel', 'capacity' => 50]);
        $registration = Registration::create(['user_id' => $student->id, 'year' => now()->year - 1, 'details' => [], 'recollection_schedule_id' => $day->id, 'attendance' => 'Present', 'attendance_verified_at' => now()]);
        $certificate = $this->postJson('/api/admin/registrations/'.$registration->id.'/certificate')->assertOk()->json();
        $this->assertSame(2, $certificate['snapshot']['layout_version']);
        $before = $this->get('/certificates/'.$certificate['id'])->assertOk()->getContent();
        $this->assertStringContainsString('Maria Santos', $before);
        $this->assertStringContainsString('Recollection Year '.(now()->year - 1), $before);
        $this->assertStringNotContainsString('{{student_name}}', $before);
        $this->assertStringContainsString('fill="#a12345"', $before);
        $this->assertSame(2, substr_count($before, 'data:image/png;base64,'));

        $this->postJson('/api/admin/settings/certificate', [...CertificateTemplate::editorDefaults(), 'remove_image' => true, 'remove_logo' => true])->assertOk();
        $reset = CertificateTemplate::current();
        $this->assertNull($reset['image_path']);
        $this->assertNull($reset['logo_path']);
        $this->assertEquals(CertificateTemplate::editorDefaults()['elements'], $reset['elements']);
        $this->assertNotSame($before, $this->get('/certificates/'.$certificate['id'])->getContent());
        Storage::disk('local')->assertExists($saved['logo_path']);
        Storage::disk('local')->assertExists($saved['image_path']);
        $this->assertDatabaseCount('cfo_settings', 1);
        $this->assertDatabaseCount('certificates', 1);
        $this->actingAs($student)->get('/certificates/'.$certificate['id'].'?download=1')->assertOk()->assertDontSee('fill="#a12345"', false);
        $this->actingAs(User::factory()->create(['role' => 'Student', 'status' => 'Approved']))->get('/certificates/'.$certificate['id'])->assertForbidden();
    }

    public function test_editor_rejects_invalid_elements_and_uploads_before_persistence(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $invalid = [
            ['elements.name.font_family', 'RemoteFont'], ['elements.name.font_size', 99],
            ['elements.name.color', 'url(https://example.test)'], ['elements.name.align', 'justify'],
            ['elements.name.x', -1], ['elements.name.x', 1000], ['elements.name.y', 780],
            ['elements.name.width', 1200], ['elements.name.height', 0], ['elements.name.weight', 'evil'],
            ['elements.name.script', 'alert(1)'], ['elements.unknown', []], ['elements.name.text', ''],
            ['logo.x', 1110], ['logo.width', 'invalid'], ['logo.href', 'https://example.test'],
            ['border_style', 'unknown'], ['gradient_direction', 'unknown'], ['gradient_color', '#fff'],
            ['layout_version', 3], ['logo_image', UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml')],
            ['image', UploadedFile::fake()->createWithContent('fake.png', '<script>alert(1)</script>')],
        ];
        foreach ($invalid as [$key, $value]) {
            $settings = CertificateTemplate::editorDefaults();
            data_set($settings, $key, $value);
            $this->postJson('/api/admin/certificate-template/preview', $settings)->assertUnprocessable();
            $this->postJson('/api/admin/settings/certificate', $settings)->assertUnprocessable();
        }
        $settings = CertificateTemplate::editorDefaults();
        unset($settings['elements']['name']);
        $this->postJson('/api/admin/settings/certificate', $settings)->assertUnprocessable();
        $this->assertDatabaseMissing('cfo_settings', ['key' => 'certificate']);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_styles_borders_and_long_unicode_are_safe_and_logo_paths_cannot_be_injected(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $settings = CertificateTemplate::editorDefaults();
        $settings['elements']['name']['text'] = '<script>alert(1)</script> & '.str_repeat('Niño María ', 15);
        $settings['elements']['name']['align'] = 'left';
        $settings['border_style'] = 'none';
        $preview = $this->postJson('/api/admin/certificate-template/preview', [...$settings, 'logo_path' => '.env', 'image_path' => '.env'])->assertOk()->json('svg');
        $this->assertNotFalse(simplexml_load_string($preview));
        $this->assertStringNotContainsString('<script>', $preview);
        $this->assertStringNotContainsString('stroke-width=', $preview);
        $this->assertStringNotContainsString('base64,', $preview);
        $this->assertStringContainsString('text-anchor="start"', $preview);
        $settings['border_style'] = 'simple';
        $preview = $this->postJson('/api/admin/certificate-template/preview', $settings)->assertOk()->json('svg');
        $this->assertSame(1, substr_count($preview, 'stroke-width='));
        $settings['border_style'] = 'double';
        $preview = $this->postJson('/api/admin/certificate-template/preview', $settings)->assertOk()->json('svg');
        $this->assertSame(2, substr_count($preview, 'stroke-width='));
        $this->postJson('/api/admin/settings/certificate', [...$settings, 'logo_path' => '.env', 'image_path' => '.env'])->assertOk();
        $this->assertNull(CertificateTemplate::current()['logo_path']);
        $this->assertNull(CertificateTemplate::current()['image_path']);
    }
}
