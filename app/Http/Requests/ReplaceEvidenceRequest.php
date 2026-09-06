<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('replace', $this->route('evidence')) ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'thumb' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ];
    }
}
