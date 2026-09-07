<?php

namespace App\Services;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceStatus;
use App\Enums\EvidenceStep;
use App\Enums\LopStatus;
use App\Models\Designator;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\QeMaterialReservation;
use App\Models\QeSurvey;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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
            ->whereRelation('type', 'code', 'MATERIAL')
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

    /**
     * Rekap qty aktual terpakai per designator. qty_actual di-clamp ke qty
     * reservasi (validasi form sudah menolak nilai > reservasi; ini jaring
     * pengaman terakhir). Item di luar reservasi diabaikan.
     *
     * @param  array<int, array{designator_id: int|string, qty_actual: int|float|string}>  $usage
     */
    public function saveMaterialUsage(QeLop $lop, User $technician, array $usage): void
    {
        $this->assertActiveAssignment($lop, $technician);

        $reservation = $lop->materialReservation;

        if ($reservation === null || $reservation->items->isEmpty()) {
            throw ValidationException::withMessages(['usage' => 'Belum ada reservasi material untuk direkap.']);
        }

        $byDesignator = collect($usage)->keyBy(fn ($row) => (int) $row['designator_id']);

        DB::transaction(function () use ($reservation, $byDesignator) {
            foreach ($reservation->items as $item) {
                $row = $byDesignator->get((int) $item->designator_id);

                if ($row === null) {
                    continue;
                }

                $item->update([
                    'qty_actual' => min((float) $row['qty_actual'], (float) $item->qty),
                ]);
            }
        });
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
        $data = $this->prepareEvidenceData($lop, $technician, $data);

        return $this->evidenceService->uploadMany($lop, $data, $files, $technician);
    }

    /**
     * Jalur upload async per-file (progress + retry). Sama semantiknya dengan
     * uploadEvidence() tapi satu file + thumbnail opsional dari browser.
     */
    public function uploadEvidenceFile(
        QeLop $lop,
        User $technician,
        array $data,
        UploadedFile $file,
        ?UploadedFile $thumb = null,
    ): QeEvidence {
        $data = $this->prepareEvidenceData($lop, $technician, $data);

        return DB::transaction(fn () => $this->evidenceService->storeOne($lop, $data, $file, $technician, $thumb));
    }

    /**
     * Validasi assignment + reservasi material, null-kan designator untuk
     * kategori non-item, lalu turunkan `step` dari `category`.
     */
    private function prepareEvidenceData(QeLop $lop, User $technician, array $data): array
    {
        $this->assertActiveAssignment($lop, $technician);
        $category = EvidenceCategory::from($data['category']);

        if (in_array($category, [EvidenceCategory::BEFORE, EvidenceCategory::PROGRESS, EvidenceCategory::AFTER], true)) {
            $isReserved = $lop->materialReservation?->items()
                ->where('designator_id', $data['designator_id'])->exists() ?? false;

            if (! $isReserved) {
                throw ValidationException::withMessages(['designator_id' => 'Designator tidak termasuk reservasi material project ini.']);
            }
        } else {
            $data['designator_id'] = null;
        }

        $data['step'] = match ($category) {
            EvidenceCategory::PRE, EvidenceCategory::INSERA => EvidenceStep::SURVEY->value,
            EvidenceCategory::MATERIAL_ARRIVAL, EvidenceCategory::BEFORE => EvidenceStep::BEFORE->value,
            EvidenceCategory::PROGRESS => EvidenceStep::PROGRESS->value,
            EvidenceCategory::AFTER, EvidenceCategory::SLOT_PORT => EvidenceStep::AFTER->value,
        };

        return $data;
    }

    public function completeSurvey(QeLop $lop, User $technician): void
    {
        $state = $this->state($lop);

        if (! $state['step3Complete']) {
            throw ValidationException::withMessages(['workflow' => 'Lengkapi tag lokasi pekerjaan, foto kondisi awal, dan capture tiket Insera.']);
        }

        if ($lop->status_lop === LopStatus::SURVEY) {
            $this->lopService->transitionStatus($lop, LopStatus::PROGRESS, $technician, 'Survey dan evidence pra selesai');
        }
    }

    public function submitApproval(QeLop $lop, User $technician): void
    {
        $state = $this->state($lop);

        if (! $state['step2Complete'] || ! $state['step3Complete'] || ! $state['step4Complete']
            || ($state['step1Complete'] && ! $state['materialUsageComplete']) || ! $state['step5Complete']) {
            throw ValidationException::withMessages([
                'workflow' => ! $state['materialUsageComplete']
                    ? 'Rekap qty material terpakai belum lengkap.'
                    : 'Semua checklist evidence harus lengkap sebelum diajukan.',
            ]);
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
        $progressIds = $validEvidence->where('category', EvidenceCategory::PROGRESS)->pluck('designator_id')->unique();
        $afterIds = $validEvidence->where('category', EvidenceCategory::AFTER)->pluck('designator_id')->unique();

        // Step 1 Reservasi · Step 2 Material Tiba · Step 3 Evidence Pra
        // (lokasi + foto kondisi awal, TIDAK per designator) ·
        // Step 4 Progress (per designator) · Step 5 After (per designator).
        $step1 = $items->isNotEmpty();
        $step2 = $step1
            && $validEvidence->where('category', EvidenceCategory::MATERIAL_ARRIVAL)->isNotEmpty();
        $step3 = $step2
            && $lop->survey !== null
            && $validEvidence->where('category', EvidenceCategory::PRE)->isNotEmpty()
            && $validEvidence->where('category', EvidenceCategory::INSERA)->isNotEmpty();
        $step4 = $step1 && $reservedIds->diff($progressIds)->isEmpty();
        $materialUsageComplete = $step1 && $items->every(fn ($item) => $item->qty_actual !== null);
        $step5 = $step1
            && $reservedIds->diff($afterIds)->isEmpty()
            && $validEvidence->where('category', EvidenceCategory::SLOT_PORT)->isNotEmpty()
            && $materialUsageComplete;

        $currentStep = ! $step1 ? 1 : (! $step2 ? 2 : (! $step3 ? 3 : (! $step4 ? 4 : 5)));

        return [
            'reservation' => $lop->materialReservation,
            'items' => $items,
            'survey' => $lop->survey,
            'evidences' => $lop->evidences,
            'step1Complete' => $step1,
            'step2Complete' => $step2,
            'step3Complete' => $step3,
            'step4Complete' => $step4,
            'step5Complete' => $step5,
            'currentStep' => $currentStep,
            'materialUsageComplete' => $materialUsageComplete,
            'slotPortComplete' => $validEvidence->where('category', EvidenceCategory::SLOT_PORT)->isNotEmpty(),
            'missingProgress' => $items->whereIn('designator_id', $reservedIds->diff($progressIds)),
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
