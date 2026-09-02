<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('branch')?->id_branch;

        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('branches', 'code')->ignore($id, 'id_branch'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'region_id' => ['required', 'integer', 'exists:regions,id_region'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper(trim((string) $this->input('code'))),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
