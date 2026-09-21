<?php

namespace App\Http\Requests;

use App\Enums\LopBudgetType;
use App\Enums\LopSegment;
use App\Enums\ProgramType;
use App\Models\QeLop;
use App\Support\DatekRules;
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
        $isRelok = $this->input('program_type') === ProgramType::RELOK_UTILITAS->value;

        $segmentRules = $isRelok
            ? ['required', 'array', 'min:1', 'max:3']
            : ['required', Rule::enum(LopSegment::class)];

        $rules = [
            'incident' => ['required', 'string', 'max:100', 'unique:qe_lops,incident'],
            'nama_lop' => ['nullable', 'string', 'max:255'],
            'program_type' => ['required', Rule::enum(ProgramType::class)],
            'sto' => ['required', 'string', 'max:100'],
            'branch' => ['required', 'string', 'max:100', Rule::exists('branches', 'name')],
            'area' => ['required', 'string', 'max:20'],
            'segment' => $segmentRules,
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

        if ($isRelok) {
            $rules['segment.*'] = ['required', 'string', Rule::enum(LopSegment::class)];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'segment.max' => 'Maksimal 3 segmen untuk program Relok Utilitas.',
            'segment.min' => 'Pilih minimal 1 segmen.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $program = $this->input('program_type');
        $segmentInput = $this->input('segment');

        if ($program === ProgramType::RELOK_UTILITAS->value) {
            // Normalisasi ke array string lowercase, preserve order, unique
            if (is_string($segmentInput)) {
                $segmentInput = $segmentInput !== '' ? [trim($segmentInput)] : [];
            } elseif (! is_array($segmentInput)) {
                $segmentInput = $segmentInput !== null ? [(string) $segmentInput] : [];
            }

            $normalized = [];
            $seen = [];
            foreach ((array) $segmentInput as $v) {
                $val = strtolower(trim((string) $v));
                if ($val === '' || isset($seen[$val])) {
                    continue;
                }
                $seen[$val] = true;
                $normalized[] = $val;
            }
            $segmentNormalized = $normalized;
        } else {
            // Program lain: single string
            if (is_array($segmentInput)) {
                $segmentInput = $segmentInput[0] ?? null;
            }
            $segmentNormalized = $segmentInput !== null && $segmentInput !== ''
                ? strtolower(trim((string) $segmentInput))
                : $segmentInput;
        }

        $this->merge([
            'incident' => mb_strtoupper(trim((string) $this->input('incident'))),
            'sto' => mb_strtoupper(trim((string) $this->input('sto'))),
            'segment' => $segmentNormalized,
            'ihld_id' => filled($this->input('ihld_id'))
                ? trim((string) $this->input('ihld_id'))
                : null,
            'datek' => DatekRules::decode($this->input('datek')),
        ]);
    }
}
