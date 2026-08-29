<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\LopStatus;
use App\Models\QeLop;
use App\Models\QeLopAssignment;
use App\Models\QeLopHistory;
use App\Models\User;
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
    /**
     * Membuat LOP baru dengan status awal draft.
     */
    public function create(array $data, User $creator): QeLop
    {
        return DB::transaction(function () use ($data, $creator) {
            $lop = QeLop::create([
                'kode_lop' => $data['kode_lop'],
                'nama_lop' => $data['nama_lop'],
                'wbs_type' => $data['wbs_type'],
                'sto' => $data['sto'] ?? null,
                'branch' => $data['branch'] ?? null,
                'package_id' => $data['package_id'] ?? null,
                'status_lop' => LopStatus::DRAFT,
                'created_by' => $creator->id_user,
            ]);

            $this->recordHistory($lop, null, LopStatus::DRAFT, $creator, 'created', 'LOP dibuat');

            return $lop;
        });
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
