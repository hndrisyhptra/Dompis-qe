<?php

namespace App\Services;

/**
 * Ekstraksi rule-based "datek terdampak" (elemen jaringan yang terimbas) dari
 * teks ringkasan tiket SQM GAMAS. Pure - tanpa DB, tanpa efek samping.
 *
 * Best-effort: pola disesuaikan dengan format tiket yang ada saat ini
 * (pipe-delimited, token dalam kurung siku, label eksplisit). Hasil selalu
 * dapat diedit admin di form Input LOP Baru sebelum disimpan.
 */
class DatekParserService
{
    /**
     * @return array{
     *   kategori: ?string,
     *   odc: array<int, string>,
     *   odp: array<int, string>,
     *   gpon: array<int, array{name: string, ip: ?string, ports: array<int, string>}>,
     *   kabel: array<int, string>,
     *   ip: array<int, string>,
     *   olt: bool,
     *   rca: ?string,
     *   est: ?string,
     *   pic: ?array{nama: ?string, telp: ?string},
     * }
     */
    public function parse(?string $summary, ?string $segmentHint = null): array
    {
        $text = trim((string) $summary);

        $result = [
            'kategori' => $this->kategori($text, $segmentHint),
            'odc' => [],
            'odp' => [],
            'gpon' => [],
            'kabel' => [],
            'ip' => [],
            'olt' => false,
            'rca' => null,
            'est' => null,
            'pic' => null,
        ];

        if ($text === '') {
            return $result;
        }

        $tokens = array_map(
            static fn ($t) => trim((string) $t),
            preg_split('/\|/', $text) ?: [],
        );

        $usedIps = [];

        $result['odc'] = $this->odc($text);
        $result['gpon'] = $this->gpon($text, $usedIps);
        $result['odp'] = $this->odp($text);
        $result['kabel'] = $this->kabel($tokens);
        $result['ip'] = $this->leftoverIps($text, $usedIps);
        $result['olt'] = (bool) preg_match('/\bOLT\b/iu', $text);
        $result['rca'] = $this->rca($text);
        $result['est'] = $this->est($text);
        $result['pic'] = $this->pic($text);

        return $result;
    }

    /**
     * True bila tidak ada satupun elemen jaringan (odc/odp/gpon/kabel) terdeteksi.
     *
     * @param  array<string, mixed>  $datek
     */
    public function isEmpty(array $datek): bool
    {
        return empty($datek['odc'])
            && empty($datek['odp'])
            && empty($datek['gpon'])
            && empty($datek['kabel']);
    }

