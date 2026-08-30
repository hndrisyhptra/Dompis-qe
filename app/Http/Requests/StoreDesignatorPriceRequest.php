<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDesignatorPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    public function rules(): array
    {
        return [
            'designator_id' => [
                'required', 'integer', 'exists:designators,id_designator',
                Rule::unique('designator_package_prices', 'designator_id')
                    ->where(fn ($query) => $query->where('package_id', $this->input('package_id'))),
            ],
            'package_id' => ['required', 'integer', 'exists:packages,id_package'],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'designator_id.unique' => 'Kombinasi designator dan package ini sudah punya harga (KHS).',
        ];
    }
}
