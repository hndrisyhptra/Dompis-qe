<?php

namespace App\Exports;

use App\Services\MaterialReportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Export .xlsx Laporan Sisa Material. Lihat BoqActualExport - bentuknya sama,
 * beda hanya kolom & judul sheet.
 */
class SisaMaterialExport implements FromArray, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    public function __construct(
        private readonly MaterialReportService $service,
        private readonly array $payload,
        private readonly string $report,   // 'sisa'
        private readonly string $mode,     // 'per_lop' | 'rekap'
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function array(): array
    {
        return $this->service->flatten($this->payload, $this->report, $this->mode);
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return array_values($this->service->columns($this->report, $this->mode, $this->payload['priced']));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        $keys = array_keys($this->service->columns($this->report, $this->mode, $this->payload['priced']));

        return array_map(fn ($k) => $row[$k] ?? '', $keys);
    }

    public function title(): string
    {
        return 'Sisa Material';
    }
}
