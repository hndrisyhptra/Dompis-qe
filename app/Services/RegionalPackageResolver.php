<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Package;
use App\Models\QeLop;

/**
 * Menentukan paket harga reservasi teknisi dari region operasional LOP.
 */
class RegionalPackageResolver
{
    /** @var array<string, Package|null> */
    private array $packages = [];

    public function expectedCode(QeLop $lop): ?string
    {
        $region = $this->normalizedRegion($lop);
        $branch = $this->resolveBranch($lop);
        $branchName = $branch?->name ?? '';

        return match (true) {
            str_contains($region, 'JATIM'), str_contains($region, 'JAWATIMUR'), str_contains($region, 'JATENGDIY') => 'Paket-5',
            str_contains($region, 'BALNUS') => $this->resolveBalnusPackage($branchName),
            default => null,
        };
    }

    private function resolveBalnusPackage(string $branchName): string
    {
        $normalized = mb_strtoupper($branchName);

        // Bali / Denpasar -> Paket-10
        if (str_contains($normalized, 'DENPASAR') || str_contains($normalized, 'BALI')) {
            return 'Paket-10';
        }

        // NUSRA: Flores, Kupang, Mataram -> Paket-12
        if (str_contains($normalized, 'FLORES') || str_contains($normalized, 'KUPANG') || str_contains($normalized, 'MATARAM')) {
            return 'Paket-12';
        }

        // Fallback default Paket-10
        return 'Paket-10';
    }

    private function resolveBranch(QeLop $lop): ?\App\Models\Branch
    {
        $lop->loadMissing([
            'branchRef.regionRef',
            'serviceArea.branch.regionRef',
        ]);

        $branch = $lop->branchRef ?? $lop->serviceArea?->branch;
        if ($branch === null && filled($lop->branch)) {
            $branch = \App\Models\Branch::query()
                ->with('regionRef')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $lop->branch))])
                ->first();
        }

        return $branch;
    }

    public function forLop(QeLop $lop): ?Package
    {
        $code = $this->expectedCode($lop);

        return $code === null ? null : $this->byCanonicalCode($code);
    }

    public function package5(): ?Package
    {
        return $this->byCanonicalCode('Paket-5');
    }

    public function package10(): ?Package
    {
        return $this->byCanonicalCode('Paket-10');
    }

    public function package12(): ?Package
    {
        return $this->byCanonicalCode('Paket-12');
    }

    private function normalizedRegion(QeLop $lop): string
    {
        $lop->loadMissing([
            'branchRef.regionRef',
            'serviceArea.region',
            'serviceArea.branch.regionRef',
        ]);

        $branch = $lop->branchRef ?? $lop->serviceArea?->branch;
        if ($branch === null && filled($lop->branch)) {
            $branch = Branch::query()
                ->with('regionRef')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $lop->branch))])
                ->first();
        }

        $region = $branch?->regionRef?->code
            ?? $lop->serviceArea?->region?->code
            ?? $branch?->region
            ?? '';

        return mb_strtoupper((string) preg_replace('/[^a-z0-9]+/i', '', (string) $region));
    }

    private function byCanonicalCode(string $canonicalCode): ?Package
    {
        if (! array_key_exists($canonicalCode, $this->packages)) {
            $normalizedTarget = $this->normalizePackageCode($canonicalCode);
            $this->packages[$canonicalCode] = Package::query()
                ->orderBy('id_package')
                ->get()
                ->first(fn (Package $package): bool => $this->normalizePackageCode($package->code) === $normalizedTarget);
        }

        return $this->packages[$canonicalCode];
    }

    private function normalizePackageCode(string $code): string
    {
        return mb_strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $code));
    }
}
