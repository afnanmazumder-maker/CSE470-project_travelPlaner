<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:100'],
            'destination_id' => ['sometimes', 'integer', 'exists:destinations,id'],
            'days' => ['sometimes', 'integer', 'between:1,90'],
            'travelers' => ['sometimes', 'integer', 'between:1,20'],
            'comfort' => ['sometimes', 'in:'.implode(',', config('voyenta.budget_bands'))],
            'limit_amount' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:1000000'],
        ];
    }
}
