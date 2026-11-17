<?php

namespace App\Http\Requests;

use App\Models\VisaRequirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminVisaIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect(['passport', 'destination'])
            ->filter(fn (string $field): bool => is_string($this->input($field)) && $this->input($field) !== '')
            ->mapWithKeys(fn (string $field): array => [$field => strtoupper(trim($this->input($field)))])
            ->all());
    }

    public function rules(): array
    {
        return [
            'passport' => ['nullable', 'string', 'size:2', 'exists:countries,code'],
            'destination' => ['nullable', 'string', 'size:2', 'exists:countries,code'],
            'source' => ['nullable', Rule::in(VisaRequirement::SOURCES)],
            'stale' => ['nullable', 'boolean'],
        ];
    }
}
