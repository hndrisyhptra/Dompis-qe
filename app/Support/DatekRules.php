<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalisasi + validasi input untuk kolom JSON `qe_lops.datek`.
 * Dipakai bersama oleh StoreLopRequest & UpdateLopRequest.
 *
 * Bentuk datek: { kategori, odc[], odp[], gpon[{name,ip,ports[]}], kabel[],
 * ip[], olt(bool), rca, est, pic{nama,telp} }.
 *
 * `datek` bersifat referensi (hasil ekstraksi best-effort). Nilai yang tidak
 * rapi DIBERSIHKAN/DIPOTONG di `decode()` - tidak boleh menggagalkan simpan LOP.
 */
class DatekRules
{
    /**
     * Input `datek` dikirim form sebagai string JSON (hidden input). Ubah jadi
     * array yang sudah dibersihkan; null bila kosong / tidak bermakna.
     */
    public static function decode(mixed $raw): ?array
    {
        $arr = is_array($raw)
            ? $raw
            : (is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null);

        if (! is_array($arr)) {
            return null;
        }

        $clean = self::sanitize($arr);

        return self::isMeaningful($clean) ? $clean : null;
    }

    /**
     * Validasi ringan - struktur saja. Isi sudah dijamin bersih oleh decode().
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'datek' => ['nullable', 'array'],
        ];
    }

    private static function sanitize(array $d): array
    {
        $limit = static fn ($v, int $max): string => Str::limit(trim((string) $v), $max, '');

        $list = static fn ($v, int $max): array => collect(is_array($v) ? $v : [])
            ->map(static fn ($x) => Str::limit(trim((string) $x), $max, ''))
            ->filter()
            ->values()
            ->all();

        $gpon = collect(is_array($d['gpon'] ?? null) ? $d['gpon'] : [])
            ->map(static function ($g) {
                if (! is_array($g)) {
                    return null;
                }

                return [
                    'name' => Str::limit(trim((string) ($g['name'] ?? '')), 80, ''),
                    'ip' => Str::limit(trim((string) ($g['ip'] ?? '')), 45, ''),
                    'ports' => collect(is_array($g['ports'] ?? null) ? $g['ports'] : [])
                        ->map(static fn ($p) => Str::limit(trim((string) $p), 20, ''))
                        ->filter()
                        ->values()
                        ->all(),
                ];
            })
            ->filter(static fn ($g) => $g !== null && ($g['name'] !== '' || $g['ip'] !== '' || $g['ports'] !== []))
            ->values()
            ->all();

        return [
            'kategori' => $limit($d['kategori'] ?? '', 30),
            'odc' => $list($d['odc'] ?? [], 60),
            'odp' => $list($d['odp'] ?? [], 60),
            'gpon' => $gpon,
            'kabel' => $list($d['kabel'] ?? [], 80),
            'ip' => $list($d['ip'] ?? [], 45),
            'olt' => (bool) ($d['olt'] ?? false),
            'rca' => $limit($d['rca'] ?? '', 255),
            'est' => $limit($d['est'] ?? '', 60),
            'pic' => [
                'nama' => $limit($d['pic']['nama'] ?? '', 120),
                'telp' => $limit($d['pic']['telp'] ?? '', 40),
            ],
        ];
    }

    private static function isMeaningful(array $d): bool
    {
        foreach (['odc', 'odp', 'gpon', 'kabel', 'ip'] as $k) {
            if (! empty($d[$k])) {
                return true;
            }
        }

        foreach (['kategori', 'rca', 'est'] as $k) {
            if (! empty($d[$k])) {
                return true;
            }
        }

        if (! empty($d['olt'])) {
            return true;
        }

        return ! empty($d['pic']['nama']) || ! empty($d['pic']['telp']);
    }
}
