<?php

namespace App\Http\Requests;

use App\Enums\LopStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionLopStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transitionStatus', $this->route('qe_lop')) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(LopStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
