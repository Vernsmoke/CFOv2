<?php
namespace Tests\Feature;

use App\Models\{Certificate, PasswordResetRequest, Registration, Schedule, ScheduleChange, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Cache, Crypt, DB, Hash, Storage};
use Tests\TestCase;
use Tests\Support\ConfiguredRegistrationChoices;

class CfoWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguredRegistrationChoices;
    private function account(string $role = 'Student', array $extra = []): User
    {
        return User::factory()->create([...['first_name' => 'Test', 'last_name' => $role, 'role' => $role, 'status' => 'Approved', 'password' => 'CfoTest!234'], ...$extra]);
    }
    private function schedule(array $extra = []): Schedule
    {
        return Schedule::create([...['title' => 'Test Recollection', 'date' => today()->toDateString(), 'start_time' => '08:00', 'end_time' => '16:00', 'venue' => 'Test Hall', 'capacity' => 2, 'active' => true], ...$extra]);
    }
    private function details(): array
    {
        return ['name' => 'Test Student', 'age' => 20, 'sex' => 'Female', 'course' => 'BSIT', 'yearSection' => '2A', 'email' => 'student@example.test', 'phone' => '09123456789', 'religion' => 'Roman Catholic', 'emergencyName' => 'Test Contact', 'relationship' => 'Parent', 'emergencyPhone' => '09123456780', 'participation' => true, 'photos' => true];
    }
    public function test_registration_approval_login_role_and_logout(): void
    {
        $admin=$this->account('Admin');
        $input=['student_id'=>'24-10723','first_name'=>'New','last_name'=>'Student','email'=>'new@gmail.com','password'=>'CfoTest!234','password_confirmation'=>'CfoTest!234'];
        $this->postJson('/api/register',[...$input,'role'=>'SuperAdmin'])->assertUnprocessable();
        $this->postJson('/api/register',[...$input,'email'=>'new@outlook.com'])->assertUnprocessable();
        $this->withVerifiedRegistrationEmail($input['email'])->postJson('/api/register',$input)->assertCreated()->assertJsonPath('login_email','new@cfo.office.com');
        $user=User::where('email',$input['email'])->firstOrFail();
        $this->assertSame('Pending',$user->status);
        $this->assertSame('new@cfo.office.com',$user->system_username);
        $this->postJson('/api/login',['email'=>$input['email'],'password'=>$input['password']])->assertUnprocessable();
        $this->actingAs($admin)->patchJson('/api/admin/users/'.$user->id,['first_name'=>'New','last_name'=>'Student','status'=>'Approved'])->assertOk();
        $this->postJson('/api/logout')->assertOk();
        $this->postJson('/api/login',['email'=>$user->system_username,'password'=>$input['password']])->assertOk()->assertJsonPath('redirect','/student#dashboard');
        $this->get('/student')->assertOk()->assertSee('cfo-backend',false)->assertSee('{{0}}',false);
        $this->getJson('/api/admin/state')->assertForbidden();
        $this->postJson('/api/logout')->assertOk();
        $this->getJson('/api/student')->assertUnauthorized();
    }
    public function test_student_account_modal_fields_save_each_status_without_changing_academic_details(): void
    {
        $admin = $this->account('Admin');
        $student = $this->account('Student', ['student_id' => 'ACCOUNT-EDIT-001', 'course' => 'BSIT', 'year_level' => '2', 'section' => 'A']);
        $input = ['student_id' => $student->student_id, 'email' => 'account.edited@gmail.com', 'first_name' => 'Updated', 'middle_name' => 'Middle', 'last_name' => 'Student'];
        foreach (['Pending', 'Approved', 'Rejected'] as $status) {
            $this->actingAs($admin)->patchJson('/api/admin/users/'.$student->id, [...$input, 'status' => $status])
                ->assertOk()->assertJsonPath('message', 'Student account saved.');
            $this->assertDatabaseHas('users', ['id' => $student->id, ...$input, 'status' => $status, 'name' => 'Updated Middle Student',
                'original_email' => $input['email'], 'login_email' => 'account.edited@cfo.office.com', 'course' => 'BSIT', 'year_level' => '2', 'section' => 'A']);
        }
    }
    public function test_full_registration_receipt_assignment_attendance_certificate_flow(): void
    {
        Storage::fake('local');
        $student=$this->account(); $admin=$this->account('Admin'); $schedule=$this->schedule();
        $this->configuredRegistrationSchedule();
        $file=UploadedFile::fake()->createWithContent('receipt.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));
        $this->actingAs($student)->postJson('/api/registrations',['details'=>$this->details(),'receipt'=>$file,'schedule_id'=>$schedule->id])->assertCreated();
        $r=Registration::firstOrFail();
        $this->assertNull($r->recollection_schedule_id);
        $this->postJson('/api/registrations',['details'=>$this->details()])->assertUnprocessable();
        $this->getJson('/api/student')->assertOk()->assertJsonCount(1,'registrations');
        $this->actingAs($admin)->patchJson('/api/admin/registrations/'.$r->id,['schedule_id'=>$schedule->id])->assertUnprocessable();
        $this->patchJson('/api/admin/registrations/'.$r->id,['status'=>'Approved','receipt_status'=>'Approved','schedule_id'=>$schedule->id])->assertOk();
        $this->get('/api/receipts/'.$r->id)->assertOk();
        $this->postJson('/api/admin/registrations/'.$r->id.'/certificate')->assertUnprocessable();
        $this->patchJson('/api/admin/registrations/'.$r->id,['attendance'=>'Present'])->assertOk();
        $this->postJson('/api/admin/registrations/'.$r->id.'/certificate')->assertUnprocessable();
        $this->patchJson('/api/admin/registrations/'.$r->id,['verify_attendance'=>true])->assertOk();
        $this->postJson('/api/admin/settings/certificate',['title'=>'Certificate of Participation','description'=>'Completed the retreat.','signatory'=>'Test CFO'])->assertOk();
        $this->postJson('/api/admin/registrations/'.$r->id.'/certificate')->assertOk();
        $certificate=Certificate::firstOrFail();
        $this->postJson('/api/admin/registrations/'.$r->id.'/certificate')->assertOk();
        $this->assertDatabaseCount('certificates',1);
        $this->patchJson('/api/admin/registrations/'.$r->id,['attendance'=>'Absent','confirm_edit'=>false])->assertUnprocessable();
        $this->actingAs($student)->get('/certificates/'.$certificate->id)->assertOk()->assertSee($certificate->certificate_number);
        $other=$this->account();
        $this->actingAs($other)->get('/certificates/'.$certificate->id)->assertForbidden();
        $this->get('/api/receipts/'.$r->id)->assertForbidden();
        $this->getJson('/api/admin/state')->assertForbidden();
    }
    public function test_yearly_history_and_validation_and_receipt_replacement(): void
    {
        Storage::fake('local'); $user=$this->account();
        $this->configuredRegistrationSchedule();
        $prior=Registration::create(['user_id'=>$user->id,'year'=>now()->year-1,'details'=>['name'=>'Previous year']]);
        $this->actingAs($user)->postJson('/api/registrations',['details'=>[]])->assertUnprocessable();
        $this->postJson('/api/registrations',['details'=>$this->details(),'receipt'=>UploadedFile::fake()->create('bad.svg',1,'image/svg+xml')])->assertUnprocessable();
        $initialReceipt=UploadedFile::fake()->createWithContent('initial.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));
        $this->postJson('/api/registrations',['details'=>$this->details(),'receipt'=>$initialReceipt])->assertCreated();
        $this->assertSame('Previous year',$prior->fresh()->details['name']);
        $this->getJson('/api/student')->assertJsonCount(2,'registrations');
        $r=Registration::where('year',now()->year)->firstOrFail();
        $r->update(['receipt_status'=>'Rejected', 'receipt_reason'=>'Upload a clearer receipt.']);
        $file=UploadedFile::fake()->createWithContent('proof.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));
        $this->postJson('/api/registrations/'.$r->id.'/receipt',['receipt'=>$file])->assertOk();
        $this->assertSame('Pending',$r->fresh()->receipt_status);
    }
    public function test_schedule_capacity_and_change_request_decisions(): void
    {
        $admin=$this->account('Admin'); $s1=$this->schedule(); $s2=$this->schedule(['capacity'=>1]);
        $student=$this->account();
        $r=Registration::create(['user_id'=>$student->id,'year'=>now()->year,'details'=>$this->details(),'status'=>'Approved','receipt_status'=>'Approved','recollection_schedule_id'=>$s1->id]);
        $this->actingAs($student)->postJson('/api/change-requests',['registration_id'=>$r->id,'requested_schedule_id'=>$s2->id,'reason'=>'Class conflict'])->assertCreated();
        $this->postJson('/api/change-requests',['registration_id'=>$r->id,'requested_schedule_id'=>$s2->id,'reason'=>'Duplicate request'])->assertUnprocessable()->assertSee('pending change request');
        $change=ScheduleChange::firstOrFail();
        $this->actingAs($admin)->patchJson('/api/admin/change-requests/'.$change->id,['status'=>'Rejected'])->assertUnprocessable();
        $this->patchJson('/api/admin/change-requests/'.$change->id,['status'=>'Rejected','review_reason'=>'Please arrange an alternative with the office.'])->assertOk();
        $this->assertSame($s1->id,$r->fresh()->recollection_schedule_id);
        $this->actingAs($student)->postJson('/api/change-requests',['registration_id'=>$r->id,'requested_schedule_id'=>$s2->id,'reason'=>'New request'])->assertCreated();
        $change=ScheduleChange::latest('id')->firstOrFail();
        $this->actingAs($admin)->patchJson('/api/admin/change-requests/'.$change->id,['status'=>'Approved'])->assertOk();
        $this->assertSame($s2->id,$r->fresh()->recollection_schedule_id);
        $other=$this->account(); $otherRegistration=Registration::create(['user_id'=>$other->id,'year'=>now()->year,'details'=>[],'status'=>'Approved','receipt_status'=>'Approved']);
        $this->patchJson('/api/admin/registrations/'.$otherRegistration->id,['schedule_id'=>$s2->id])->assertUnprocessable();
        $this->deleteJson('/api/admin/schedules/'.$s2->id)->assertUnprocessable();
        $this->patchJson('/api/admin/change-requests/'.$change->id,['status'=>'Approved'])->assertUnprocessable();
    }
    public function test_attendance_requires_confirmation_and_verification_is_reset_on_edit(): void
    {
        $admin=$this->account('Admin'); $student=$this->account(); $s=$this->schedule(['date'=>today()->addDays(4)->toDateString()]);
        $r=Registration::create(['user_id'=>$student->id,'year'=>now()->year,'details'=>[],'recollection_schedule_id'=>$s->id]);
        $this->actingAs($admin)->patchJson('/api/admin/registrations/'.$r->id,['attendance'=>'Present','verify_attendance'=>true])->assertOk();
        $this->patchJson('/api/admin/registrations/'.$r->id,['attendance'=>'Absent'])->assertUnprocessable();
        $this->patchJson('/api/admin/registrations/'.$r->id,['attendance'=>'Absent','confirm_edit'=>true])->assertOk();
        $this->assertNull($r->fresh()->attendance_verified_at);
    }
    public function test_recovery_is_session_bound_single_use_and_completes(): void
    {
        $admin=$this->account('Admin'); $student=$this->account();
        $this->postJson('/api/forgot-password',['email'=>$student->email])->assertOk();
        $reset=PasswordResetRequest::firstOrFail();
        $this->actingAs($admin)->patchJson('/api/admin/password-resets/'.$reset->id,['status'=>'Approved','identity_verified'=>true])->assertOk();
        $this->getJson('/api/admin/state')->assertOk()->assertJsonMissingPath('resets.0.temp_password_hash');
        $this->postJson('/api/logout')->assertOk();
        // Logout invalidates browser recovery state; restore the known owner secret for this test.
        $secret='test-browser-secret'; $reset->update(['recovery_hash'=>hash('sha256',$secret)]);
        $this->withSession(['recovery_secret'=>'other-browser'])->getJson('/api/recovery')->assertJsonPath('status','Pending')->assertJsonMissingPath('temporary_password');
        $result=$this->withSession(['recovery_secret'=>$secret])->getJson('/api/recovery')->assertOk()->assertJsonPath('status','Approved');
        $temporary=$result->json('temporary_password');
        $this->assertNotEmpty($temporary);
        $this->getJson('/api/recovery')->assertJsonMissingPath('temporary_password');
        $this->postJson('/api/login',['email'=>$student->email,'password'=>'Incorrect!234'])->assertUnprocessable();
        $this->postJson('/api/recovery/verify',['temporary_password'=>'bad'])->assertUnprocessable();
        $this->postJson('/api/reset-password',['password'=>'NewCfo!234','password_confirmation'=>'NewCfo!234'])->assertUnprocessable();
        $this->postJson('/api/recovery/verify',['temporary_password'=>$temporary])->assertOk();
        $this->postJson('/api/recovery/verify',['temporary_password'=>$temporary])->assertUnprocessable();
        $this->postJson('/api/reset-password',['password'=>'NewCfo!234','password_confirmation'=>'NewCfo!234'])->assertOk();
        $this->assertSame('Completed',$reset->fresh()->status);
        $this->assertTrue(Hash::check('NewCfo!234',$student->fresh()->password));
        $this->postJson('/api/login',['email'=>$student->email,'password'=>'NewCfo!234'])->assertOk();
        $logs=json_encode(DB::table('activity_logs')->get());
        $this->assertStringNotContainsString($temporary,$logs);
        $this->assertStringNotContainsString('NewCfo!234',$logs);
    }
    public function test_recovery_has_no_expiry_and_preserves_rate_limit_and_generic_response(): void
    {
        $user=$this->account();
        $known=$this->postJson('/api/forgot-password',['email'=>$user->email])->assertOk()->json('message');
        $unknown=$this->postJson('/api/forgot-password',['email'=>'unknown@example.test'])->assertOk()->json('message');
        $this->assertSame($known,$unknown);
        $this->postJson('/api/forgot-password',['email'=>'unknown@example.test'])->assertOk();
        $this->postJson('/api/forgot-password',['email'=>'unknown@example.test'])->assertOk();
        $this->postJson('/api/forgot-password',['email'=>'unknown@example.test'])->assertOk();
        $this->postJson('/api/forgot-password',['email'=>'unknown@example.test'])->assertOk();
        $this->postJson('/api/forgot-password',['email'=>'unknown@example.test'])->assertTooManyRequests();
        $reset=PasswordResetRequest::firstOrFail();
        $reset->update(['recovery_hash'=>hash('sha256','expired'),'status'=>'Approved','temp_password_hash'=>Hash::make('Temp12345'),'expires_at'=>now()->subSecond()]);
        $this->withSession(['recovery_secret'=>'expired'])->postJson('/api/recovery/verify',['temporary_password'=>'Temp12345'])->assertOk();
        $this->getJson('/api/recovery')->assertJsonPath('status','Verified');
    }
    public function test_profile_and_reference_settings_and_admin_page(): void
    {
        $admin=$this->account('Admin');
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('CFO_BACKEND',false);
        $this->postJson('/api/admin/academic-options',['type'=>'course','name'=>'BSIT'])->assertCreated();
        $this->postJson('/api/admin/academic-options',['type'=>'course','name'=>'BSIT'])->assertUnprocessable();
        $this->postJson('/api/admin/settings/office',['name'=>'CFO','school'=>'University'])->assertOk();
        $this->putJson('/api/profile',['first_name'=>'Updated','last_name'=>'Admin','email'=>$admin->email,'current_password'=>'incorrect'])->assertUnprocessable();
        $this->putJson('/api/profile',['first_name'=>'Updated','last_name'=>'Admin','email'=>$admin->email,'current_password'=>'CfoTest!234'])->assertOk()->assertJsonPath('user.name','Updated Admin');
        $this->getJson('/api/admin/state')->assertOk()->assertJsonCount(1,'academic_options')->assertJsonPath('settings.name','CFO');
    }

    public function test_recollection_days_are_course_scoped_allow_shared_dates_and_student_registration(): void
    {
        $admin=$this->account('Admin');
        $bsit=DB::table('academic_options')->insertGetId(['type'=>'course','name'=>'BSIT','created_at'=>now(),'updated_at'=>now()]);
        $bscs=DB::table('academic_options')->insertGetId(['type'=>'course','name'=>'BSCS','created_at'=>now(),'updated_at'=>now()]);
        $bsce=DB::table('academic_options')->insertGetId(['type'=>'course','name'=>'BSCE','created_at'=>now(),'updated_at'=>now()]);
        DB::table('academic_options')->insert([
            ['type'=>'year_level','name'=>'2','created_at'=>now(),'updated_at'=>now()],
            ['type'=>'section','name'=>'A','created_at'=>now(),'updated_at'=>now()],
        ]);
        $date=today()->addDays(3)->toDateString();
        $day=['title'=>'Course Recollection','date'=>$date,'start_time'=>'08:00','end_time'=>'10:00','venue'=>'Hall','course_ids'=>[$bsit],'year_level'=>'2','section'=>'A','slot_limit'=>1,'active'=>true];
        $this->actingAs($admin)->postJson('/api/admin/schedules',$day)->assertOk();
        $this->postJson('/api/admin/schedules',[...$day,'title'=>'Overlap'])->assertUnprocessable();
        $this->postJson('/api/admin/schedules',[...$day,'title'=>'Other course','venue'=>'Other Hall','course_ids'=>[$bscs]])->assertOk();
        $twoCourse=[...$day,'title'=>'Two courses','start_time'=>'11:00','end_time'=>'13:00','course_ids'=>[$bscs,$bsce]];
        $this->postJson('/api/admin/schedules',$twoCourse)->assertOk();
        $this->postJson('/api/admin/schedules',[...$twoCourse,'date'=>today()->addDays(4)->toDateString(),'active'=>false,'course_ids'=>[$bsit,$bscs,$bsce]])->assertOk()->assertJsonCount(3,'courses');
        $schedule=Schedule::whereHas('courses', fn ($query) => $query->whereKey($bsit))->whereDate('date', $date)->firstOrFail();
        $student=$this->account('Student',['course'=>'BSIT','year_level'=>'2','section'=>'A']);
        $registration=Registration::create(['user_id'=>$student->id,'year'=>(int) date('Y'),'details'=>$this->details(),'status'=>'Approved','receipt_status'=>'Approved']);
        $this->actingAs($student)->getJson('/api/student')->assertOk()->assertJsonCount(1,'schedules')->assertJsonPath('schedules.0.id',$schedule->id);
        $this->postJson('/api/registrations/'.$registration->id.'/schedule',['schedule_id'=>$schedule->id])->assertOk();
        $this->postJson('/api/registrations/'.$registration->id.'/schedule',['schedule_id'=>$schedule->id])->assertUnprocessable();
        $this->assertSame($schedule->id,$registration->fresh()->recollection_schedule_id);
        $full=$this->account('Student',['course'=>'BSIT','year_level'=>'2','section'=>'A']);
        Registration::create(['user_id'=>$full->id,'year'=>(int) date('Y'),'details'=>$this->details(),'status'=>'Approved','receipt_status'=>'Approved']);
        $this->actingAs($full)->getJson('/api/student')->assertJsonCount(0,'schedules');
    }

    public function test_resource_versions_advance_and_admin_state_can_fetch_only_changed_groups(): void
    {
        $admin=$this->account('Admin');
        $before=$this->actingAs($admin)->getJson('/api/live/versions')->assertOk()->json();
        $this->account('Student');
        $after=$this->getJson('/api/live/versions')->assertOk()->json();
        $this->assertGreaterThan($before['users'],$after['users']);
        $this->getJson('/api/admin/state?resources=users')->assertOk()->assertJsonStructure(['users', 'registrations', 'requests', 'resets'])->assertJsonMissingPath('schedules');
    }

    public function test_admin_password_reset_forces_student_to_set_a_permanent_password(): void
    {
        $admin=$this->account('Admin');
        $student=$this->account('Student');
        $response=$this->actingAs($admin)->postJson('/api/admin/users/'.$student->id.'/password-reset',['identity_verified'=>true])->assertOk();
        $temporary=$response->json('temporary_password');
        $this->assertNotEmpty($temporary);
        $this->assertTrue($student->fresh()->password_change_required);
        $this->assertTrue(Hash::check($temporary,$student->fresh()->password));
        $this->assertNotNull($student->fresh()->password_change_expires_at);

        $this->postJson('/api/logout')->assertOk();
        $this->postJson('/api/login',['email'=>$student->email,'password'=>$temporary])->assertOk()->assertJsonPath('redirect','/force-password');
        $this->get('/force-password')->assertOk()->assertSee('Create a new password');
        $this->get('/student')->assertForbidden();
        $this->postJson('/api/force-password',['password'=>'PermanentCfo!234','password_confirmation'=>'PermanentCfo!234'])->assertOk()->assertJsonPath('redirect','/login?password_changed=1');
        $this->assertGuest();
        $this->postJson('/api/login',['email'=>$student->email,'password'=>$temporary])->assertUnprocessable();
        $this->postJson('/api/login',['email'=>$student->email,'password'=>'PermanentCfo!234'])->assertOk();
        $this->assertFalse($student->fresh()->password_change_required);
        $this->assertTrue(Hash::check('PermanentCfo!234',$student->fresh()->password));
        $logs=json_encode(DB::table('activity_logs')->get());
        $this->assertStringNotContainsString($temporary,$logs);
    }
}
