<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckVisaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect(['passport', 'destination', 'transit'])
            ->filter(fn (string $field): bool => is_string($this->input($field)))
            ->mapWithKeys(fn (string $field): array => [$field => strtoupper(trim($this->input($field)))])
            ->all());
    }

    public function rules(): array
    {
        return [
            'passport' => ['required', 'string', 'size:2', 'exists:countries,code'],
            'destination' => ['required', 'string', 'size:2', 'exists:countries,code'],
            'transit' => ['nullable', 'string', 'size:2', 'exists:countries,code', 'different:destination'],
        ];
    }

    public function messages(): array
    {
        return [
            'passport.exists' => 'Choose a passport country from the list.',
            'destination.exists' => 'Choose a destination country from the list.',
            'transit.different' => 'The stopover must be a different country from the destination.',
        ];
    }
}
