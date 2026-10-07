<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequestLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadEvidence', $this->route('qe_lop')) ?? false;
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:12'],
            'files.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Pilih minimal satu Surat Permintaan.',
            'files.max' => 'Maksimal 12 file dalam satu kali upload.',
            'files.*.mimes' => 'Surat Permintaan harus berupa PDF, JPG, JPEG, PNG, atau WEBP.',
            'files.*.max' => 'Ukuran setiap file maksimal 10 MB.',
        ];
    }
}
