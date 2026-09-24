<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBoqRequest extends FormRequest
{
    public function authorize(): bool
    {
        $boq = $this->route('boq');

        return $boq !== null && ($this->user()?->can('update', $boq->lop) ?? false);
    }

    public function rules(): array
    {
        return [
            'package_id' => ['nullable', 'integer', 'exists:packages,id_package'],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.designator_id' => ['required', 'integer', 'distinct', 'exists:designators,id_designator'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.qty.integer' => 'QTY BOQ harus berupa angka bulat tanpa desimal.',
            'items.*.qty.min' => 'QTY BOQ minimal 1.',
        ];
    }
}
