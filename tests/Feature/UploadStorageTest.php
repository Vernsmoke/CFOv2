<?php

namespace Tests\Feature;

use App\Models\{Registration, Schedule, User};
use App\Services\CertificateTemplate;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ConfiguredRegistrationChoices;
use Tests\TestCase;

class UploadStorageTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguredRegistrationChoices;

    private const IMAGE = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.upload_disk' => 's3']);
        Storage::fake('local');
        Storage::fake('s3');
    }

    public static function uploadDiskOptions(): array
    {
        return [['local', 'local'], ['s3', 's3'], ['unsupported', 'local']];
    }

    #[DataProvider('uploadDiskOptions')]
    public function test_upload_disk_and_alias_follow_the_environment(string $value, string $expected): void
    {
        $previousServer = $_SERVER['FILESYSTEM_UPLOAD_DISK'] ?? null;
        $previousEnv = $_ENV['FILESYSTEM_UPLOAD_DISK'] ?? null;
        try {
            $_SERVER['FILESYSTEM_UPLOAD_DISK'] = $_ENV['FILESYSTEM_UPLOAD_DISK'] = $value;
            $filesystems = require config_path('filesystems.php');
            $this->assertSame($expected, $filesystems['upload_disk']);
            $this->assertSame($filesystems['disks'][$expected], $filesystems['disks']['uploads']);
            $this->assertSame($expected, $filesystems['disks']['uploads']['driver']);
            if ($expected === 'local') $this->assertSame(storage_path('app/private'), $filesystems['disks']['uploads']['root']);
        } finally {
            if ($previousServer === null) unset($_SERVER['FILESYSTEM_UPLOAD_DISK']);
            else $_SERVER['FILESYSTEM_UPLOAD_DISK'] = $previousServer;
            if ($previousEnv === null) unset($_ENV['FILESYSTEM_UPLOAD_DISK']);
            else $_ENV['FILESYSTEM_UPLOAD_DISK'] = $previousEnv;
        }
    }

    private function image(string $name = 'receipt.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::IMAGE));
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'Student', 'status' => 'Approved', 'course' => 'BSIT', 'year_level' => '2', 'section' => 'A']);
    }

    private function details(User $student): array
    {
        return ['name' => $student->name, 'age' => 20, 'sex' => 'Female', 'course' => 'BSIT', 'yearSection' => '2A', 'email' => $student->email,
            'phone' => '09123456789', 'religion' => 'Roman Catholic', 'emergencyName' => 'Parent', 'relationship' => 'Mother',
            'emergencyPhone' => '09123456780', 'participation' => true, 'photos' => true];
    }

    public function test_receipt_upload_edit_replacement_and_failed_replacement_use_the_selected_disk(): void
    {
        $this->configuredRegistrationSchedule();
        $student = $this->student();
        $id = $this->actingAs($student)->postJson('/api/registrations', ['details' => $this->details($student), 'receipt' => $this->image()])
            ->assertCreated()->json('id');
        $registration = Registration::findOrFail($id);
        $original = $registration->receipt_path;
        $this->assertStringStartsWith('receipts/', $original);
        Storage::disk('s3')->assertExists($original);
        $this->get('/api/receipts/'.$id)->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertStreamedContent(base64_decode(self::IMAGE));
        $this->actingAs($this->student())->get('/api/receipts/'.$id)->assertForbidden();
        $this->actingAs($student)->postJson('/api/registrations/'.$id, ['_method' => 'PATCH', 'details' => $this->details($student), 'receipt' => $this->image()])->assertOk();
        $edited = $registration->fresh()->receipt_path;
        Storage::disk('s3')->assertMissing($original);
        Storage::disk('s3')->assertExists($edited);

        $registration->refresh()->update(['receipt_status' => 'Rejected']);
        $this->postJson('/api/registrations/'.$id.'/receipt', ['receipt' => $this->image()])->assertOk();
        $replacement = $registration->fresh()->receipt_path;
        Storage::disk('s3')->assertMissing($edited);
        Storage::disk('s3')->assertExists($replacement);
        $this->postJson('/api/registrations/'.$id.'/receipt', ['receipt' => $this->image()])->assertUnprocessable();
        $this->assertSame([$replacement], Storage::disk('s3')->allFiles('receipts'));
        $this->assertSame([], Storage::disk('local')->allFiles());
        Storage::disk('s3')->delete($replacement);
        $this->get('/api/receipts/'.$id)->assertNotFound();
    }

    public function test_absent_retry_copies_the_receipt_on_the_selected_disk_and_keeps_the_original(): void
    {
        $this->configuredRegistrationSchedule();
        $student = $this->student();
        $schedule = Schedule::create(['title' => 'Previous recollection', 'date' => today()->subDay()->toDateString(), 'start_time' => '08:00',
            'end_time' => '10:00', 'venue' => 'Chapel', 'capacity' => 50]);
        Storage::disk('s3')->put('receipts/original.png', base64_decode(self::IMAGE));
        $original = Registration::create(['user_id' => $student->id, 'year' => now()->year, 'details' => $this->details($student),
            'receipt_path' => 'receipts/original.png', 'receipt_status' => 'Approved', 'status' => 'Approved',
            'recollection_schedule_id' => $schedule->id, 'attendance' => 'Absent']);
        $newId = $this->actingAs($student)->postJson('/api/registrations/'.$original->id.'/retry', ['details' => $this->details($student)])
            ->assertCreated()->json('registration.id');
        $new = Registration::findOrFail($newId)->receipt_path;
        $this->assertNotSame($original->receipt_path, $new);
        Storage::disk('s3')->assertExists($original->receipt_path);
        $this->assertSame(base64_decode(self::IMAGE), Storage::disk('s3')->get($new));
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_saved_template_images_are_embedded_in_previews_and_certificate_downloads(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->actingAs($admin)->postJson('/api/admin/settings/certificate', [...CertificateTemplate::editorDefaults(),
            'image' => $this->image('background.png'), 'logo_image' => $this->image('logo.png')])->assertOk();
        $settings = CertificateTemplate::current();
        foreach (['image_path', 'logo_path'] as $field) {
            $this->assertStringStartsWith('certificate-templates/', $settings[$field]);
            Storage::disk('s3')->assertExists($settings[$field]);
        }
        $preview = $this->postJson('/api/admin/certificate-template/preview', $settings)->assertOk()->json('svg');
        $this->assertSame(2, substr_count($preview, 'data:image/png;base64,'.self::IMAGE));
        $legacy = CertificateTemplate::render([...CertificateTemplate::defaults(), 'image_path' => $settings['image_path']], 'LEGACY');
        $this->assertStringContainsString('data:image/png;base64,'.self::IMAGE, $legacy);

        $student = $this->student();
        $schedule = $this->configuredRegistrationSchedule();
        $registration = Registration::create(['user_id' => $student->id, 'year' => now()->year, 'details' => [],
            'recollection_schedule_id' => $schedule->id, 'attendance' => 'Present', 'attendance_verified_at' => now()]);
        $certificateId = $this->postJson('/api/admin/registrations/'.$registration->id.'/certificate')->assertOk()->json('id');
        $download = $this->actingAs($student)->get('/certificates/'.$certificateId.'?download=1')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));
        $this->assertSame(2, substr_count($download->getContent(), 'data:image/png;base64,'.self::IMAGE));
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_failed_template_save_removes_new_uploads_and_retains_the_previous_image(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'status' => 'Approved']);
        $this->actingAs($admin)->postJson('/api/admin/settings/certificate', [...CertificateTemplate::editorDefaults(),
            'image' => $this->image('previous.png')])->assertOk();
        $previousImage = CertificateTemplate::current()['image_path'];
        DB::listen(function (QueryExecuted $query) {
            if (str_contains($query->sql, 'cfo_settings') && preg_match('/^(insert|update)\b/i', $query->sql)) {
                throw new \RuntimeException('Simulated template save failure.');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->postJson('/api/admin/settings/certificate', [...CertificateTemplate::editorDefaults(),
                'image' => $this->image('background.png'), 'logo_image' => $this->image('logo.png')]);
            $this->fail('The template save should fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated template save failure.', $exception->getMessage());
        }
        $this->assertSame([$previousImage], Storage::disk('s3')->allFiles('certificate-templates'));
        $this->assertSame($previousImage, CertificateTemplate::current()['image_path']);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
