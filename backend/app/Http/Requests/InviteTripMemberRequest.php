<?php

namespace App\Http\Requests;

use App\Models\TripMember;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** R16: invite by email, or leave the email out to get a one-time invite link. */
class InviteTripMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'role' => ['required', Rule::in(TripMember::ROLES)],
        ];
    }
}
