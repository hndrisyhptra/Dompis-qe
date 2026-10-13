<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetBoqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('qe_lop')) ?? false;
    }

    public function rules(): array
    {
        return ['confirmed' => ['required', 'accepted']];
    }

    public function messages(): array
    {
        return ['confirmed.accepted' => 'Konfirmasi reset LOP terlebih dahulu.', 'confirmed.required' => 'Konfirmasi reset LOP terlebih dahulu.'];
    }
}
