<?php

namespace App\Support;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceStatus;
use App\Enums\ProgramType;
use App\Models\QeLop;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MaterialArrivalRules
{
    public static function perDesignator(QeLop $lop): bool
    {
        return $lop->program_type === ProgramType::RELOK_UTILITAS;
    }

    /** Material ber-volume dari snapshot BOQ Plan, bukan harga/master atau reservasi aktual. */
    public static function items(QeLop $lop): Collection
    {
        if (! self::perDesignator($lop)) {
            return collect();
        }
        $lop->loadMissing('boq.items');

        return ($lop->boq?->items ?? collect())
            ->filter(fn ($item) => mb_strtoupper(trim($item->type)) === 'MATERIAL' && (float) $item->qty > 0)
            ->values();
    }

    public static function complete(QeLop $lop, Collection $evidences, ?EvidenceStatus $requiredStatus = null): bool
    {
        $proof = $evidences->where('category', EvidenceCategory::MATERIAL_ARRIVAL)
            ->filter(fn ($e) => $requiredStatus ? $e->status === $requiredStatus : $e->status !== EvidenceStatus::REJECTED);
        if (! self::perDesignator($lop)) {
            return $proof->isNotEmpty();
        }
        $items = self::items($lop);
        $ids = $proof->pluck('designator_id')->filter()->unique();

        return $items->isNotEmpty() && $items->every(fn ($item) => $item->designator_id !== null && $ids->contains($item->designator_id));
    }

    public static function validateDesignator(QeLop $lop, mixed $designatorId): void
    {
        if (self::perDesignator($lop) && (! $designatorId || ! self::items($lop)->contains('designator_id', $designatorId))) {
            throw ValidationException::withMessages([
                'designator_id' => 'Material Tiba QE Relok Utilitas wajib diupload per item material yang memiliki volume pada BOQ Plan. Jika BOQ Plan belum tersedia, hubungi admin.',
            ]);
        }
    }
}
