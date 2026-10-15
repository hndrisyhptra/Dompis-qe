<?php

namespace App\Http\Requests;

use App\Enums\LopStatus;
use App\Enums\ProgramType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncidentMonitoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-incident-monitoring') ?? false;
    }

    public function rules(): array
    {
        return [
            'region' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'integer', 'min:1'],
            'service_area' => ['nullable', 'integer', 'min:1'],
            'program' => ['nullable', Rule::enum(ProgramType::class)],
            'status' => ['nullable', Rule::enum(LopStatus::class)],
            'ticket_type' => ['nullable', 'string', 'max:500'],
            'q' => ['nullable', 'string', 'max:100'],
            'missing_ihld' => ['nullable', 'boolean'],
            'retry' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
