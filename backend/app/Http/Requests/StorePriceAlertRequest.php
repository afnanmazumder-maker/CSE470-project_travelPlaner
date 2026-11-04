<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** R9: watch a route for a price drop. */
class StorePriceAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'origin' => ['required', 'string', 'size:3'],
            'destination' => ['required', 'string', 'size:3', 'different:origin'],
            'depart_date' => ['nullable', 'date', 'after_or_equal:today'],
            'cabin_class' => ['required', Rule::in(config('flights.cabin_classes'))],
            'target_price' => ['nullable', 'numeric', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'origin' => strtoupper((string) $this->input('origin')),
            'destination' => strtoupper((string) $this->input('destination')),
        ]);
    }
}
