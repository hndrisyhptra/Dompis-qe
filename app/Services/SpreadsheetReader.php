<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class SpreadsheetReader
{
    /**
     * @return array{header_row:int, headers:array<string,string>, rows:array<int,array{row_number:int,data:array<string,mixed>}>}
     */
    public function table(string $path, array $requiredHeaders, int $scanRows = 20): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (Throwable $e) {
            throw new RuntimeException('File tidak dapat dibaca sebagai XLSX, XLS, atau CSV.', previous: $e);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highestColumn = $sheet->getHighestColumn();
        $highestRow = $sheet->getHighestRow();
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
