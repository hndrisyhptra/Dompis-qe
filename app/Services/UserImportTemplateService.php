<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Region;
use App\Models\Role;
use App\Models\ServiceArea;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UserImportTemplateService
{
    public function samples(string $scope = 'all'): array
    {
        $area = Area::where('is_active', true)->orderBy('code')->first();
        $region = Region::where('is_active', true)->orderBy('code')->first();
        $branch = Branch::where('is_active', true)->orderBy('code')->first();
        $primary = ServiceArea::where('is_active', true)->orderBy('workzone')->first();
        $codes = $primary ? ServiceArea::where('is_active', true)->where('branch_id', $primary->branch_id)
            ->orderBy('workzone')->limit(2)->pluck('workzone')->implode('|') : 'ISI_KODE_STO';
        $rows = [];
        foreach (['area', 'region', 'branch', 'service_area', 'non_admin'] as $level) {
            if ($scope !== 'all' && $scope !== $level) {
                continue;
            }
            $rows[] = [
                'name' => 'CONTOH '.($level === 'non_admin' ? 'Teknisi' : 'Admin '.str_replace('_', ' ', $level)),
                'username' => 'CONTOH_'.strtoupper($level), 'nik' => '', 'email' => '', 'phone' => '', 'password' => '',
                'role' => $level === 'non_admin' ? 'TEKNISI' : 'ADMIN', 'status' => 'inactive',
                'admin_scope_type' => $level === 'non_admin' ? '' : $level,
                'area' => $level === 'area' ? ($area?->code ?? 'ISI_KODE_AREA') : '',
                'region' => $level === 'region' ? ($region?->code ?? 'ISI_KODE_REGION') : '',
                'branch' => in_array($level, ['branch', 'non_admin'], true) ? ($branch?->code ?? 'ISI_KODE_BRANCH') : '',
                'service_areas' => $level === 'service_area' ? $codes : '',
            ];
        }

        return array_map(fn (array $row): array => array_map(fn (string $header): string => (string) $row[$header], UserImportService::HEADERS), $rows);
    }

    public function workbook(string $scope): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Users');
        $rows = [UserImportService::HEADERS, ...$this->samples($scope)];
        $this->writeTextRows($sheet, $rows);
        $sheet->freezePane('C2');
        $sheet->setAutoFilter('A1:M'.count($rows));
        $widths = [28, 25, 20, 28, 20, 25, 20, 16, 23, 20, 25, 25, 32];
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
        }
        $sheet->getStyle('A1:M1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:M1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF1F2937');
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getStyle('B2:F1001')->getNumberFormat()->setFormatCode('@');

        $guide = $book->createSheet()->setTitle('Panduan');
        $this->writeTextRows($guide, [
            ['Kolom / Scope', 'Cara mengisi'],
            ['Wajib', 'name, username, password (minimal 8 karakter), role. Ganti/hapus seluruh baris CONTOH sebelum upload.'],
            ['Password', 'Isi password awal tiap akun. Jangan bagikan file berisi password. File upload akan dihapus setelah diproses.'],
            ['Username / NIK / Phone', 'Gunakan format Text agar nol depan dan angka panjang tidak berubah. NIK/email/phone opsional.'],
            ['Status', 'active atau inactive. Contoh memakai inactive; ubah menjadi active jika akun boleh login. Kosong berarti active.'],
            ['Admin Area', 'role=ADMIN, admin_scope_type=area, isi area. Kosongkan region, branch, service_areas.'],
            ['Admin Region', 'role=ADMIN, admin_scope_type=region, isi region. Area opsional jika sesuai. Kosongkan branch dan service_areas.'],
            ['Admin Branch', 'role=ADMIN, admin_scope_type=branch, isi branch. Area/region opsional jika sesuai. Kosongkan service_areas.'],
            ['Admin Service Area', 'role=ADMIN, admin_scope_type=service_area, isi kode STO di service_areas. Banyak STO dipisah |, contoh SDA|SDB.'],
            ['Multi Service Area', 'Boleh lintas branch. Kosongkan parent area/region/branch jika berbeda. Maksimal 100 Service Area per user.'],
            ['Role selain Admin', 'SUPER_ADMIN, TEKNISI, MANAGER, APPROVER. Branch opsional. Kosongkan admin_scope_type, area, region, service_areas.'],
            ['Duplikasi', 'Username/NIK/email yang sudah ada (termasuk terhapus) tidak ditimpa. Baris gagal dicatat, baris valid tetap dibuat.'],
            ['Batas file', 'Excel XLSX/XLS atau CSV, maksimal 5 MB dan 1000 user per file. Import diproses melalui worker antrean.'],
            ['Sheet import', 'Isi sheet Users dan jangan ubah nama header. Referensi lokasi ada pada sheet berikutnya. Sheet panduan/referensi tidak diimport.'],
        ]);
        $guide->getColumnDimension('A')->setWidth(28);
        $guide->getColumnDimension('B')->setWidth(110);
        $guide->getStyle('B1:B14')->getAlignment()->setWrapText(true);

        $references = [['Jenis', 'Kode', 'Nama', 'Induk']];
        foreach (Role::orderBy('code')->get() as $role) {
            $references[] = ['Role', $role->code, $role->name, ''];
        }
        foreach (Area::where('is_active', true)->orderBy('code')->get() as $area) {
            $references[] = ['Area', $area->code, $area->name, ''];
        }
        foreach (Region::with('area')->where('is_active', true)->orderBy('code')->get() as $region) {
            $references[] = ['Region', $region->code, $region->name, $region->area?->code ?? ''];
        }
        foreach (Branch::with('regionRef')->where('is_active', true)->orderBy('code')->get() as $branch) {
            $references[] = ['Branch', $branch->code, $branch->name, $branch->regionRef?->code ?? ''];
        }
        foreach (ServiceArea::with('branch')->where('is_active', true)->orderBy('workzone')->get() as $serviceArea) {
            $references[] = ['Service Area', $serviceArea->workzone, $serviceArea->name, $serviceArea->branch?->code ?? ''];
        }
        $referenceSheet = $book->createSheet()->setTitle('Referensi Lokasi');
        $this->writeTextRows($referenceSheet, $references);
        foreach (['A' => 20, 'B' => 26, 'C' => 40, 'D' => 25] as $column => $width) {
            $referenceSheet->getColumnDimension($column)->setWidth($width);
        }
        $referenceSheet->freezePane('A2');
        $book->setActiveSheetIndex(0);

        return $book;
    }

    private function writeTextRows(Worksheet $sheet, array $rows): void
    {
        $sheet->setShowGridlines(false);
        foreach ($rows as $index => $row) {
            foreach ($row as $column => $value) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($column + 1).($index + 1), (string) $value, DataType::TYPE_STRING);
            }
        }
        $sheet->getStyle($sheet->calculateWorksheetDimension())->getFont()->setName('Arial')->setSize(10);
        $sheet->getStyle('1:1')->getFont()->setBold(true);
    }
}
