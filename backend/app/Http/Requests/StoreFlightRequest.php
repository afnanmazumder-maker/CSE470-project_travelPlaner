<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** R9 (admin): create or edit a flight fare. */
class StoreFlightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $airports = array_keys(config('flights.airports'));

        return [
            'airline' => ['required', 'string', 'max:80'],
            'airline_code' => ['required', 'string', 'between:2,3'],
            'flight_number' => ['required', 'string', 'max:10'],
            'origin' => ['required', Rule::in($airports)],
            'destination' => ['required', Rule::in($airports), 'different:origin'],
            'depart_at' => ['required', 'date'],
            'arrive_at' => ['required', 'date', 'after:depart_at'],
            // Entered separately because departure and arrival are local times in different zones.
            'duration_minutes' => ['required', 'integer', 'min:20', 'max:2880'],
            'stops' => ['required', 'integer', 'min:0', 'max:3'],
            'stopover' => ['nullable', 'string', 'max:20', 'required_unless:stops,0'],
            'cabin_class' => ['required', Rule::in(config('flights.cabin_classes'))],
            'price' => ['required', 'numeric', 'min:1', 'max:100000'],
            'seats_available' => ['required', 'integer', 'min:0', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'origin' => strtoupper((string) $this->input('origin')),
            'destination' => strtoupper((string) $this->input('destination')),
            'airline_code' => strtoupper((string) $this->input('airline_code')),
        ]);
    }
}
