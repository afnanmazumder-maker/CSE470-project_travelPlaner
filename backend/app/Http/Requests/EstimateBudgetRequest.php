<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EstimateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'destination_id' => ['required', 'integer', 'exists:destinations,id'],
            'days' => ['required', 'integer', 'between:1,90'],
            'travelers' => ['required', 'integer', 'between:1,20'],
            'comfort' => ['required', 'in:'.implode(',', config('voyenta.budget_bands'))],
        ];
    }
}
