<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteLopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->route('qe_lop')) ?? false;
    }

    public function rules(): array
    {
        return [
            'incident_confirmation' => ['required', 'string', Rule::in([$this->route('qe_lop')->incident])],
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:1000'],
            'confirmed' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'incident_confirmation.required' => 'Ketik Incident LOP untuk verifikasi penghapusan.',
            'incident_confirmation.in' => 'Incident verifikasi tidak sesuai dengan LOP yang dipilih.',
            'cancellation_reason.required' => 'Tuliskan alasan LOP dibatalkan.',
            'cancellation_reason.min' => 'Alasan pembatalan minimal 5 karakter.',
            'confirmed.accepted' => 'Konfirmasi bahwa LOP ini dibatalkan.',
        ];
    }
}
