<?php

namespace App\Services;

use App\Enums\LopSegment;
use App\Enums\ProgramType;
use App\Models\Package;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BulkLopTemplateService
{
    public const HEADERS = ['incident', 'sto', 'branch', 'area', 'program_type', 'segment', 'budget_type', 'job_description', 'ihld_id', 'nama_lop', 'package_code'];

    public function __construct(private readonly LopVisibilityService $visibility) {}

    public function locations(User $actor): Collection
    {
        return ServiceArea::query()->with('branch.regionRef.area')
            ->where('is_active', true)
            ->whereIn('id_service_area', $this->visibility->accessibleServiceAreaIds($actor))
            ->whereHas('branch', fn ($query) => $query->where('is_active', true))
            ->orderBy('workzone')->get();
    }

    public function samples(User $actor, ?Collection $locations = null): array
    {
        $location = ($locations ?? $this->locations($actor))->first();

        return array_map(fn (ProgramType $program): array => [
            'INC-CONTOH-'.$program->order(),
            $location?->workzone ?? 'ISI_KODE_STO',
            $location?->branch?->name ?? '',
            (string) ($location?->branch?->regionRef?->area?->code ?? ''),
            $program->label(),
            $program === ProgramType::RELOK_TIANG ? 'tiang|odp' : 'feeder|distribusi|odc|odp|otb',
            $program->budgetType()->value,
            'CONTOH pekerjaan '.$program->label(), '', '', '',
        ], ProgramType::cases());
    }

    public function guideRows(): array
    {
        return [
            ['Kolom / aturan', 'Panduan pengisian'],
            ['Mulai', 'Isi sheet LOP. Ganti atau hapus semua baris CONTOH sebelum upload. Jangan ubah nama header. Sheet Panduan dan Referensi tidak diimport.'],
            ['Format file', 'XLSX, XLS, atau CSV UTF-8, maksimal 10 MB. File diproses melalui antrean; hasil sukses/gagal tersedia per baris.'],
            ['Kolom wajib', 'sto, program_type, segment, job_description. Kolom lain boleh kosong sesuai aturan di bawah.'],
            ['incident', 'Nomor unik diawali INC atau INP, maksimal 100 karakter. Kosong untuk generate INP otomatis. Nomor yang sudah dipakai, termasuk LOP dihapus, tidak boleh digunakan ulang.'],
            ['Jenis tiket incident', 'Recovery/Preventive menerima tiket yang mengandung GAMAS atau berjenis UNKNOWN; Relok Tiang wajib NON NUMBERING (huruf besar/kecil diabaikan). Jenis tiket dibaca dari database tiket, bukan dari file. Incident yang tidak ditemukan/gagal dicek ditolak per baris.'],
            ['Tanpa incident', 'Semua program boleh mengosongkan incident untuk generate INP otomatis. INP manual dan Relok Utilitas tidak memerlukan pengecekan jenis tiket.'],
            ['Duplikat INCIDENT', 'Baris gagal menyebut nama LOP yang memakai INCIDENT. Duplikasi dalam file menyebut baris pembuatnya. LOP di luar scope hanya diberi keterangan tanpa nama.'],
            ['sto', 'Isi kode STO/workzone aktif dari Referensi Lokasi. Harus termasuk scope akun yang mengupload. Jangan isi nama Service Area.'],
            ['branch', 'Isi nama Branch sesuai STO; boleh kosong untuk mengikuti master STO. Gunakan Referensi Lokasi.'],
            ['area', 'Isi kode Area sesuai Branch/STO; boleh kosong untuk mengikuti master lokasi. Jangan isi nama Area.'],
            ['program_type', 'QE Recovery, QE Preventive, QE Relok Utilitas, QE Relok Tiang. Huruf besar/kecil dan pemisah spasi, underscore, atau tanda hubung diterima.'],
            ['Variasi program', 'Contoh: qe recovery, qe_recovery, QE RECOVERY, qe-relok-tiang. Nama/kode pada Referensi Program diterima. Jangan gabungkan beberapa program dalam satu sel.'],
            ['segment', 'Pilih 1–5 segmen untuk SEMUA program. Pisahkan dengan |, contoh feeder|distribusi|odc|odp|tiang. Koma/titik koma juga diterima. Urutan menjadi urutan pada Nama LOP.'],
            ['Segmen tersedia', implode(', ', array_map(fn (LopSegment $segment) => $segment->value, LopSegment::cases())).'. Mini OLT juga diterima sebagai mini_olt.'],
            ['budget_type', 'Otomatis CAPEX untuk QE Relok Utilitas, OPEX untuk Recovery/Preventive/Relok Tiang. Boleh kosong; nilai kiriman selalu mengikuti program.'],
            ['job_description', 'Deskripsi pekerjaan wajib, maksimal 2000 karakter.'],
            ['ihld_id', 'ID IHLD opsional, maksimal 100 karakter. Dapat diisi kemudian lewat edit LOP.'],
            ['nama_lop', 'Opsional, maksimal 255 karakter. Kosong agar sistem generate memakai format Nama LOP aktif.'],
            ['package_code', 'Kode paket harga dari Referensi Paket, opsional. Import BOQ berikutnya tetap membaca paket dan volume dari file BOQ.'],
            ['Status awal', 'Semua LOP baru berstatus draft. Preventive/Relok Utilitas/Relok Tiang masuk Usulan dan menjadi On Going saat assign; Recovery tidak memakai status_project.'],
            ['Identifier', 'Pertahankan format Text untuk INCIDENT, STO, Area, IHLD, dan kode paket agar nol depan/angka panjang tidak berubah.'],
            ['Setelah upload', 'Periksa hasil tiap baris. Baris gagal tidak menimpa LOP existing; perbaiki hanya baris gagal lalu upload ulang. Restart worker setelah deploy kode import baru.'],
        ];
    }

    public function workbook(User $actor): Spreadsheet
    {
        $book = new Spreadsheet;
        $locations = $this->locations($actor);
        $sheet = $book->getActiveSheet()->setTitle('LOP');
        $this->writeRows($sheet, [self::HEADERS, ...$this->samples($actor, $locations)]);
        $sheet->freezePane('C2');
        $sheet->setAutoFilter('A1:K5');
        foreach ([24, 18, 26, 12, 26, 40, 18, 42, 22, 44, 20] as $index => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
        }
        $sheet->getStyle('A2:K5')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFFBEB');
        $sheet->getStyle('A1:K1001')->getNumberFormat()->setFormatCode('@');

        $guide = $book->createSheet()->setTitle('Panduan');
        $this->writeRows($guide, $this->guideRows());
        $guide->getColumnDimension('A')->setWidth(27);
        $guide->getColumnDimension('B')->setWidth(105);
        $guide->getStyle('B2:B'.count($this->guideRows()))->getAlignment()->setWrapText(true);
        for ($row = 2; $row <= count($this->guideRows()); $row++) {
            $guide->getRowDimension($row)->setRowHeight(42);
        }

        $programs = [['Program', 'Nilai canonical', 'Kode nama LOP', 'Budget otomatis', 'Status project awal']];
        foreach (ProgramType::cases() as $program) {
            $programs[] = [$program->label(), $program->value, $program->code(), $program->budgetType()->value, $program->usesProjectStatus() ? 'usulan' : 'Tidak digunakan'];
        }
        $programSheet = $book->createSheet()->setTitle('Referensi Program');
        $this->writeRows($programSheet, $programs);
        foreach (['A' => 27, 'B' => 25, 'C' => 23, 'D' => 23, 'E' => 25] as $column => $width) {
            $programSheet->getColumnDimension($column)->setWidth($width);
        }

        $segmentSheet = $book->createSheet()->setTitle('Referensi Segmen');
        $this->writeRows($segmentSheet, [['Nilai import', 'Label'], ...array_map(fn (LopSegment $segment): array => [$segment->value, $segment->label()], LopSegment::cases())]);
        $segmentSheet->getColumnDimension('A')->setWidth(24);
        $segmentSheet->getColumnDimension('B')->setWidth(28);

        $locationSheet = $book->createSheet()->setTitle('Referensi Lokasi');
        $this->writeRows($locationSheet, [['STO / workzone', 'Service Area', 'Branch', 'Region', 'Kode Area'], ...$locations->map(fn (ServiceArea $sa): array => [
            $sa->workzone, $sa->name, $sa->branch?->name ?? '', $sa->branch?->regionRef?->name ?? $sa->branch?->region ?? '', (string) ($sa->branch?->regionRef?->area?->code ?? '3'),
        ])->all()]);
        foreach (['A' => 23, 'B' => 40, 'C' => 28, 'D' => 28, 'E' => 18] as $column => $width) {
            $locationSheet->getColumnDimension($column)->setWidth($width);
        }

        $packageSheet = $book->createSheet()->setTitle('Referensi Paket');
        $this->writeRows($packageSheet, [['Kode paket', 'Nama paket'], ...Package::orderBy('code')->get()->map(fn (Package $package): array => [$package->code, $package->name])->all()]);
        $packageSheet->getColumnDimension('A')->setWidth(24);
        $packageSheet->getColumnDimension('B')->setWidth(50);
        $book->setActiveSheetIndex(0);

        return $book;
    }

    private function writeRows(Worksheet $sheet, array $rows): void
    {
        $sheet->setShowGridlines(false);
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $column => $value) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($column + 1).($rowIndex + 1), (string) $value, DataType::TYPE_STRING);
            }
        }
        $range = $sheet->calculateWorksheetDimension();
        $sheet->getStyle($range)->getFont()->setName('Arial')->setSize(10);
        $sheet->getStyle($range)->getAlignment()->setVertical('center');
        $lastColumn = Coordinate::stringFromColumnIndex(count($rows[0]));
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF1F2937');
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getDefaultRowDimension()->setRowHeight(23);
        $sheet->freezePane('A2');
    }
}
