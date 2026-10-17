<?php

// One-time repair for the two legacy days identified in the September 28 audit.
// Default is a read-only preview. --apply uses the owner's BSIT-3A instruction.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{AcademicOption, Schedule};
use App\Services\CfoService;
use Illuminate\Support\Facades\DB;

$expected = [1 => ['Recollection', '2026-09-27'], 2 => ['hfgyufh', '2026-09-30']];
$apply = in_array('--apply', $argv, true);
DB::transaction(function () use ($expected, $apply) {
    $course = AcademicOption::where('type', 'course')->where('name', 'BSIT')->lockForUpdate()->firstOrFail();
    $days = Schedule::whereIn('id', array_keys($expected))->orderBy('id')->lockForUpdate()->get();
    if ($days->count() !== count($expected)) throw new RuntimeException('Expected legacy schedules are missing; stop and inspect.');
    foreach ($days as $day) {
        if ([$day->title, $day->date] !== $expected[$day->id]) throw new RuntimeException('Schedule identity changed; stop and inspect.');
        $courses = $day->courses()->pluck('academic_options.id')->map(fn ($id) => (int) $id)->all();
        if ($courses !== [] && ($courses !== [$course->id] || $day->year_level !== '3' || $day->section !== 'A')) throw new RuntimeException('Schedule already has a different scope; stop and inspect.');
        $mismatches = $day->registrations()->whereHas('user', fn ($query) => $query->where(function ($query) {
            $query->whereNull('course')->orWhere('course', '!=', 'BSIT')->orWhereNull('year_level')->orWhere('year_level', '!=', '3')->orWhereNull('section')->orWhere('section', '!=', 'A');
        }))->count();
        echo 'Schedule '.$day->id.': BSIT / 3 / A; assigned registrations: '.$day->registrations()->count().'; mismatching current profiles: '.$mismatches.PHP_EOL;
        if ($apply && $mismatches) throw new RuntimeException('Assigned profile scope differs. Clarify before changing this day. No repair committed.');
    }
    if (! $apply) { echo "Preview only; no records changed.\n"; return; }
    foreach (['year_level' => '3', 'section' => 'A'] as $type => $name) AcademicOption::firstOrCreate(compact('type', 'name'));
    foreach ($days as $day) {
        if ($day->year_level === '3' && $day->section === 'A' && $day->courses()->whereKey($course->id)->exists()) continue;
        $before = ['course_ids' => $day->courses()->pluck('academic_options.id')->all(), 'year_level' => $day->year_level, 'section' => $day->section];
        $day->update(['year_level' => '3', 'section' => 'A']);
        $day->courses()->sync([$course->id]);
        CfoService::audit('schedule.scope_repaired', 'schedule', $day->id, ['source' => 'Owner instruction: BSIT-3A for both legacy days', 'before' => $before, 'after' => ['course_ids' => [$course->id], 'year_level' => '3', 'section' => 'A']]);
    }
    CfoService::touch('academic_options');
    CfoService::touch('schedules');
    echo "Committed both schedule scopes. Profiles and registrations were preserved.\n";
});
