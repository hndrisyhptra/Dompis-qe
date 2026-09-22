<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    public function rules(): array
    {
        return [
            'workzone' => ['required', 'string', 'max:20', 'unique:service_areas,workzone'],
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
