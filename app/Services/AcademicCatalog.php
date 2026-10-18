<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class AcademicCatalog
{
    public static function departments(): array
    {
        return array_values(config('academic-programs.departments', []));
    }

    public static function frontend(): array
    {
        return [
            'departments' => self::departments(),
            'year_levels' => array_values(config('academic-programs.year_levels', [])),
            'sections' => array_values(config('academic-programs.sections', [])),
        ];
    }

    public static function courses(): array
    {
        return array_values(array_merge(...array_map(
            fn (array $department) => array_map(
                fn (array $course) => [...$course, 'department' => $department['name']],
                $department['courses']
            ),
            self::departments()
        )));
    }

    public static function courseNames(): array
    {
        return array_column(self::courses(), 'name');
    }

    public static function normalizeSelection(array $selection, string $prefix = ''): array
    {
        $departmentInput = trim((string) ($selection['department'] ?? ''));
        $courseInput = trim((string) ($selection['course'] ?? ''));
        $majorInput = trim((string) ($selection['major'] ?? ''));
        $key = fn (string $name) => $prefix.$name;

        $department = collect(self::departments())->first(
            fn (array $item) => strcasecmp($item['name'], $departmentInput) === 0
        );
        if (! $department) {
            throw ValidationException::withMessages([$key('department') => 'Select a department from the available choices.']);
        }

        $course = collect($department['courses'])->first(
            fn (array $item) => strcasecmp($item['name'], $courseInput) === 0
        );
        if (! $course) {
            throw ValidationException::withMessages([$key('course') => 'Select a course offered by the chosen department.']);
        }

        $majors = $course['majors'] ?? [];
        if ($majors !== []) {
            $major = collect($majors)->first(fn (string $item) => strcasecmp($item, $majorInput) === 0);
            if (! $major) {
                throw ValidationException::withMessages([$key('major') => 'Select a major offered by the chosen course.']);
            }
        } else {
            $major = null;
            if ($majorInput !== '') {
                throw ValidationException::withMessages([$key('major') => 'This course does not require a major.']);
            }
        }

        return ['department' => $department['name'], 'course' => $course['name'], 'major' => $major];
    }

    public static function normalizeIfCatalogCourse(array $selection, string $prefix = ''): array
    {
        $courseInput = trim((string) ($selection['course'] ?? ''));
        $course = collect(self::courses())->first(fn (array $item) => strcasecmp($item['name'], $courseInput) === 0);
        if (! $course) {
            return $selection;
        }

        $selection['department'] = trim((string) ($selection['department'] ?? '')) ?: $course['department'];

        return [...$selection, ...self::normalizeSelection($selection, $prefix)];
    }
}
