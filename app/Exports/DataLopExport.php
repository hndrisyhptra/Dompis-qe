<?php

namespace App\Exports;

use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
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

class DataLopExport extends DefaultValueBinder implements FromGenerator, WithColumnWidths, WithCustomValueBinder, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(private readonly iterable $rows) {}

    public function generator(): Generator
    {
        yield from $this->rows;
    }

    public function headings(): array
    {
        return ['ID IHLD', 'Incident', 'Nama LOP', 'STO', 'Branch', 'Area', 'Program', 'Segmen', 'Budget Type', 'Status Dompis', 'Status AISS', 'BOQ', 'Jumlah Item BOQ', 'Deskripsi Pekerjaan', 'Dibuat Oleh', 'Tanggal Dibuat (WIB)'];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if ($value !== null && ($cell->getRow() === 1 || ! in_array($cell->getColumn(), ['M', 'P'], true))) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function columnWidths(): array
    {
        return ['A' => 24, 'B' => 26, 'C' => 55, 'D' => 14, 'E' => 25, 'F' => 12, 'G' => 25, 'H' => 25, 'I' => 16, 'J' => 23, 'K' => 25, 'L' => 24, 'M' => 20, 'N' => 65, 'O' => 26, 'P' => 26];
    }

    public function styles(Worksheet $sheet): array
    {
        $last = $sheet->getHighestRow();
        $sheet->setShowGridlines(false);
        $sheet->freezePane('C2');
        $sheet->setAutoFilter('A1:P'.$last);
        $sheet->getStyle('A1:P'.$last)->getFont()->setName('Arial')->setSize(11);
        $sheet->getStyle('A1:P'.$last)->getAlignment()->setVertical('center');
        $sheet->getRowDimension(1)->setRowHeight(30);
        if ($last > 1) {
            $sheet->getStyle('A2:L'.$last)->getNumberFormat()->setFormatCode('@');
            $sheet->getStyle('A2:L'.$last)->getAlignment()->setHorizontal('left');
            $sheet->getStyle('N2:O'.$last)->getAlignment()->setHorizontal('left');
            $sheet->getStyle('M2:M'.$last)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('P2:P'.$last)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
            foreach (['C', 'N'] as $column) {
                $sheet->getStyle($column.'2:'.$column.$last)->getAlignment()->setWrapText(true);
            }
        }

        return [1 => [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => 'center'],
        ]];
    }

    public function title(): string
    {
        return 'Data LOP';
    }
}
