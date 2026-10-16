<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class TicketCoverageDownloadRequest extends TicketCoverageRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'download_usage' => ['nullable', Rule::in(['all', 'without_lop', 'with_lop'])]];
    }
}
