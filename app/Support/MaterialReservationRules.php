<?php

namespace App\Support;

use App\Enums\LopStatus;
use App\Models\QeLop;
use App\Models\QeMaterialReservationItem;
use Illuminate\Validation\ValidationException;

class MaterialReservationRules
{
    public static function editable(QeLop $lop): bool
    {
        return in_array($lop->status_lop, [LopStatus::DRAFT, LopStatus::ASSIGNED, LopStatus::PICKED_UP, LopStatus::SURVEY, LopStatus::PROGRESS, LopStatus::WAITING_APPROVAL, LopStatus::REJECTED], true);
    }

    public static function assertQuantity(QeMaterialReservationItem $item, int $qty): void
    {
        if ($item->qty_actual !== null && (float) $item->qty_actual > $qty) {
            $code = $item->designator?->code ?? (string) $item->designator_id;
            throw ValidationException::withMessages(['items' => "Qty {$code} tidak boleh lebih kecil dari qty terpakai."]);
        }
    }

    public static function assertRemovable(QeMaterialReservationItem $item, bool $hasEvidence): void
    {
        if (! self::removable($item, $hasEvidence)) {
            $code = $item->designator?->code ?? (string) $item->designator_id;
            throw ValidationException::withMessages(['items' => "Item {$code} tidak dapat dihapus karena sudah memiliki evidence atau rekap pemakaian."]);
        }
    }

    public static function removable(QeMaterialReservationItem $item, bool $hasEvidence): bool
    {
        return $item->qty_actual === null && ! $hasEvidence;
    }
}
