<?php

namespace App\Http\Requests;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTechnicianEvidenceRequest extends FormRequest
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
            'files' => ['required', 'array', 'min:1', 'max:12'],
            'files.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'note' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $category = EvidenceCategory::tryFrom((string) $this->input('category'));

            if (in_array($category, [EvidenceCategory::BEFORE, EvidenceCategory::PROGRESS, EvidenceCategory::AFTER], true)
                && ! $this->filled('designator_id')) {
                $validator->errors()->add('designator_id', 'Pilih item designator untuk evidence ini.');
            }
        }];
    }
}
