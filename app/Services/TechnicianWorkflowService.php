<?php

namespace App\Services;

<<<<<<< HEAD
=======
use App\Enums\DesignatorType;
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
use App\Enums\EvidenceCategory;
use App\Enums\EvidenceStatus;
use App\Enums\EvidenceStep;
use App\Enums\LopStatus;
use App\Models\Designator;
use App\Models\QeLop;
use App\Models\QeMaterialReservation;
use App\Models\QeSurvey;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TechnicianWorkflowService
{
    public function __construct(
        private readonly LopService $lopService,
        private readonly EvidenceService $evidenceService,
    ) {}

    public function pickup(QeLop $lop, User $technician): void
    {
        $this->assertActiveAssignment($lop, $technician);

        if ($lop->status_lop !== LopStatus::ASSIGNED) {
            throw ValidationException::withMessages(['workflow' => 'Project ini tidak dapat di-pickup pada status sekarang.']);
        }

        $this->lopService->transitionStatus($lop, LopStatus::PICKED_UP, $technician, 'Project di-pickup teknisi');
    }

    public function resumeRejected(QeLop $lop, User $technician): void
    {
        $this->assertActiveAssignment($lop, $technician);

        if ($lop->status_lop !== LopStatus::REJECTED) {
            throw ValidationException::withMessages(['workflow' => 'Project ini tidak sedang ditolak.']);
        }

        $this->lopService->transitionStatus($lop, LopStatus::PROGRESS, $technician, 'Perbaikan evidence dimulai');
    }

    public function saveMaterials(QeLop $lop, User $technician, array $items): QeMaterialReservation
    {
        $this->assertActiveAssignment($lop, $technician);

        $ids = collect($items)->pluck('designator_id');
        $materialCount = Designator::query()
            ->whereIn('id_designator', $ids)
<<<<<<< HEAD
            ->whereRelation('type', 'code', 'MATERIAL')
=======
            ->where('type', DesignatorType::MATERIAL->value)
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
            ->count();

        if ($materialCount !== $ids->unique()->count()) {
            throw ValidationException::withMessages(['items' => 'Reservasi hanya dapat menggunakan designator bertipe material.']);
        }

        $reservation = DB::transaction(function () use ($lop, $technician, $items) {
            $reservation = QeMaterialReservation::updateOrCreate(
                ['qe_lop_id' => $lop->id_qe_lops],
                ['technician_id' => $technician->id_user, 'status' => 'draft']
            );

            $reservation->items()->delete();
            $reservation->items()->createMany(collect($items)->map(fn (array $item) => [
                'designator_id' => $item['designator_id'],
                'qty' => $item['qty'],
            ])->all());

            return $reservation->load('items.designator');
        });

        if ($lop->status_lop === LopStatus::PICKED_UP) {
            $this->lopService->transitionStatus($lop->fresh(), LopStatus::SURVEY, $technician, 'Reservasi material disiapkan');
        }

        return $reservation;
    }

    public function saveLocation(QeLop $lop, User $technician, array $data): QeSurvey
    {
        $this->assertActiveAssignment($lop, $technician);

        return QeSurvey::updateOrCreate(
            ['qe_lop_id' => $lop->id_qe_lops],
            [
                ...$data,
                'captured_by' => $technician->id_user,
                'captured_at' => now(),
            ]
        );
    }

    public function uploadEvidence(QeLop $lop, User $technician, array $data, array $files): array
    {
        $this->assertActiveAssignment($lop, $technician);
        $category = EvidenceCategory::from($data['category']);

        if (in_array($category, [EvidenceCategory::BEFORE, EvidenceCategory::AFTER], true)) {
            $isReserved = $lop->materialReservation?->items()
                ->where('designator_id', $data['designator_id'])->exists() ?? false;

            if (! $isReserved) {
                throw ValidationException::withMessages(['designator_id' => 'Designator tidak termasuk reservasi material project ini.']);
            }
        } else {
            $data['designator_id'] = null;
        }

        $data['step'] = match ($category) {
            EvidenceCategory::PRE => EvidenceStep::SURVEY->value,
            EvidenceCategory::MATERIAL_ARRIVAL, EvidenceCategory::BEFORE => EvidenceStep::BEFORE->value,
            EvidenceCategory::PROGRESS => EvidenceStep::PROGRESS->value,
            EvidenceCategory::AFTER => EvidenceStep::AFTER->value,
        };

        return $this->evidenceService->uploadMany($lop, $data, $files, $technician);
    }

    public function completeSurvey(QeLop $lop, User $technician): void
    {
        $state = $this->state($lop);

        if (! $state['step2Complete']) {
            throw ValidationException::withMessages(['workflow' => 'Lengkapi lokasi, evidence pra, material tiba, dan before setiap item.']);
        }

        if ($lop->status_lop === LopStatus::SURVEY) {
            $this->lopService->transitionStatus($lop, LopStatus::PROGRESS, $technician, 'Survey dan evidence pra selesai');
        }
    }

    public function submitApproval(QeLop $lop, User $technician): void
    {
        $state = $this->state($lop);

        if (! $state['step2Complete'] || ! $state['step3Complete'] || ! $state['step4Complete']) {
            throw ValidationException::withMessages(['workflow' => 'Semua checklist evidence harus lengkap sebelum diajukan.']);
        }

        if ($lop->status_lop !== LopStatus::PROGRESS) {
            throw ValidationException::withMessages(['workflow' => 'Status project belum siap diajukan.']);
        }

        $reservation = $lop->materialReservation;
        $reservation?->update(['status' => 'submitted', 'submitted_at' => now()]);
        $this->lopService->transitionStatus($lop, LopStatus::WAITING_APPROVAL, $technician, 'Evidence diajukan untuk approval');
    }

    public function state(QeLop $lop): array
    {
        $lop->loadMissing(['materialReservation.items.designator', 'survey', 'evidences.designator']);
        $items = $lop->materialReservation?->items ?? collect();
        $validEvidence = $lop->evidences->filter(fn ($evidence) => $evidence->status !== EvidenceStatus::REJECTED);
        $reservedIds = $items->pluck('designator_id')->unique();
        $beforeIds = $validEvidence->where('category', EvidenceCategory::BEFORE)->pluck('designator_id')->unique();
        $afterIds = $validEvidence->where('category', EvidenceCategory::AFTER)->pluck('designator_id')->unique();

        $step1 = $items->isNotEmpty();
        $step2 = $step1
            && $lop->survey !== null
            && $validEvidence->where('category', EvidenceCategory::PRE)->isNotEmpty()
            && $validEvidence->where('category', EvidenceCategory::MATERIAL_ARRIVAL)->isNotEmpty()
            && $reservedIds->diff($beforeIds)->isEmpty();
        $step3 = $validEvidence->where('category', EvidenceCategory::PROGRESS)->isNotEmpty();
        $step4 = $step1 && $reservedIds->diff($afterIds)->isEmpty();

        $currentStep = ! $step1 ? 1 : (! $step2 ? 2 : (! $step3 ? 3 : 4));

        return [
            'reservation' => $lop->materialReservation,
            'items' => $items,
            'survey' => $lop->survey,
            'evidences' => $lop->evidences,
            'step1Complete' => $step1,
            'step2Complete' => $step2,
            'step3Complete' => $step3,
            'step4Complete' => $step4,
            'currentStep' => $currentStep,
            'missingBefore' => $items->whereIn('designator_id', $reservedIds->diff($beforeIds)),
            'missingAfter' => $items->whereIn('designator_id', $reservedIds->diff($afterIds)),
        ];
    }

    private function assertActiveAssignment(QeLop $lop, User $technician): void
    {
        $assigned = $lop->assignments()
            ->where('technician_id', $technician->id_user)
            ->where('status', 'active')
            ->exists();

        if (! $assigned) {
            throw ValidationException::withMessages(['workflow' => 'Project ini tidak ditugaskan kepada Anda.']);
        }
    }
}
