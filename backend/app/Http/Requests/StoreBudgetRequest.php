<?php

namespace App\Http\Requests;

class StoreBudgetRequest extends EstimateBudgetRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'title' => ['required', 'string', 'max:100'],
            'limit_amount' => ['nullable', 'numeric', 'min:1', 'max:1000000'],
        ]);
    }
}
