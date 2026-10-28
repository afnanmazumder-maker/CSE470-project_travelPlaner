<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'interests' => ['required', 'array', 'min:1', 'max:10'],
            'interests.*' => ['string', Rule::in(config('voyenta.tags'))],
            'pace' => ['required', Rule::in(config('voyenta.paces'))],
            'budget_band' => ['required', Rule::in(config('voyenta.budget_bands'))],
        ];
    }
}
