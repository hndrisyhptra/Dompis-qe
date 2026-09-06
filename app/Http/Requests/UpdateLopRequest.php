<?php

namespace App\Http\Requests;

use App\Enums\LopBudgetType;
use App\Enums\LopSegment;
use App\Enums\ProgramType;
use App\Support\DatekRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('qe_lop')) ?? false;
    }

    public function rules(): array
    {
        $lopId = $this->route('qe_lop')?->id_qe_lops;

        return [
            'incident' => [
                'required', 'string', 'max:100',
                Rule::unique('qe_lops', 'incident')->ignore($lopId, 'id_qe_lops'),
            ],
            'nama_lop' => ['nullable', 'string', 'max:255'],
            'program_type' => ['required', Rule::enum(ProgramType::class)],
            'sto' => ['required', 'string', 'max:100'],
            'branch' => ['required', 'string', 'max:100', Rule::exists('branches', 'name')],
            'area' => ['required', 'string', 'max:20'],
            'segment' => ['required', Rule::enum(LopSegment::class)],
            'budget_type' => [
                'nullable',
                'required_if:program_type,'.ProgramType::RELOK_UTILITAS->value,
                Rule::enum(LopBudgetType::class),
            ],
            'job_description' => ['required', 'string', 'max:2000'],
            'ticket_summary' => ['nullable', 'string', 'max:5000'],
            'ihld_id' => ['nullable', 'string', 'max:100'],
            ...DatekRules::rules(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'incident' => mb_strtoupper(trim((string) $this->input('incident'))),
            'sto' => mb_strtoupper(trim((string) $this->input('sto'))),
            'ihld_id' => filled($this->input('ihld_id'))
                ? trim((string) $this->input('ihld_id'))
                : null,
            'datek' => DatekRules::decode($this->input('datek')),
        ]);
    }
}
