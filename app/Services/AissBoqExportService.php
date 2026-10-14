<?php

namespace App\Services;

use App\Models\QeLop;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class AissBoqExportService
{
    public function __construct(private readonly LopBoqProjectionService $projection) {}

    public function workbook(QeLop $lop): Spreadsheet
    {
        $lop->loadMissing(['branchRef', 'serviceArea.branch']);
        $payload = $this->projection->masterData($lop);
        $packageCode = $payload['package']?->code;
        $package = $packageCode !== null && preg_match('/^(?:paket[\s_-]*)?(\d+)$/i', trim($packageCode), $matches)
            ? (int) $matches[1]
            : $packageCode;
        $sto = $lop->serviceArea?->workzone ?? $lop->sto;
        $branch = $lop->branchRef?->name ?? $lop->serviceArea?->branch?->name ?? $lop->branch;

        $book = IOFactory::load(base_path('plans/Format AISS BOQ AWAL.xlsx'));
        $sheet = $book->getActiveSheet();
        $styles = [];
        foreach (range('A', 'L') as $column) {
            $styles[$column] = clone $sheet->getStyle($column.'2')->getSharedComponent();
        }
        if ($sheet->getHighestRow() > 1) {
            $sheet->removeRow(2, $sheet->getHighestRow() - 1);
        }

        foreach ($payload['lines'] as $index => $line) {
            $row = [
                $lop->ihld_id,
                $line['designator_code'],
                $line['designator_name'],
                $line['unit'],
                $line['qty_actual'],
                $line['price'],
                $line['total_actual'],
                $package,
                $sto,
                $branch,
                $lop->nama_lop,
                $branch,
            ];
            foreach ($row as $columnIndex => $value) {
                $column = Coordinate::stringFromColumnIndex($columnIndex + 1);
                $coordinate = $column.($index + 2);
                $sheet->duplicateStyle($styles[$column], $coordinate);
                if ($value !== null) {
                    $sheet->setCellValueExplicit($coordinate, $value, is_string($value) ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC);
                }
            }
        }

        return $book;
    }
}
