<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\EvidenceCategory;
use App\Enums\EvidenceStatus;
use App\Enums\LopSegment;
use App\Enums\LopStatus;
use App\Enums\ProgramType;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\QeLopAssignment;
use App\Models\QeLopHistory;
use App\Models\ServiceArea;
use App\Models\User;
use App\Notifications\TechnicianActivityNotification;
use App\Support\BaBongkarRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Satu-satunya tempat yang boleh mengubah status qe_lops dan menulis
 * qe_lop_histories. Controller TIDAK BOLEH memanggil QeLop::update() untuk
 * status_lop secara langsung - semua transisi wajib lewat service ini agar
 * whitelist transisi (LopStatus::transitions()) selalu ditegakkan dan audit
 * trail selalu konsisten.
 */
class LopService
{
    public function __construct(
        private readonly LopNamingService $namingService,
        private readonly BoqService $boqService,
        private readonly ManualIncidentService $manualIncident,
        private readonly IncidentReportedDateService $reportedDates,
    ) {}

    /**
     * Salinan tanggal laporan tiket untuk kolom qe_lops.reported_date.
     *
     * Incident INP buatan sistem dan nilai yang tidak ditemukan tetap null.
     * Sumber tiket yang sedang mati tidak boleh menggagalkan penyimpanan LOP —
     * tanggalnya dapat menyusul lewat `php artisan dompis:sync-reported-date`.
     */
    private function reportedDateFor(string $incident): ?string
    {
        $incident = mb_strtoupper(trim($incident));

        if ($incident === '' || str_starts_with($incident, 'INP')) {
            return null;
        }

        return $this->reportedDates->datesFor([$incident])[$incident] ?? null;
    }

    /**
     * Semua program menyimpan maksimal lima segmen unik dengan urutan pilihan.
     */
    private function normalizeSegment(mixed $segment): array
    {
        $segments = LopSegment::normalizeInput($segment);
        if (count($segments) > LopSegment::MAX_SELECTIONS) {
            throw new InvalidArgumentException('Maksimal 5 segmen untuk setiap program.');
        }

        return $segments;
    }

    /**
     * Membuat LOP baru dengan status awal draft.
     */
    public function create(
        array $data,
        User $creator,
        ?Branch $resolvedBranch = null,
        ?ServiceArea $resolvedServiceArea = null,
    ): QeLop {
        return DB::transaction(function () use ($data, $creator, $resolvedBranch, $resolvedServiceArea) {
            $normalizedSegment = $this->normalizeSegment($data['segment'] ?? null);
            if ($resolvedBranch !== null && $resolvedServiceArea !== null) {
                $branch = $resolvedBranch;
                $serviceArea = $resolvedServiceArea;
            } else {
                [$branch, $serviceArea] = $this->resolveLocation($data);
            }
            $program = ProgramType::from((string) $data['program_type']);
            if (blank($data['incident'] ?? null)) {
                $data['incident'] = $this->manualIncident->generate($branch, $program);
            }
            $data['budget_type'] = $program->budgetType()->value;
            $reportedDate = $this->reportedDateFor($data['incident']);

            // Untuk penamaan, teruskan array segmen (LopNamingService akan join dengan _)
            $namingData = array_merge($data, ['segment' => $normalizedSegment]);

            $lop = QeLop::create([
                'incident' => $data['incident'],
                'nama_lop' => filled($data['nama_lop'] ?? null)
                    ? $data['nama_lop']
                    : $this->namingService->generate($namingData),
                'program_type' => $data['program_type'],
                'status_project' => $program->usesProjectStatus() ? ProjectStatus::USULAN : null,
                'sto' => $serviceArea->workzone,
                'branch' => $branch->name,
                'branch_id' => $branch->id_branch,
                'service_area_id' => $serviceArea->id_service_area,
                'area' => $data['area'],
                'segment' => $normalizedSegment,
                'budget_type' => $data['budget_type'],
                'job_description' => $data['job_description'],
                'ticket_summary' => $data['ticket_summary'] ?? null,
                'datek' => $data['datek'] ?? null,
                'reported_date' => $reportedDate,
                'ihld_id' => $data['ihld_id'] ?? null,
                'package_id' => $data['package_id'] ?? null,
                'status_lop' => LopStatus::DRAFT,
                'created_by' => $creator->id_user,
            ]);

            $this->recordHistory($lop, null, LopStatus::DRAFT, $creator, 'created', 'LOP dibuat');

            return $lop;
        });
    }

