<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class TicketCoverageService
{
    public function __construct(private readonly LopVisibilityService $visibility) {}

    public function dashboard(User $user, array $input): array
    {
        $filters = [
            'region' => trim((string) ($input['region'] ?? '')),
            'branch' => $input['branch'] ?? '',
            'service_area' => $input['service_area'] ?? '',
            'kind' => $input['kind'] ?? '',
            'usage' => $input['usage'] ?? 'without_lop',
            'date_from' => $input['date_from'] ?? '',
            'date_to' => $input['date_to'] ?? '',
            'q' => trim((string) ($input['q'] ?? '')),
        ];
        $branches = Branch::query()->with('regionRef:id_region,name')
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

        // Occupancy includes archives and other scopes; only visible LOP details are loaded below.
        $occupancy = [];
        foreach (QeLop::withTrashed()->select(['id_qe_lops', 'incident'])->toBase()->cursor() as $lop) {
            $occupancy[$this->normalize($lop->incident)] = [
                'id' => (int) $lop->id_qe_lops,
                'usage' => 'with_lop',
            ];
        }
        $stats = null;
        $chartRows = collect();
        $tickets = collect();
        $total = 0;
        $sourceUnavailable = false;
        $checkedAt = null;
        $page = (int) ($input['page'] ?? 1);

        if ($restrictWorkzones && $selectedAreas->isEmpty()) {
            $stats = ['total' => 0, 'without_lop' => 0, 'with_lop' => 0];
        } else {
            try {
                [$stats, $chartRows, $tickets, $total] = $this->sourceData(
                    $filters, $selectedAreas, $restrictWorkzones, $occupancy, $page,
                );
                $checkedAt = now('Asia/Jakarta');
            } catch (Throwable $exception) {
                report($exception);
                $sourceUnavailable = true;
            }
        }

        $pageIds = $tickets->map(fn ($ticket) => $occupancy[$this->normalize($ticket->incident)]['id'] ?? null)->filter()->all();
        $visibleLops = $this->visibility->apply(QeLop::query()->whereIntegerInRaw('id_qe_lops', $pageIds), $user)
            ->get(['id_qe_lops', 'incident', 'nama_lop', 'ihld_id', 'status_lop', 'program_type'])
            ->keyBy('id_qe_lops');
        $areasByWorkzone = $serviceAreas->keyBy(fn (ServiceArea $area) => $this->normalize($area->workzone));
        $branchesById = $branches->keyBy('id_branch');
        $rows = $tickets->map(function ($ticket) use ($occupancy, $visibleLops, $areasByWorkzone, $branchesById): array {
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
            ];
        });
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
            'scopeLabel' => $user->hasRole(UserRole::SUPER_ADMIN) ? 'Seluruh wilayah' : $this->visibility->label($user),
            'scopeWarning' => $user->hasRole(UserRole::ADMIN) && $serviceAreas->isEmpty(),
        ];
    }

    private function sourceData(array $filters, Collection $areas, bool $restrictWorkzones, array $occupancy, int $page): array
    {
        $source = DB::connection('mysql_dompis');
        $types = $source->table('ticket')->distinct()->pluck('jenis_tiket_2')->filter(function ($type) use ($filters): bool {
            $normalized = $this->normalize($type);
            $kind = $normalized === 'INFRACARE' ? 'infracare' : (str_contains($normalized, 'GAMAS') ? 'gamas' : null);

            return $kind !== null && ($filters['kind'] === '' || $filters['kind'] === $kind);
        })->values();
        $query = $source->table('ticket')->whereIn('jenis_tiket_2', $types)
            ->whereRaw("TRIM(incident) <> ''");
        if ($restrictWorkzones) {
            $allowed = $areas->map(fn (ServiceArea $area) => $this->normalize($area->workzone))->flip();
            $workzones = $source->table('ticket')->distinct()->pluck('workzone')
                ->filter(fn ($workzone) => $allowed->has($this->normalize($workzone)))->values();
            $query->whereIn('workzone', $workzones);
        }
        if ($filters['q'] !== '') {
            $query->where(fn (Builder $q) => $q->where('incident', 'like', '%'.$filters['q'].'%')
                ->orWhere('workzone', 'like', '%'.$filters['q'].'%'));
        }

        $reportedDate = $this->reportedDateExpression($source->getDriverName());
        if ($filters['date_from'] !== '') {
            $query->whereRaw("{$reportedDate} >= ?", [$filters['date_from']]);
        }
        if ($filters['date_to'] !== '') {
            $query->whereRaw("{$reportedDate} <= ?", [$filters['date_to']]);
        }

        $used = array_keys($occupancy);
        $usedSql = $used === [] ? '0 = 1' : 'UPPER(TRIM(incident)) IN ('.implode(',', array_fill(0, count($used), '?')).')';
        $coverage = "CASE WHEN {$usedSql} THEN 'with_lop' ELSE 'without_lop' END";
        $groups = (clone $query)->select('jenis_tiket_2')
            ->selectRaw("{$coverage} AS coverage, COUNT(*) AS total", $used)
            ->groupBy('coverage', 'jenis_tiket_2')->get();
        $counts = $groups->groupBy('coverage')->map(fn (Collection $rows) => $rows->sum('total'));
        $stats = [
            'total' => (int) $counts->sum(),
            'without_lop' => (int) $counts->get('without_lop', 0),
            'with_lop' => (int) $counts->get('with_lop', 0),
        ];
        $chartRows = collect(['gamas' => 'GAMAS', 'infracare' => 'INFRACARE'])->map(function ($label, $kind) use ($groups): array {
            $count = (int) $groups->filter(fn ($row) => ($this->normalize($row->jenis_tiket_2) === 'INFRACARE' ? 'infracare' : 'gamas') === $kind)->sum('total');

            return ['kind' => $kind, 'label' => $label, 'count' => $count];
        })->filter(fn ($row) => $row['count'] > 0)->sortByDesc('count')->values();
        $max = max(1, (int) $chartRows->max('count'));
        $chartRows = $chartRows->map(fn ($row) => [...$row, 'width' => $row['count'] / $max * 100]);
        $total = $filters['usage'] === 'all' ? $stats['total'] : $stats[$filters['usage']];

        if ($filters['usage'] === 'without_lop') {
            $query->whereNotIn(DB::raw('UPPER(TRIM(incident))'), array_keys($occupancy));
        } elseif ($filters['usage'] !== 'all') {
            $query->whereIn(DB::raw('UPPER(TRIM(incident))'), $used);
        }
        $tickets = $query->select(['id_ticket', 'incident', 'jenis_tiket_2', 'workzone'])
            ->selectRaw("{$reportedDate} AS reported_day")
            ->orderByDesc('id_ticket')->forPage($page, 25)->get();

        return [$stats, $chartRows, $tickets, $total];
    }

    private function reportedDateExpression(string $driver): string
    {
        $value = 'TRIM(reported_date)';
        if ($driver === 'sqlite') {
            $rest = "SUBSTR({$value}, INSTR({$value}, '/') + 1)";
            $day = "SUBSTR({$value}, 1, INSTR({$value}, '/') - 1)";
            $month = "SUBSTR({$rest}, 1, INSTR({$rest}, '/') - 1)";
            $year = "SUBSTR({$rest}, INSTR({$rest}, '/') + 1, 4)";
            $slashDate = "DATE(PRINTF('%04d-%02d-%02d', {$year}, {$month}, {$day}))";
        } else {
            $slashDate = "DATE(STR_TO_DATE({$value}, '%d/%m/%Y'))";
        }

        return "CASE WHEN INSTR({$value}, '/') > 0 THEN {$slashDate} ELSE DATE({$value}) END";
    }

    private function normalize(?string $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }
}
