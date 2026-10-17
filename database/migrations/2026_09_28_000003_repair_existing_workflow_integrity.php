<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        // Preserve previously entered labels as reference choices; do not discard profiles.
        foreach (['course', 'year_level', 'section'] as $type) {
            $names = DB::table('users')->whereNotNull($type)->where($type, '!=', '')->distinct()->pluck($type);
            if ($type !== 'course') $names = $names->merge(DB::table('recollection_schedules')->whereNotNull($type)->where($type, '!=', '')->distinct()->pluck($type));
            foreach ($names->unique() as $name) {
                if (! DB::table('academic_options')->where(compact('type', 'name'))->exists()) DB::table('academic_options')->insert(['type' => $type, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        Schema::table('recollection_registrations', fn (Blueprint $table) => $table->timestamp('attendance_recorded_at')->nullable());
        Schema::table('certificates', function (Blueprint $table) {
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users');
            $table->string('revocation_reason')->nullable();
        });
        DB::table('cfo_resource_versions')->where('resource', 'academic_options')->increment('version');
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn(['revoked_at', 'revocation_reason']);
        });
        Schema::table('recollection_registrations', fn (Blueprint $table) => $table->dropColumn('attendance_recorded_at'));
        // Reference choices intentionally retained: existing profiles may still use them.
    }
};
