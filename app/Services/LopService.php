<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\LopStatus;
use App\Models\QeLop;
use App\Models\QeLopAssignment;
use App\Models\QeLopHistory;
use App\Models\User;
use App\Notifications\TechnicianActivityNotification;
use Illuminate\Support\Facades\DB;
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
    public function __construct(private readonly LopNamingService $namingService) {}

    /**
     * Membuat LOP baru dengan status awal draft.
     */
    public function create(array $data, User $creator): QeLop
    {
        return DB::transaction(function () use ($data, $creator) {
            $lop = QeLop::create([
                'incident' => $data['incident'],
                'nama_lop' => filled($data['nama_lop'] ?? null)
                    ? $data['nama_lop']
                    : $this->namingService->generate($data),
                'wbs_type' => $data['wbs_type'],
                'sto' => $data['sto'],
                'branch' => $data['branch'],
                'area' => $data['area'],
                'segment' => $data['segment'],
                'budget_type' => $data['wbs_type'] === 'relok_utilitas' ? ($data['budget_type'] ?? null) : null,
                'job_description' => $data['job_description'],
                'ticket_summary' => $data['ticket_summary'] ?? null,
                'datek' => $data['datek'] ?? null,
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
        $lop->update([
            'incident' => $data['incident'],
            'nama_lop' => filled($data['nama_lop'] ?? null)
                ? $data['nama_lop']
                : $this->namingService->generate($data),
            'wbs_type' => $data['wbs_type'],
            'sto' => $data['sto'],
            'branch' => $data['branch'],
            'area' => $data['area'],
            'segment' => $data['segment'],
            'budget_type' => $data['wbs_type'] === 'relok_utilitas' ? ($data['budget_type'] ?? null) : null,
            'job_description' => $data['job_description'],
            'ticket_summary' => $data['ticket_summary'] ?? null,
            'datek' => $data['datek'] ?? null,
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
            }

            $technician->notify(new TechnicianActivityNotification(
                'Assignment project dibatalkan',
                "{$lop->incident} · {$lop->nama_lop}",
                $lop->id_qe_lops,
                'assignment_cancelled'
            ));
        });
    }

    /**
     * Pindahkan status LOP ke status baru, wajib melalui whitelist transisi
     * di LopStatus::transitions(). Melempar InvalidArgumentException kalau
     * transisi tidak valid (misal draft -> completed langsung).
     */
    public function transitionStatus(
        QeLop $lop,
        LopStatus $target,
        User $actor,
        ?string $note = null
    ): QeLop {
        $current = $lop->status_lop;

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
