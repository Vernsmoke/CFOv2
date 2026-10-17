<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecollectionRegistrationsTest extends TestCase
{
    use RefreshDatabase;

    private string $url = '/admin/recollection-registrations';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 4)->setTime(12, 0));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
    }

    private function registration(array $student = [], array $attributes = []): Registration
    {
        $user = User::factory()->create(array_merge(['role' => 'Student', 'status' => 'Approved', 'name' => 'John Dela Cruz',
            'first_name' => 'John', 'last_name' => 'Dela Cruz', 'course' => 'BSIT', 'year_level' => '3', 'section' => 'A', 'student_id' => fake()->unique()->numerify('26-#####')], $student));

        return Registration::create(array_merge(['user_id' => $user->id, 'year' => now()->year, 'details' => [], 'receipt_status' => 'Pending', 'created_at' => '2026-04-25 09:14:00'], $attributes));
    }

    private function ids(array $filters): array
    {
        return $this->get($this->url.'?'.http_build_query($filters))->assertOk()->viewData('registrations')->pluck('id')->all();
    }

    public function test_admin_only_access_to_list_details_and_export(): void
    {
        $r = $this->registration();
        foreach ([$this->url, $this->url.'/export', $this->url.'/'.$r->id] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->actingAs($r->user);
        foreach ([$this->url, $this->url.'/export', $this->url.'/'.$r->id] as $url) {
            $this->get($url)->assertForbidden();
        }
        $admin = $this->admin();
        $this->actingAs($admin);
        foreach ([$this->url, $this->url.'/export', $this->url.'/'.$r->id] as $url) {
            $this->get($url)->assertOk();
        }
        $admin->update(['status' => 'Pending']);
        $this->get($this->url)->assertForbidden();
    }

    public function test_cloud_proxy_generates_https_registration_details_and_actions(): void
    {
        $environment = \Illuminate\Support\Env::getRepository();
        $previousProxies = $environment->get('TRUSTED_PROXIES');
        $previousCloud = $_ENV['LARAVEL_CLOUD'] ?? null;
        $environment->set('TRUSTED_PROXIES', '');
        config(['trustedproxy.proxies' => (require config_path('trustedproxy.php'))['proxies']]);
        $_ENV['LARAVEL_CLOUD'] = '1';

        try {
            $registration = $this->registration();
            $this->actingAs($this->admin())
                ->withServerVariables(['REMOTE_ADDR' => '10.20.0.10'])
                ->withHeaders(['X-Forwarded-Proto' => 'https']);

            $list = $this->get('http://app.example.com'.$this->url)->assertOk();
            preg_match('/data-details="([^"]+)"/', $list->getContent(), $detailsUrl);
            $this->assertSame('https://app.example.com'.$this->url.'/'.$registration->id, $detailsUrl[1] ?? null);
            $details = $this->get('http://app.example.com'.$this->url.'/'.$registration->id)->assertOk();
            preg_match('/id="registration-schedule-form"[^>]*action="([^"]+)"/', $details->getContent(), $actionUrl);
            $this->assertSame('https://app.example.com/api/admin/registrations/'.$registration->id.'/schedule', $actionUrl[1] ?? null);
        } finally {
            if ($previousProxies === null) {
                $environment->clear('TRUSTED_PROXIES');
            } else {
                $environment->set('TRUSTED_PROXIES', $previousProxies);
            }
            if ($previousCloud === null) {
                unset($_ENV['LARAVEL_CLOUD']);
            } else {
                $_ENV['LARAVEL_CLOUD'] = $previousCloud;
            }
        }
    }

    public function test_counts_use_receipt_status_and_exclude_history_and_other_years(): void
    {
        foreach (['Approved', 'Pending', 'Not uploaded', 'Rejected'] as $status) {
            $this->registration([], ['receipt_status' => $status]);
        }
        $archived = $this->registration([], ['receipt_status' => 'Approved', 'archived_at' => now()]);
        $this->registration([], ['year' => 2025]);
        $this->registration([], ['year' => 2027]);
        $response = $this->actingAs($this->admin())->get($this->url)->assertOk()->assertSee('registrations for 2026.')
            ->assertSee('Dela Cruz, John')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertEquals(['Total Registrations' => 4, 'Verified' => 1, 'Pending' => 2, 'Rejected' => 1], $response->viewData('stats'));
        $this->assertCount(4, $response->viewData('registrations'));
        $this->get($this->url.'/'.$archived->id)->assertNotFound();
        $this->get($this->url.'?status=Verified')->assertViewHas('stats', $response->viewData('stats'));
    }

    public function test_each_filter_and_combined_filters(): void
    {
        $match = $this->registration([], ['receipt_status' => 'Approved']);
        $other = $this->registration(['course' => 'BSA', 'year_level' => '2', 'section' => 'B'], ['receipt_status' => 'Rejected', 'created_at' => '2026-04-26 00:00:00']);
        $missingReceipt = $this->registration(['course' => 'BSTM'], ['receipt_status' => 'Not uploaded', 'created_at' => '2026-04-24 23:59:59']);
        $this->actingAs($this->admin());
        $this->assertSame([$match->id], $this->ids(['course' => 'BSIT']));
        $this->assertSame([$other->id], $this->ids(['year_section' => '2B']));
        $this->assertSame([$match->id], $this->ids(['status' => 'Verified']));
        $this->assertSame([$other->id], $this->ids(['status' => 'Rejected']));
        $this->assertSame([$missingReceipt->id], $this->ids(['status' => 'Pending']));
        $this->assertSame([$other->id], $this->ids(['date_from' => '2026-04-26']));
        $this->assertSame([$missingReceipt->id], $this->ids(['date_to' => '2026-04-24']));
        $this->assertSame([$match->id], $this->ids(['date_from' => '2026-04-25', 'date_to' => '2026-04-25']));
        $this->assertSame([$match->id], $this->ids(['search' => 'Dela Cruz, John', 'course' => 'BSIT', 'year_section' => '3A', 'status' => 'Verified', 'date_from' => '2026-04-25', 'date_to' => '2026-04-25']));
        $this->get($this->url.'?course=missing')->assertSee('No registrations found')->assertSee('Showing 0–0 of 0 registrations');
    }

    public function test_search_name_id_course_year_and_section_and_literal_wildcards(): void
    {
        $match = $this->registration(['student_id' => '26-12345']);
        $this->registration(['name' => 'Maria Santos', 'first_name' => 'Maria', 'last_name' => 'Santos', 'student_id' => '26-22222', 'course' => 'BSA', 'year_level' => '2', 'section' => 'B']);
        $this->actingAs($this->admin());
        foreach (['Dela Cruz', 'John', 'Cruz, John', '26-12345', 'BSIT', '3', '3A', 'BSIT 3 A'] as $search) {
            $this->assertSame([$match->id], $this->ids(compact('search')), $search);
        }
        $this->assertSame([], $this->ids(['search' => '%']));
        $this->assertSame([], $this->ids(['search' => '_']));
    }

    public function test_schedule_status_filter_combines_with_other_filters_pagination_and_export(): void
    {
        $schedule = Schedule::create(['title' => 'Assigned batch', 'date' => '2026-10-05', 'start_time' => '08:00', 'end_time' => '12:00', 'venue' => 'Chapel', 'capacity' => 50, 'active' => true]);
        for ($i = 0; $i < 11; $i++) {
            $this->registration(['student_id' => 'UNSCHEDULED-'.$i], ['receipt_status' => 'Approved', 'status' => 'Approved']);
        }
        $unscheduled = $this->registration(['student_id' => 'UNASSIGNED'], ['receipt_status' => 'Pending']);
        $this->registration(['student_id' => 'ALREADY-ASSIGNED'], ['recollection_schedule_id' => $schedule->id, 'receipt_status' => 'Approved', 'status' => 'Approved']);
        $this->registration(['student_id' => 'DIFFERENT-COURSE', 'course' => 'BSA'], ['recollection_schedule_id' => $schedule->id, 'receipt_status' => 'Approved']);
        $this->registration(['student_id' => 'ARCHIVED-SCHEDULED'], ['recollection_schedule_id' => $schedule->id, 'archived_at' => now(), 'receipt_status' => 'Approved']);
        $this->actingAs($this->admin());
        $this->assertSame([$unscheduled->id], $this->ids(['schedule_status' => 'not_yet_scheduled', 'status' => 'Pending']));
        $this->assertSame([], $this->ids(['schedule_status' => 'scheduled']));
        $this->registration(['student_id' => 'OUTSIDE-DATE'], ['recollection_schedule_id' => $schedule->id, 'receipt_status' => 'Approved', 'created_at' => '2026-04-26 00:00:00']);
        $filters = ['schedule_status' => 'not_yet_scheduled', 'status' => 'Verified', 'course' => 'BSIT', 'year_section' => '3A', 'search' => 'John', 'date_from' => '2026-04-25', 'date_to' => '2026-04-25', 'per_page' => 10];
        $response = $this->get($this->url.'?'.http_build_query($filters))->assertOk()
            ->assertSee('Schedule Status')->assertSee('Not Yet Scheduled')->assertSee('name="date_from"', false)->assertDontSee('name="date_to"', false);
        $page = $response->viewData('registrations');
        $this->assertSame(11, $page->total());
        $this->assertCount(10, $page);
        $this->assertStringContainsString('schedule_status=not_yet_scheduled', $page->nextPageUrl());
        $this->assertStringContainsString('date_from=2026-04-25', $page->nextPageUrl());
        $this->assertStringContainsString('date_to=2026-04-25', $page->nextPageUrl());
        $this->get($page->nextPageUrl())->assertOk()->assertSee('Showing 11–11 of 11 registrations');
        $csv = $this->get($this->url.'/export?'.http_build_query($filters))->assertOk()->streamedContent();
        $this->assertSame(12, substr_count($csv, "\n"));
        foreach (['UNASSIGNED', 'ALREADY-ASSIGNED', 'DIFFERENT-COURSE', 'ARCHIVED-SCHEDULED', 'OUTSIDE-DATE'] as $excluded) {
            $this->assertStringNotContainsString($excluded, $csv);
        }
        $csv = $this->get($this->url.'/export?schedule_status=not_yet_scheduled&status=Pending')->assertOk()->streamedContent();
        $this->assertStringContainsString('UNASSIGNED', $csv);
        $this->assertSame(2, substr_count($csv, "\n"));
        foreach ([$this->url, $this->url.'/export'] as $url) {
            $this->getJson($url.'?schedule_status=unknown')->assertUnprocessable();
            $this->getJson($url.'?schedule_status[]=scheduled')->assertUnprocessable();
        }
    }

    public function test_pagination_sizes_stable_order_and_query_string(): void
    {
        $rows = collect();
        for ($i = 0; $i < 26; $i++) {
            $rows->push($this->registration());
        }
        $this->actingAs($this->admin());
        foreach ([10, 25, 50, 100] as $size) {
            $page = $this->get($this->url.'?course=BSIT&status=Pending&per_page='.$size)->assertOk()->viewData('registrations');
            $this->assertCount(min($size, 26), $page);
            $this->assertSame($size, $page->perPage());
            $this->assertStringContainsString('course=BSIT', $page->url(2));
            $this->assertStringContainsString('status=Pending', $page->url(2));
            $this->assertSame($rows->last()->id, $page->first()->id);
        }
        $this->get($this->url.'?per_page=10&page=2')->assertSee('Showing 11–20 of 26 registrations');
    }

    public function test_scheduling_the_last_row_redirects_to_the_last_available_filtered_page(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $last = $this->registration([], ['receipt_path' => 'receipts/example.png']);
        }
        $schedule = Schedule::create(['title' => 'Batch', 'date' => '2026-10-05', 'start_time' => '08:00', 'end_time' => '12:00', 'venue' => 'Chapel', 'capacity' => 50, 'active' => true]);
        $this->actingAs($this->admin())->postJson('/api/admin/registrations/'.$last->id.'/schedule', ['schedule_id' => $schedule->id])->assertOk();
        $this->get($this->url.'?course=BSIT&status=Pending&per_page=10&page=2')
            ->assertRedirect(route('admin.registrations.index', ['course' => 'BSIT', 'status' => 'Pending', 'per_page' => 10, 'page' => 1]));
        $response = $this->get($this->url)->assertOk();
        $this->assertSame(10, $response->viewData('stats')['Total Registrations']);
        $this->assertNotContains($last->id, $response->viewData('registrations')->pluck('id'));
        $this->assertNull($last->fresh()->archived_at);
        $this->assertDatabaseCount('recollection_registrations', 11);
    }

    public function test_registered_dates_include_entire_day_support_open_ranges_and_reset(): void
    {
        $early = $this->registration([], ['created_at' => '2026-04-24 23:59:59']);
        $start = $this->registration([], ['created_at' => '2026-04-25 00:00:00']);
        $end = $this->registration([], ['created_at' => '2026-04-25 23:59:59']);
        $late = $this->registration([], ['created_at' => '2026-04-26 00:00:00']);
        $this->actingAs($this->admin());
        $this->assertSame([$end->id, $start->id], $this->ids(['date_from' => '2026-04-25', 'date_to' => '2026-04-25', 'schedule_status' => 'not_yet_scheduled']));
        $this->assertSame([$end->id, $start->id, $early->id], $this->ids(['date_from' => '', 'date_to' => '2026-04-25']));
        $this->assertSame([$late->id, $end->id, $start->id], $this->ids(['date_from' => '2026-04-25', 'date_to' => '']));
        $this->assertSame([], $this->ids(['date_from' => '2026-04-25', 'date_to' => '2026-04-25', 'schedule_status' => 'scheduled']));
        $this->assertSame([$late->id, $end->id, $start->id, $early->id], $this->ids([]));
    }

    public function test_filters_remain_selected_when_the_last_matching_student_moves_to_attendance(): void
    {
        $r = $this->registration(['course' => 'BSA', 'year_level' => '2', 'section' => 'B'], ['receipt_path' => 'receipts/example.png']);
        $schedule = Schedule::create(['title' => 'Batch', 'date' => '2026-10-05', 'start_time' => '08:00', 'end_time' => '12:00', 'venue' => 'Chapel', 'capacity' => 50, 'active' => true]);
        $this->actingAs($this->admin())->postJson('/api/admin/registrations/'.$r->id.'/schedule', ['schedule_id' => $schedule->id])->assertOk();
        $this->get($this->url.'?course=BSA&year_section=2B&status=Verified')->assertOk()
            ->assertViewHas('registrations', fn ($rows) => $rows->isEmpty())
            ->assertViewHas('courses', fn ($options) => $options->contains('BSA'))
            ->assertViewHas('yearSections', fn ($options) => $options->contains('2B'))
            ->assertViewHas('filters', ['course' => 'BSA', 'year_section' => '2B', 'status' => 'Verified']);
        $this->get($this->url.'/export?course=BSA&year_section=2B&status=Verified')->assertOk()->assertStreamed();
    }

    public function test_export_uses_all_filtered_rows_and_escapes_spreadsheet_formulas(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->registration(['last_name' => '=SUM(1,2)'], ['receipt_status' => 'Approved']);
        }
        $excluded = $this->registration(['student_id' => 'EXCLUDED'], ['receipt_status' => 'Rejected']);
        $this->registration(['student_id' => 'ARCHIVED'], ['receipt_status' => 'Approved', 'archived_at' => now()]);
        $response = $this->actingAs($this->admin())->get($this->url.'/export?course=BSIT&year_section=3A&status=Verified&date_from=2026-04-25&date_to=2026-04-25&search=John&per_page=10&page=2')->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=SUM(1,2), John", $csv);
        $this->assertStringNotContainsString($excluded->user->student_id, $csv);
        $this->assertStringNotContainsString('ARCHIVED', $csv);
        $this->assertSame(13, substr_count($csv, "\n"));
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_invalid_inputs_are_rejected_for_list_and_export(): void
    {
        $this->actingAs($this->admin());
        foreach (['status=Approved', 'per_page=500', 'page=0', 'search[]=x', 'course[]=x', 'year_section[]=x', 'date_from=bad', 'date_from=2026-04-26&date_to=2026-04-25'] as $query) {
            foreach ([$this->url, $this->url.'/export'] as $url) {
                $this->getJson($url.'?'.$query)->assertUnprocessable();
            }
        }
    }

    public function test_details_escape_values_and_reuse_receipt_and_schedule_workflow(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/example.png', 'test receipt');
        $r = $this->registration(['first_name' => '<script>alert(1)</script>'], ['receipt_path' => 'receipts/example.png']);
        $day = Schedule::create(['title' => 'October Recollection', 'date' => '2026-10-05', 'start_time' => '08:00', 'end_time' => '12:00', 'venue' => 'Chapel', 'capacity' => 50, 'active' => true]);
        $this->actingAs($this->admin())->get($this->url.'/'.$r->id)->assertOk()->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('/api/receipts/'.$r->id)->assertSee('/api/admin/registrations/'.$r->id)->assertSee('No schedule assigned.');
        $review = '/api/admin/registrations/'.$r->id;
        $this->patchJson($review, ['receipt_status' => 'Rejected', 'receipt_reason' => 'Unreadable'])->assertOk();
        $this->assertNull($r->fresh()->recollection_schedule_id);
        $this->patchJson($review, ['receipt_status' => 'Pending'])->assertOk();
        $this->assertNull($r->fresh()->recollection_schedule_id);
        $this->patchJson($review, ['receipt_status' => 'Approved'])->assertOk();
        $this->get($this->url.'/'.$r->id)->assertSee('Assign Schedule')->assertSee('October Recollection');
        $this->postJson($review.'/schedule', ['schedule_id' => $day->id])->assertOk();
        $this->assertSame('Approved', $r->fresh()->status);
        $this->assertSame('Approved', $r->fresh()->receipt_status);
        $this->assertSame($day->id, $r->fresh()->recollection_schedule_id);
        $this->get($this->url.'/'.$r->id)->assertSee('Chapel');
        $this->getJson('/api/admin/state')->assertJsonPath('registrations.0.recollection_schedule_id', $day->id);
        $this->postJson($review.'/attendance', ['attendance' => 'Present'])->assertOk();
        $this->assertDatabaseHas('activity_logs', ['record_id' => $r->id, 'action' => 'schedule.assigned']);
    }

    public function test_list_does_not_lazy_load_relationships(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->registration();
        }
        $this->actingAs($this->admin());
        Registration::preventLazyLoading();
        try {
            $this->get($this->url.'?per_page=25')->assertOk();
        } finally {
            Registration::preventLazyLoading(false);
        }
    }

    public function test_a_stale_details_modal_cannot_review_an_archived_registration(): void
    {
        $registration = $this->registration([], ['receipt_path' => 'receipts/example.png', 'archived_at' => now()]);
        $this->actingAs($this->admin())->patchJson('/api/admin/registrations/'.$registration->id, ['receipt_status' => 'Approved'])->assertUnprocessable();
        $this->assertSame('Pending', $registration->fresh()->receipt_status);
    }

    public function test_review_shows_submitted_information_and_actual_consents_without_row_menu(): void
    {
        $details = [
            'name' => 'Submitted Student Name', 'nickname' => 'Saved nickname', 'age' => 21, 'sex' => 'Male',
            'course' => 'Saved course', 'yearSection' => '4C', 'email' => 'submitted@example.test', 'phone' => '09170001111',
            'emergencyName' => 'Saved emergency contact', 'relationship' => 'Guardian', 'emergencyPhone' => '09170002222',
            'medical' => 'Saved medical information', 'allergies' => 'Saved allergy', 'medications' => 'Saved medication',
            'religion' => 'Saved religion', 'parish' => 'Saved parish', 'ministry' => 'Saved ministry',
            'baptism' => 'Yes', 'communion' => 'Yes', 'confirmation' => 'No', 'firstRecollection' => 'No',
            'previousRecollections' => 'Saved previous event', 'expectations' => 'Saved expectations',
            'reflection' => '<script>unsafe()</script>', 'participation' => '1', 'photos' => false,
        ];
        $registration = $this->registration([], ['details' => $details]);
        $this->actingAs($this->admin())->get($this->url)->assertOk()->assertSee('View Registration')->assertDontSee('class="row-menu"', false);
        $response = $this->get($this->url.'/'.$registration->id)->assertOk();
        foreach ($details as $key => $value) {
            if (! in_array($key, ['participation', 'photos'])) {
                $response->assertSee((string) $value);
            }
        }
        $response->assertDontSee('<script>unsafe()</script>', false)
            ->assertSee('VIII. CONSENT AND COMMITMENT')->assertSee('No receipt has been uploaded.')
            ->assertSee('Verify the uploaded receipt before assigning a schedule.');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(2, $xpath->query('//section[contains(@class,"consent-section")]//input[@disabled]')->length);
        $this->assertSame(1, $xpath->query('//section[contains(@class,"consent-section")]//input[@checked]')->length);
        $this->assertSame(1, $xpath->query('//select[@name="schedule_id" and @disabled]')->length);
        $this->assertSame(1, $xpath->query('//button[@form="registration-schedule-form" and @disabled]')->length);
    }

    public function test_modal_retains_current_past_schedule_and_disables_full_alternatives(): void
    {
        $past = Schedule::create(['title' => 'Existing assignment', 'date' => '2026-10-01', 'start_time' => '08:00', 'end_time' => '12:00', 'venue' => 'Original Chapel', 'capacity' => 50, 'active' => true]);
        $full = Schedule::create(['title' => 'Full alternative', 'date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '13:00', 'venue' => 'New Chapel', 'capacity' => 0, 'active' => true]);
        $registration = $this->registration([], ['receipt_path' => 'receipts/saved.png', 'receipt_status' => 'Approved', 'status' => 'Approved', 'recollection_schedule_id' => $past->id]);
        $response = $this->actingAs($this->admin())->get($this->url.'/'.$registration->id)->assertOk()
            ->assertSee('October 01, 2026')->assertSee('08:00 AM - 12:00 PM')->assertSee('Original Chapel')
            ->assertSee('Scheduled')->assertSee('View Full Size')->assertSee('Zoom in receipt')->assertSee('Reset receipt zoom');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//select[@name="schedule_id"]/option[@value="'.$past->id.'" and @selected]')->length);
        $this->assertSame(1, $xpath->query('//select[@name="schedule_id"]/option[@value="'.$full->id.'" and @disabled]')->length);
        $this->assertSame(0, $xpath->query('//select[@name="schedule_id" and @disabled]')->length);
    }
}
