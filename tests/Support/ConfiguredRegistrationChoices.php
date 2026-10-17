<?php

namespace Tests\Support;

use App\Models\{AcademicOption, Schedule};

trait ConfiguredRegistrationChoices
{
    protected function configuredRegistrationSchedule(array $groups = ['BSIT' => ['2' => ['A']]], array $changes = []): Schedule
    {
        $years = [];
        $sections = [];
        $courses = [];
        foreach ($groups as $name => $blocks) {
            $course = AcademicOption::firstOrCreate(['type' => 'course', 'name' => $name]);
            $courses[$course->id] = ['sections' => $blocks];
            foreach ($blocks as $year => $choices) {
                $years[] = (string) $year;
                $sections = [...$sections, ...$choices];
            }
        }
        $schedule = Schedule::create([...[
            'title' => 'Admin-configured registration choices', 'date' => today()->toDateString(),
            'start_time' => '08:00', 'end_time' => '16:00', 'venue' => 'Registration Hall',
            'capacity' => 50, 'status' => 'Open', 'year_levels' => array_values(array_unique($years)),
            'sections' => array_values(array_unique($sections)),
        ], ...$changes]);
        $schedule->courses()->sync($courses);

        return $schedule;
    }
}
