<?php

namespace App\Services;

use App\Enums\ExcludeReason;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\IncidentExclude;
use App\Models\QeLop;
use App\Models\ServiceArea;
use App\Models\User;
use App\Support\TicketReportedDate;
use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class TicketCoverageService
{
    public function __construct(private readonly LopVisibilityService $visibility) {}

    private function resolveWorkzones(array $filters, array $branchQuery): array
    {
        $selected = collect();
        if ($filters['region'] !== '') {
            $selected = $branchQuery->where('region', $filters['region'])->pluck('id_branch');
        }
        if ($filters['branch'] !== '') {
            $selected = $selected->merge([$filters['branch']]);
        }
        $areaQuery = ServiceArea::query()->whereIn('branch_id', $selected);

        if (! empty($filters['sto_filter'])) {
            $areaQuery->whereIn('workzone', $filters['sto_filter']);
        }

        return $areaQuery->pluck('workzone')->map(fn ($w) => mb_strtoupper(trim((string) $w)))->flip()->filter()->all();
    }

    public function monitoringContext(User $user, array $input): array
    {
        $reportedFrom = TicketReportedDate::floor();
        $period = ($input['period'] ?? 'range') === 'month' ? 'month' : 'range';
        $month = $input['month'] ?? now('Asia/Jakarta')->format('Y-m');
        $dateFrom = trim((string) ($input['date_from'] ?? ''));
        $dateTo = trim((string) ($input['date_to'] ?? ''));
        if ($period === 'month') {
            $monthStart = Carbon::createFromFormat('!Y-m', $month);
            $dateFrom = max($monthStart->toDateString(), $reportedFrom);
            $dateTo = $monthStart->endOfMonth()->toDateString();
        }
        $filters = [
            'region' => trim((string) ($input['region'] ?? '')),
            'branch' => $input['branch'] ?? '',
            'service_area' => $input['service_area'] ?? '',
            'service_area_name' => trim((string) ($input['service_area_name'] ?? '')),
            'kind' => $input['kind'] ?? '',
            'usage' => in_array($input['usage'] ?? '', ['all', 'without_lop', 'with_lop', 'excluded'], true) ? $input['usage'] : 'without_lop',
            'period' => $period,
            'month' => $month,
            'date_from' => $dateFrom !== '' ? max($dateFrom, $reportedFrom) : $reportedFrom,
            'date_to' => $dateTo !== '' ? $dateTo : now('Asia/Jakarta')->toDateString(),
            'q' => trim((string) ($input['q'] ?? '')),
            'sto_filter' => $input['sto_filter'] ?? [],
        ];
        $branches = Branch::query()->with(['regionRef:id_region,name,code', 'serviceAreas:id_service_area,workzone,name,branch_id'])
            ->whereIn('id_branch', $this->visibility->accessibleBranchIds($user))
            ->orderBy('name')->get(['id_branch', 'name', 'region_id', 'region']);
        $serviceAreas = ServiceArea::query()
            ->whereIn('id_service_area', $this->visibility->accessibleServiceAreaIds($user))
            ->orderBy('workzone')->get(['id_service_area', 'workzone', 'name', 'branch_id']);
        $regions = $branches->map(fn (Branch $branch) => $branch->regionRef?->name ?? $branch->region)
            ->filter()->unique()->sort()->values();
        $selectedBranches = $branches->filter(fn (Branch $branch) => ($filters['region'] === '' || ($branch->regionRef?->name ?? $branch->region) === $filters['region'])
            && ($filters['branch'] === '' || (int) $branch->getKey() === (int) $filters['branch']));
        $selectedAreas = $serviceAreas->whereIn('branch_id', $selectedBranches->modelKeys())
            ->when($filters['service_area'] !== '', fn (Collection $areas) => $areas->where('id_service_area', (int) $filters['service_area']))
            ->when($filters['service_area_name'] !== '', fn (Collection $areas) => $areas->filter(
                fn (ServiceArea $area) => trim((string) $area->name) === $filters['service_area_name']
            ));
        $serviceAreaNames = $serviceAreas->whereIn('branch_id', $selectedBranches->modelKeys())
            ->map(fn (ServiceArea $area) => trim((string) $area->name))
            ->filter()->unique()->sort()->values();
        $restrictWorkzones = $user->hasRole(UserRole::ADMIN)
            || $filters['region'] !== '' || $filters['branch'] !== '' || $filters['service_area'] !== '' || $filters['service_area_name'] !== '';

        return compact('filters', 'branches', 'serviceAreas', 'serviceAreaNames', 'regions', 'selectedBranches', 'selectedAreas', 'restrictWorkzones');
    }

    public function dashboard(User $user, array $input, bool $forExport = false): array
    {
        ['filters' => $filters, 'branches' => $branches, 'serviceAreas' => $serviceAreas, 'serviceAreaNames' => $serviceAreaNames,
            'regions' => $regions, 'selectedAreas' => $selectedAreas, 'restrictWorkzones' => $restrictWorkzones] = $this->monitoringContext($user, $input);

        // Occupancy includes archives and other scopes; only visible LOP details are loaded below.
        $occupancy = [];
        foreach (QeLop::withTrashed()->select(['id_qe_lops', 'incident'])->toBase()->cursor() as $lop) {
            $occupancy[$this->normalize($lop->incident)] = [
                'id' => (int) $lop->id_qe_lops,
                'usage' => 'with_lop',
            ];
        }
        // Load excluded incidents keyed by normalized incident.
        $excluded = IncidentExclude::query()->get(['incident', 'keterangan_exclude'])
            ->mapWithKeys(fn (IncidentExclude $exclude) => [
                $this->normalize($exclude->incident) => $exclude->keterangan_exclude,
            ]);
        $stats = null;
        $chartRows = collect();
        $tickets = collect();
        $total = 0;
        $sourceUnavailable = false;
        $checkedAt = null;
        $page = (int) ($input['page'] ?? 1);

        if ($restrictWorkzones && $selectedAreas->isEmpty()) {
            $stats = ['total' => 0, 'without_lop' => 0, 'with_lop' => 0, 'excluded' => 0];
        } else {
            try {
                [$stats, $chartRows, $tickets, $total] = $this->sourceData(
                    $filters, $selectedAreas, $restrictWorkzones, $occupancy, $excluded->all(), $page, $forExport,
                );
                $checkedAt = now('Asia/Jakarta');
            } catch (Throwable $exception) {
                report($exception);
                $sourceUnavailable = true;
            }
        }

        $scopeLabel = $user->hasRole(UserRole::SUPER_ADMIN) ? 'Seluruh wilayah' : $this->visibility->label($user);
        if ($forExport) {
            return [
                'filters' => $filters, 'stats' => $stats, 'sourceUnavailable' => $sourceUnavailable, 'scopeLabel' => $scopeLabel,
                'locationLabel' => implode('; ', array_filter([
                    $filters['region'],
                    $filters['branch'] !== '' ? ($branches->firstWhere('id_branch', (int) $filters['branch'])?->name ?? 'Branch di luar scope') : null,
                    $filters['service_area_name'] !== '' ? $filters['service_area_name'] : null,
                    $filters['service_area'] !== '' ? ($serviceAreas->firstWhere('id_service_area', (int) $filters['service_area'])?->workzone ?? 'STO di luar scope') : null,
                ])) ?: 'Seluruh wilayah dalam scope',
                'checkedAt' => $checkedAt,
                'rows' => $this->exportRows($tickets instanceof Builder ? $tickets : null, $occupancy, $excluded->all(), $serviceAreas, $branches, $user),
            ];
        }
        $rows = $this->presentRows($tickets, $occupancy, $excluded->all(), $serviceAreas, $branches, $user);
        $paginator = new LengthAwarePaginator($rows, $total, 25, $page, [
            'path' => route('incident-monitoring.tickets'),
            'query' => array_filter($filters, fn ($value) => $value !== ''),
        ]);

        return [
            'filters' => $filters,
            'branches' => $branches,
            'serviceAreas' => $serviceAreas,
            'serviceAreaNames' => $serviceAreaNames,
            'regions' => $regions,
            'stats' => $stats,
            'chartRows' => $chartRows,
            'tickets' => $paginator,
            'sourceUnavailable' => $sourceUnavailable,
            'checkedAt' => $checkedAt,
            'scopeLabel' => $scopeLabel,
            'scopeWarning' => $user->hasRole(UserRole::ADMIN) && $serviceAreas->isEmpty(),
            'excludeReasons' => collect(ExcludeReason::cases())
                ->mapWithKeys(fn (ExcludeReason $reason) => [$reason->value => $reason->label()]),
        ];
    }

    /**
     * Hitung tiket yang belum punya LOP dan belum di-exclude per branch dan
     * service area, mengikuti periode default Monitoring Incident.
     *
     * @return array{branch: array<int, int>, service_area: array<int, int>}|null null bila sumber tiket tidak tersedia
     */
    public function withoutExcludeCountsByLocation(User $user): ?array
    {
        try {
            $context = $this->monitoringContext($user, []);
            $areas = $context['selectedAreas'];
            if ($context['restrictWorkzones'] && $areas->isEmpty()) {
                return [];
            }

            $occupancy = QeLop::withTrashed()->whereNotNull('incident')->pluck('incident')
                ->map(fn ($incident) => $this->normalize($incident))->flip();
            $excluded = IncidentExclude::query()->pluck('incident')
                ->map(fn ($incident) => $this->normalize($incident))->flip();
            $areaByWorkzone = $areas->keyBy(fn (ServiceArea $area) => $this->normalize($area->workzone));
            $counts = ['branch' => [], 'service_area' => []];

            [$query] = $this->sourceQuery($context['filters'], $areas, $context['restrictWorkzones']);
            $used = $occupancy->keys()->all();
            $excludedIncidents = $excluded->keys()->all();
            $query->whereNotIn(DB::raw('UPPER(TRIM(incident))'), $used)
                ->whereNotIn(DB::raw('UPPER(TRIM(incident))'), $excludedIncidents);

            $grouped = $query->selectRaw('UPPER(TRIM(workzone)) AS normalized_workzone, COUNT(*) AS total')
                ->groupBy('normalized_workzone')->get();

            foreach ($grouped as $row) {
                $area = $areaByWorkzone->get($row->normalized_workzone);
                if ($area === null) {
                    continue;
                }

                $areaId = (int) $area->id_service_area;
                $branchId = (int) $area->branch_id;
                $total = (int) $row->total;
                $counts['service_area'][$areaId] = ($counts['service_area'][$areaId] ?? 0) + $total;
                $counts['branch'][$branchId] = ($counts['branch'][$branchId] ?? 0) + $total;
            }

            return $counts;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public function presentRows(Collection $tickets, array $occupancy, array $excluded, Collection $serviceAreas, Collection $branches, User $user): Collection
    {
        $pageIds = $tickets->map(fn ($ticket) => $occupancy[$this->normalize($ticket->incident)]['id'] ?? null)->filter()->all();
        $visibleLops = $pageIds === [] ? collect() : $this->visibility->apply(QeLop::query()->whereIntegerInRaw('id_qe_lops', $pageIds), $user)
            ->get(['id_qe_lops', 'incident', 'nama_lop', 'ihld_id', 'status_lop', 'program_type'])
            ->keyBy('id_qe_lops');
        $areasByWorkzone = $serviceAreas->keyBy(fn (ServiceArea $area) => $this->normalize($area->workzone));
        $branchesById = $branches->keyBy('id_branch');

        return $tickets->map(function ($ticket) use ($occupancy, $excluded, $visibleLops, $areasByWorkzone, $branchesById): array {
            $incident = $this->normalize($ticket->incident);
            $match = $occupancy[$incident] ?? null;
            $area = $areasByWorkzone->get($this->normalize($ticket->workzone));

            return [
                'incident' => $incident,
                'ticket_type' => $this->normalize($ticket->jenis_tiket_2),
                'reported_date' => $ticket->reported_day === null ? null : Carbon::createFromFormat('!Y-m-d', $ticket->reported_day)->format('d/m/Y'),
                'workzone' => $this->normalize($ticket->workzone) ?: 'Belum terisi',
                'branch' => $area ? $branchesById->get($area->branch_id)?->name : null,
                'usage' => $match['usage'] ?? 'without_lop',
                'lop' => $match ? $visibleLops->get($match['id']) : null,
                'exclude_reason' => $excluded[$incident] ?? null,
            ];
        });
    }

    private function exportRows(?Builder $query, array $occupancy, array $excluded, Collection $areas, Collection $branches, User $user): Generator
    {
        if ($query === null) {
            return;
        }
        $lastId = null;
        do {
            $chunk = (clone $query)->when($lastId !== null, fn (Builder $q) => $q->where('id_ticket', '<', $lastId))->limit(500)->get();
            yield from $this->presentRows($chunk, $occupancy, $excluded, $areas, $branches, $user);
            $lastId = $chunk->last()?->id_ticket;
        } while ($chunk->count() === 500);
    }

    public function sourceQuery(array $filters, Collection $areas, bool $restrictWorkzones): array
    {
        $source = DB::connection('mysql_dompis');
        $driver = $source->getDriverName();
        $typesQuery = $source->table('ticket');
        TicketReportedDate::applyRange($typesQuery, $driver, $filters['date_from'], $filters['date_to']);
        $types = $typesQuery->distinct()->pluck('jenis_tiket_2')->filter(function ($type) use ($filters): bool {
            $kind = $this->ticketKind($type);

            return $kind !== null && ($filters['kind'] === '' || $filters['kind'] === $kind);
        })->values();
        $query = $source->table('ticket')->whereIn('jenis_tiket_2', $types)
            ->whereRaw("TRIM(incident) <> ''");
        $allowed = $areas->map(fn (ServiceArea $area) => $this->normalize($area->workzone))->flip();
        if (! empty($filters['sto_filter'])) {
            $filteredSto = collect($filters['sto_filter'])->map(fn ($workzone) => $this->normalize($workzone));
            $allowed = $allowed->intersectByKeys($filteredSto->flip());
        }
        if ($restrictWorkzones || ! empty($filters['sto_filter'])) {
            $workzones = $allowed->keys()->all();
            if ($workzones === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn(DB::raw("UPPER(TRIM(COALESCE(workzone, '')) )"), $workzones);
            }
        }
        if ($filters['q'] !== '') {
            $query->where(fn (Builder $q) => $q->where('incident', 'like', '%'.$filters['q'].'%')
                ->orWhere('workzone', 'like', '%'.$filters['q'].'%'));
        }

        $reportedDate = TicketReportedDate::applyRange($query, $driver, $filters['date_from'], $filters['date_to']);

        return [$query, $reportedDate];
    }

    private function sourceData(array $filters, Collection $areas, bool $restrictWorkzones, array $occupancy, array $excluded, int $page, bool $forExport = false): array
    {
        [$query, $reportedDate] = $this->sourceQuery($filters, $areas, $restrictWorkzones);

        $used = array_keys($occupancy);
        $usedSql = $used === [] ? '0 = 1' : 'UPPER(TRIM(incident)) IN ('.implode(',', array_fill(0, count($used), '?')).')';
        $excludedKeys = array_keys($excluded);
        $excludedSql = $excludedKeys === [] ? '0 = 1' : 'UPPER(TRIM(incident)) IN ('.implode(',', array_fill(0, count($excludedKeys), '?')).')';
        // without_lop: no LOP and not excluded; excluded: no LOP but excluded.
        $coverage = "CASE WHEN {$usedSql} THEN 'with_lop' WHEN {$excludedSql} THEN 'excluded' ELSE 'without_lop' END";
        $groups = (clone $query)->select('jenis_tiket_2')
            ->selectRaw("{$coverage} AS coverage, COUNT(*) AS total", [...$used, ...$excludedKeys])
            ->groupBy('coverage', 'jenis_tiket_2')->get();
        $counts = $groups->groupBy('coverage')->map(fn (Collection $rows) => $rows->sum('total'));
        $stats = [
            'total' => (int) $counts->sum(),
            'without_lop' => (int) $counts->get('without_lop', 0),
            'with_lop' => (int) $counts->get('with_lop', 0),
            'excluded' => (int) $counts->get('excluded', 0),
        ];
        $chartRows = collect(['gamas' => 'GAMAS', 'infracare' => 'INFRACARE', 'preman' => 'PREMAN'])->map(function ($label, $kind) use ($groups): array {
            $count = (int) $groups->filter(fn ($row) => $this->ticketKind($row->jenis_tiket_2) === $kind)->sum('total');

            return ['kind' => $kind, 'label' => $label, 'count' => $count];
        })->filter(fn ($row) => $row['count'] > 0)->sortByDesc('count')->values();
        $max = max(1, (int) $chartRows->max('count'));
        $chartRows = $chartRows->map(fn ($row) => [...$row, 'width' => $row['count'] / $max * 100]);
        $total = $filters['usage'] === 'all' ? $stats['total'] : $stats[$filters['usage']];

        if ($filters['usage'] === 'without_lop') {
            $query->whereNotIn(DB::raw('UPPER(TRIM(incident))'), array_keys($occupancy));
            $query->whereNotIn(DB::raw('UPPER(TRIM(incident))'), $excludedKeys);
        } elseif ($filters['usage'] === 'excluded') {
            $query->whereIn(DB::raw('UPPER(TRIM(incident))'), $excludedKeys);
        } elseif ($filters['usage'] !== 'all') {
            $query->whereIn(DB::raw('UPPER(TRIM(incident))'), $used);
        }
        $query->select(['id_ticket', 'incident', 'jenis_tiket_2', 'workzone'])
            ->selectRaw("{$reportedDate} AS reported_day")
            ->orderByDesc('id_ticket');
        $tickets = $forExport ? $query : $query->forPage($page, 25)->get();

        return [$stats, $chartRows, $tickets, $total];
    }

    public function ticketKind(?string $type): ?string
    {
        $normalized = $this->normalize($type);

        return match (true) {
            $normalized === 'INFRACARE' => 'infracare',
            // PREMAN hanya diakui persis; varian lain di luar cakupan monitoring.
            $normalized === 'PREMAN' => 'preman',
            str_contains($normalized, 'GAMAS') => 'gamas',
            default => null,
        };
    }

    public function normalize(?string $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    public function exclude(string $incident, string $reason, User $user): void
    {
        $this->assertIncidentInScope($user, $incident);

        IncidentExclude::updateOrCreate(
            ['incident' => $this->normalize($incident)],
            ['keterangan_exclude' => $reason, 'created_by' => $user->id_user]
        );
    }

    /** @return array{eligible:list<string>, already_excluded:list<string>, has_lop:list<string>, not_found:list<string>, out_of_scope:list<string>} */
    public function previewMany(array $incidents, User $user): array
    {
        $normalized = collect($incidents)->map(fn ($incident) => $this->normalize($incident))
            ->filter()->unique()->values();
        abort_if($normalized->isEmpty(), 422, 'Masukkan minimal satu nomor incident.');

        $result = ['eligible' => [], 'already_excluded' => [], 'has_lop' => [], 'not_found' => [], 'out_of_scope' => []];
        $tickets = DB::connection('mysql_dompis')->table('ticket')
            ->whereIn(DB::raw('UPPER(TRIM(incident))'), $normalized->all())
            ->get(['incident', 'workzone', 'jenis_tiket_2', 'reported_date'])
            ->groupBy(fn ($ticket) => $this->normalize($ticket->incident));
        $knownLops = QeLop::withTrashed()->pluck('incident')->map(fn ($incident) => $this->normalize($incident))->flip();
        $existing = IncidentExclude::query()->whereIn('incident', $normalized)->pluck('incident')->flip();
        $allowedWorkzones = $user->hasRole(UserRole::ADMIN)
            ? ServiceArea::query()->whereIn('id_service_area', $this->visibility->accessibleServiceAreaIds($user))
                ->pluck('workzone')->map(fn ($workzone) => $this->normalize($workzone))->filter()->flip()
            : null;

        foreach ($normalized as $incident) {
            $matches = $tickets->get($incident);
            if ($matches === null || $matches->isEmpty()) {
                $result['not_found'][] = $incident;

                continue;
            }
            $matches = $matches->filter(fn ($ticket) => $this->ticketKind($ticket->jenis_tiket_2) !== null
                && $ticket->reported_date !== null
                && (string) $ticket->reported_date >= TicketReportedDate::floor());
            if ($matches->isEmpty()) {
                $result['not_found'][] = $incident;

                continue;
            }
            if ($allowedWorkzones !== null && $matches->every(fn ($ticket) => ! $allowedWorkzones->has($this->normalize($ticket->workzone)))) {
                $result['out_of_scope'][] = $incident;

                continue;
            }
            if ($knownLops->has($incident)) {
                $result['has_lop'][] = $incident;

                continue;
            }
            if ($existing->has($incident)) {
                $result['already_excluded'][] = $incident;

                continue;
            }
            $result['eligible'][] = $incident;
        }

        return $result;
    }

    /** @return array{excluded:int, already_excluded:int, has_lop:int, not_found:int, out_of_scope:int} */
    public function excludeMany(array $incidents, string $reason, User $user): array
    {
        $preview = $this->previewMany($incidents, $user);
        $stats = [
            'excluded' => 0,
            'already_excluded' => count($preview['already_excluded']),
            'has_lop' => count($preview['has_lop']),
            'not_found' => count($preview['not_found']),
            'out_of_scope' => count($preview['out_of_scope']),
        ];

        DB::transaction(function () use ($preview, $reason, $user, &$stats): void {
            foreach ($preview['eligible'] as $incident) {
                IncidentExclude::create([
                    'incident' => $incident,
                    'keterangan_exclude' => $reason,
                    'created_by' => $user->id_user,
                ]);
                $stats['excluded']++;
            }
        });

        return $stats;
    }

    public function unexclude(string $incident, User $user): bool
    {
        $this->assertIncidentInScope($user, $incident);

        return IncidentExclude::where('incident', $this->normalize($incident))->delete() > 0;
    }

    private function assertIncidentInScope(User $user, string $incident): void
    {
        if (! $user->hasRole(UserRole::ADMIN)) {
            return;
        }

        $allowedWorkzones = ServiceArea::query()
            ->whereIn('id_service_area', $this->visibility->accessibleServiceAreaIds($user))
            ->pluck('workzone')
            ->map(fn ($workzone) => $this->normalize($workzone))
            ->filter()
            ->flip();

        if ($allowedWorkzones->isEmpty()) {
            abort(403, 'Akun Anda belum memiliki akses STO untuk tiket ini.');
        }

        $workzones = DB::connection('mysql_dompis')->table('ticket')
            ->whereRaw('UPPER(TRIM(incident)) = ?', [$this->normalize($incident)])
            ->pluck('workzone');

        if ($workzones->isEmpty()) {
            abort(404, 'Tiket tidak ditemukan pada sumber Dompis.');
        }

        if (! $workzones->contains(fn ($workzone) => $allowedWorkzones->has($this->normalize($workzone)))) {
            abort(403, 'Tiket berada di luar scope akses Anda.');
        }
    }
}
