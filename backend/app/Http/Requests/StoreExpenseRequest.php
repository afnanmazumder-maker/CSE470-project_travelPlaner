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
            // R8: "other" records spending outside the planned categories (Miscellaneous / Unplanned).
            'category' => ['required', 'in:'.implode(',', [...BudgetService::CATEGORIES, 'other'])],
            'description' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'spent_on' => ['nullable', 'date'],
            // R8: the currency actually paid; converted to USD with that day's rate.
            'currency' => ['nullable', 'string', 'in:'.implode(',', config('currency.currencies'))],
        ];
    }
}
