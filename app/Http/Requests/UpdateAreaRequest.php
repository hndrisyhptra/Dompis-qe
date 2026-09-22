<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    public function rules(): array
    {
        $area = $this->route('area');

        return [
            'code' => ['required', 'string', 'max:10', Rule::unique('areas', 'code')->ignore($area?->id_area, 'id_area')],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
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
