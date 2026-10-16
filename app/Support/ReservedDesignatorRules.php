<?php

namespace App\Support;

use App\Models\QeLop;
use App\Models\QeMaterialReservationItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReservedDesignatorRules
{
    public const FIXED_ACTUAL_CODES = ['J-CO-OF', 'J-CORING', 'J-OS-SM-1'];

    public static function fixedActual(?string $code): bool
    {
        return in_array(mb_strtoupper(trim((string) $code)), self::FIXED_ACTUAL_CODES, true);
    }

    public static function eligible(string $type, ?string $code): bool
    {
        return mb_strtoupper(trim($type)) === 'MATERIAL' || self::fixedActual($code);
    }

    public static function apply(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereRelation('type', 'code', 'MATERIAL')
            ->orWhereIn(DB::raw('UPPER(TRIM(code))'), self::FIXED_ACTUAL_CODES));
    }

    public static function matchKey(?string $code): string
    {
        $code = mb_strtoupper(trim((string) $code));

        return self::fixedActual($code) ? $code : (string) preg_replace('/^[MJ]-/', '', $code);
    }

    public static function specialSql(string $column): string
    {
        return 'UPPER(TRIM('.$column.')) IN ('.implode(',', array_map(fn ($code) => "'{$code}'", self::FIXED_ACTUAL_CODES)).')';
    }

    public static function actualComplete(QeMaterialReservationItem $item): bool
    {
        return $item->qty_actual !== null && (! self::fixedActual($item->designator?->code)
            || (float) $item->qty_actual === (float) $item->qty);
    }

    public static function workflowItems(QeLop $lop): Collection
    {
        $lop->loadMissing(['materialReservation.items.designator', 'boq.items.designator']);
        $items = $lop->materialReservation?->items ?? collect();
        $missing = ($lop->boq?->items ?? collect())->filter(fn ($item) => self::fixedActual($item->designator_code)
            && $item->designator_id !== null && ! $items->contains('designator_id', $item->designator_id));

        return $items->concat($missing->map(fn ($item) => (new QeMaterialReservationItem([
            'designator_id' => $item->designator_id, 'qty' => $item->qty,
        ]))->setRelation('designator', $item->designator)))->values();
    }
}