    public function update(QeLop $lop, array $data): QeLop
    {
        $data['budget_type'] = ProgramType::from((string) $data['program_type'])->budgetType()->value;
        $normalizedSegment = $this->normalizeSegment($data['segment'] ?? null);
        $namingData = array_merge($data, ['segment' => $normalizedSegment]);
        [$branch, $serviceArea] = $this->resolveLocation($data);
        $program = ProgramType::from((string) $data['program_type']);

        $lop->update([
            'incident' => $data['incident'],
            'nama_lop' => filled($data['nama_lop'] ?? null)
                ? $data['nama_lop']
                : $this->namingService->generate($namingData),
            'program_type' => $data['program_type'],
            'status_project' => $program->usesProjectStatus()
                ? ($lop->activeAssignment()->exists() ? ProjectStatus::ON_GOING : ($lop->status_project ?? ProjectStatus::USULAN))
                : null,
            'sto' => $serviceArea->workzone,
            'branch' => $branch->name,
            'branch_id' => $branch->id_branch,
            'service_area_id' => $serviceArea->id_service_area,
            'area' => $data['area'],
            'segment' => $normalizedSegment,
            'budget_type' => $data['budget_type'],
            'job_description' => $data['job_description'],
            'ticket_summary' => $data['ticket_summary'] ?? null,
            'datek' => $data['datek'] ?? null,
            // Incident bisa berubah saat edit, jadi tanggal laporannya ikut disegarkan.
            'reported_date' => $this->reportedDateFor($data['incident']),
            'ihld_id' => $data['ihld_id'] ?? null,
        ]);

        return $lop->refresh();
    }

    /**
     * Assign teknisi ke LOP. Menonaktifkan assignment aktif sebelumnya (jika
     * ada) tanpa menghapusnya, lalu membuat assignment baru + memindahkan
     * status LOP ke ASSIGNED (hanya jika transisi dari status saat ini valid).
     */
    public function assign(QeLop $lop, User $technician, User $assignedBy): QeLopAssignment
    {
        return DB::transaction(function () use ($lop, $technician, $assignedBy) {
            $lop->activeAssignment()->update([
                'status' => AssignmentStatus::REPLACED,
                'unassigned_at' => now(),
            ]);

            $assignment = QeLopAssignment::create([
                'qe_lop_id' => $lop->id_qe_lops,
                'technician_id' => $technician->id_user,
                'assigned_by' => $assignedBy->id_user,
                'assigned_at' => now(),
                'status' => AssignmentStatus::ACTIVE,
            ]);

            if ($lop->program_type->usesProjectStatus()) {
                $lop->update(['status_project' => ProjectStatus::ON_GOING]);
            }

            // BOQ adalah sumber awal reservasi. Sinkronisasi hanya mengisi
            // reservasi kosong agar penyesuaian teknisi tidak hilang saat reassign.
            $this->boqService->syncReservation($lop, $technician);

            $technician->notify(new TechnicianActivityNotification(
                'Project baru ditugaskan',
                "{$lop->incident} · {$lop->nama_lop}",
                $lop->id_qe_lops,
                'assignment'
            ));

            // draft -> assigned adalah transisi normal. Kalau LOP sedang
            // di-reassign saat status sudah lebih maju (mis. picked_up),
            // status LOP TIDAK dipaksa mundur - hanya assignment yang berubah.
            if ($lop->status_lop === LopStatus::DRAFT) {
                $this->transitionStatus($lop, LopStatus::ASSIGNED, $assignedBy, 'Teknisi ditugaskan');
            } else {
                $this->recordHistory(
                    $lop,
                    $lop->status_lop,
                    $lop->status_lop,
                    $assignedBy,
                    'reassigned',
                    "Teknisi di-reassign ke {$technician->name}"
                );
            }

            return $assignment;
        });
    }

    public function unassign(QeLop $lop, User $actor): void
    {
        DB::transaction(function () use ($lop, $actor) {
            $assignment = $lop->activeAssignment()->with('technician')->firstOrFail();
            $technician = $assignment->technician;

            $assignment->update([
                'status' => AssignmentStatus::REPLACED,
                'unassigned_at' => now(),
            ]);

            if ($lop->status_lop === LopStatus::ASSIGNED) {
                $this->transitionStatus(
                    $lop,
                    LopStatus::DRAFT,
                    $actor,
                    "Assignment {$technician->name} dibatalkan"
                );

                if ($lop->program_type->usesProjectStatus()) {
                    $lop->update(['status_project' => ProjectStatus::USULAN]);
                }
            }

            $technician->notify(new TechnicianActivityNotification(
                'Assignment project dibatalkan',
                "{$lop->incident} · {$lop->nama_lop}",
                $lop->id_qe_lops,
                'assignment_cancelled'
            ));
        });
    }

