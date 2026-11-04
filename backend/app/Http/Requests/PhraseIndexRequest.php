<?php

namespace App\Http\Requests;

use App\Models\Phrase;
use Illuminate\Foundation\Http\FormRequest;

class PhraseIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'language' => ['required', 'string', 'exists:phrases,language_code'],
            'category' => ['nullable', 'in:'.implode(',', Phrase::CATEGORIES)],
            'q' => ['nullable', 'string', 'max:80'],
        ];
    }
}
