<?php

namespace App\Http\Requests;

use App\Enums\LopBudgetType;
use App\Enums\LopSegment;
use App\Enums\WbsType;
use App\Models\QeLop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', QeLop::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'incident' => ['required', 'string', 'max:100', 'unique:qe_lops,incident'],
            'nama_lop' => ['nullable', 'string', 'max:255'],
            'wbs_type' => ['required', Rule::enum(WbsType::class)],
            'sto' => ['required', 'string', 'max:100'],
            'branch' => ['required', 'string', 'max:100', Rule::exists('branches', 'name')],
            'area' => ['required', 'string', 'max:20'],
            'segment' => ['required', Rule::enum(LopSegment::class)],
            'budget_type' => [
                'nullable',
                'required_if:wbs_type,'.WbsType::RELOK_UTILITAS->value,
                Rule::enum(LopBudgetType::class),
            ],
            'job_description' => ['required', 'string', 'max:2000'],
            'ihld_id' => ['nullable', 'string', 'max:100'],
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
        ]);
    }
}
