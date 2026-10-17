<?php

use App\Services\CfoService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $changed = false;
        DB::table('users')->where('role', 'Student')->where(function ($query) {
            foreach (['course', 'year_level', 'section'] as $column) {
                $query->orWhereNull($column)->orWhereRaw("TRIM($column) = ''");
            }
        })->orderBy('id')->chunkById(200, function ($students) use (&$changed) {
            foreach ($students as $student) {
                DB::transaction(function () use ($student, &$changed) {
                    $user = DB::table('users')->where('id', $student->id)->lockForUpdate()->first();
                    $registration = DB::table('recollection_registrations')->where('user_id', $student->id)
                        ->whereNull('archived_at')->orderByDesc('year')->orderByDesc('attempt_no')->orderByDesc('id')->first();
                    if (! $user || ! $registration) return;
                    $details = json_decode($registration->details ?? '', true) ?: [];
                    $snapshot = json_decode($registration->student_snapshot ?? '', true) ?: [];
                    // Older submissions only stored the combined year/section (e.g. 2A or 3-A).
                    $legacy = [];
                    if (is_scalar($details['yearSection'] ?? null)) {
                        preg_match('/^(\d+)\s*[-\/]?\s*([A-Za-z]+)$/D', trim((string) $details['yearSection']), $legacy);
                    }
                    $updates = [];
                    foreach (['course' => 'course', 'year_level' => 'yearLevel', 'section' => 'section'] as $column => $key) {
                        if (trim((string) ($user->$column ?? '')) !== '') continue;
                        $candidates = [$details[$key] ?? null];
                        if ($column === 'year_level') $candidates[] = $legacy[1] ?? null;
                        if ($column === 'section') $candidates[] = isset($legacy[2]) ? strtoupper($legacy[2]) : null;
                        $candidates[] = $snapshot[$column] ?? null;
                        foreach ($candidates as $value) {
                            if (is_scalar($value) && trim((string) $value) !== '') {
                                $updates[$column] = trim((string) $value);
                                break;
                            }
                        }
                    }
                    if ($updates !== []) {
                        DB::table('users')->where('id', $user->id)->update($updates);
                        $changed = true;
                    }
                });
            }
        });
        if ($changed) {
            CfoService::touch('users');
            CfoService::touch('registrations');
        }
    }

    public function down(): void
    {
        // Keep filled academic data: removing it would erase valid student information.
    }
};
