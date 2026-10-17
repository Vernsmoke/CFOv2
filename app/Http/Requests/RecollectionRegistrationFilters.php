<?php

namespace App\Http\Requests;

use App\Models\Registration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecollectionRegistrationFilters extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasOfficeAccess() === true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'course' => ['nullable', 'string', 'max:255'],
            'year_section' => ['nullable', 'string', 'max:510'],
            'status' => ['nullable', Rule::in(array_keys(Registration::RECEIPT_LABELS))],
            'schedule_status' => ['nullable', 'string', Rule::in(['scheduled', 'not_yet_scheduled'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 25, 50, 100])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
