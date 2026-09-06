<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Helper scoping lokasi per-akun + filter helicopter-view region/branch.
 * Diangkat dari pola private ProgramController, digeneralisasi atas nama kolom
 * branch (`qe_lops.branch` string, bisa ter-alias saat di-join) dan atas
 * daftar role yang tidak di-scope (default hanya SUPER_ADMIN).
 */
trait LocationScope
{
    /**
     * Role di $unscopedRoles => lintas-branch. Selain itu dikunci ke branch
     * akun (`$branchColumn = $user->branch->name`); tanpa branch => tidak ada
     * data (aman, seperti dashboard admin).
     */
    protected function scopeForUser(
        Builder $query,
        User $user,
        string $branchColumn = 'branch',
        array $unscopedRoles = [UserRole::SUPER_ADMIN],
    ): Builder {
        if ($user->hasRole(...$unscopedRoles)) {
            return $query;
        }

        $branchName = $user->branch?->name;

        if ($branchName === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($branchColumn, $branchName);
    }

    /**
     * Baca & sanitasi filter region/branch dari request (divalidasi terhadap
     * tabel branches; nilai tak dikenal dikosongkan).
     *
     * @return array{0: string, 1: string} [region, branch]
     */
    protected function resolveLocationFilters(Request $request): array
    {
        $region = trim((string) $request->string('region'));
        $branch = trim((string) $request->string('branch'));

        if ($region !== '' && ! Branch::query()->where('region', $region)->exists()) {
            $region = '';
        }

        if ($branch !== '') {
            $branchQuery = Branch::query()->where('name', $branch);
            if ($region !== '') {
                $branchQuery->where('region', $region);
            }
            if (! $branchQuery->exists()) {
                $branch = '';
            }
        }

        return [$region, $branch];
    }

    /**
     * Filter branch = cocok nama; filter region = branch ada di daftar nama
     * branch region tsb.
     */
    protected function applyLocationFilter(
        Builder $query,
        string $region,
        string $branch,
        string $branchColumn = 'branch',
    ): void {
        if ($branch !== '') {
            $query->where($branchColumn, $branch);

            return;
        }

        if ($region !== '') {
            $query->whereIn($branchColumn, Branch::query()->where('region', $region)->select('name'));
        }
    }

    /**
     * @return array{regions: Collection, branches: Collection, regionFilter: string, branchFilter: string}
     */
    protected function locationOptions(string $regionFilter, string $branchFilter): array
    {
        return [
            'regions' => Branch::query()->whereNotNull('region')->distinct()->orderBy('region')->pluck('region'),
            'branches' => Branch::query()->orderBy('region')->orderBy('name')->get(['id_branch', 'name', 'region']),
            'regionFilter' => $regionFilter,
            'branchFilter' => $branchFilter,
        ];
    }

    /**
     * @return array{canFilterLocation: bool, scopeLabel: string, scopeWarning: bool}
     */
    protected function scopeContext(
        User $user,
        bool $canFilterLocation,
        string $regionFilter,
        string $branchFilter,
    ): array {
        if ($canFilterLocation) {
            return [
                'canFilterLocation' => true,
                'scopeLabel' => $branchFilter ?: ($regionFilter ?: 'Semua region & branch'),
                'scopeWarning' => false,
            ];
        }

        $branchName = $user->branch?->name;

        return [
            'canFilterLocation' => false,
            'scopeLabel' => $branchName ?? 'Branch belum diatur',
            'scopeWarning' => $branchName === null,
        ];
    }
}
