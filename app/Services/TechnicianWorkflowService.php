<?php

namespace App\Services;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceStatus;
use App\Enums\EvidenceStep;
use App\Enums\LopStatus;
use App\Enums\UserRole;
use App\Models\Designator;
use App\Models\Package;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\QeMaterialReservation;
use App\Models\QeSurvey;
use App\Models\User;
use App\Support\MaterialArrivalRules;
use App\Support\MaterialReservationRules;
use App\Support\ReservedDesignatorRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TechnicianWorkflowService
{
    public function __construct(
        private readonly LopService $lopService,
        private readonly EvidenceService $evidenceService,
        private readonly RegionalPackageResolver $regionalPackages,
    ) {}

    /** @return array{package: Package|null, expected_code: string|null, source: string} */
    public function reservationPackage(QeLop $lop): array
    {
        $lop->loadMissing(['boq.package', 'package']);
        $regionalPackage = $this->regionalPackages->forLop($lop);
        $package = $lop->boq?->package ?? $regionalPackage ?? $lop->package;

        return [
            'package' => $package,
            'expected_code' => $this->regionalPackages->expectedCode($lop),
            'source' => $lop->boq?->package !== null
                ? 'BOQ Plan'
                : ($regionalPackage !== null ? 'Otomatis sesuai region' : 'Paket belum terpetakan'),
        ];
    }

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

        $lop->loadMissing(['boq.items', 'materialReservation.items']);
        $targets = collect($items)->keyBy('designator_id');
        foreach (($lop->boq?->items ?? collect())->filter(fn ($item) => ReservedDesignatorRules::fixedActual($item->designator_code) && $item->designator_id !== null) as $item) {
            if (! $targets->has($item->designator_id)) {
                $reserved = $lop->materialReservation?->items->firstWhere('designator_id', $item->designator_id);
                $targets->put($item->designator_id, ['designator_id' => $item->designator_id, 'qty' => (int) ($reserved?->qty ?? $item->qty)]);
            }
        }
        $items = $targets->values()->all();
        if (count($items) > 100) {
            throw ValidationException::withMessages(['items' => 'Maksimal 100 designator termasuk jasa khusus dari BOQ Plan.']);
        }
        $ids = collect($items)->pluck('designator_id');
        $materialCount = ReservedDesignatorRules::apply(Designator::query()->whereIn('id_designator', $ids))
            ->count();

        if ($materialCount !== $ids->unique()->count()) {
            throw ValidationException::withMessages(['items' => 'Reservasi hanya menerima MATERIAL dan jasa khusus J-CO-OF, J-Coring, J-OS-SM-1.']);
        }

        $packageSelection = $this->reservationPackage($lop);
        $reservation = DB::transaction(function () use ($lop, $technician, $items, $packageSelection) {
            $lop = QeLop::query()->lockForUpdate()->findOrFail($lop->getKey());
            $this->assertActiveAssignment($lop, $technician);
            if (! in_array($lop->status_lop, [LopStatus::PICKED_UP, LopStatus::SURVEY, LopStatus::PROGRESS], true)) {
                throw ValidationException::withMessages(['items' => 'Reservasi tidak dapat diubah pada status project sekarang.']);
            }
            if ($lop->boq === null && $packageSelection['package'] !== null) {
                $lop->update(['package_id' => $packageSelection['package']->id_package]);
            }

            $reservation = QeMaterialReservation::updateOrCreate(
                ['qe_lop_id' => $lop->id_qe_lops],
                ['technician_id' => $technician->id_user, 'status' => 'draft']
            );

            $existing = $reservation->items()->with('designator')->lockForUpdate()->get()->keyBy('designator_id');
            $targets = collect($items)->keyBy('designator_id');
            $evidenceIds = $lop->evidences()->whereNotNull('designator_id')->pluck('designator_id');
            foreach ($existing as $id => $item) {
                if (! $targets->has($id)) {
                    MaterialReservationRules::assertRemovable($item, $evidenceIds->contains($id));
                    $item->delete();
                }
            }
            foreach ($targets as $id => $item) {
                $old = $existing->get($id);
                if ($old !== null) {
                    MaterialReservationRules::assertQuantity($old, (int) $item['qty']);
                    $old->update(['qty' => (int) $item['qty']]);
                } else {
                    $reservation->items()->create(['designator_id' => $id, 'qty' => (int) $item['qty']]);
                }
            }

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

        DB::transaction(function () use ($lop, $technician, $byDesignator) {
            $lop = QeLop::query()->lockForUpdate()->findOrFail($lop->getKey());
            $this->assertActiveAssignment($lop, $technician);
            if (! in_array($lop->status_lop, [LopStatus::PICKED_UP, LopStatus::SURVEY, LopStatus::PROGRESS], true)) {
                throw ValidationException::withMessages(['usage' => 'Qty aktual hanya dapat disimpan saat pekerjaan masih berjalan.']);
            }
            $reservation = $lop->materialReservation()->lockForUpdate()->firstOrFail();
            $reservation->setRelation('items', $reservation->items()->with('designator')->lockForUpdate()->get());
            foreach ($reservation->items as $item) {
                $row = $byDesignator->get((int) $item->designator_id);

                if ($row === null) {
                    continue;
                }

                $item->update([
                    'qty_actual' => ReservedDesignatorRules::fixedActual($item->designator?->code)
                        ? (float) $item->qty : min((float) $row['qty_actual'], (float) $item->qty),
                ]);
            }
        });
    }

    public function saveLocation(QeLop $lop, User $technician, array $data): ?QeSurvey
    {
        $this->assertActiveAssignment($lop, $technician);

        // Melewati lokasi bukan menyimpan survey kosong atau menghapus lokasi lama.
        if (($data['latitude'] ?? null) === null && ($data['longitude'] ?? null) === null) {
            return null;
        }
        unset($data['coordinates']);
        $data['location_source'] = $data['location_source'] ?? 'manual';

        return QeSurvey::updateOrCreate(
            ['qe_lop_id' => $lop->id_qe_lops],
            [
                ...$data,
                'captured_by' => $technician->id_user,
                'captured_at' => now(),
            ]
        );
    }

    public function optionalCaptureChoice(QeLop $lop, User $technician, EvidenceCategory $category, bool $unavailable): array
    {
        if (! $category->isOptionalCapture()) {
            throw ValidationException::withMessages(['category' => 'Pilihan hanya untuk Capture Insera atau Logic Slot Port.']);
        }
        if (! Schema::hasColumn('qe_lops', 'optional_capture_choices')) {
            throw ValidationException::withMessages(['unavailable' => 'Penyimpanan pilihan belum aktif. Administrator perlu menjalankan migration capture opsional.']);
        }

        return DB::transaction(function () use ($lop, $technician, $category, $unavailable) {
            $lop = QeLop::query()->lockForUpdate()->findOrFail($lop->getKey());
            Gate::forUser($technician)->authorize('setOptionalCaptureChoice', $lop);
            if ($unavailable && $lop->evidences()->where('category', $category)->exists()) {
                throw ValidationException::withMessages(['unavailable' => 'Evidence masih tersedia. Hapus evidence yang belum approved terlebih dahulu.']);
            }
            $this->lopService->recordOptionalCaptureChoice($lop, $technician, $category, $unavailable);

            return ['unavailable' => $unavailable, 'choice' => $lop->optional_capture_choices[$category->value] ?? null];
        });
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

        if ($category === EvidenceCategory::REQUEST_LETTER) {
            if (! $lop->program_type->usesProjectStatus()) {
                throw ValidationException::withMessages([
                    'request_letter' => 'Surat Permintaan hanya tersedia untuk QE Relok Utilitas dan QE Preventive.',
                ]);
            }

            $hasAdminLetter = $lop->evidences()
                ->where('category', EvidenceCategory::REQUEST_LETTER)
                ->whereHas('uploader.role', fn ($query) => $query->whereIn('code', [
                    UserRole::SUPER_ADMIN->value,
                    UserRole::ADMIN->value,
                ]))
                ->exists();

            if ($hasAdminLetter) {
                throw ValidationException::withMessages([
                    'request_letter' => 'Surat Permintaan sudah disediakan admin dan tidak perlu diupload ulang.',
                ]);
            }
        }

        if (in_array($category, [EvidenceCategory::BEFORE, EvidenceCategory::PROGRESS, EvidenceCategory::AFTER], true)) {
            $isReserved = $lop->materialReservation?->items()
                ->where('designator_id', $data['designator_id'])->exists() ?? false;

            if (! $isReserved) {
                throw ValidationException::withMessages(['designator_id' => 'Designator tidak termasuk reservasi material project ini.']);
            }
        } elseif ($category === EvidenceCategory::MATERIAL_ARRIVAL && MaterialArrivalRules::perDesignator($lop)) {
            MaterialArrivalRules::validateDesignator($lop, $data['designator_id'] ?? null);
        } else {
            $data['designator_id'] = null;
        }

        $data['step'] = match ($category) {
            EvidenceCategory::PRE, EvidenceCategory::INSERA, EvidenceCategory::REQUEST_LETTER => EvidenceStep::SURVEY->value,
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
            throw ValidationException::withMessages(['workflow' => 'Lengkapi evidence Material Tiba dan foto kondisi awal. Tag lokasi dan Capture Ticket Insera opsional.']);
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
        $lop->loadMissing(['activeAssignment', 'materialReservation.items.designator', 'survey', 'evidences.designator', 'evidences.uploader.role']);
        $evidenceLop = (clone $lop)->unsetRelation('evidences');
        $lop->evidences->each(fn ($evidence) => $evidence->setRelation('lop', $evidenceLop));
        $items = ReservedDesignatorRules::workflowItems($lop);
        $validEvidence = $lop->evidences->filter(fn ($evidence) => $evidence->status !== EvidenceStatus::REJECTED);
        $rejectedSteps = $lop->evidences->filter(fn ($evidence) => $evidence->status === EvidenceStatus::REJECTED)
            ->map(fn ($evidence) => match ($evidence->category?->value) {
                'material_arrival' => 2,
                'pre', 'insera', 'before' => 3,
                'progress' => 4,
                'after', 'slot_port' => 5,
                default => null,
            })->filter()->countBy()->sortKeys();
        $reservedIds = $items->pluck('designator_id')->unique();
        $progressIds = $validEvidence->where('category', EvidenceCategory::PROGRESS)->pluck('designator_id')->unique();
        $afterIds = $validEvidence->where('category', EvidenceCategory::AFTER)->pluck('designator_id')->unique();

        // Step 1 Reservasi · Step 2 Material Tiba · Step 3 Evidence Pra
        // (foto kondisi awal global wajib, lokasi opsional) ·
        // Step 4 Progress (per designator) · Step 5 After (per designator).
        $step1 = $items->isNotEmpty() && $items->every(fn ($item) => $item->exists);
        $step2 = $step1 && MaterialArrivalRules::complete($lop, $validEvidence);
        $step3 = $step2
            && $validEvidence->where('category', EvidenceCategory::PRE)->isNotEmpty();
        $step4 = $step1 && $reservedIds->diff($progressIds)->isEmpty();
        $materialUsageComplete = $step1 && $items->every(fn ($item) => ReservedDesignatorRules::actualComplete($item));
        $step5 = $step1
            && $reservedIds->diff($afterIds)->isEmpty()
            && $materialUsageComplete;

        $currentStep = ! $step1 ? 1 : (! $step2 ? 2 : (! $step3 ? 3 : (! $step4 ? 4 : 5)));

        return [
            'reservation' => $lop->materialReservation,
            'items' => $items,
            'materialArrivalPerItem' => MaterialArrivalRules::perDesignator($lop),
            'materialArrivalItems' => MaterialArrivalRules::items($lop),
            'survey' => $lop->survey,
            'evidences' => $lop->evidences,
            'rejectedSteps' => $rejectedSteps,
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
