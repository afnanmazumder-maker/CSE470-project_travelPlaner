<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** R9: save or edit a flight or stay booking record. */
class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(Booking::TYPES)],
            'budget_id' => ['nullable', 'integer', 'exists:budgets,id'],
            'flight_id' => ['nullable', 'integer', 'exists:flights,id', 'prohibited_unless:type,flight'],
            'accommodation_id' => ['nullable', 'integer', 'exists:accommodations,id', 'prohibited_unless:type,stay'],
            'title' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'confirmation_number' => ['nullable', 'string', 'max:40'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'status' => ['required', Rule::in(Booking::STATUSES)],
            'price' => ['nullable', 'numeric', 'min:0'],
            'party_size' => ['nullable', 'integer', 'min:1', 'max:20'],
            'cancellation_deadline' => ['nullable', 'date', 'before_or_equal:start_at'],
            'payment_due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
