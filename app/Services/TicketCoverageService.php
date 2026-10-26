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

    public function monitoringContext(User $user, array $input): array
    {
        $reportedFrom = TicketReportedDate::floor();
        $dateFrom = trim((string) ($input['date_from'] ?? ''));
        $dateTo = trim((string) ($input['date_to'] ?? ''));
        $filters = [
            'region' => trim((string) ($input['region'] ?? '')),
            'branch' => $input['branch'] ?? '',
            'service_area' => $input['service_area'] ?? '',
            'kind' => $input['kind'] ?? '',
            'usage' => in_array($input['usage'] ?? '', ['all', 'without_lop', 'with_lop', 'excluded'], true) ? $input['usage'] : 'without_lop',
            'date_from' => $dateFrom !== '' ? max($dateFrom, $reportedFrom) : $reportedFrom,
            'date_to' => $dateTo !== '' ? $dateTo : now('Asia/Jakarta')->toDateString(),
            'q' => trim((string) ($input['q'] ?? '')),
        ];
        $branches = Branch::query()->with('regionRef:id_region,name,code')
            ->whereIn('id_branch', $this->visibility->accessibleBranchIds($user))
            ->orderBy('name')->get(['id_branch', 'name', 'region_id', 'region']);
        $serviceAreas = ServiceArea::query()
            ->whereIn('id_service_area', $this->visibility->accessibleServiceAreaIds($user))
            ->orderBy('workzone')->get(['id_service_area', 'workzone', 'branch_id']);
        $regions = $branches->map(fn (Branch $branch) => $branch->regionRef?->name ?? $branch->region)
            ->filter()->unique()->sort()->values();
        $selectedBranches = $branches->filter(fn (Branch $branch) => ($filters['region'] === '' || ($branch->regionRef?->name ?? $branch->region) === $filters['region'])
            && ($filters['branch'] === '' || (int) $branch->getKey() === (int) $filters['branch']));
        $selectedAreas = $serviceAreas->whereIn('branch_id', $selectedBranches->modelKeys())
            ->when($filters['service_area'] !== '', fn (Collection $areas) => $areas->where('id_service_area', (int) $filters['service_area']));
        $restrictWorkzones = $user->hasRole(UserRole::ADMIN)
            || $filters['region'] !== '' || $filters['branch'] !== '' || $filters['service_area'] !== '';

        return compact('filters', 'branches', 'serviceAreas', 'regions', 'selectedAreas', 'restrictWorkzones');
    }

    public function dashboard(User $user, array $input, bool $forExport = false): array
    {
        ['filters' => $filters, 'branches' => $branches, 'serviceAreas' => $serviceAreas,
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
        if ($restrictWorkzones) {
            $allowed = $areas->map(fn (ServiceArea $area) => $this->normalize($area->workzone))->flip();
            $workzonesQuery = $source->table('ticket');
            TicketReportedDate::applyRange($workzonesQuery, $driver, $filters['date_from'], $filters['date_to']);
            $workzones = $workzonesQuery->distinct()->pluck('workzone')
                ->filter(fn ($workzone) => $allowed->has($this->normalize($workzone)))->values();
            $query->whereIn('workzone', $workzones);
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
        IncidentExclude::updateOrCreate(
            ['incident' => $this->normalize($incident)],
            ['keterangan_exclude' => $reason, 'created_by' => $user->id_user]
        );
    }

    public function unexclude(string $incident): void
    {
        IncidentExclude::where('incident', $this->normalize($incident))->delete();
    }
}
