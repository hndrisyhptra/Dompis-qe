<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    public function rules(): array
    {
        $sa = $this->route('service_area');

        return [
            'workzone' => ['required', 'string', 'max:20', Rule::unique('service_areas', 'workzone')->ignore($sa?->id_service_area, 'id_service_area')],
            'name' => ['required', 'string', 'max:100'],
            'branch_id' => ['required', 'integer', 'exists:branches,id_branch'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'workzone' => mb_strtoupper(trim((string) $this->input('workzone'))),
            'name' => mb_strtoupper(trim((string) $this->input('name'))),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
