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
            'service_area_name' => ['nullable', 'string', 'max:255'],
            'kind' => ['nullable', Rule::in(['gamas', 'infracare', 'preman'])],
            'usage' => ['nullable', Rule::in(['all', 'without_lop', 'with_lop', 'excluded'])],
            'period' => ['nullable', Rule::in(['range', 'month'])],
            'month' => ['exclude_unless:period,month', 'required', 'date_format:Y-m', 'after_or_equal:'.substr($reportedFrom, 0, 7)],
            'date_from' => ['exclude_if:period,month', 'nullable', 'date_format:Y-m-d', 'after_or_equal:'.$reportedFrom],
            'date_to' => ['exclude_if:period,month', 'nullable', 'date_format:Y-m-d', 'after_or_equal:'.$reportedFrom, ...($this->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'q' => ['nullable', 'string', 'max:100'],
            'sto_filter' => ['sometimes', 'array', 'max:100'],
            'sto_filter.*' => ['string', 'max:255'],
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
            'month.required' => 'Pilih bulan laporan tiket.',
            'month.date_format' => 'Bulan laporan harus berupa bulan yang valid.',
            'month.after_or_equal' => 'Bulan laporan minimal '.substr(TicketReportedDate::floor(), 0, 7).'.',
        ];
    }
}
