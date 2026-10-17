<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('original_email')->nullable()->unique();
            $table->string('login_email')->nullable()->unique();
        });

        DB::table('users')->whereNull('original_email')->update([
            'original_email' => DB::raw('LOWER(email)'),
        ]);

        DB::table('users')->where('role', 'Student')->orderBy('id')->get()->each(function ($user) {
            $original = strtolower(trim($user->email));
            $login = strtolower(trim($user->system_username ?: (
                str_ends_with($original, '@gmail.com')
                    ? substr($original, 0, -10).'@cfo.office.com'
                    : $original
            )));

            DB::table('users')->where('id', $user->id)->update([
                'original_email' => $original,
                'login_email' => $login,
                'system_username' => $login,
            ]);
        });

        Schema::table('recollection_schedules', function (Blueprint $table) {
            $table->string('year_level')->nullable()->index();
            $table->string('section')->nullable()->index();
            $table->unsignedInteger('slot_limit')->nullable();
        });
        DB::table('recollection_schedules')->update(['slot_limit' => DB::raw('capacity')]);

        Schema::create('recollection_schedule_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('recollection_schedules')->cascadeOnDelete();
            $table->foreignId('academic_option_id')->constrained('academic_options')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['schedule_id', 'academic_option_id'], 'schedule_course_unique');
            $table->index(['academic_option_id', 'schedule_id'], 'schedule_course_option_idx');
        });

        Schema::create('cfo_resource_versions', function (Blueprint $table) {
            $table->string('resource')->primary();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        DB::table('cfo_resource_versions')->insert(array_map(fn ($resource) => [
            'resource' => $resource,
            'version' => 0,
            'updated_at' => now(),
        ], [
            'users', 'schedules', 'registrations', 'attendance', 'schedule_changes',
            'password_resets', 'notifications', 'activity_logs', 'academic_options', 'settings',
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('cfo_resource_versions');
        Schema::dropIfExists('recollection_schedule_courses');

        Schema::table('recollection_schedules', function (Blueprint $table) {
            $table->dropIndex(['year_level']);
            $table->dropIndex(['section']);
            $table->dropColumn(['year_level', 'section', 'slot_limit']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['original_email']);
            $table->dropUnique(['login_email']);
            $table->dropColumn(['original_email', 'login_email']);
        });
    }
};
