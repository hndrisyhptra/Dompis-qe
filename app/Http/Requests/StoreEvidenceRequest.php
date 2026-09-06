<?php

namespace App\Http\Requests;

use App\Enums\EvidenceStep;
use App\Enums\EvidenceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadEvidence', $this->route('qe_lop')) ?? false;
    }

    public function rules(): array
    {
        return [
            'step' => ['required', Rule::enum(EvidenceStep::class)],
            'type' => ['required', Rule::enum(EvidenceType::class)],
            'designator_id' => ['nullable', 'integer', 'exists:designators,id_designator'],
            // Validasi extension + mime type + limit ukuran sesuai
            // CLAUDE.md Security Rules - file upload wajib divalidasi.
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
