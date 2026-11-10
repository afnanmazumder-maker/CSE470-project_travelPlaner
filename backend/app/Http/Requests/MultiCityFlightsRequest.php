<?php

namespace App\Http\Requests;

use App\Services\FlightSearchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** R9: a multi-city search — 2 to 6 legs in date order. */
class MultiCityFlightsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'legs' => ['required', 'array', 'min:2', 'max:6'],
            'legs.*.origin' => ['required', 'string', 'size:3'],
            'legs.*.destination' => ['required', 'string', 'size:3', 'different:legs.*.origin'],
            'legs.*.date' => ['required', 'date'],
            'passengers' => ['required', 'integer', 'min:1', 'max:9'],
            'cabin_class' => ['required', Rule::in(config('flights.cabin_classes'))],
            'sort' => ['nullable', Rule::in(FlightSearchService::SORTS)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $legs = $this->input('legs', []);
                for ($i = 1; $i < count($legs); $i++) {
                    if (isset($legs[$i]['date'], $legs[$i - 1]['date'])
                        && Carbon::parse($legs[$i]['date'])->lt(Carbon::parse($legs[$i - 1]['date']))) {
                        $validator->errors()->add("legs.{$i}.date", 'Each leg must depart on or after the previous leg.');
                    }
                }
            },
        ];
    }
}
