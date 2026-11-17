<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePassportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('passport_country'))) {
            $this->merge(['passport_country' => strtoupper(trim($this->input('passport_country')))]);
        }
    }

    public function rules(): array
    {
        return [
            'passport_country' => ['required', 'string', 'size:2', 'exists:countries,code'],
        ];
    }
}
