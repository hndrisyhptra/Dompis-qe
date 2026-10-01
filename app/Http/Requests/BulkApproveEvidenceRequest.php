<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkApproveEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reviewEvidence', $this->route('qe_lop')) ?? false;
    }

    public function rules(): array
    {
        return [
            'evidence_ids' => ['required', 'array', 'min:1', 'max:200'],
            'evidence_ids.*' => ['required', 'integer', 'distinct', 'exists:qe_evidences,id_evidence'],
        ];
    }

    public function messages(): array
    {
        return [
            'evidence_ids.required' => 'Pilih minimal satu evidence yang akan disetujui.',
            'evidence_ids.array' => 'Daftar evidence tidak valid.',
            'evidence_ids.max' => 'Maksimal 200 evidence dapat disetujui dalam satu proses.',
            'evidence_ids.*.distinct' => 'Terdapat evidence yang dipilih lebih dari satu kali.',
            'evidence_ids.*.exists' => 'Salah satu evidence tidak ditemukan.',
        ];
    }
}
