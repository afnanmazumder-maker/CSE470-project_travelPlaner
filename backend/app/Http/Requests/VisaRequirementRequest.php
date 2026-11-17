<?php

namespace App\Http\Requests;

use App\Models\VisaRequirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * R6: admin create and update of a visa rule. Admin access is checked by the EnsureUserIsAdmin middleware.
 */
class VisaRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect(['passport_code', 'destination_code'])
            ->filter(fn (string $field): bool => is_string($this->input($field)))
            ->mapWithKeys(fn (string $field): array => [$field => strtoupper(trim($this->input($field)))])
            ->all());
    }

    public function rules(): array
    {
        $current = $this->route('visa_requirement');

        return [
            'passport_code' => ['required', 'string', 'size:2', 'exists:countries,code'],
            'destination_code' => [
                'required', 'string', 'size:2', 'exists:countries,code', 'different:passport_code',
                Rule::unique('visa_requirements')
                    ->where('passport_code', $this->input('passport_code'))
                    ->ignore($current?->id),
            ],
            'requirement' => ['required', Rule::in(array_keys(VisaRequirement::REQUIREMENTS))],
            'max_stay_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'conditions' => ['nullable', 'string', 'max:2000'],
            'required_documents' => ['nullable', 'array', 'max:20'],
            'required_documents.*' => ['string', 'max:300'],
            'passport_validity_months' => ['nullable', 'integer', 'min:0', 'max:24'],
            'fee_note' => ['nullable', 'string', 'max:200'],
            'processing_note' => ['nullable', 'string', 'max:200'],
            'transit_notes' => ['nullable', 'string', 'max:1000'],
            'official_url' => ['nullable', 'url:https,http', 'max:500'],
            'last_verified_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'destination_code.unique' => 'A rule for this passport and destination already exists. Edit that one instead.',
            'destination_code.different' => 'The destination must be different from the passport country.',
        ];
    }
}
