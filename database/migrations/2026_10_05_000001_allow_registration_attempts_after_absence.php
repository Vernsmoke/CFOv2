<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recollection_registrations', function (Blueprint $table) {
            $table->unsignedInteger('attempt_no')->default(1);
            $table->unique(['user_id', 'year', 'attempt_no'], 'registrations_student_year_attempt_unique');
        });
        Schema::table('recollection_registrations', fn (Blueprint $table) => $table->dropUnique('recollection_registrations_user_id_year_unique'));
    }

    public function down(): void
    {
        if (DB::table('recollection_registrations')->select('user_id', 'year')->groupBy('user_id', 'year')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot roll back while repeat attempts exist. Retain their history; no records have been deleted.');
        }
        Schema::table('recollection_registrations', fn (Blueprint $table) => $table->unique(['user_id', 'year']));
        Schema::table('recollection_registrations', function (Blueprint $table) {
            $table->dropUnique('registrations_student_year_attempt_unique');
            $table->dropColumn('attempt_no');
        });
    }
};
