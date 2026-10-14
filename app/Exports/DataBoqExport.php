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
    ) {}

    public function generator(): Generator
    {
        yield from $this->service->rows($this->user);
    }

    public function headings(): array
    {
        return ['Incident', 'Nama LOP', 'Designator', 'Item', 'Kategori', 'Reservasi', 'Aktual', 'Harga', 'Total'];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if ($value !== null && in_array($cell->getColumn(), ['A', 'B', 'C', 'D', 'E'], true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function columnFormats(): array
    {
        return ['F' => '#,##0', 'G' => '#,##0', 'H' => '#,##0.00', 'I' => '#,##0.00'];
    }

    public function columnWidths(): array
    {
        return ['A' => 24, 'B' => 45, 'C' => 28, 'D' => 65, 'E' => 16, 'F' => 16, 'G' => 16, 'H' => 20, 'I' => 22];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:I'.$sheet->getHighestRow());

        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E293B']]],
            'B' => ['alignment' => ['wrapText' => true]],
            'D' => ['alignment' => ['wrapText' => true]],
        ];
    }

    public function title(): string
    {
        return 'Data BOQ';
    }
}
