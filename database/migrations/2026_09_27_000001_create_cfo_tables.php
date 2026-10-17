<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'student_id')) Schema::table('users', function (Blueprint $t) {
            $t->string('student_id')->nullable()->unique();
            $t->string('system_username')->nullable()->unique();
            $t->string('first_name')->default('');
            $t->string('middle_name')->nullable();
            $t->string('last_name')->default('');
            $t->string('role')->default('Student')->index();
            $t->string('status')->default('Pending')->index();
            $t->text('rejection_reason')->nullable();
            $t->string('course')->nullable();
            $t->string('year_level')->nullable();
            $t->string('section')->nullable();
        });
        if (! Schema::hasTable('academic_options')) Schema::create('academic_options', function (Blueprint $t) {
            $t->id(); $t->string('type'); $t->string('name'); $t->timestamps();
            $t->unique(['type', 'name']);
        });
        if (! Schema::hasTable('recollection_schedules')) Schema::create('recollection_schedules', function (Blueprint $t) {
            $t->id(); $t->string('title'); $t->date('date')->index();
            $t->string('start_time', 5); $t->string('end_time', 5);
            $t->string('venue'); $t->unsignedInteger('capacity');
            $t->boolean('active')->default(true); $t->string('theme')->nullable();
            $t->string('facilitator')->nullable(); $t->text('description')->nullable(); $t->timestamps();
        });
        if (! Schema::hasTable('recollection_registrations')) Schema::create('recollection_registrations', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('year'); $t->json('details');
            $t->foreignId('recollection_schedule_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('status')->default('Pending'); $t->text('rejection_reason')->nullable();
            $t->string('receipt_path')->nullable(); $t->string('receipt_status')->default('Not uploaded');
            $t->text('receipt_reason')->nullable(); $t->foreignId('receipt_verified_by')->nullable()->constrained('users');
            $t->timestamp('receipt_verified_at')->nullable();
            $t->string('attendance')->default('Not Yet Recorded');
            $t->foreignId('attendance_recorded_by')->nullable()->constrained('users');
            $t->foreignId('attendance_verified_by')->nullable()->constrained('users');
            $t->timestamp('attendance_verified_at')->nullable(); $t->timestamps();
            $t->unique(['user_id', 'year']);
        });
        if (! Schema::hasTable('schedule_change_requests')) Schema::create('schedule_change_requests', function (Blueprint $t) {
            $t->id(); $t->foreignId('registration_id')->constrained('recollection_registrations')->restrictOnDelete();
            $t->foreignId('current_schedule_id')->constrained('recollection_schedules');
            $t->foreignId('requested_schedule_id')->constrained('recollection_schedules');
            $t->text('reason'); $t->string('status')->default('Pending')->index();
            $t->foreignId('reviewed_by')->nullable()->constrained('users'); $t->timestamp('reviewed_at')->nullable(); $t->timestamps();
        });
        if (! Schema::hasTable('certificates')) Schema::create('certificates', function (Blueprint $t) {
            $t->id(); $t->foreignId('registration_id')->unique()->constrained('recollection_registrations');
            $t->string('certificate_number')->unique(); $t->json('snapshot');
            $t->foreignId('issued_by')->constrained('users'); $t->timestamps();
        });
        if (! Schema::hasTable('password_reset_requests')) Schema::create('password_reset_requests', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained(); $t->string('status')->default('Pending')->index();
            $t->string('recovery_hash', 64); $t->string('temp_password_hash')->nullable();
            $t->timestamp('expires_at')->nullable(); $t->timestamp('verified_at')->nullable();
            $t->timestamp('used_at')->nullable(); $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamp('reviewed_at')->nullable(); $t->timestamps();
        });
        if (! Schema::hasTable('cfo_settings')) Schema::create('cfo_settings', function (Blueprint $t) {
            $t->string('key')->primary(); $t->json('value'); $t->timestamps();
        });
        if (! Schema::hasTable('activity_logs')) Schema::create('activity_logs', function (Blueprint $t) {
            $t->id(); $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('action'); $t->string('record_type'); $t->unsignedBigInteger('record_id')->nullable();
            $t->json('metadata')->nullable(); $t->timestamp('created_at')->useCurrent();
        });
        if (! Schema::hasTable('cfo_notifications')) Schema::create('cfo_notifications', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained(); $t->string('message');
            $t->timestamp('read_at')->nullable(); $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['cfo_notifications', 'activity_logs', 'cfo_settings', 'password_reset_requests', 'certificates', 'schedule_change_requests', 'recollection_registrations', 'recollection_schedules', 'academic_options'] as $table) Schema::dropIfExists($table);
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['student_id', 'system_username', 'first_name', 'middle_name', 'last_name', 'role', 'status', 'rejection_reason', 'course', 'year_level', 'section']));
    }
};
