<?php

namespace Tests\Feature;

use App\Models\{Registration, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;
use Tests\Support\ConfiguredRegistrationChoices;

class StudentRegistrationEditTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguredRegistrationChoices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuredRegistrationSchedule(['BSIT' => ['2' => ['A']], 'BSN' => ['3' => ['B']]]);
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'course' => 'BSIT', 'year_level' => '2', 'section' => 'A']);
    }

    private function details(): array
    {
        return ['name' => 'Juan Dela Cruz', 'age' => 20, 'sex' => 'Male', 'course' => 'BSIT', 'yearSection' => '2A', 'email' => 'juan@example.test', 'phone' => '09123456789', 'religion' => 'Roman Catholic', 'emergencyName' => 'Parent', 'relationship' => 'Mother', 'emergencyPhone' => '09123456780', 'medical' => 'Asthma', 'participation' => true, 'photos' => true];
    }

    private function receipt(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));
    }

    public function test_pending_edit_updates_the_existing_record_and_snapshot_without_duplicate_submissions(): void
    {
        $student = $this->student();
        $this->actingAs($student);
        $registration = $this->postJson('/api/registrations', ['details' => $this->details()])->assertCreated()->json();
        $id = $registration['id'];
        $version = $this->getJson('/api/live/versions')->json('registrations');
        $details = [...$this->details(), 'name' => 'Juan Cruz', 'course' => 'BSN', 'yearSection' => '3B', 'medical' => 'Updated health information', 'expectations' => 'Reflection'];
        $this->patchJson('/api/registrations/'.$id, ['details' => $details, 'status' => 'Approved', 'user_id' => 999, 'year' => 2000])
            ->assertOk()->assertJsonPath('registration.status', 'Pending')->assertJsonPath('registration.year', now()->year)
            ->assertJsonPath('registration.student_snapshot.name', 'Juan Cruz')->assertJsonPath('registration.student_snapshot.course', 'BSN')
            ->assertJsonPath('registration.student_snapshot.submitted_year_section', '3B');
        $this->assertSame([...$details, 'yearLevel' => '3', 'section' => 'B'], Registration::findOrFail($id)->details);
        $this->assertSame('BSN', $student->fresh()->course);
        $this->assertSame('3', $student->fresh()->year_level);
        $this->assertSame('B', $student->fresh()->section);
        $this->actingAs($student->fresh())->getJson('/api/student?resources=users')->assertJsonPath('user.course', 'BSN')->assertJsonPath('user.year_level', '3')->assertJsonPath('user.section', 'B');
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->actingAs($admin)->get('/admin/recollection-registrations?course=BSN&year_section=3B')->assertOk()
            ->assertViewHas('registrations', fn ($rows) => $rows->pluck('id')->all() === [$id])->assertSee('BSN')->assertSee('3B');
        $this->actingAs($student);
        $this->assertGreaterThan($version, $this->getJson('/api/live/versions')->json('registrations'));
        $this->patchJson('/api/registrations/'.$id, ['details' => $details])->assertOk();
        $this->assertSame(1, DB::table('activity_logs')->where('action', 'registration.student_updated')->count());
        $this->postJson('/api/registrations', ['details' => $details])->assertUnprocessable();
        $this->assertDatabaseCount('recollection_registrations', 1);
    }

    public function test_approval_after_loading_the_form_blocks_edits_and_retains_all_details(): void
    {
        $student = $this->student();
        $r = Registration::create(['user_id' => $student->id, 'year' => now()->year, 'details' => $this->details()]);
        $this->actingAs($student)->getJson('/api/student')->assertJsonPath('registrations.0.status', 'Pending');
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->actingAs($admin)->patchJson('/api/admin/registrations/'.$r->id, ['status' => 'Approved'])->assertOk();
        $before = $r->fresh()->getAttributes();
        $this->actingAs($student)->patchJson('/api/registrations/'.$r->id, ['details' => [...$this->details(), 'name' => 'Changed after approval']])->assertUnprocessable();
        $this->assertSame($before, $r->fresh()->getAttributes());
    }

    public function test_other_students_historical_and_rejected_records_cannot_be_edited(): void
    {
        $owner = $this->student();
        $r = Registration::create(['user_id' => $owner->id, 'year' => now()->year, 'details' => $this->details()]);
        $this->actingAs($this->student())->patchJson('/api/registrations/'.$r->id, ['details' => $this->details()])->assertForbidden();
        $this->actingAs($owner);
        $r->update(['status' => 'Rejected']);
        $this->patchJson('/api/registrations/'.$r->id, ['details' => $this->details()])->assertUnprocessable();
        $r->update(['status' => 'Pending', 'year' => now()->year - 1]);
        $this->patchJson('/api/registrations/'.$r->id, ['details' => $this->details()])->assertUnprocessable();
    }

    public function test_pending_receipts_can_change_but_verified_receipts_and_review_fields_are_preserved(): void
    {
        Storage::fake('local');
        $student = $this->student();
        $this->actingAs($student);
        $id = $this->post('/api/registrations', ['details' => $this->details(), 'receipt' => $this->receipt()], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $r = Registration::findOrFail($id);
        $oldPath = $r->receipt_path;
        $this->post('/api/registrations/'.$id, ['_method' => 'PATCH', 'details' => $this->details(), 'receipt' => $this->receipt()], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('local')->assertMissing($oldPath);
        $path = $r->fresh()->receipt_path;
        Storage::disk('local')->assertExists($path);
        $r->refresh()->update(['receipt_status' => 'Approved', 'receipt_verified_by' => $student->id, 'receipt_verified_at' => now()]);
        $this->patchJson('/api/registrations/'.$id, ['details' => [...$this->details(), 'phone' => '09123456700']])->assertOk();
        $this->assertSame('Approved', $r->fresh()->receipt_status);
        $this->assertNotNull($r->fresh()->receipt_verified_at);
        $this->post('/api/registrations/'.$id, ['_method' => 'PATCH', 'details' => $this->details(), 'receipt' => $this->receipt()], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame($path, $r->fresh()->receipt_path);
        $this->assertCount(1, Storage::disk('local')->allFiles('receipts'));
    }

    public function test_submitted_receipts_remain_available_to_the_owner_after_review(): void
    {
        Storage::fake('local');
        $student = $this->student();
        $id = $this->actingAs($student)->post('/api/registrations', ['details' => $this->details(), 'receipt' => $this->receipt()], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('has_receipt', true)->json('id');
        $registration = Registration::findOrFail($id);
        $path = $registration->receipt_path;
        $this->get('/student')->assertOk()->assertSee('/assets/student/student-receipts.js?v='.filemtime(public_path('assets/student/student-receipts.js')), false);
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        foreach (['Pending', 'Rejected', 'Approved'] as $status) {
            $this->actingAs($admin)->patchJson('/api/admin/registrations/'.$id, ['receipt_status' => $status, 'receipt_reason' => $status === 'Rejected' ? 'Upload a clearer photo.' : null])->assertOk();
            $this->actingAs($student)->getJson('/api/student?resources=registrations')->assertOk()
                ->assertJsonPath('registrations.0.has_receipt', true)->assertJsonPath('registrations.0.receipt_status', $status);
            $this->get('/api/receipts/'.$id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertSame($path, $registration->fresh()->receipt_path);
            Storage::disk('local')->assertExists($path);
        }
        $this->actingAs($this->student())->get('/api/receipts/'.$id)->assertForbidden();
    }

    public function test_edit_validates_all_required_details_before_changing_anything(): void
    {
        $student = $this->student();
        $r = Registration::create(['user_id' => $student->id, 'year' => now()->year, 'details' => $this->details()]);
        $before = $r->fresh()->getAttributes();
        $this->actingAs($student)->patchJson('/api/registrations/'.$r->id, ['details' => [...$this->details(), 'name' => '', 'participation' => false]])->assertUnprocessable()->assertJsonValidationErrors(['details.name', 'details.participation']);
        $this->assertSame($before, $r->fresh()->getAttributes());
    }
}
