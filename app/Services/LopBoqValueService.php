<?php

namespace App\Services;

use App\Enums\ProgramType;
use App\Models\Package;
use App\Models\QeLop;

/**
 * Menyatukan representasi nilai BOQ Plan dan fallback BOQ Aktual yang
 * dibentuk dari reservasi material teknisi ketika LOP tidak memiliki BOQ.
 */
class LopBoqValueService
{
    public function __construct(
        private readonly RegionalPackageResolver $regionalPackages,
        private readonly LopBoqProjectionService $projection,
    ) {}

    private bool $referencePackageResolved = false;

    private ?Package $referencePackage = null;

    public function referencePackage(): ?Package
    {
        if (! $this->referencePackageResolved) {
            $this->referencePackage = Package::query()
                ->whereHas('prices')
                ->orderByDesc('id_package')
                ->first();
            $this->referencePackageResolved = true;
        }

        return $this->referencePackage;
    }

    public function referencePackageId(): ?int
    {
        return $this->referencePackage()?->id_package;
    }

    /** @return array<string, mixed> */
    public function summarize(QeLop $lop): array
    {
        $lop->loadMissing([
            'package',
            'boq.package',
            'boq.items.designator.type',
            'materialReservation.items.designator.type',
        ]);

        if ($lop->boq !== null && $lop->program_type !== ProgramType::RECOVERY) {
            $items = $lop->boq->items->map(fn ($item): array => [
                'designator_id' => $item->designator_id,
                'code' => $item->designator_code,
                'name' => $item->item_name,
                'unit' => $item->unit,
                'type' => $item->type,
                'qty' => (float) $item->qty,
                'qty_reserved' => null,
                'qty_actual' => null,
                'unit_price' => (float) $item->unit_price,
                'total' => (float) $item->total_price,
                'price_missing' => false,
            ]);

            return $this->summaryPayload(
                'plan',
                'BOQ Plan',
                $lop->boq->package,
                false,
                $items,
            );
        }

        $regionalPackage = $this->regionalPackages->forLop($lop);
        $projection = $this->projection->report($lop, 'actual');
        $package = $projection['package'];
        $usesRegionalPackage = $regionalPackage !== null;
        $usesReferencePackage = ! $usesRegionalPackage && $lop->package_id === null && $package !== null;
        $items = collect($projection['lines'])->map(function (array $item): array {
            $qtyActual = $item['qty_actual'] === null ? null : (float) $item['qty_actual'];

            return [
                'designator_id' => $item['designator_id'],
                'code' => $item['designator_code'],
                'name' => $item['designator_name'],
                'unit' => $item['unit'] ?? '—',
                'type' => $item['type'],
                'qty' => $qtyActual ?? 0.0,
                'qty_reserved' => (float) $item['qty'],
                'qty_actual' => $qtyActual,
                'unit_price' => $item['price'],
                'total' => (float) ($item['total_actual'] ?? 0),
                'price_missing' => $item['price_missing'],
                'source' => $item['source'],
            ];
        })->values();

        return $this->summaryPayload(
            'reservation',
            'BOQ Aktual · Reservasi Teknisi',
            $package,
            $usesReferencePackage,
            $items,
            $usesRegionalPackage,
        );
    }

    /** @return array<string, mixed> */
    private function summaryPayload(
        string $source,
        string $sourceLabel,
        ?Package $package,
        bool $usesReferencePackage,
        $items,
        bool $usesRegionalPackage = false,
    ): array {
        return [
            'source' => $source,
            'source_label' => $sourceLabel,
            'is_plan' => $source === 'plan',
            'package' => $package,
            'package_label' => $package
                ? $package->code.($usesRegionalPackage ? ' · otomatis wilayah' : ($usesReferencePackage ? ' · referensi otomatis' : ''))
                : 'Harga belum tersedia',
            'uses_regional_package' => $usesRegionalPackage,
            'uses_reference_package' => $usesReferencePackage,
            'items' => $items,
            'item_count' => $items->count(),
            'service_total' => (float) $items->where('type', 'JASA')->sum('total'),
            'material_total' => (float) $items->where('type', 'MATERIAL')->sum('total'),
            'grand_total' => (float) $items->sum('total'),
            'missing_price_count' => $items->where('price_missing', true)->count(),
        ];
    }
}
