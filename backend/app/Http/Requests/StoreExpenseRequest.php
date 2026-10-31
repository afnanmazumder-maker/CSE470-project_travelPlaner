<?php

namespace App\Http\Requests;

use App\Services\BudgetService;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'in:'.implode(',', BudgetService::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'spent_on' => ['nullable', 'date'],
        ];
    }
}
