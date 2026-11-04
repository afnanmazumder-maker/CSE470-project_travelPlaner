<?php

namespace App\Http\Requests;

use App\Models\Notification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** R16: which channel each reminder uses, the daily digest and lead times. */
class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channels' => ['required', 'array'],
            'channels.*.in_app' => ['required', 'boolean'],
            'channels.*.email' => ['required', 'boolean'],
            'daily_digest' => ['required', 'boolean'],
            'digest_time' => ['required', 'date_format:H:i'],
            'checkin_lead_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'deadline_lead_days' => ['required', 'integer', 'min:1', 'max:30'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unknown = array_diff(array_keys($this->input('channels', [])), array_keys(Notification::TYPES));
                if ($unknown) {
                    $validator->errors()->add('channels', 'Unknown notification types: '.implode(', ', $unknown));
                }
            },
        ];
    }
}
