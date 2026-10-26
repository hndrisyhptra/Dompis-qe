<?php

namespace App\Http\Requests;

use App\Enums\ExcludeReason;
use App\Support\TicketReportedDate;
use Illuminate\Validation\Rule;

class TicketExcludeMonitoringRequest extends TicketCoverageRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'period' => ['nullable', Rule::in(['range', 'month'])],
            'month' => ['exclude_unless:period,month', 'required', 'date_format:Y-m', 'after_or_equal:'.substr(TicketReportedDate::floor(), 0, 7)],
            'date_from' => ['exclude_if:period,month', ...parent::rules()['date_from']],
            'date_to' => ['exclude_if:period,month', ...parent::rules()['date_to']],
            'matrix_region' => ['nullable', 'string', 'max:255'],
            'detail_kind' => ['nullable', Rule::in(['gamas', 'preman', 'infracare'])],
            'exclude_state' => ['nullable', Rule::in(['all', 'excluded', 'not_excluded'])],
            'reason' => ['nullable', Rule::in([...array_column(ExcludeReason::cases(), 'value'), 'other'])],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'month.required' => 'Pilih bulan laporan tiket.',
            'month.date_format' => 'Bulan laporan harus berupa bulan yang valid.',
            'month.after_or_equal' => 'Bulan laporan minimal '.substr(TicketReportedDate::floor(), 0, 7).'.',
        ];
    }
}
