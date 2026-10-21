<?php

namespace App\Exports;

use App\Models\User;
use App\Services\DataBoqExportService;
use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DataBoqExport extends DefaultValueBinder implements FromGenerator, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(
        private readonly DataBoqExportService $service,
        private readonly User $user,
        private readonly string $dateFrom = '',
        private readonly string $dateTo = '',
    ) {}

    public function generator(): Generator
    {
        yield from $this->service->rows($this->user, $this->dateFrom, $this->dateTo);
    }

    public function headings(): array
    {
        return ['ID IHLD', 'Incident', 'Reported Date', 'Nama LOP', 'Designator', 'Item', 'Kategori', 'Reservasi', 'Aktual', 'Harga', 'Total'];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if ($value !== null && in_array($cell->getColumn(), ['A', 'B', 'C', 'D', 'E', 'F', 'G'], true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function columnFormats(): array
    {
        return ['H' => '#,##0', 'I' => '#,##0', 'J' => '#,##0.00', 'K' => '#,##0.00'];
    }

    public function columnWidths(): array
    {
        return ['A' => 22, 'B' => 24, 'C' => 16, 'D' => 45, 'E' => 28, 'F' => 65, 'G' => 16, 'H' => 16, 'I' => 16, 'J' => 20, 'K' => 22];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:K'.$sheet->getHighestRow());

        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E293B']]],
            'D' => ['alignment' => ['wrapText' => true]],
            'F' => ['alignment' => ['wrapText' => true]],
        ];
    }

    public function title(): string
    {
        return 'Data BOQ';
    }
}
