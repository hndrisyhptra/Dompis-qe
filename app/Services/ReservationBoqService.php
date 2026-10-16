<?php

namespace App\Services;

use App\Enums\LopStatus;
use App\Models\Designator;
use App\Models\QeLop;
use App\Models\QeMaterialReservation;
use App\Models\User;
use App\Support\MaterialReservationRules;
use App\Support\ReservedDesignatorRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReservationBoqService
{
    public function __construct(private readonly LopService $lopService) {}

    public static function version(QeMaterialReservation $reservation): string
    {
        $reservation->loadMissing('items');

        return hash('sha256', json_encode([
            'id' => $reservation->id_reservation,
            'technician_id' => $reservation->technician_id,
            'status' => $reservation->status,
            'items' => $reservation->items->sortBy('id_reservation_item')->map(fn ($item) => [
                $item->id_reservation_item, $item->designator_id, $item->qty, $item->qty_actual,
            ])->values()->all(),
        ], JSON_THROW_ON_ERROR));
    }

    public function update(QeLop $lop, array $items, string $version, User $actor): QeMaterialReservation
    {
        Gate::forUser($actor)->authorize('update', $lop);
        Validator::make(['items' => $items], [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.designator_id' => ['required', 'integer', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999999999999'],
        ])->validate();

        return DB::transaction(function () use ($lop, $items, $version, $actor) {
            $lockedLop = QeLop::whereKey($lop->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $lockedLop);
            if (! MaterialReservationRules::editable($lockedLop)) {
                throw ValidationException::withMessages(['items' => 'BOQ aktual terkunci karena LOP Completed. Reset LOP terlebih dahulu.']);
            }
            if ($lockedLop->boq()->exists()) {
                throw ValidationException::withMessages(['items' => 'LOP sudah memiliki BOQ Plan. Gunakan Kelola item BOQ pada tabel BOQ Plan.']);
            }

            $reservation = $lockedLop->materialReservation()->lockForUpdate()->first();
            if ($reservation === null) {
                throw ValidationException::withMessages(['items' => 'Reservasi teknisi belum tersedia.']);
            }
            $reservation->setRelation('items', $reservation->items()->with('designator')->lockForUpdate()->get());
            if (! hash_equals(self::version($reservation), $version)) {
                throw ValidationException::withMessages(['items' => 'Reservasi telah berubah sejak halaman dibuka. Muat ulang Data BOQ agar perubahan teknisi tidak tertimpa.']);
            }

            $before = $this->snapshot($reservation);
            $targets = collect($items)->keyBy(fn ($item) => (int) $item['designator_id']);
            $existingIds = $reservation->items->pluck('designator_id')->map(fn ($id) => (int) $id);
            $newIds = $targets->keys()->diff($existingIds);
            $materialIds = ReservedDesignatorRules::apply(Designator::whereIn('id_designator', $newIds))->pluck('id_designator');
            if ($materialIds->count() !== $newIds->count()) {
                throw ValidationException::withMessages(['items' => 'Reservasi hanya menerima MATERIAL serta J-CO-OF, J-Coring, J-OS-SM-1 yang masih tersedia.']);
            }
            $evidenceIds = $lockedLop->evidences()->whereNotNull('designator_id')->pluck('designator_id')->map(fn ($id) => (int) $id);

            foreach ($reservation->items as $item) {
                $target = $targets->get((int) $item->designator_id);
                if ($target === null) {
                    MaterialReservationRules::assertRemovable($item, $evidenceIds->contains((int) $item->designator_id));
                    $item->delete();
                } else {
                    MaterialReservationRules::assertQuantity($item, (int) $target['qty']);
                    $item->update(['qty' => (int) $target['qty']]);
                }
            }
            foreach ($newIds as $designatorId) {
                $reservation->items()->create(['designator_id' => $designatorId, 'qty' => (int) $targets[$designatorId]['qty']]);
            }
            $reservation->update(['status' => 'draft', 'submitted_at' => null]);
            $reservation->load('items.designator');
            $this->lopService->recordReservationChange($lockedLop, $actor, $before, $this->snapshot($reservation));
            if ($lockedLop->status_lop === LopStatus::WAITING_APPROVAL && $newIds->isNotEmpty()) {
                $this->lopService->transitionStatus($lockedLop, LopStatus::PROGRESS, $actor, 'BOQ mendapat material tambahan; teknisi perlu melengkapi evidence dan rekap aktual lalu mengajukan kembali.');
            }

            return $reservation;
        });
    }

    private function snapshot(QeMaterialReservation $reservation): array
    {
        return [
            'reservation_id' => $reservation->id_reservation,
            'technician_id' => $reservation->technician_id,
            'status' => $reservation->status,
            'items' => $reservation->items->map(fn ($item) => [
                'designator_id' => $item->designator_id,
                'code' => $item->designator?->code,
                'qty' => (int) $item->qty,
                'qty_actual' => $item->qty_actual === null ? null : (float) $item->qty_actual,
            ])->values()->all(),
        ];
    }
}
