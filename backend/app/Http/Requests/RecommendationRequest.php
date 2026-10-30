<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'destination_id' => ['nullable', 'integer', 'exists:destinations,id'],
            'limit' => ['nullable', 'integer', 'between:1,50'],
        ];
    }
}