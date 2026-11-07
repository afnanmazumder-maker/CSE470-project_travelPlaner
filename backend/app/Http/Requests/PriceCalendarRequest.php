<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** R9: flexible-date price calendar for one month. */
class PriceCalendarRequest extends FormRequest
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
            'cabin_class' => ['required', Rule::in(config('flights.cabin_classes'))],
            'month' => ['required', 'date_format:Y-m'],
            'passengers' => ['nullable', 'integer', 'min:1', 'max:9'],
        ];
    }
}
