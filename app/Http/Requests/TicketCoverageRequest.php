<?php

namespace App\Http\Requests;

use App\Support\TicketReportedDate;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TicketCoverageRequest extends IncidentMonitoringRequest
{
    public function rules(): array
    {
        $reportedFrom = TicketReportedDate::floor();

        return [
            'region' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'integer', 'min:1'],
            'service_area' => ['nullable', 'integer', 'min:1'],
            'kind' => ['nullable', Rule::in(['gamas', 'infracare', 'preman'])],
            'usage' => ['nullable', Rule::in(['all', 'without_lop', 'with_lop', 'excluded'])],
            'date_from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$reportedFrom],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$reportedFrom, ...($this->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        $reportedFromLabel = Carbon::createFromFormat('!Y-m-d', TicketReportedDate::floor())->format('d/m/Y');

        return [
            'date_from.date_format' => 'Tanggal mulai harus berupa tanggal yang valid.',
            'date_from.after_or_equal' => 'Tanggal mulai minimal '.$reportedFromLabel.'.',
            'date_to.date_format' => 'Tanggal akhir harus berupa tanggal yang valid.',
            'date_to.after_or_equal' => 'Tanggal akhir minimal '.$reportedFromLabel.' dan tidak boleh sebelum tanggal mulai.',
        ];
    }
}
