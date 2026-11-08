<?php

namespace App\Http\Requests;

use App\Enums\ExcludeReason;
use Illuminate\Validation\Rule;

class TicketExcludeMonitoringRequest extends TicketCoverageRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'matrix_region' => ['nullable', 'string', 'max:255'],
            'detail_kind' => ['nullable', Rule::in(['gamas', 'preman', 'infracare'])],
            'exclude_state' => ['nullable', Rule::in(['all', 'included', 'excluded', 'not_excluded'])],
            'reason' => ['nullable', Rule::in([...array_column(ExcludeReason::cases(), 'value'), 'other'])],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
        ];
    }
}
