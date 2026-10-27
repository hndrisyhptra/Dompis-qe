<?php

namespace App\Http\Requests;

use App\Enums\LopStatus;
use App\Enums\UserRole;
use App\Models\QeLop;
use Illuminate\Foundation\Http\FormRequest;

class StoreInseraEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lop = $this->route('qe_lop');

        return $lop instanceof QeLop
            && $this->user()?->hasRole(...UserRole::adminLevel())
            && $lop->status_lop !== LopStatus::COMPLETED
            && $this->user()->can('uploadEvidence', $lop);
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:12'],
            'files.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Pilih minimal satu screenshot Capture Ticket Insera.',
            'files.max' => 'Maksimal 12 file dalam satu kali upload.',
            'files.*.mimes' => 'Capture Ticket Insera harus berupa gambar JPG, PNG, atau WEBP.',
            'files.*.max' => 'Ukuran setiap file maksimal 10 MB.',
        ];
    }
}
