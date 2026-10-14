<?php

namespace App\Services;

use App\Models\QeLop;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class AissBoqExportService
{
    private const HEADERS = [
        'ID IHLD', 'DESIGNATOR', 'URAIAN PEKERJAAN', 'SATUAN', 'QTY', 'HARGA',
        'TOTAL', 'PAKET', 'STO', 'WITEL', 'NAMA_PROYEK', 'BRANCH',
    ];

    // Layout captured from the original AISS workbook; no runtime template needed.
    private const COLUMN_WIDTHS = [
        'A' => 9,
        'B' => 36.1796875,
        'C' => 53.453125,
        'D' => 20.453125,
        'E' => 10.453125,
    ];

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

        $book = new Spreadsheet;
        $book->getDefaultStyle()->getFont()->setName('Aptos Narrow')->setSize(11);
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('PELIMPAHAN');
        foreach (self::HEADERS as $columnIndex => $header) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($columnIndex + 1).'1', $header, DataType::TYPE_STRING);
        }
        foreach (self::COLUMN_WIDTHS as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
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
                if ($value !== null) {
                    $sheet->setCellValueExplicit($coordinate, $value, is_string($value) ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC);
                }
            }
        }

        return $book;
    }
}
