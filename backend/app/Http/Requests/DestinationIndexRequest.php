<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DestinationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'budget_level' => ['nullable', 'in:budget,moderate,luxury'],
            'climate' => ['nullable', 'string', 'max:50'],
            'theme' => ['nullable', 'string', 'max:50'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'min_safety' => ['nullable', 'integer', 'between:1,5'],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ];
    }
}
