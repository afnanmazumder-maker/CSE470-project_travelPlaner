<?php

namespace App\Http\Requests;

use App\Models\Flight;
use App\Services\FlightSearchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** R9: one-way flight search with sorting and filters. */
class SearchFlightsRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'passengers' => ['required', 'integer', 'min:1', 'max:9'],
            'cabin_class' => ['required', Rule::in(config('flights.cabin_classes'))],
            'sort' => ['nullable', Rule::in(FlightSearchService::SORTS)],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'max_stops' => ['nullable', 'integer', 'min:0', 'max:3'],
            'max_duration_hours' => ['nullable', 'numeric', 'min:1', 'max:48'],
            'depart_windows' => ['nullable', 'array'],
            'depart_windows.*' => [Rule::in(array_keys(Flight::DEPART_WINDOWS))],
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
