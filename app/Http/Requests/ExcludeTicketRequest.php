<?php

namespace App\Http\Requests;

use App\Enums\ExcludeReason;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExcludeTicketRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('incidents_text')) {
            return;
        }

        $incidents = preg_split('/[\r\n,]+/', (string) $this->input('incidents_text')) ?: [];
        $this->merge(['incidents' => array_values(array_filter(array_map('trim', $incidents), fn ($incident) => $incident !== ''))]);
    }

    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::SUPER_ADMIN, UserRole::ADMIN) ?? false;
    }

    public function rules(): array
    {
        return [
            'incident' => ['required_without:incidents', 'nullable', 'string', 'max:100'],
            'reason' => ['required', Rule::enum(ExcludeReason::class)],
            'incidents' => ['sometimes', 'array', 'min:1', 'max:500'],
            'incidents.*' => ['required', 'string', 'max:100'],
            'incidents_text' => ['sometimes', 'string', 'max:50000'],
        ];
    }
}
