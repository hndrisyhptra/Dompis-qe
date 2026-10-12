<?php

namespace App\Http\Requests;

use App\Enums\LopBudgetType;
use App\Enums\LopSegment;
use App\Enums\ProgramType;
use App\Models\Branch;
use App\Models\ServiceArea;
use App\Services\LopVisibilityService;
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
        $lop = $this->route('qe_lop');
        $lopId = $lop?->id_qe_lops;

        // Nomor tiket asli (INC…) tidak boleh diubah; hanya nomor manual (INP…).
        // prepareForValidation() sudah memaksa balik nilai lama untuk tiket asli,
        // jadi rule regex hanya relevan saat LOP memang bernomor manual.
        $incidentIsManual = $lop === null || $lop->usesManualIncident();

        $rules = [
            'incident' => [
                'required', 'string', 'max:100',
                ...($incidentIsManual ? ['regex:/^INP\d+$/'] : []),
                Rule::unique('qe_lops', 'incident')->ignore($lopId, 'id_qe_lops'),
            ],
            'nama_lop' => ['nullable', 'string', 'max:255'],
            'program_type' => ['required', Rule::enum(ProgramType::class)],
            'sto' => ['required', 'string', 'max:20', Rule::exists('service_areas', 'workzone')],
            'branch' => ['required', 'string', 'max:100', Rule::exists('branches', 'name')],
            'area' => ['required', 'string', 'max:10', Rule::exists('areas', 'code')],
            'segment' => ['required', 'array', 'min:1', 'max:'.LopSegment::MAX_SELECTIONS],
            'segment.*' => ['required', 'string', Rule::enum(LopSegment::class)],
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

        return $rules;
    }

    public function messages(): array
    {
        return [
            'incident.regex' => 'Nomor tiket manual harus memakai format INP diikuti angka (contoh: INP3102092601).',
            'segment.max' => 'Maksimal 5 segmen untuk setiap program.',
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

            $branch = Branch::with('regionRef.area')->where('name', $branchName)->first();
            if ($branch && $branch->regionRef && $branch->regionRef->area) {
                if ((string) $branch->regionRef->area->code !== (string) $area) {
                    $v->errors()->add('branch', 'Branch tidak termasuk dalam Area terpilih.');
                }
            }

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
        $lop = $this->route('qe_lop');

        // LOP bernomor tiket asli: abaikan input user, kunci ke nilai lama.
        $incident = $lop !== null && ! $lop->usesManualIncident()
            ? $lop->incident
            : mb_strtoupper(trim((string) $this->input('incident')));

        $program = $this->input('program_type');
        $segmentNormalized = LopSegment::normalizeInput($this->input('segment'));

        $this->merge([
            'incident' => $incident,
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
