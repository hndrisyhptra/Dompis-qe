<?php

namespace App\Http\Requests;

use App\Enums\LopSegment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketSegmentMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-master-data') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('ticket_segment_map')?->id_ticket_segment_map;

        return [
            'source_value' => [
                'required', 'string', 'max:100',
                Rule::unique('ticket_segment_maps', 'source_value')->ignore($id, 'id_ticket_segment_map'),
            ],
            'segment' => ['required', Rule::enum(LopSegment::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source_value' => mb_strtoupper(trim((string) $this->input('source_value'))),
        ]);
    }
}
