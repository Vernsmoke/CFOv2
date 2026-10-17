<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AdminHeaderTest extends TestCase
{
    use RefreshDatabase;

    private function renderedHeader(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $headers = $xpath->query('//header[contains(concat(" ", normalize-space(@class), " "), " admin-header ")]');
        $this->assertCount(1, $headers, $url.' must render one shared header');

        return [$xpath, $headers->item(0)];
    }

    public function test_both_admin_layouts_share_accessible_controls_and_the_same_identity(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved', 'name' => 'Different Admin Name']);
        $this->actingAs($admin);

        foreach (['/admin', '/admin/recollection-registrations'] as $url) {
            [$xpath, $header] = $this->renderedHeader($url);
            $this->assertCount(1, $xpath->query('.//button[@id="toggle-nav"]', $header));
            $this->assertCount(1, $xpath->query('.//*[@id="live-status"]', $header));
            $this->assertCount(1, $xpath->query('.//button[@aria-label="Notifications"]', $header));
            $this->assertStringContainsString('Different Admin Name', $header->textContent);
            $this->assertStringContainsString('Super Admin', $header->textContent);
            $avatars = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " avatar ")]', $header);
            $this->assertCount(1, $avatars);
            $this->assertSame('AD', trim($avatars->item(0)->textContent));
        }
    }

    public function test_dashboard_subtitle_is_plain_and_registration_breadcrumb_links_back_to_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Admin', 'status' => 'Approved']));
        [$xpath, $header] = $this->renderedHeader('/admin');
        $this->assertSame('Dashboard', trim($xpath->query('.//*[@id="admin-header-title"]', $header)->item(0)->textContent));
        $breadcrumb = $xpath->query('.//*[@id="admin-header-breadcrumb"]', $header)->item(0);
        $this->assertSame('CFO / Admin workspace', trim($breadcrumb->textContent));
        $this->assertCount(0, $xpath->query('.//a', $breadcrumb));

        [$xpath, $header] = $this->renderedHeader('/admin/recollection-registrations');
        $this->assertSame('Recollection Registrations', trim($xpath->query('.//*[@id="admin-header-title"]', $header)->item(0)->textContent));
        $breadcrumb = $xpath->query('.//*[@id="admin-header-breadcrumb"]', $header)->item(0);
        $links = $xpath->query('.//a', $breadcrumb);
        $this->assertCount(1, $links);
        $this->assertSame('Dashboard', trim($links->item(0)->textContent));
        $this->assertSame(url('/admin').'#admin-dashboard', $links->item(0)->getAttribute('href'));
        $current = $xpath->query('.//*[@aria-current="page"]', $breadcrumb)->item(0);
        $this->assertInstanceOf(DOMElement::class, $current);
        $this->assertSame('Recollection Registrations', trim($current->textContent));
        $this->assertNotSame('a', $current->tagName);
    }

    public function test_route_updates_preserve_breadcrumb_semantics_and_safe_labels(): void
    {
        $process = new Process(['node', base_path('tests/Browser/admin-header.cjs')], base_path());
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }
}
