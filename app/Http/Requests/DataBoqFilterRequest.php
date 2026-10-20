<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filter daftar Data BOQ. Pencarian `q` sengaja tidak divalidasi di sini
 * karena aturannya tidak berubah; yang ditambahkan hanya rentang tanggal
 * laporan incident.
 */
class DataBoqFilterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.date_format' => 'Tanggal mulai harus berupa tanggal yang valid.',
            'date_to.date_format' => 'Tanggal akhir harus berupa tanggal yang valid.',
            'date_to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
        ];
    }

    public function attributes(): array
    {
        return ['date_from' => 'tanggal mulai', 'date_to' => 'tanggal akhir'];
    }
}
