<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recollection_schedules', function (Blueprint $table) {
            $table->json('course_majors')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recollection_schedules', function (Blueprint $table) {
            $table->dropColumn('course_majors');
        });
    }
};
