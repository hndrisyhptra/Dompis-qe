<?php

namespace App\Http\Requests;

use App\Enums\LopBudgetType;
use App\Enums\LopSegment;
use App\Enums\ProgramType;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\ServiceArea;
use App\Services\LopVisibilityService;
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
        $isRelok = ProgramType::tryFrom((string) $this->input('program_type'))?->supportsMultipleSegments() ?? false;

        $segmentRules = $isRelok
            ? ['required', 'array', 'min:1', 'max:3']
            : ['required', Rule::enum(LopSegment::class)];

        $rules = [
            'incident' => ['required', 'string', 'max:100', 'unique:qe_lops,incident'],
            'nama_lop' => ['nullable', 'string', 'max:255'],
            'program_type' => ['required', Rule::enum(ProgramType::class)],
            'sto' => ['required', 'string', 'max:20', Rule::exists('service_areas', 'workzone')],
            'branch' => ['required', 'string', 'max:100', Rule::exists('branches', 'name')],
            'area' => ['required', 'string', 'max:10', Rule::exists('areas', 'code')],
            'segment' => $segmentRules,
            'budget_type' => [
                'nullable',
                'required',
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
            'segment.max' => 'Maksimal 3 segmen untuk program Relok Utilitas atau Relok Tiang.',
            'segment.min' => 'Pilih minimal 1 segmen.',
            'sto.exists' => 'STO tidak ditemukan di Master Service Area.',
            'branch.exists' => 'Branch tidak ditemukan.',
            'area.exists' => 'Area tidak ditemukan.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $area = trim((string) $this->input('area'));
            $branchName = trim((string) $this->input('branch'));
            $sto = mb_strtoupper(trim((string) $this->input('sto')));

            if ($area === '' || $branchName === '' || $sto === '') {
                return;
            }

            // Branch harus masuk ke Area terpilih
            $branch = Branch::with('regionRef.area')->where('name', $branchName)->first();
            if ($branch && $branch->regionRef && $branch->regionRef->area) {
                if ((string) $branch->regionRef->area->code !== (string) $area) {
                    $v->errors()->add('branch', 'Branch tidak termasuk dalam Area terpilih.');
                }
            }

            // STO harus masuk ke Branch terpilih
            $sa = ServiceArea::where('workzone', $sto)->first();
            if ($sa && $sa->branch && $sa->branch->name !== $branchName) {
                $v->errors()->add('sto', 'STO tidak termasuk dalam Branch terpilih.');
            }

            if ($branch && $sa && ! app(LopVisibilityService::class)->canUseLocation($this->user(), $branch->id_branch, $sa->id_service_area)) {
                $v->errors()->add('sto', 'Service Area berada di luar scope admin Anda.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $program = $this->input('program_type');
        $segmentInput = $this->input('segment');

        if (ProgramType::tryFrom((string) $program)?->supportsMultipleSegments()) {
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
            'branch' => trim((string) $this->input('branch')),
            'area' => trim((string) $this->input('area')),
            'segment' => $segmentNormalized,
            'budget_type' => ProgramType::tryFrom((string) $program)?->budgetType()->value,
            'ihld_id' => filled($this->input('ihld_id'))
                ? trim((string) $this->input('ihld_id'))
                : null,
            'datek' => DatekRules::decode($this->input('datek')),
        ]);
    }
}
