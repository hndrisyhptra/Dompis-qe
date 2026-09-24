<?php

namespace App\Services;

use App\Enums\LopStatus;
use App\Models\Designator;
use App\Models\Package;
use App\Models\QeBoq;
use App\Models\QeBoqHistory;
use App\Models\QeLop;
use App\Models\QeMaterialReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BoqService
{
    /**
     * @param  array<int, array{designator_id:int|string, qty:int|string, unit_price?:int|float|string|null}>  $items
     */
    public function save(QeLop $lop, array $items, ?Package $package, User $actor, string $source = 'manual'): QeBoq
    {
        if (! in_array($lop->status_lop, [
            LopStatus::DRAFT,
            LopStatus::ASSIGNED,
            LopStatus::PICKED_UP,
            LopStatus::SURVEY,
            LopStatus::PROGRESS,
            LopStatus::REJECTED,
        ], true)) {
            throw ValidationException::withMessages([
                'boq' => 'BOQ tidak dapat diubah ketika sudah menunggu approval atau selesai.',
            ]);
        }

        $normalized = $this->normalizeItems($items);

        if ($normalized === []) {
            throw ValidationException::withMessages(['items' => 'BOQ harus memiliki minimal satu item.']);
        }

        return DB::transaction(function () use ($lop, $normalized, $package, $actor, $source) {
            $boq = QeBoq::withTrashed()->firstOrNew(['qe_lop_id' => $lop->id_qe_lops]);
            $before = $boq->exists ? $this->snapshot($boq->load('items')) : null;

            if ($boq->trashed()) {
                $boq->restore();
            }

            $boq->fill([
                'package_id' => $package?->id_package,
                'source' => $source,
                'status' => 'ready',
                'item_count' => count($normalized),
                'grand_total' => collect($normalized)->sum('total_price'),
                'updated_by' => $actor->id_user,
            ]);

            if (! $boq->exists) {
                $boq->created_by = $actor->id_user;
            }

            $boq->save();
            $boq->items()->delete();
            $boq->items()->createMany($normalized);
            $boq->load(['items.designator', 'package']);

            $this->syncLegacySnapshot($lop, $boq, $actor);

            if (in_array($lop->status_lop, [LopStatus::DRAFT, LopStatus::ASSIGNED], true)) {
                $this->syncReservation($lop, $lop->activeAssignment?->technician, true);
            } else {
                $previousBoqDesignatorIds = collect($before['items'] ?? [])
                    ->pluck('designator_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $this->syncWorkingReservation($lop, $lop->activeAssignment?->technician, $previousBoqDesignatorIds);
            }

            $this->recordHistory(
                $boq,
                $actor,
                $before === null ? 'created' : 'updated',
                $before,
                $this->snapshot($boq),
                $source === 'import' ? 'BOQ diperbarui melalui import.' : 'BOQ diperbarui melalui Master Data.'
            );

            return $boq->refresh()->load(['items.designator', 'package']);
        });
    }

    public function delete(QeBoq $boq, User $actor): void
    {
        $boq->load(['lop.activeAssignment', 'items']);

        if (! in_array($boq->lop->status_lop, [LopStatus::DRAFT, LopStatus::ASSIGNED], true)) {
            throw ValidationException::withMessages([
                'boq' => 'BOQ tidak dapat dihapus setelah pekerjaan dipickup teknisi.',
            ]);
        }

        DB::transaction(function () use ($boq, $actor) {
            $before = $this->snapshot($boq);
            $this->recordHistory($boq, $actor, 'deleted', $before, null, 'BOQ dihapus dari Master Data.');

            $boq->items()->delete();
            $boq->delete();
            $boq->lop->update(['boq_snapshot' => null]);

            $reservation = $boq->lop->materialReservation;
            if ($reservation?->status === 'draft') {
                $reservation->items()->delete();
                $reservation->delete();
            }
        });
    }

    /**
     * Isi reservasi material dari BOQ pada assignment pertama. Ketika force
     * false, penyesuaian yang sudah dibuat teknisi tidak ditimpa saat reassign.
     */
    public function syncReservation(QeLop $lop, ?User $technician, bool $force = false): ?QeMaterialReservation
    {
        if ($technician === null) {
            return null;
        }

        $lop->loadMissing(['boq.items', 'materialReservation.items']);
        $materialItems = $lop->boq?->items
            ->where('type', 'MATERIAL')
            ->map(fn ($item) => [
                'designator_id' => $item->designator_id,
                'qty' => $item->qty,
            ])
            ->values() ?? collect();

        if ($materialItems->isEmpty()) {
            return $lop->materialReservation;
        }

        $reservation = QeMaterialReservation::withTrashed()->firstOrNew([
            'qe_lop_id' => $lop->id_qe_lops,
        ]);

        if ($reservation->trashed()) {
            $reservation->restore();
        }

        $reservation->technician_id = $technician->id_user;
        $reservation->status = 'draft';
        $reservation->submitted_at = null;
        $reservation->save();

        $hasAdjustedItems = $reservation->items()->exists();
        if ($force || ! $hasAdjustedItems) {
            $reservation->items()->delete();
            $reservation->items()->createMany($materialItems->all());
        }

        return $reservation->refresh()->load('items.designator');
    }

    /**
     * Sinkronisasi BOQ saat pekerjaan sudah berjalan. Item reservasi manual
     * teknisi dipertahankan; hanya item yang sebelumnya berasal dari BOQ yang
     * diperbarui/dihapus. Item dengan evidence atau qty aktual tidak boleh
     * dihapus agar histori pekerjaan tetap konsisten.
     *
     * @param  array<int, int>  $previousBoqDesignatorIds
     */
    private function syncWorkingReservation(QeLop $lop, ?User $technician, array $previousBoqDesignatorIds): ?QeMaterialReservation
    {
        if ($technician === null) {
            return $lop->materialReservation;
        }

        $lop->unsetRelation('boq');
        $lop->load(['boq.items', 'materialReservation.items']);

        if ($lop->materialReservation === null) {
            return $this->syncReservation($lop, $technician, true);
        }

        $reservation = $lop->materialReservation;
        $targetItems = $lop->boq?->items
            ->where('type', 'MATERIAL')
            ->keyBy('designator_id') ?? collect();
        $evidenceDesignatorIds = $lop->evidences()
            ->whereNotNull('designator_id')
            ->pluck('designator_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        foreach ($reservation->items as $reservationItem) {
            $designatorId = (int) $reservationItem->designator_id;
            $target = $targetItems->get($designatorId);

            if ($target !== null) {
                $newQty = (int) round((float) $target->qty);
                if ($reservationItem->qty_actual !== null && (float) $reservationItem->qty_actual > $newQty) {
                    throw ValidationException::withMessages([
                        'items' => "Qty {$target->designator_code} tidak boleh lebih kecil dari qty terpakai.",
                    ]);
                }

                $reservationItem->update(['qty' => $newQty]);
                $targetItems->forget($designatorId);

                continue;
            }

            $cameFromPreviousBoq = in_array($designatorId, $previousBoqDesignatorIds, true);
            if (! $cameFromPreviousBoq) {
                continue;
            }

            if ($reservationItem->qty_actual !== null || $evidenceDesignatorIds->contains($designatorId)) {
                $code = $reservationItem->designator?->code ?? (string) $designatorId;
                throw ValidationException::withMessages([
                    'items' => "Item {$code} tidak dapat dihapus karena sudah memiliki evidence atau rekap pemakaian.",
                ]);
            }

            $reservationItem->delete();
        }

        foreach ($targetItems as $target) {
            $reservation->items()->create([
                'designator_id' => $target->designator_id,
                'qty' => (int) round((float) $target->qty),
            ]);
        }

        $reservation->update([
            'technician_id' => $technician->id_user,
            'status' => 'draft',
            'submitted_at' => null,
        ]);

        return $reservation->refresh()->load('items.designator');
    }

    /** @return array<int, array<string, mixed>> */
    private function normalizeItems(array $items): array
    {
        $ids = collect($items)->pluck('designator_id')->map(fn ($id) => (int) $id);

        if ($ids->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => 'Designator BOQ tidak boleh duplikat.']);
        }

        $designators = Designator::query()
            ->with('type')
            ->whereIn('id_designator', $ids)
            ->get()
            ->keyBy('id_designator');

        if ($designators->count() !== $ids->count()) {
            throw ValidationException::withMessages(['items' => 'Ada designator yang tidak ditemukan atau sudah tidak aktif.']);
        }

        return collect($items)->map(function (array $item) use ($designators) {
            $designator = $designators->get((int) $item['designator_id']);
            $rawQty = (float) $item['qty'];
            $qty = (int) $rawQty;
            $unitPrice = (float) ($item['unit_price'] ?? 0);

            if ($qty <= 0 || floor($rawQty) !== $rawQty || $unitPrice < 0) {
                throw ValidationException::withMessages(['items' => 'Qty harus berupa angka bulat lebih dari 0 dan harga tidak boleh negatif.']);
            }

            return [
                'designator_id' => $designator->id_designator,
                'designator_code' => $designator->code,
                'item_name' => $designator->item_name,
                'unit' => $designator->unit,
                'type' => strtoupper((string) ($designator->type?->code ?? 'MATERIAL')),
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'total_price' => round($qty * $unitPrice, 2),
            ];
        })->all();
    }

    private function syncLegacySnapshot(QeLop $lop, QeBoq $boq, User $actor): void
    {
        $rows = $boq->items->map(fn ($item) => [
            'designator' => $item->designator_code,
            'type' => $item->type,
            'uraian' => $item->item_name,
            'satuan' => $item->unit,
            'harga' => (float) $item->unit_price,
            'vol' => (int) round((float) $item->qty),
            'total' => (float) $item->total_price,
        ])->values();

        $lop->update([
            'package_id' => $boq->package_id,
            'boq_snapshot' => [
                'package_detected' => $boq->package?->code,
                'package_used' => $boq->package?->code,
                'imported_at' => now()->toDateTimeString(),
                'imported_by' => $actor->id_user,
                'material' => [
                    'count' => $rows->where('type', 'MATERIAL')->count(),
                    'total' => $rows->where('type', 'MATERIAL')->sum('total'),
                ],
                'jasa' => [
                    'count' => $rows->where('type', 'JASA')->count(),
                    'total' => $rows->where('type', 'JASA')->sum('total'),
                ],
                'grand_total' => $rows->sum('total'),
                'rows' => $rows->all(),
            ],
        ]);
    }

    private function snapshot(QeBoq $boq): array
    {
        $boq->loadMissing('items');

        return [
            'package_id' => $boq->package_id,
            'status' => $boq->status,
            'item_count' => $boq->item_count,
            'grand_total' => (float) $boq->grand_total,
            'items' => $boq->items->map(fn ($item) => [
                'designator_id' => $item->designator_id,
                'code' => $item->designator_code,
                'qty' => (int) round((float) $item->qty),
                'unit_price' => (float) $item->unit_price,
            ])->values()->all(),
        ];
    }

    private function recordHistory(
        QeBoq $boq,
        User $actor,
        string $event,
        ?array $before,
        ?array $after,
        ?string $note = null,
    ): void {
        QeBoqHistory::create([
            'qe_boq_id' => $boq->id_boq,
            'user_id' => $actor->id_user,
            'event_type' => $event,
            'before_data' => $before,
            'after_data' => $after,
            'note' => $note,
        ]);
    }
}
