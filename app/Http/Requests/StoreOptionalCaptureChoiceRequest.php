<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOptionalCaptureChoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('setOptionalCaptureChoice', $this->route('qe_lop')) ?? false;
    }

    public function rules(): array
    {
        return ['category' => ['required', Rule::in(['insera', 'slot_port'])], 'unavailable' => ['required', 'boolean']];
    }
}
