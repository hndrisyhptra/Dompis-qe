<?php

namespace App\Services;

use App\Enums\LopStatus;
use App\Enums\ProgramType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class IncidentMonitoringService
{
    public function __construct(
        private readonly LopVisibilityService $visibility,
        private readonly TicketLookupService $tickets,
    ) {}

    public function dashboard(User $user, array $input): array
    {
        $filters = [
            'region' => trim((string) ($input['region'] ?? '')),
            'branch' => $input['branch'] ?? '',
            'service_area' => $input['service_area'] ?? '',
            'program' => $input['program'] ?? '',
            'status' => $input['status'] ?? '',
            'ticket_type' => $input['ticket_type'] ?? '',
            'q' => trim((string) ($input['q'] ?? '')),
            'missing_ihld' => (bool) ($input['missing_ihld'] ?? false),
        ];
        $branches = Branch::query()->with('regionRef:id_region,name')
            ->whereIn('id_branch', $this->visibility->accessibleBranchIds($user))
            ->orderBy('name')->get(['id_branch', 'name', 'region_id', 'region']);
        $serviceAreas = ServiceArea::query()
            ->whereIn('id_service_area', $this->visibility->accessibleServiceAreaIds($user))
            ->orderBy('workzone')->get(['id_service_area', 'workzone', 'branch_id']);
        $regions = $branches->map(fn (Branch $branch) => $branch->regionRef?->name ?? $branch->region)
            ->filter()->unique()->sort()->values();

        $query = $this->visibility->apply(QeLop::query(), $user);
        $this->applyFilters($query, $filters, $branches, $serviceAreas);

        $stats = ['total' => 0, 'completed' => 0, 'waiting_approval' => 0, 'missing_ihld' => 0];
        $groups = [];
        $typesByLop = [];
        $matchingIds = [];
        $sourceUnavailable = false;
        $checkedAt = null;

        // Scan only identifiers and counters; load full rows for the current page below.
        (clone $query)->select(['id_qe_lops', 'incident', 'status_lop', 'ihld_id'])
            ->toBase()->chunkById(500, function (Collection $lops) use (
                $filters, $input, &$stats, &$groups, &$typesByLop, &$matchingIds, &$sourceUnavailable, &$checkedAt,
            ): void {
                $incidents = $lops->pluck('incident')->map(fn ($incident) => mb_strtoupper(trim((string) $incident)))
                    ->reject(fn ($incident) => $incident === '' || str_starts_with($incident, 'INP'))
                    ->unique()->values()->all();
                $tickets = $this->ticketTypes($incidents, (bool) ($input['retry'] ?? false));

                foreach ($tickets as $ticket) {
                    $sourceUnavailable = $sourceUnavailable || ($ticket['error'] ?? false);
                    if (isset($ticket['checked_at'])) {
                        $checkedAt = $checkedAt === null ? $ticket['checked_at'] : min($checkedAt, $ticket['checked_at']);
                    }
                }

                foreach ($lops as $lop) {
                    $incident = mb_strtoupper(trim((string) $lop->incident));
                    [$key, $label] = $this->classification($incident, $tickets[$incident] ?? []);
                    $groups[$key] ??= ['key' => $key, 'label' => $label, 'count' => 0];
                    $groups[$key]['count']++;
                    if ($filters['ticket_type'] !== '' && $filters['ticket_type'] !== $key) {
                        continue;
                    }
                    $typesByLop[$lop->id_qe_lops] = $label;
                    if ($filters['ticket_type'] !== '') {
                        $matchingIds[] = (int) $lop->id_qe_lops;
                    }
                    $stats['total']++;
                    $stats['completed'] += (int) ($lop->status_lop === LopStatus::COMPLETED->value);
                    $stats['waiting_approval'] += (int) ($lop->status_lop === LopStatus::WAITING_APPROVAL->value);
                    $stats['missing_ihld'] += (int) blank($lop->ihld_id);
                }
            }, 'id_qe_lops');

        $typeOptions = collect($groups)->sortBy('label')->values();
        $chartRows = collect($groups)->when($filters['ticket_type'] !== '',
            fn (Collection $rows) => $rows->where('key', $filters['ticket_type']))
            ->sort(fn ($a, $b) => ($b['count'] <=> $a['count']) ?: strcmp($a['label'], $b['label']))->values();
        $maxCount = max(1, (int) $chartRows->max('count'));
        $chartRows = $chartRows->map(fn ($row) => [...$row, 'width' => $row['count'] / $maxCount * 100]);

        if ($filters['ticket_type'] !== '') {
            $query->whereIntegerInRaw('id_qe_lops', $matchingIds);
        }
        $lops = $query->with(['branchRef:id_branch,name', 'serviceArea:id_service_area,workzone'])
            ->select(['id_qe_lops', 'incident', 'nama_lop', 'program_type', 'ihld_id', 'status_lop',
                'branch_id', 'branch', 'service_area_id', 'sto', 'created_at'])
            ->orderByDesc('created_at')->orderByDesc('id_qe_lops')
            ->paginate(25, ['*'], 'page', (int) ($input['page'] ?? 1));
        $lops->appends(array_filter($filters, fn ($value) => $value !== '' && $value !== false));

        return [
            'filters' => $filters,
            'filterLabels' => $this->filterLabels($filters, $branches, $serviceAreas, $groups),
            'branches' => $branches,
            'serviceAreas' => $serviceAreas,
            'regions' => $regions,
            'stats' => $stats,
            'chartRows' => $chartRows,
            'typeOptions' => $typeOptions,
            'lops' => $lops,
            'typesByLop' => $typesByLop,
            'sourceUnavailable' => $sourceUnavailable,
            'checkedAt' => $checkedAt === null ? null : Carbon::createFromTimestamp($checkedAt, 'Asia/Jakarta'),
            'scopeLabel' => $user->hasRole(UserRole::SUPER_ADMIN) ? 'Seluruh wilayah' : $this->visibility->label($user),
            'scopeWarning' => $user->hasRole(UserRole::ADMIN) && $branches->isEmpty(),
        ];
    }

    private function applyFilters(Builder $query, array $filters, Collection $branches, Collection $serviceAreas): void
    {
        if ($filters['region'] !== '' || $filters['branch'] !== '') {
            $selected = $branches->filter(fn (Branch $branch) => ($filters['region'] === '' || ($branch->regionRef?->name ?? $branch->region) === $filters['region'])
                && ($filters['branch'] === '' || (int) $branch->getKey() === (int) $filters['branch']));
            $query->where(fn (Builder $q) => $q->whereIn('branch_id', $selected->modelKeys())
                ->orWhere(fn (Builder $legacy) => $legacy->whereNull('branch_id')->whereIn('branch', $selected->pluck('name'))));
        }
        if ($filters['service_area'] !== '') {
            $selected = $serviceAreas->where('id_service_area', (int) $filters['service_area']);
            $query->where(fn (Builder $q) => $q->whereIn('service_area_id', $selected->modelKeys())
                ->orWhere(fn (Builder $legacy) => $legacy->whereNull('service_area_id')->whereIn('sto', $selected->pluck('workzone'))));
        }
        foreach (['program' => 'program_type', 'status' => 'status_lop'] as $filter => $column) {
            if ($filters[$filter] !== '') {
                $query->where($column, $filters[$filter]);
            }
        }
        if ($filters['q'] !== '') {
            $needle = '%'.$filters['q'].'%';
            $query->where(function (Builder $outer) use ($needle, $filters, $branches, $serviceAreas): void {
                $outer->where(fn (Builder $q) => $q->where('incident', 'like', $needle)
                    ->orWhere('nama_lop', 'like', $needle)->orWhere('ihld_id', 'like', $needle));
                // Pencarian lokasi memakai nama branch dan workzone STO; baris legacy tanpa foreign key tetap terjangkau lewat kolom fallback.
                $branchIds = $branches->filter(fn (Branch $branch) => str_contains(mb_strtoupper($branch->name), mb_strtoupper($filters['q'])))->modelKeys();
                $outer->orWhereIn('branch_id', $branchIds)
                    ->orWhere(fn (Builder $legacyBranch) => $legacyBranch->whereNull('branch_id')->where('branch', 'like', $needle));
                $serviceAreaIds = $serviceAreas->filter(fn (ServiceArea $area) => str_contains(mb_strtoupper($area->workzone), mb_strtoupper($filters['q'])))->modelKeys();
                $outer->orWhereIn('service_area_id', $serviceAreaIds)
                    ->orWhere(fn (Builder $legacySto) => $legacySto->whereNull('service_area_id')->where('sto', 'like', $needle));
            });
        }
        if ($filters['missing_ihld']) {
            $query->where(fn (Builder $q) => $q->whereNull('ihld_id')->orWhereRaw("TRIM(ihld_id) = ''"));
        }
    }

    private function ticketTypes(array $incidents, bool $retry): array
    {
        if ($incidents === []) {
            return [];
        }
        $keys = array_combine($incidents, array_map(fn ($incident) => 'incident-monitoring:type:v1:'.hash('sha256', $incident), $incidents));
        $cached = $retry ? [] : Cache::many(array_values($keys));
        $results = [];
        $missing = [];
        foreach ($keys as $incident => $key) {
            if (isset($cached[$key])) {
                $results[$incident] = $cached[$key];
            } else {
                $missing[] = $incident;
            }
        }
        if ($missing === []) {
            return $results;
        }
        $toCache = [];
        foreach ($this->tickets->lookupTypes($missing) as $incident => $ticket) {
            $results[$incident] = $ticket;
            if (! ($ticket['error'] ?? false)) {
                $results[$incident]['checked_at'] = now()->timestamp;
                if ($ticket['found'] ?? false) {
                    $toCache[$keys[$incident]] = $results[$incident];
                }
            }
        }
        if ($toCache !== []) {
            Cache::putMany($toCache, now()->addMinutes(10));
        }

        return $results;
    }

    private function classification(string $incident, array $ticket): array
    {
        if ($incident === '') {
            return ['no_incident', 'Incident belum diisi'];
        }
        if (str_starts_with($incident, 'INP')) {
            return ['manual', 'Tiket Manual'];
        }
        if ($ticket['error'] ?? false) {
            return ['unavailable', 'Belum terverifikasi'];
        }
        if (! ($ticket['found'] ?? false)) {
            return ['not_found', 'Tiket tidak ditemukan'];
        }
        $type = mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $ticket['jenis_tiket_2'] ?? '')));

        return $type === '' ? ['empty_type', 'Jenis tiket belum diisi'] : ['type:'.$type, $type];
    }

    private function filterLabels(array $filters, Collection $branches, Collection $serviceAreas, array $groups): array
    {
        return array_filter([
            'region' => $filters['region'] ? 'Region: '.$filters['region'] : null,
            'branch' => $filters['branch'] !== '' ? 'Branch: '.($branches->firstWhere('id_branch', (int) $filters['branch'])?->name ?? 'Di luar pilihan') : null,
            'service_area' => $filters['service_area'] !== '' ? 'STO: '.($serviceAreas->firstWhere('id_service_area', (int) $filters['service_area'])?->workzone ?? 'Di luar pilihan') : null,
            'program' => $filters['program'] ? ProgramType::from($filters['program'])->label() : null,
            'status' => $filters['status'] ? 'Status: '.LopStatus::from($filters['status'])->label() : null,
            'ticket_type' => $filters['ticket_type'] !== '' ? 'Jenis: '.($groups[$filters['ticket_type']]['label'] ?? 'Tidak tersedia') : null,
            'q' => $filters['q'] !== '' ? 'Cari: '.$filters['q'] : null,
            'missing_ihld' => $filters['missing_ihld'] ? 'IHLD belum diisi' : null,
        ]);
    }
}
