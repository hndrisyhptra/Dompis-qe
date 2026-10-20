<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class TicketCoverageRequest extends IncidentMonitoringRequest
{
    public function rules(): array
    {
        return [
            'region' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'integer', 'min:1'],
            'service_area' => ['nullable', 'integer', 'min:1'],
            'kind' => ['nullable', Rule::in(['gamas', 'infracare', 'preman'])],
            'usage' => ['nullable', Rule::in(['all', 'without_lop', 'with_lop', 'excluded'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'q' => ['nullable', 'string', 'max:100'],
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
}
