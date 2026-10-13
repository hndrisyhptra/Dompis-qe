<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReservationBoqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('qe_lop')) ?? false;
    }

    public function rules(): array
    {
        return [
            'reservation_version' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
            'confirmed' => ['required', 'accepted'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.designator_id' => ['required', 'integer', 'distinct', 'exists:designators,id_designator'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmed.required' => 'Konfirmasi perubahan melalui tombol Ya, simpan.',
            'confirmed.accepted' => 'Perubahan harus dikonfirmasi sebelum disimpan.',
            'items.*.qty.integer' => 'Qty reservasi harus berupa angka bulat tanpa desimal.',
            'items.min' => 'Pertahankan minimal satu item reservasi.',
            'items.max' => 'Maksimal 100 item material dalam reservasi.',
            'reservation_version.required' => 'Halaman belum memiliki versi reservasi. Muat ulang Data BOQ.',
        ];
    }
}
