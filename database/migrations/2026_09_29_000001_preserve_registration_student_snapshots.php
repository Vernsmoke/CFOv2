<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recollection_registrations', function (Blueprint $table) {
            $table->json('student_snapshot')->nullable();
            $table->index('year');
        });

        // Older records have no separate academic snapshot. Prefer their submitted
        // details; retain the available profile as a clearly marked legacy fallback.
        DB::table('recollection_registrations')->orderBy('id')->chunkById(200, function ($rows) {
            $users = DB::table('users')->whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');
            foreach ($rows as $row) {
                $user = $users->get($row->user_id);
                $details = json_decode($row->details, true) ?: [];
                DB::table('recollection_registrations')->where('id', $row->id)->update(['student_snapshot' => json_encode([
                    'name' => $details['name'] ?? $user?->name,
                    'student_id' => $user?->student_id,
                    'course' => $details['course'] ?? $user?->course,
                    'year_level' => $user?->year_level,
                    'section' => $user?->section,
                    'submitted_year_section' => $details['yearSection'] ?? null,
                    'legacy' => true,
                ])]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('recollection_registrations', function (Blueprint $table) {
            $table->dropIndex(['year']);
            $table->dropColumn('student_snapshot');
        });
    }
};
