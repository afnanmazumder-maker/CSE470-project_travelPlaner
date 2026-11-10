<?php

namespace App\Http\Requests;

use App\Models\Accommodation;
use App\Services\StaySearchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** R9: stay search; north/south/east/west come from the visible map for map search. */
class SearchStaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bounds = ['nullable', 'numeric', 'required_with:north,south,east,west'];

        return [
            'destination_id' => ['nullable', 'integer', 'exists:destinations,id', 'required_without:north'],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date', 'after:check_in', 'required_with:check_in'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'min_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'max_distance_km' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => [Rule::in(Accommodation::AMENITIES)],
            'property_types' => ['nullable', 'array'],
            'property_types.*' => [Rule::in(Accommodation::PROPERTY_TYPES)],
            'north' => [...$bounds, 'between:-90,90'],
            'south' => [...$bounds, 'between:-90,90', 'lt:north'],
            'east' => [...$bounds, 'between:-180,180'],
            'west' => [...$bounds, 'between:-180,180', 'lt:east'],
            'sort' => ['nullable', Rule::in(StaySearchService::SORTS)],
        ];
    }
}
