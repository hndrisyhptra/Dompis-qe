<?php

namespace App\Exports;

use App\Enums\ExcludeReason;
use Generator;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TicketCoverageExport extends DefaultValueBinder implements FromGenerator, WithColumnWidths, WithCustomValueBinder, WithStrictNullComparison, WithStyles, WithTitle
{
    public const HEADER_ROW = 17;

    public const CATEGORIES = ['all' => 'Total Tiket', 'without_lop' => 'Belum Memiliki LOP', 'with_lop' => 'Sudah Memiliki LOP', 'excluded' => 'Tiket Exclude'];

    public function __construct(private readonly array $data) {}

    public function generator(): Generator
    {
        $filters = $this->data['filters'];
        yield ['Monitoring Incident'];
        yield ['Scope', $this->data['scopeLabel']];
        yield ['Dasar tanggal', 'reported_date tiket Dompis'];
        yield ['Tanggal mulai', $filters['date_from'] ?: 'Tanpa batas'];
        yield ['Tanggal akhir', $filters['date_to'] ?: 'Tanpa batas'];
        yield ['Wilayah', $this->data['locationLabel']];
        yield ['Jenis tiket', $filters['kind'] === '' ? 'GAMAS dan INFRACARE' : strtoupper($filters['kind'])];
        yield ['Pencarian', $filters['q'] === '' ? 'Semua' : $filters['q']];
        yield ['Kategori unduhan', self::CATEGORIES[$filters['usage']]];
        yield ['Dibaca pada (WIB)', $this->data['checkedAt']?->format('d/m/Y H:i:s') ?? 'Tidak ada STO dalam scope'];
        yield [null];
        yield ['Total Tiket', $this->data['stats']['total']];
        yield ['Belum Memiliki LOP', $this->data['stats']['without_lop']];
        yield ['Sudah Memiliki LOP', $this->data['stats']['with_lop']];
        yield ['Tiket Exclude', $this->data['stats']['excluded'] ?? 0];
        yield ['Ringkasan seluruh penggunaan sesuai filter. Daftar mengikuti kategori unduhan.'];
        yield ['Incident', 'Tanggal Laporan', 'Jenis Tiket', 'STO', 'Branch', 'Pemakaian LOP', 'ID IHLD', 'Nama LOP', 'Status Dompis', 'Keterangan'];
        foreach ($this->data['rows'] as $row) {
            $lop = $row['lop'];
            $keterangan = $row['usage'] === 'with_lop' && $lop === null ? 'Detail LOP tidak tersedia' : null;
            if ($row['exclude_reason'] !== null) {
                $excludeLabel = ExcludeReason::tryFrom($row['exclude_reason'])?->label() ?? $row['exclude_reason'];
                $keterangan = $keterangan ? $keterangan.'; Dikecualikan: '.$excludeLabel : 'Dikecualikan: '.$excludeLabel;
            }
            yield [
                $row['incident'],
                $row['reported_date'] === null ? null : Date::dateTimeToExcel(Carbon::createFromFormat('!d/m/Y', $row['reported_date'])),
                $row['ticket_type'], $row['workzone'], $row['branch'],
                self::CATEGORIES[$row['usage']],
                $lop?->ihld_id, $lop?->nama_lop, $lop?->status_lop->label(),
                $keterangan,
            ];
        }
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if ($value !== null && ! ($cell->getColumn() === 'B' && ($cell->getRow() > self::HEADER_ROW || in_array($cell->getRow(), [12, 13, 14, 15], true)))) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function columnWidths(): array
    {
        return ['A' => 29, 'B' => 23, 'C' => 26, 'D' => 17, 'E' => 24, 'F' => 26, 'G' => 24, 'H' => 65, 'I' => 24, 'J' => 36];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();
        $sheet->setShowGridlines(false);
        $sheet->freezePane('B18');
        $sheet->setAutoFilter('A17:J'.$sheet->getHighestRow());
        $sheet->getDefaultRowDimension()->setRowHeight(21);
        $sheet->getStyle('A1:J'.$sheet->getHighestRow())->getFont()->setName('Arial')->setSize(11);
        $sheet->getStyle('A1:J'.$sheet->getHighestRow())->getAlignment()->setVertical('center');
        $sheet->getStyle('B12:B15')->getAlignment()->setHorizontal('right');
        $sheet->getStyle('B12:B15')->getNumberFormat()->setFormatCode('#,##0');
        if ($lastRow > self::HEADER_ROW) {
            $sheet->getStyle('B18:B'.$lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            $sheet->getStyle('H18:J'.$lastRow)->getAlignment()->setWrapText(true);
            for ($row = self::HEADER_ROW + 1; $row <= $lastRow; $row++) {
                $sheet->getRowDimension($row)->setRowHeight(-1);
            }
        }
        $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(28);

        return [
            1 => ['font' => ['bold' => true, 'size' => 15]],
            16 => ['font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '64748B']]],
            self::HEADER_ROW => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E293B']], 'alignment' => ['horizontal' => 'center']],
            'A12:B15' => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Monitoring Incident';
    }
}