    public function delete(QeLop $lop, User $actor, string $reason): void
    {
        DB::transaction(function () use ($lop, $actor, $reason) {
            $lop = QeLop::query()->lockForUpdate()->findOrFail($lop->getKey());
            Gate::forUser($actor)->authorize('delete', $lop);
            $lop->assignments()->where('status', AssignmentStatus::ACTIVE->value)->update([
                'status' => AssignmentStatus::REPLACED->value,
                'unassigned_at' => now(),
            ]);
            $this->recordHistory(
                $lop,
                $lop->status_lop,
                $lop->status_lop,
                $actor,
                'deleted',
                'LOP dibatalkan dan diarsipkan: '.$reason
            );
            $lop->delete();
        });
    }

    /** @return array{0: Branch, 1: ServiceArea} */
    private function resolveLocation(array $data): array
    {
        $serviceArea = isset($data['service_area_id'])
            ? ServiceArea::query()->with('branch')->find((int) $data['service_area_id'])
            : ServiceArea::query()->with('branch')->where('workzone', mb_strtoupper(trim((string) ($data['sto'] ?? ''))))->first();

        if ($serviceArea === null || $serviceArea->branch === null) {
            throw new InvalidArgumentException('Service Area tidak ditemukan atau belum terhubung ke Branch.');
        }

        $branch = isset($data['branch_id'])
            ? Branch::query()->find((int) $data['branch_id'])
            : $serviceArea->branch;

        if ($branch === null || (int) $branch->id_branch !== (int) $serviceArea->branch_id) {
            throw new InvalidArgumentException('Branch tidak sesuai dengan Service Area yang dipilih.');
        }

        return [$branch, $serviceArea];
    }

    /**
     * Pindahkan status LOP ke status baru, wajib melalui whitelist transisi
     * di LopStatus::transitions(). Melempar InvalidArgumentException kalau
     * transisi tidak valid (misal draft -> completed langsung).
     */
    public function recordBaBongkarEvent(QeLop $lop, User $actor, string $event, array $meta): void
    {
        $this->recordHistory($lop, $lop->status_lop, $lop->status_lop, $actor, $event, 'BA Bongkar')->update(['meta' => $meta]);
    }

    public function transitionStatus(
        QeLop $lop,
        LopStatus $target,
        User $actor,
        ?string $note = null
    ): QeLop {
        if (! $lop->status_lop->canTransitionTo($target)) {
            throw new InvalidArgumentException("Transisi status tidak valid: {$lop->status_lop->value} -> {$target->value}");
        }
        if (in_array($target, [LopStatus::WAITING_APPROVAL, LopStatus::COMPLETED], true)) {
            $lop->unsetRelation('evidences');
            if ($target === LopStatus::COMPLETED) {
                BaBongkarRules::assertCompletable($lop);
                $reviewable = $lop->evidences->reject(fn ($e) => $e->category === EvidenceCategory::REQUEST_LETTER);
                if ($reviewable->isEmpty() || $reviewable->contains(fn ($e) => $e->status !== EvidenceStatus::APPROVED)) {
                    throw ValidationException::withMessages(['review' => 'Seluruh evidence wajib approved sebelum Completed.']);
                }
            } else {
                BaBongkarRules::assertSubmittable($lop);
            }
        }
        $current = $lop->status_lop;

        if ($current === LopStatus::COMPLETED) {
            Gate::forUser($actor)->authorize('resetBoq', $lop);
        } elseif ($current === LopStatus::WAITING_APPROVAL && $target === LopStatus::PROGRESS) {
            Gate::forUser($actor)->authorize($actor->hasRole(UserRole::TEKNISI) ? 'correctEvidence' : 'update', $lop);
        }

        if (! $current->canTransitionTo($target)) {
            throw new InvalidArgumentException(
                "Transisi status tidak valid: {$current->value} -> {$target->value}"
            );
        }

        return DB::transaction(function () use ($lop, $current, $target, $actor, $note) {
            $lop->update(['status_lop' => $target]);

            $this->recordHistory($lop, $current, $target, $actor, 'status_change', $note);

            return $lop->refresh();
        });
    }

