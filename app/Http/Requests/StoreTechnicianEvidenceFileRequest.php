<?php

namespace App\Http\Requests;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Upload evidence teknisi SATU file (jalur async per-file dengan progress).
 * Aturan sama dengan StoreTechnicianEvidenceRequest tapi `file` tunggal +
 * `thumb` opsional (webp kecil yang dibuat di browser).
 */
class StoreTechnicianEvidenceFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadEvidence', $this->route('qe_lop')) ?? false;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(EvidenceCategory::class)],
            'type' => ['required', Rule::enum(EvidenceType::class)],
            'designator_id' => ['nullable', 'integer', 'exists:designators,id_designator'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'thumb' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'note' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $category = EvidenceCategory::tryFrom((string) $this->input('category'));

            if (in_array($category, [EvidenceCategory::BEFORE, EvidenceCategory::AFTER], true)
                && ! $this->filled('designator_id')) {
                $validator->errors()->add('designator_id', 'Pilih item designator untuk evidence ini.');
            }
        }];
    }
}
