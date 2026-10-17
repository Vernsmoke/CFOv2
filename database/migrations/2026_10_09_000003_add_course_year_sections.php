<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_year_sections')) {
            Schema::create('course_year_sections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained('academic_options')->restrictOnDelete();
                $table->string('year_level', 190);
                $table->string('section', 190);
                $table->timestamps();
                $table->unique(['course_id', 'year_level', 'section'], 'course_year_section_unique');
                $table->index(['course_id', 'year_level'], 'course_year_section_lookup');
            });
        }
        if (! Schema::hasColumn('recollection_schedule_courses', 'sections')) {
            Schema::table('recollection_schedule_courses', fn (Blueprint $table) => $table->json('sections')->nullable());
        }
        // Leave historical groups untouched. An unconfigured course/year uses
        // the existing global section choices until an administrator configures it.
    }

    public function down(): void
    {
        if (Schema::hasColumn('recollection_schedule_courses', 'sections')) {
            Schema::table('recollection_schedule_courses', fn (Blueprint $table) => $table->dropColumn('sections'));
        }
        Schema::dropIfExists('course_year_sections');
    }
};