    public function updateAissStatus(QeLop $lop, string $status, User $actor, string $batchUuid, string $matchStage): bool
    {
        Gate::forUser($actor)->authorize('update', $lop);
        if ($lop->status_aiss === $status) {
            return false;
        }
        $before = $lop->status_aiss;
        // Importer holds the parent lock. Bypass model normalization to change no workflow/legacy fields.
        DB::table('qe_lops')->where('id_qe_lops', $lop->getKey())->update(['status_aiss' => $status, 'updated_at' => now()]);
        $this->recordHistory($lop, $lop->status_lop, $lop->status_lop, $actor, 'aiss_status_updated', 'Status AISS diperbarui melalui import.')
            ->update(['meta' => ['before' => $before, 'after' => $status, 'batch_uuid' => $batchUuid, 'match_stage' => $matchStage]]);

        return true;
    }

    public function recordReservationChange(QeLop $lop, User $actor, array $before, array $after): QeLopHistory
    {
        $history = $this->recordHistory($lop, $lop->status_lop, $lop->status_lop, $actor, 'reservation_updated', 'Item BOQ aktual / reservasi diperbarui melalui Master Data.');
        $history->update(['meta' => ['before' => $before, 'after' => $after]]);

        return $history;
    }

    public function recordOptionalCaptureChoice(QeLop $lop, User $actor, EvidenceCategory $category, bool $unavailable): void
    {
        $choices = $lop->optional_capture_choices ?? [];
        $before = $choices[$category->value] ?? null;
        if (($before !== null) === $unavailable) {
            return;
        }
        if ($unavailable) {
            $choices[$category->value] = ['unavailable' => true, 'user_id' => $actor->getKey(), 'recorded_at' => now()->toIso8601String()];
        } else {
            unset($choices[$category->value]);
        }
        DB::table('qe_lops')->where('id_qe_lops', $lop->getKey())->update([
            'optional_capture_choices' => $choices === [] ? null : json_encode($choices, JSON_THROW_ON_ERROR), 'updated_at' => now(),
        ]);
        $lop->setAttribute('optional_capture_choices', $choices);
        $this->recordHistory($lop, $lop->status_lop, $lop->status_lop, $actor, 'optional_capture_choice', $category->label().($unavailable ? ': Tidak Ada' : ': pilihan Tidak Ada dibatalkan'))
            ->update(['meta' => ['category' => $category->value, 'before' => $before, 'after' => $choices[$category->value] ?? null]]);
    }

    public function recordEvidenceDeletion(QeLop $lop, User $actor, QeEvidence $evidence): void
    {
        $this->recordHistory($lop, $lop->status_lop, $lop->status_lop, $actor, 'evidence_deleted', 'Evidence dihapus untuk koreksi.')
            ->update(['meta' => ['evidence_id' => $evidence->getKey(), 'category' => $evidence->category?->value, 'status' => $evidence->status->value, 'designator_id' => $evidence->designator_id]]);
    }

    public function resetCompletedForBoq(QeLop $lop, User $actor): QeLop
    {
        return DB::transaction(function () use ($lop, $actor) {
            $locked = QeLop::whereKey($lop->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $locked);
            if ($locked->status_lop !== LopStatus::COMPLETED) {
                throw ValidationException::withMessages(['boq' => 'LOP sudah tidak Completed. Muat ulang Data BOQ sebelum melanjutkan.']);
            }

            $this->transitionStatus($locked, LopStatus::PROGRESS, $actor, 'Reset Completed untuk koreksi BOQ; evidence dan rekap aktual tetap disimpan.');
            $locked->materialReservation()->update(['status' => 'draft', 'submitted_at' => null]);

            return $locked->refresh();
        });
    }

    private function recordHistory(
        QeLop $lop,
        ?LopStatus $before,
        LopStatus $after,
        User $actor,
        string $eventType,
        ?string $note = null
    ): QeLopHistory {
        return QeLopHistory::create([
            'qe_lop_id' => $lop->id_qe_lops,
            'user_id' => $actor->id_user,
            'status_before' => $before?->value,
            'status_after' => $after->value,
            'event_type' => $eventType,
            'note' => $note,
        ]);
    }
}
