<?php

namespace Database\Seeders;

use App\Services\AcademicCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AcademicCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [];
        foreach (AcademicCatalog::courseNames() as $name) {
            $rows[] = compact('name') + ['type' => 'course', 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (config('academic-programs.year_levels', []) as $name) {
            $rows[] = compact('name') + ['type' => 'year_level', 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (config('academic-programs.sections', []) as $name) {
            $rows[] = compact('name') + ['type' => 'section', 'created_at' => $now, 'updated_at' => $now];
        }

        DB::table('academic_options')->insertOrIgnore($rows);
    }
}
