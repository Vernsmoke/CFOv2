<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveRecollectionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasOfficeAccess() === true;
    }

    public function academicYearLevels(): array
    {
        $configured = DB::table('academic_options')->where('type', 'year_level')->pluck('name')->all();

        return array_values(array_unique([...array_map('strval', range(1, 4)), ...$configured]));
    }

    public function academicSections(): array
    {
        $configured = DB::table('academic_options')->where('type', 'section')->pluck('name')->all();

        // Course/year configuration still overrides this fallback in the controller.
        return array_values(array_unique([...range('A', 'Z'), ...$configured]));
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('schedule') === null && $this->input('save_action') === 'draft') {
            $this->merge(['status' => 'Draft']);
        }
    }

    public function rules(): array
    {
        $hasYearBlocks = $this->exists('course_sections_by_year');
        $mappedSections = $this->exists('course_sections') || $hasYearBlocks;
        $yearLevels = $this->academicYearLevels();
        $sections = $this->academicSections();

        return [
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i|after:start_time',
            'venue' => 'required|string|max:190', 'slot_limit' => 'nullable|integer|min:1|max:100000',
            'course_ids' => 'required|array|min:1',
            'course_ids.*' => ['required', 'integer', 'distinct', Rule::exists('academic_options', 'id')->where('type', 'course')],
            'year_level' => $this->exists('year_levels') ? ['exclude'] : ['required', 'string', 'max:190', Rule::in($yearLevels)],
            'section' => $this->exists('sections') || $mappedSections ? ['exclude'] : ['required', 'string', 'max:190', Rule::in($sections)],
            'year_levels' => 'sometimes|required|array|min:1',
            'year_levels.*' => ['required', 'string', 'max:190', 'distinct:strict', Rule::in($yearLevels)],
            'sections' => $mappedSections ? ['exclude'] : ['sometimes', 'required', 'array', 'min:1'],
            'sections.*' => $mappedSections ? ['exclude'] : ['required', 'string', 'max:190', 'distinct:strict', Rule::in($sections)],
            'course_sections' => 'sometimes|required|array|min:1',
            'course_sections.*' => 'required|array|min:1',
            'course_sections.*.*' => 'required|string|max:190',
            'course_sections_by_year' => 'sometimes|required|array|min:1',
            'course_sections_by_year.*' => 'required|array|min:1',
            'course_sections_by_year.*.*' => 'required|array|min:1',
            'course_sections_by_year.*.*.*' => 'required|string|max:190',
            'course_groups' => $this->route('schedule') === null ? ['sometimes', 'required', 'array', 'min:1'] : ['exclude'],
            'course_groups.*' => $this->route('schedule') === null ? ['required', 'array:course_id,year_level,sections'] : ['exclude'],
            'course_groups.*.course_id' => ['required', 'integer', Rule::exists('academic_options', 'id')->where('type', 'course')],
            'course_groups.*.year_level' => ['required', 'string', 'max:190', Rule::in($yearLevels)],
            'course_groups.*.sections' => 'required|array|min:1',
            'course_groups.*.sections.*' => 'required|string|max:190',
            'active' => 'required_without:status|boolean', 'status' => 'sometimes|required|in:Draft,Open,Closed',
            'save_action' => $this->route('schedule') === null ? ['sometimes', 'required', Rule::in(['draft', 'another', 'publish'])] : ['exclude'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->route('schedule') === null && $this->input('save_action') === 'publish'
                && $this->input('status') === 'Draft') {
                $validator->errors()->add('status', 'Choose Open for Registration or Closed before publishing. Draft schedules cannot be published.');
            }
            if ($this->route('schedule') !== null || ! $this->exists('course_groups') || $validator->errors()->isNotEmpty()) {
                return;
            }
            if (! $this->exists('course_sections_by_year')) {
                $validator->errors()->add('course_groups', 'Save the selected sections for every course group.');

                return;
            }
            $mapping = [];
            foreach ($this->input('course_groups') as $index => $group) {
                $courseId = (int) $group['course_id'];
                $year = $group['year_level'];
                if (isset($mapping[$courseId][$year])) {
                    $validator->errors()->add('course_groups.'.$index.'.year_level', 'This course and year level already has a group. Add its sections to the existing group.');

                    continue;
                }
                $mapping[$courseId][$year] = $group['sections'];
            }
            if ($this->normalizedMapping($mapping) !== $this->normalizedMapping($this->input('course_sections_by_year'))) {
                $validator->errors()->add('course_groups', 'The saved course groups must match the selected courses, year levels and sections.');
            }
        }];
    }

    private function normalizedMapping(array $mapping): array
    {
        foreach ($mapping as &$years) {
            foreach ($years as &$sections) {
                sort($sections, SORT_STRING);
            }
            unset($sections);
            ksort($years, SORT_STRING);
        }
        unset($years);
        ksort($mapping, SORT_STRING);

        return $mapping;
    }

    public function messages(): array
    {
        return [
            'end_time.after' => 'End time must be after start time.',
            'slot_limit.min' => 'Maximum capacity must be greater than zero, or leave it blank for no limit.',
            'course_ids.min' => 'Select at least one course group.',
            'year_levels.*.in' => 'Choose a year level from the configured options.',
            'course_sections_by_year.*.*.min' => 'Select at least one section for every course group.',
        ];
    }
}
