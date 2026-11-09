<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** R8: the trip start date (to match expenses to days) and the alert threshold. */
class UpdateBudgetTrackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date'],
            'alert_threshold_pct' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }
}
