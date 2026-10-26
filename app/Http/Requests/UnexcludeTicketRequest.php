<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnexcludeTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-incident-monitoring') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['incident' => $this->route('incident')]);
    }

    public function rules(): array
    {
        return [
            'incident' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'incident.required' => 'Incident yang akan dibatalkan tidak tersedia.',
            'incident.max' => 'Nomor incident maksimal 100 karakter.',
        ];
    }
}