    private function kategori(string $text, ?string $hint): ?string
    {
        $hint = strtolower(trim((string) $hint));

        if (in_array($hint, ['distribusi', 'feeder', 'gpon', 'odc', 'odp'], true)) {
            return $hint;
        }

        if (preg_match('/\b(DISTRIBUSI|FEEDER|GPON)\b/iu', $text, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    /** @return array<int, string> */
    private function odc(string $text): array
    {
        preg_match_all('/\bODC-[A-Z0-9]+(?:[-\/][A-Z0-9]+)*/iu', $text, $m);

        return $this->cleanList($m[0]);
    }

    /** @return array<int, string> */
    private function odp(string $text): array
    {
        $out = [];

        // (a) daftar berlabel: "Datek ODP Terdampak : [A, B, C]"
        if (preg_match('/datek\s+odp\s+terdampak\s*:?\s*\[([^\]]+)\]/iu', $text, $lab)) {
            foreach (explode(',', $lab[1]) as $piece) {
                $out[] = $piece;
            }
        }

        // (b) global
        preg_match_all('/\bODP-[A-Z0-9]+(?:[-\/][A-Z0-9]+)*/iu', $text, $m);
        foreach ($m[0] as $v) {
            $out[] = $v;
        }

        return $this->cleanList($out);
    }

    /**
     * @param  array<int, string>|null  $usedIps  IP yang sudah dikaitkan ke sebuah GPON
     * @return array<int, array{name: string, ip: ?string, ports: array<int, string>}>
     */
    private function gpon(string $text, ?array &$usedIps): array
    {
        $usedIps = [];
        $out = [];

        if (! preg_match_all('/\bGPON\d*-[A-Z0-9]+(?:-[A-Z0-9]+)*/iu', $text, $m, PREG_OFFSET_CAPTURE)) {
            return $out;
        }

        $count = count($m[0]);

        foreach ($m[0] as $i => [$name, $offset]) {
            $start = $offset + strlen($name);
            $end = $i + 1 < $count ? $m[0][$i + 1][1] : strlen($text);
            $window = substr($text, $start, max(0, $end - $start));

            // jangan menyerap data GPON lain / segmen berikutnya
            $window = preg_split('/\||;/', $window)[0] ?? $window;

            $ip = null;
            if (preg_match('/((?:\d{1,3}\.){3}\d{1,3})/', $window, $ipm)) {
                $ip = $ipm[1];
                $usedIps[] = $ip;
            }

            $ports = [];
            if (preg_match('/\[\s*([0-9]+(?:\/[0-9]+)?(?:\s*,\s*[0-9]+(?:\/[0-9]+)?)*)\s*\]/', $window, $pm)) {
                foreach (explode(',', $pm[1]) as $p) {
                    $p = trim($p);
                    if ($p !== '') {
                        $ports[] = $p;
                    }
                }
            }

            $out[] = [
                'name' => strtoupper($name),
                'ip' => $ip,
                'ports' => $ports,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $tokens  hasil explode('|') yang sudah di-trim
     * @return array<int, string>
     */
    private function kabel(array $tokens): array
    {
        $out = [];

        foreach ($tokens as $tok) {
            $tok = trim((string) $tok, " \t\n\r\0\x0B[]");

            if ($tok === '' || str_contains($tok, ' ')) {
                continue;
            }

            if (preg_match('/^(ODP|ODC|GPON)/i', $tok)) {
                continue;
            }

            if (preg_match('/^[A-Z]{2,5}(?:-[A-Z0-9]+){3,}(?:\/[0-9-]+)?$/i', $tok)) {
                $out[] = strtoupper($tok);
            }
        }

        return $this->cleanList($out);
    }

    /**
     * @param  array<int, string>  $usedIps
     * @return array<int, string>
     */
    private function leftoverIps(string $text, array $usedIps): array
    {
        preg_match_all('/(?:\d{1,3}\.){3}\d{1,3}/', $text, $m);

        $used = array_flip($usedIps);

        return $this->cleanList(array_filter($m[0], static fn ($ip) => ! isset($used[$ip])));
    }

    private function rca(string $text): ?string
    {
        if (preg_match('/rca\s*:?\s*([^\]\|]+)/iu', $text, $m)) {
            $v = trim($m[1], " \t\n\r\0\x0B].");

            return $v !== '' ? $v : null;
        }

        return null;
    }

    private function est(string $text): ?string
    {
        if (preg_match('/\bEST\b\s*[:\-]?\s*([0-9]{1,2}\/[0-9]{1,2}\/[0-9]{2,4}(?:\s+[0-9]{1,2}:[0-9]{2})?)/iu', $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /** @return ?array{nama: ?string, telp: ?string} */
    private function pic(string $text): ?array
    {
        if (preg_match('/\bPIC\b\s*:?\s*([A-Za-z][A-Za-z .]*?)\s*[\/,]\s*(\+?\d[\d\-\s]{5,})/iu', $text, $m)) {
            $nama = trim($m[1], " \t\n\r\0\x0B/.,");
            $telp = preg_replace('/[^\d+]/', '', $m[2]);

            return ['nama' => $nama !== '' ? $nama : null, 'telp' => $telp !== '' ? $telp : null];
        }

        return null;
    }

    /**
     * Upper-case, buang pembungkus kurung/spasi, buang kosong, unik (jaga urutan).
     *
     * @param  iterable<int, string>  $items
     * @return array<int, string>
     */
    private function cleanList(iterable $items): array
    {
        $seen = [];

        foreach ($items as $it) {
            $it = strtoupper(trim((string) $it, " \t\n\r\0\x0B[](){}"));

            if ($it === '' || isset($seen[$it])) {
                continue;
            }

            $seen[$it] = true;
        }

        return array_keys($seen);
    }
}
