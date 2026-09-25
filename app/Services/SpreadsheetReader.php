<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;
use SplFileObject;
use Throwable;

class SpreadsheetReader
{
    /**
     * @return array{header_row:int, headers:array<string,string>, rows:array<int,array{row_number:int,data:array<string,mixed>}>}
     */
    public function table(string $path, array $requiredHeaders, int $scanRows = 20): array
    {
        if (mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'csv') {
            return $this->csvTable($path, $requiredHeaders, $scanRows);
        }

        $spreadsheet = $this->load($path);

        $sheet = $spreadsheet->getActiveSheet();
        // HighestData* mengabaikan baris/kolom yang hanya memiliki formatting.
        // Template Excel sering memformat ribuan baris kosong dan sebelumnya
        // seluruh baris tersebut tetap dipindai.
        $highestColumnIndex = min(100, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));
        $highestColumn = $this->columnLetter($highestColumnIndex);
        $highestRow = $sheet->getHighestDataRow();
        $headerRow = null;
        $headers = [];

        for ($row = 1; $row <= min($scanRows, $highestRow); $row++) {
            $candidate = [];
            foreach ($sheet->rangeToArray("A{$row}:{$highestColumn}{$row}", null, true, false)[0] as $index => $value) {
                $key = $this->normalizeHeader((string) $value);
                if ($key !== '') {
                    $candidate[$key] = $this->columnLetter($index + 1);
                }
            }

            if (collect($requiredHeaders)->every(fn ($required) => array_key_exists($required, $candidate))) {
                $headerRow = $row;
                $headers = $candidate;
                break;
            }
        }

        if ($headerRow === null) {
            $spreadsheet->disconnectWorksheets();
            throw new RuntimeException('Header wajib tidak ditemukan: '.implode(', ', $requiredHeaders).'.');
        }

        $rows = [];
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $data = [];
            foreach ($headers as $key => $column) {
                $data[$key] = $sheet->getCell("{$column}{$row}")->getCalculatedValue();
            }

            if (collect($data)->every(fn ($value) => trim((string) $value) === '')) {
                continue;
            }

            $rows[] = ['row_number' => $row, 'data' => $data];
        }

        $spreadsheet->disconnectWorksheets();

        return ['header_row' => $headerRow, 'headers' => $headers, 'rows' => $rows];
    }

    /**
     * Jalur streaming khusus CSV. Tidak memuat seluruh file ke memori dan
     * otomatis mengenali delimiter comma, semicolon, atau tab.
     *
     * @return array{header_row:int, headers:array<string,string>, rows:array<int,array{row_number:int,data:array<string,mixed>}>}
     */
    private function csvTable(string $path, array $requiredHeaders, int $scanRows): array
    {
        $headerRow = null;
        $headerIndexes = [];
        $delimiter = ',';

        foreach ([',', ';', "\t"] as $candidateDelimiter) {
            $file = new SplFileObject($path, 'r');
            $file->setCsvControl($candidateDelimiter);
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);

            foreach ($file as $index => $values) {
                if ($index >= $scanRows) {
                    break;
                }
                if (! is_array($values)) {
                    continue;
                }

                $candidate = [];
                foreach ($values as $columnIndex => $value) {
                    $value = $columnIndex === 0
                        ? preg_replace('/^\xEF\xBB\xBF/', '', (string) $value)
                        : (string) $value;
                    $key = $this->normalizeHeader($value);
                    if ($key !== '') {
                        $candidate[$key] = $columnIndex;
                    }
                }

                if (collect($requiredHeaders)->every(fn ($required) => array_key_exists($required, $candidate))) {
                    $headerRow = $index + 1;
                    $headerIndexes = $candidate;
                    $delimiter = $candidateDelimiter;
                    break 2;
                }
            }
        }

        if ($headerRow === null) {
            throw new RuntimeException('Header wajib tidak ditemukan: '.implode(', ', $requiredHeaders).'.');
        }

        $headers = collect($headerIndexes)
            ->map(fn (int $index): string => $this->columnLetter($index + 1))
            ->all();
        $rows = [];
        $file = new SplFileObject($path, 'r');
        $file->setCsvControl($delimiter);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);

        foreach ($file as $index => $values) {
            $rowNumber = $index + 1;
            if ($rowNumber <= $headerRow || ! is_array($values)) {
                continue;
            }

            $data = [];
            foreach ($headerIndexes as $key => $columnIndex) {
                $data[$key] = $values[$columnIndex] ?? null;
            }
            if (collect($data)->every(fn ($value) => trim((string) $value) === '')) {
                continue;
            }

            $rows[] = ['row_number' => $rowNumber, 'data' => $data];
        }

        return ['header_row' => $headerRow, 'headers' => $headers, 'rows' => $rows];
    }

    public function load(string $path): Spreadsheet
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);

            return $reader->load($path);
        } catch (Throwable $e) {
            throw new RuntimeException('File tidak dapat dibaca sebagai XLSX, XLS, atau CSV.', previous: $e);
        }
    }

    public function normalizeHeader(string $header): string
    {
        $key = strtolower(trim($header));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? '';
        $key = trim($key, '_');

        return match ($key) {
            'program', 'wbs', 'wbs_type' => 'program_type',
            'segmen' => 'segment',
            'deskripsi_pekerjaan', 'uraian_pekerjaan' => 'job_description',
            'id_ihld' => 'ihld_id',
            'paket', 'package' => 'package_code',
            'designator_code', 'kode_designator' => 'designator',
            'quantity', 'volume', 'vol' => 'qty',
            'harga', 'harga_satuan', 'price' => 'unit_price',
            default => $key,
        };
    }

    private function columnLetter(int $index): string
    {
        $letters = '';
        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)).$letters;
            $index = intdiv($index, 26);
        }

        return $letters;
    }
}
