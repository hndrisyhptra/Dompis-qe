<?php

namespace App\Http\Requests;

use App\Enums\ExcludeReason;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExcludeTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::SUPER_ADMIN, UserRole::ADMIN) ?? false;
    }

    public function rules(): array
    {
        return [
            'incident' => ['required', 'string', 'max:100'],
            'reason' => ['required', Rule::enum(ExcludeReason::class)],
        ];
    }
}
