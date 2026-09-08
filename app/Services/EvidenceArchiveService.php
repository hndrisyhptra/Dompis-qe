<?php

namespace App\Services;

use App\Models\QeLop;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

class EvidenceArchiveService
{
    /** Ekstensi yang dianggap "image" dan ikut dibungkus. */
    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'heic'];

    /**
     * Bungkus seluruh evidence gambar sebuah LOP ke satu file ZIP sementara.
     * Struktur di dalam ZIP: {namaLop}/{kategori}/{kode|global}-{id}.{ext}
     *
     * @return array{0: string, 1: string} [path file temp, nama file unduhan]
     *
     * @throws RuntimeException 'empty' bila tidak ada gambar; pesan lain bila gagal bikin ZIP
     */
    public function build(QeLop $lop): array
    {
        $lop->loadMissing(['evidences.designator']);

        $folder = $this->safeName($lop->nama_lop ?: $lop->incident ?: 'LOP');
        $disk = Storage::disk(config('evidence.disk', 'public'));

        $images = $lop->evidences
            ->filter(fn ($e) => in_array($this->ext($e->file_path), self::IMAGE_EXT, true))
            ->filter(fn ($e) => $disk->exists($e->file_path))
            ->values();

        if ($images->isEmpty()) {
            throw new RuntimeException('empty');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'lop_evd_');
        $zip = new ZipArchive;

        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuat arsip ZIP.');
        }

        $used = [];
        foreach ($images as $e) {
            $category = $this->safeName($e->category?->value ?? $e->step?->value ?? 'lainnya');
            $label = $this->safeName($e->designator?->code ?? 'global');
            $ext = $this->ext($e->file_path);

            $entry = "{$folder}/{$category}/{$label}-{$e->id_evidence}.{$ext}";
            $n = 1;
            while (isset($used[$entry])) {
                $entry = "{$folder}/{$category}/{$label}-{$e->id_evidence}-".(++$n).".{$ext}";
            }
            $used[$entry] = true;

            try {
                $local = $disk->path($e->file_path);
                if (is_string($local) && is_file($local)) {
                    $zip->addFile($local, $entry);

                    continue;
                }
            } catch (Throwable) {
                // disk non-lokal: jatuh ke addFromString di bawah
            }

            $zip->addFromString($entry, $disk->get($e->file_path));
        }

        $zip->close();

        return [$tmp, $folder.'.zip'];
    }

    private function ext(?string $path): string
    {
        return strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
    }

    /**
     * Sisakan huruf/angka/spasi/titik/strip/underscore; sisanya jadi underscore.
     * Aman dipakai sebagai nama file ZIP maupun nama folder/entry di dalamnya.
     */
    private function safeName(string $name): string
    {
        $clean = preg_replace('/[^\p{L}\p{N} ._-]+/u', '_', trim($name));
        $clean = preg_replace('/_{2,}/', '_', (string) $clean);

        return trim((string) $clean, ' ._-') ?: 'LOP';
    }
}
