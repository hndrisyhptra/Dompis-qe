<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaterialReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadEvidence', $this->route('qe_lop')) ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.designator_id' => ['required', 'integer', 'distinct', 'exists:designators,id_designator'],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:999999999999.999'],
        ];
    }
}
