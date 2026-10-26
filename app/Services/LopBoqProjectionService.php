<?php

namespace App\Services;

use App\Enums\ProgramType;
use App\Models\Designator;
use App\Models\DesignatorPackagePrice;
use App\Models\Package;
use App\Models\QeBoqItem;
use App\Models\QeLop;
use App\Models\QeMaterialReservationItem;
use App\Support\ReservedDesignatorRules;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Membentuk tampilan read-only BOQ Plan, BOQ Actual, dan Sisa Material per LOP.
 *
 * Reservasi berisi MATERIAL dan tiga jasa khusus yang wajib evidence. Relok Utilitas memakai JASA
 * pasangan virtual dari pola M-... -> J-... sehingga tidak
 * mengotori reservasi dan evidence per material.
 */
class LopBoqProjectionService
{
    public function __construct(private readonly RegionalPackageResolver $regionalPackages) {}

    private ?array $preparedPrices = null;

    /** Keep price reuse inside one synchronous batch, including when rendering throws. */
    public function withPreparedPrices(EloquentCollection $lops, Closure $callback): mixed
    {
        if ($lops->isEmpty()) {
            return $callback();
        }
        $lops->loadMissing([
            'package', 'boq.package', 'materialReservation.items.designator.type',
            'branchRef.regionRef', 'serviceArea.region', 'serviceArea.branch.regionRef',
        ]);
        $previous = $this->preparedPrices;
        $this->preparedPrices = ['services' => null, 'prices' => null, 'reference_resolved' => false, 'reference' => null];
        try {
            $packages = $lops->map(fn (QeLop $lop) => $this->packageFor($lop))->filter()->unique('id_package');
            $items = $lops->flatMap(fn (QeLop $lop) => $lop->materialReservation?->items ?? collect());
            $serviceCodes = $lops->filter(fn (QeLop $lop) => $lop->program_type->includesBoqServices())
                ->flatMap(fn (QeLop $lop) => $lop->materialReservation?->items ?? collect())
                ->map(fn ($item) => $this->serviceCode($item->designator?->code))->filter()->unique()->values();
            $services = $serviceCodes->isEmpty() ? collect() : Designator::query()->with('type')
                ->whereIn('code', $serviceCodes)->get()->toBase()->keyBy(fn (Designator $item) => mb_strtoupper($item->code));
            $ids = $items->pluck('designator_id')->concat($services->pluck('id_designator'))->filter()->unique()->values();
            $prices = $packages->isEmpty() || $ids->isEmpty() ? collect() : DesignatorPackagePrice::query()
                ->whereIn('package_id', $packages->pluck('id_package'))->whereIn('designator_id', $ids)
                ->get(['package_id', 'designator_id', 'price'])->groupBy('package_id')
                ->map(fn (Collection $rows) => $rows->pluck('price', 'designator_id'));
            $this->preparedPrices['services'] = $services;
            $this->preparedPrices['prices'] = $prices;

            return $callback();
        } finally {
            $this->preparedPrices = $previous;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function masterDataLines(QeLop $lop): array
    {
        return $this->masterData($lop)['lines'];
    }

    /** @return array<string, mixed> */
    public function masterData(QeLop $lop): array
    {
        $lop->loadMissing([
            'package',
            'boq.package',
            'boq.items.designator.type',
            'materialReservation.items.designator.type',
        ]);

        if ($lop->boq !== null) {
            $package = $lop->boq->package ?? $lop->package;

            return ['package' => $package, 'lines' => $this->actualLines($lop, $package, true)->all()];
        }

        return $this->report($lop, 'actual');
    }

    /** @return array<string, mixed> */
    public function report(QeLop $lop, string $report): array
    {
        $report = in_array($report, ['plan', 'actual', 'sisa'], true) ? $report : 'actual';
        $lop->loadMissing([
            'package',
            'boq.package',
            'boq.items.designator.type',
            'materialReservation.items.designator.type',
        ]);

        $hasSnapshot = $lop->boq !== null;
        $hasPlan = $lop->program_type !== ProgramType::RECOVERY && $hasSnapshot;
        $package = $this->packageFor($lop);

        $lines = match ($report) {
            'plan' => $hasPlan ? $this->planLines($lop) : collect(),
            'sisa' => $this->actualLines($lop, $package, $hasSnapshot)
                ->where('type', 'MATERIAL')->values(),
            default => $this->actualLines($lop, $package, $hasSnapshot),
        };

        $priced = $report === 'plan'
            ? $lines->isNotEmpty()
            : $package !== null || ($hasSnapshot && $lines->contains(fn (array $line): bool => $line['price'] !== null));

        return [
            'report' => $report,
            'has_plan' => $hasPlan,
            'priced' => $priced,
            'package' => $package,
            'package_label' => $package?->code ?? ($hasSnapshot ? 'Snapshot BOQ import' : 'Harga belum tersedia'),
            'uses_regional_package' => ! $hasSnapshot && $this->regionalPackages->forLop($lop) !== null,
            'lines' => $lines->all(),
            'grand' => $this->totals($lines, $priced),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function planLines(QeLop $lop): Collection
    {
        return ($lop->boq?->items ?? collect())->filter(fn (QeBoqItem $item) => $lop->program_type->allowsBoqType($item->type, $item->designator_code))->map(fn (QeBoqItem $item): array => [
            'designator_id' => $item->designator_id,
            'designator_code' => $item->designator_code,
            'designator_name' => $item->item_name,
            'unit' => $item->unit,
            'type' => mb_strtoupper((string) $item->type),
            'qty' => (float) $item->qty,
            'qty_actual' => null,
            'sisa' => null,
            'price' => (float) $item->unit_price,
            'total_plan' => (float) $item->total_price,
            'total_actual' => null,
            'nilai_sisa' => null,
            'price_missing' => false,
            'source' => 'plan',
        ])->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function actualLines(QeLop $lop, ?Package $package, bool $hasPlan): Collection
    {
        $reservationItems = ($lop->materialReservation?->items ?? collect())
            ->filter(fn (QeMaterialReservationItem $item) => ReservedDesignatorRules::eligible($item->designator?->type?->code ?? 'MATERIAL', $item->designator?->code))->values();
        $reservationByBase = $reservationItems->keyBy(
            fn (QeMaterialReservationItem $item): string => $this->baseCode($item->designator?->code),
        );

        if ($hasPlan) {
            $planItems = ($lop->boq?->items ?? collect())->filter(fn (QeBoqItem $item) => $lop->program_type->allowsBoqType($item->type, $item->designator_code));
            $coveredBases = $planItems
                ->map(fn (QeBoqItem $item): string => $this->baseCode($item->designator_code))
                ->filter()->unique();

            $lines = $planItems->map(function (QeBoqItem $item) use ($reservationByBase): array {
                $reservation = $reservationByBase->get($this->baseCode($item->designator_code));

                return $this->line(
                    designatorId: $item->designator_id,
                    code: $item->designator_code,
                    name: $item->item_name,
                    unit: $item->unit,
                    type: mb_strtoupper((string) $item->type),
                    qty: $reservation === null ? (float) $item->qty : (float) $reservation->qty,
                    qtyActual: $reservation?->qty_actual === null ? null : (float) $reservation->qty_actual,
                    price: (float) $item->unit_price,
                    source: 'plan',
                );
            });

            // Material tambahan teknisi tetap masuk BOQ Actual, tetapi tidak
            // mengubah snapshot BOQ Plan hasil import.
            $extra = $reservationItems->filter(fn (QeMaterialReservationItem $item): bool => ! $coveredBases->contains($this->baseCode($item->designator?->code))
            );

            return $lines->concat($this->reservationLines($extra, $package, $lop->program_type->includesBoqServices()))->values();
        }

        return $this->reservationLines($reservationItems, $package, $lop->program_type->includesBoqServices());
    }

    /**
     * @param  Collection<int, QeMaterialReservationItem>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function reservationLines(Collection $items, ?Package $package, bool $includeServicePair): Collection
    {
        if ($items->isEmpty()) {
            return collect();
        }

        $serviceCodes = $includeServicePair
            ? $items->map(fn (QeMaterialReservationItem $item): ?string => $this->serviceCode($item->designator?->code))
                ->filter()->unique()->values()
            : collect();
        if ($this->preparedPrices !== null) {
            $serviceDesignators = $this->preparedPrices['services']->only($serviceCodes->all());
        } else {
            $serviceDesignators = $serviceCodes->isEmpty() ? collect() : Designator::query()->with('type')
                ->whereIn('code', $serviceCodes)->get()->keyBy(fn (Designator $item): string => mb_strtoupper($item->code));
        }

        $designatorIds = $items->pluck('designator_id')
            ->concat($serviceDesignators->pluck('id_designator'))->filter()->unique()->values();
        if ($this->preparedPrices !== null) {
            $prices = $package === null ? collect() : $this->preparedPrices['prices']->get($package->getKey(), collect());
        } else {
            $prices = $package === null ? collect() : DesignatorPackagePrice::query()
                ->where('package_id', $package->id_package)
                ->whereIn('designator_id', $designatorIds)
                ->pluck('price', 'designator_id');
        }

        return $items->flatMap(function (QeMaterialReservationItem $item) use ($includeServicePair, $serviceDesignators, $prices): array {
            $material = $item->designator;
            $rows = [$this->line(
                designatorId: $item->designator_id,
                code: $material?->code ?? '—',
                name: $material?->item_name ?? 'Designator tidak ditemukan',
                unit: $material?->unit,
                type: mb_strtoupper($material?->type?->code ?? 'MATERIAL'),
                qty: (float) $item->qty,
                qtyActual: $item->qty_actual === null ? null : (float) $item->qty_actual,
                price: $this->price($prices, $item->designator_id),
                source: 'reservation',
            )];

            if (! $includeServicePair || ($serviceCode = $this->serviceCode($material?->code)) === null) {
                return $rows;
            }

            $service = $serviceDesignators->get(mb_strtoupper($serviceCode));
            if ($service === null) {
                return $rows;
            }

            $rows[] = $this->line(
                designatorId: $service->id_designator,
                code: $service->code,
                name: $service->item_name,
                unit: $service->unit,
                type: 'JASA',
                qty: (float) $item->qty,
                qtyActual: $item->qty_actual === null ? null : (float) $item->qty_actual,
                price: $this->price($prices, $service->id_designator),
                source: 'auto_service',
            );

            return $rows;
        })->values();
    }

    /** @return array<string, mixed> */
    private function line(
        int|string|null $designatorId,
        string $code,
        string $name,
        ?string $unit,
        string $type,
        float $qty,
        ?float $qtyActual,
        ?float $price,
        string $source,
    ): array {
        $isMaterial = $type === 'MATERIAL';
        $sisa = $isMaterial && $qtyActual !== null ? max(0, $qty - $qtyActual) : null;

        return [
            'designator_id' => $designatorId,
            'designator_code' => $code,
            'designator_name' => $name,
            'unit' => $unit,
            'type' => $type,
            'qty' => $qty,
            'qty_actual' => $qtyActual,
            'sisa' => $sisa,
            'price' => $price,
            'total_plan' => $price === null ? null : round($qty * $price, 2),
            'total_actual' => $qtyActual === null || $price === null ? null : round($qtyActual * $price, 2),
            'nilai_sisa' => $sisa === null || $price === null ? null : round($sisa * $price, 2),
            'price_missing' => $price === null,
            'source' => $source,
        ];
    }

    /** @return array<string, int|float|null> */
    private function totals(Collection $lines, bool $priced): array
    {
        return [
            'qty' => (float) $lines->sum('qty'),
            'qty_actual' => (float) $lines->sum(fn (array $line): float => (float) ($line['qty_actual'] ?? 0)),
            'sisa' => (float) $lines->sum(fn (array $line): float => (float) ($line['sisa'] ?? 0)),
            'total_plan' => $priced ? (float) $lines->sum(fn (array $line): float => (float) ($line['total_plan'] ?? 0)) : null,
            'total_actual' => $priced ? (float) $lines->sum(fn (array $line): float => (float) ($line['total_actual'] ?? 0)) : null,
            'nilai_sisa' => $priced ? (float) $lines->sum(fn (array $line): float => (float) ($line['nilai_sisa'] ?? 0)) : null,
            'material_total' => $priced ? (float) $lines->where('type', 'MATERIAL')->sum(fn (array $line): float => (float) ($line['total_actual'] ?? 0)) : null,
            'service_total' => $priced ? (float) $lines->where('type', 'JASA')->sum(fn (array $line): float => (float) ($line['total_actual'] ?? 0)) : null,
            'price_missing_count' => $lines->where('price_missing', true)->pluck('designator_id')->unique()->count(),
            'not_recapped_count' => $lines->whereNull('qty_actual')->count(),
            'line_count' => $lines->count(),
            'sisa_items_count' => $lines->filter(fn (array $line): bool => ($line['sisa'] ?? 0) > 0)->count(),
        ];
    }

    private function baseCode(?string $code): string
    {
        return ReservedDesignatorRules::matchKey($code);
    }

    private function serviceCode(?string $materialCode): ?string
    {
        $code = mb_strtoupper(trim((string) $materialCode));

        $service = str_starts_with($code, 'M-') ? 'J-'.mb_substr($code, 2) : null;

        return ReservedDesignatorRules::fixedActual($service) ? null : $service;
    }

    private function price(Collection $prices, int|string|null $designatorId): ?float
    {
        $price = $designatorId === null ? null : $prices->get($designatorId);

        return $price === null ? null : (float) $price;
    }

    private function referencePackage(): ?Package
    {
        if ($this->preparedPrices === null) {
            return Package::query()->whereHas('prices')->orderByDesc('id_package')->first();
        }
        if (! $this->preparedPrices['reference_resolved']) {
            $this->preparedPrices['reference'] = Package::query()->whereHas('prices')->orderByDesc('id_package')->first();
            $this->preparedPrices['reference_resolved'] = true;
        }

        return $this->preparedPrices['reference'];
    }

    private function packageFor(QeLop $lop): ?Package
    {
        return $lop->boq !== null
            ? ($lop->boq->package ?? $lop->package)
            : ($this->regionalPackages->forLop($lop) ?? $lop->package ?? $this->referencePackage());
    }
}
