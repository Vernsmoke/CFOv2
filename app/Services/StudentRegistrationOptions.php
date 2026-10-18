<?php

namespace App\Services;

use App\Models\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentRegistrationOptions
{
    public static function schedules(?string $course = null): Collection
    {
        $matchingCourse = fn ($query) => $course === null ? $query
            : $query->whereRaw('LOWER(TRIM(academic_options.name)) = ?', [mb_strtolower(trim($course))]);
        // Academic choices are not reservations, so full schedules still supply their groups.
        return Schedule::open()->whereDate('date', '>=', today())
            ->whereDate('date', '<=', now()->endOfYear()->toDateString())
            ->whereHas('courses', $matchingCourse)->with(['courses' => $matchingCourse])->orderBy('date')->orderBy('id')->get()
            ->filter(fn (Schedule $schedule) => $schedule->year_levels !== [] && $schedule->sections !== [])->values();
    }

    public static function groups(?Collection $schedules = null): array
    {
        $groups = [];
        foreach ($schedules ?? self::schedules() as $schedule) {
            foreach ($schedule->courses as $course) {
                $major = $schedule->majorForCourse($course->id);
                foreach ($schedule->year_levels as $year) {
                    foreach ($schedule->sectionsForCourse($course->id, $year) as $section) {
                        $group = ['course' => $course->name, 'yearLevel' => (string) $year, 'section' => (string) $section];
                        if ($major !== null) $group['major'] = $major;
                        $groups[json_encode(array_values($group))] = $group;
                    }
                }
            }
        }

        return array_values($groups);
    }

    public static function normalize(array $details): array
    {
        $details = AcademicCatalog::normalizeIfCatalogCourse($details, 'details.');
        $groups = self::groups();
        $courses = array_filter($groups, fn ($group) => strcasecmp($group['course'], $details['course']) === 0);
        $matches = array_values(array_filter($courses, function ($group) use ($details) {
            if (($group['major'] ?? null) !== null && strcasecmp($group['major'], trim((string) ($details['major'] ?? ''))) !== 0) {
                return false;
            }
            if (isset($details['yearLevel'], $details['section'])) {
                return strcasecmp($group['yearLevel'], $details['yearLevel']) === 0
                    && strcasecmp($group['section'], $details['section']) === 0
                    && strcasecmp($group['yearLevel'].$group['section'], $details['yearSection']) === 0;
            }

            return strcasecmp($group['yearLevel'].$group['section'], $details['yearSection']) === 0;
        }));

        $errors = [];
        if ($courses === []) $errors['details.course'] = 'Select a course/program currently available in an admin-configured recollection schedule.';
        if (count($matches) !== 1) $errors['details.yearSection'] = 'Select an available year level and section for your chosen course/program.';
        if ($errors !== []) throw ValidationException::withMessages($errors);

        $group = $matches[0];
        return [...$details, ...$group, 'yearSection' => $group['yearLevel'].$group['section']];
    }

    public static function lockAndNormalize(array $details): array
    {
        // Schedule edits use this same lock order. Recheck before files or records are written.
        DB::table('academic_options')->where('type', 'course')->orderBy('id')->lockForUpdate()->get();

        return self::normalize($details);
    }
}
