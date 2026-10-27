<?php

namespace App\Services;

use App\Enums\AdminScopeType;
use App\Enums\ExcludeReason;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\IncidentExclude;
use App\Models\ServiceArea;
use App\Models\User;
use App\Support\TicketReportedDate;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class TicketExcludeMonitoringService
{
    public const KINDS = ['gamas' => 'SQM / GAMAS', 'preman' => 'PREMAN', 'infracare' => 'INFRACARE'];

    private const UNNAMED_GROUP = 'STO TANPA NAMA';

    private const UNMAPPED_GROUP = 'STO BELUM TERDATA';

    public function __construct(
        private readonly TicketCoverageService $coverage,
        private readonly LopVisibilityService $visibility,
    ) {}

    public function dashboard(User $user, array $input): array
    {
        $period = $input['period'] ?? 'range';
        $month = $input['month'] ?? now('Asia/Jakarta')->format('Y-m');
        if ($period === 'month') {
            $start = Carbon::createFromFormat('!Y-m', $month);
            $input['date_from'] = max($start->toDateString(), TicketReportedDate::floor());
            $input['date_to'] = $start->endOfMonth()->toDateString();
        }
        $context = $this->coverage->monitoringContext($user, $input);
        ['filters' => $filters, 'branches' => $branches, 'serviceAreas' => $areas,
            'selectedAreas' => $selectedAreas, 'restrictWorkzones' => $restricted] = $context;
        // Drill-down changes the detail list only; the matrix keeps its global filters.
        $filters = [...$filters, 'period' => $period, 'month' => $month,
            'matrix_region' => $input['matrix_region'] ?? '', 'detail_kind' => $input['detail_kind'] ?? '',
            'exclude_state' => $input['exclude_state'] ?? 'all', 'reason' => $input['reason'] ?? ''];
        unset($filters['usage']);
        if ($filters['reason'] !== '') {
            $filters['exclude_state'] = 'excluded';
        }
        $reasons = collect(ExcludeReason::cases())->mapWithKeys(fn (ExcludeReason $reason) => [$reason->value => $reason->label()])->all();
        $excluded = IncidentExclude::query()->get(['incident', 'keterangan_exclude'])
            ->mapWithKeys(fn ($row) => [$this->coverage->normalize($row->incident) => $row->keterangan_exclude])->all();
        $buckets = [];
        foreach ($excluded as $incident => $reason) {
            $buckets[array_key_exists($reason ?? '', $reasons) ? $reason : 'other'][] = $incident;
        }
        if (isset($buckets['other'])) {
            $reasons['other'] = 'Alasan lainnya';
        }

        $groupBySto = $this->groupsBySto($user);
        $groupByBranch = $user->hasRole(UserRole::SUPER_ADMIN)
            || ($user->hasRole(UserRole::ADMIN) && $user->admin_scope_type === AdminScopeType::AREA);
        $rowByWorkzone = [];
        $regionByWorkzone = [];
        $regionLabels = [];
        $rowGroups = [];
        $rowRegions = [];
        $branchesById = $branches->keyBy('id_branch');
        if ($groupByBranch) {
            foreach ($context['selectedBranches'] as $branch) {
                if ($filters['service_area'] !== '' && ! $selectedAreas->contains('branch_id', $branch->getKey())) {
                    continue;
                }
                [$regionKey, $regionLabel] = $this->region($branch);
                $key = 'branch:'.$branch->getKey();
                $regionLabels[$key] = $branch->name;
                $rowGroups[$key] = $regionLabel;
                $rowRegions[$key] = $regionKey === 'unmapped' ? 'region:unmapped' : $regionKey;
            }
        }
        $matrixAreas = $groupBySto
            ? $selectedAreas->sortBy(fn (ServiceArea $area) => mb_strtolower($this->serviceAreaGroup($area)).'|'.$this->coverage->normalize($area->workzone))->values()
            : $selectedAreas;
        foreach ($matrixAreas as $area) {
            $normalizedWorkzone = $this->coverage->normalize($area->workzone);
            $branch = $branchesById->get($area->branch_id);
            [$regionKey, $regionLabel] = $this->region($branch);
            $regionByWorkzone[$normalizedWorkzone] = $regionKey === 'unmapped' ? 'region:unmapped' : $regionKey;
            if ($groupBySto) {
                $key = 'sto:'.$normalizedWorkzone;
                $rowByWorkzone[$normalizedWorkzone] = $key;
                $regionLabels[$key] = trim((string) $area->workzone) ?: 'STO tanpa nama';
                $rowGroups[$key] = $this->serviceAreaGroup($area);

                continue;
            }
            $key = $groupByBranch && $branch ? 'branch:'.$branch->getKey() : $regionKey;
            $label = $groupByBranch ? ($branch?->name ?? 'Branch belum terpetakan') : $regionLabel;
            $rowByWorkzone[$normalizedWorkzone] = $key;
            $regionLabels[$key] = $label;
            if ($groupByBranch) {
                $rowGroups[$key] = $regionLabel;
                $rowRegions[$key] = $regionByWorkzone[$normalizedWorkzone];
            }
        }
        $matrixRows = [];
        $matrixKeys = $groupBySto || $groupByBranch
            ? array_keys($regionLabels)
            : array_unique(['jatengdiy', 'jatim', 'balnus', ...array_keys($regionLabels)]);
        foreach ($matrixKeys as $key) {
            if (isset($regionLabels[$key])) {
                $matrixRows[$key] = [...$this->emptyRow($key, $regionLabels[$key], $reasons, $rowGroups[$key] ?? null),
                    'region_key' => $rowRegions[$key] ?? null];
            }
        }
        $knownWorkzones = array_keys($rowByWorkzone);
        $total = $this->emptyRow('', 'TOTAL', $reasons);
        $rawTickets = collect();
        $detailTotal = 0;
        $sourceUnavailable = false;
        $checkedAt = null;
        $page = (int) ($input['page'] ?? 1);

        if (! $restricted || $selectedAreas->isNotEmpty()) {
            try {
                [$base, $reportedDate] = $this->coverage->sourceQuery($filters, $selectedAreas, $restricted);
                [$classification, $bindings] = $this->classification($buckets);
                $groups = (clone $base)->select('jenis_tiket_2')
                    ->selectRaw('UPPER(TRIM(workzone)) AS normalized_workzone')
                    ->selectRaw("{$classification} AS exclude_bucket, COUNT(*) AS total", $bindings)
                    ->groupBy('normalized_workzone', 'jenis_tiket_2', 'exclude_bucket')->get();
                foreach ($groups as $group) {
                    $key = $rowByWorkzone[$group->normalized_workzone ?? ''] ?? 'unmapped';
                    $matrixRows[$key] ??= [...$this->emptyRow(
                        $key,
                        $groupBySto ? 'STO belum terdata' : ($groupByBranch ? 'Branch belum terpetakan' : 'Wilayah belum terpetakan'),
                        $reasons,
                        $groupBySto ? self::UNMAPPED_GROUP : ($groupByBranch ? 'Wilayah belum terpetakan' : null),
                    ), 'region_key' => $groupByBranch ? 'region:unmapped' : null];
                    $kind = $this->coverage->ticketKind($group->jenis_tiket_2);
                    $bucket = $group->exclude_bucket;
                    $state = $bucket === 'not_excluded' ? 'not_excluded' : 'excluded';
                    foreach ([$key, 'total'] as $target) {
                        $row = $target === 'total' ? $total : $matrixRows[$target];
                        $row['cells'][$kind.':'.$state] += (int) $group->total;
                        if ($state === 'excluded') {
                            $row['cells'][$kind.':'.$bucket] += (int) $group->total;
                        }
                        $row['count'] += (int) $group->total;
                        if ($target === 'total') {
                            $total = $row;
                        } else {
                            $matrixRows[$target] = $row;
                        }
                    }
                }

                $query = clone $base;
                if ($filters['matrix_region'] !== '') {
                    $zones = array_keys(array_filter($rowByWorkzone, fn ($key, $zone) => $key === $filters['matrix_region']
                        || ($groupByBranch && ($regionByWorkzone[$zone] ?? '') === $filters['matrix_region']), ARRAY_FILTER_USE_BOTH));
                    if ($filters['matrix_region'] === 'unmapped' || ($groupByBranch && $filters['matrix_region'] === 'region:unmapped')) {
                        $query->where(fn (Builder $q) => $q->whereNull('workzone')
                            ->orWhereNotIn(DB::raw('UPPER(TRIM(workzone))'), $knownWorkzones)
                            ->orWhereIn(DB::raw('UPPER(TRIM(workzone))'), $zones));
                    } else {
                        $query->whereIn(DB::raw('UPPER(TRIM(workzone))'), $zones);
                    }
                }
                if ($filters['detail_kind'] !== '') {
                    $types = $groups->pluck('jenis_tiket_2')->unique()
                        ->filter(fn ($type) => $this->coverage->ticketKind($type) === $filters['detail_kind'])->values();
                    $query->whereIn('jenis_tiket_2', $types);
                }
                if ($filters['reason'] !== '') {
                    $query->whereIn(DB::raw('UPPER(TRIM(incident))'), $buckets[$filters['reason']] ?? []);
                } elseif ($filters['exclude_state'] === 'excluded') {
                    $query->whereIn(DB::raw('UPPER(TRIM(incident))'), array_keys($excluded));
                } elseif ($filters['exclude_state'] === 'not_excluded') {
                    $query->whereNotIn(DB::raw('UPPER(TRIM(incident))'), array_keys($excluded));
                }
                $detailTotal = $query->count();
                $rawTickets = $query->select(['id_ticket', 'incident', 'jenis_tiket_2', 'workzone'])
                    ->selectRaw("{$reportedDate} AS reported_day")->orderByDesc('id_ticket')->forPage($page, 25)->get();
                $checkedAt = now('Asia/Jakarta');
            } catch (Throwable $exception) {
                report($exception);
                $sourceUnavailable = true;
                $rawTickets = collect();
                $detailTotal = 0;
            }
        }
        $rows = $this->coverage->presentRows($rawTickets, [], $excluded, $areas, $branches, $user)
            ->map(fn ($row) => [...$row, 'is_excluded' => array_key_exists($row['incident'], $excluded),
                'reason_label' => $reasons[$row['exclude_reason'] ?? ''] ?? 'Alasan lainnya']);
        $tickets = new LengthAwarePaginator($rows, $detailTotal, 25, $page, [
            'path' => route('incident-monitoring.exclusions'),
            'query' => array_filter($filters, fn ($value) => $value !== ''),
            'fragment' => 'ticket-details',
        ]);
        $matrixGroups = [];
        if ($groupByBranch) {
            $matrixRows = collect($matrixRows)->sortBy(fn (array $row) => (['jatengdiy' => 0, 'jatim' => 1, 'balnus' => 2, 'region:unmapped' => 4][$row['region_key']] ?? 3)
                .'|'.mb_strtolower($row['group']).'|'.mb_strtolower($row['label']))->all();
        }
        if (($groupBySto || $groupByBranch) && $matrixRows !== []) {
            $matrixGroups = collect($matrixRows)
                ->groupBy(fn (array $row) => $groupByBranch ? $row['region_key'] : ($row['group'] ?? self::UNMAPPED_GROUP))
                ->map(function (Collection $rows, string $key) use ($groupByBranch): array {
                    $cells = [];
                    foreach (array_keys($rows->first()['cells']) as $cellKey) {
                        $cells[$cellKey] = (int) $rows->sum(fn (array $row) => $row['cells'][$cellKey]);
                    }

                    return [
                        'key' => $groupByBranch ? $key : '',
                        'label' => $rows->first()['group'],
                        'name' => $rows->first()['group'],
                        'row_count' => $rows->count(),
                        'count' => (int) $rows->sum('count'),
                        'cells' => $cells,
                        'rows' => $rows->values()->all(),
                    ];
                })
                ->values()
                ->all();
        }
        $detailLabel = implode(' · ', array_filter([
            $filters['matrix_region'] !== ''
                ? ($matrixRows[$filters['matrix_region']]['label'] ?? collect($matrixGroups)->firstWhere('key', $filters['matrix_region'])['name'] ?? ($groupBySto ? 'STO pilihan' : 'Wilayah pilihan'))
                : ($groupBySto ? 'Semua STO' : ($groupByBranch ? 'Semua regional / branch' : 'Semua regional')),
            self::KINDS[$filters['detail_kind']] ?? null,
            ['all' => 'Semua tiket', 'excluded' => 'Sudah exclude', 'not_excluded' => 'Belum exclude'][$filters['exclude_state']],
            $reasons[$filters['reason']] ?? null,
        ]));

        return [
            'filters' => $filters, 'branches' => $branches, 'serviceAreas' => $areas, 'regions' => $context['regions'],
            'matrixRows' => $matrixRows, 'matrixGroups' => $matrixGroups, 'totals' => $total,
            'tickets' => $tickets, 'reasons' => $reasons,
            'kinds' => self::KINDS, 'sourceUnavailable' => $sourceUnavailable, 'checkedAt' => $checkedAt,
            'detailLabel' => $detailLabel, 'groupBySto' => $groupBySto, 'groupByBranch' => $groupByBranch,
            'scopeLabel' => $user->hasRole(UserRole::SUPER_ADMIN) ? 'Seluruh wilayah' : $this->visibility->label($user),
            'scopeWarning' => $user->hasRole(UserRole::ADMIN) && $areas->isEmpty(),
        ];
    }

    /** Branch and service-area admins work per STO, so the matrix uses STO rows for them. */
    private function groupsBySto(User $user): bool
    {
        if (! $user->hasRole(UserRole::ADMIN)) {
            return false;
        }

        $scope = $user->admin_scope_type
            ?? ($user->branch_id ? AdminScopeType::BRANCH : null);

        return in_array($scope, [AdminScopeType::BRANCH, AdminScopeType::SERVICE_AREA], true);
    }

    private function classification(array $buckets): array
    {
        $sql = 'CASE';
        $bindings = [];
        foreach ($buckets as $reason => $incidents) {
            $sql .= ' WHEN UPPER(TRIM(incident)) IN ('.implode(',', array_fill(0, count($incidents), '?')).') THEN ?';
            $bindings = [...$bindings, ...$incidents, $reason];
        }

        return $buckets === [] ? ["'not_excluded'", []] : [$sql." ELSE 'not_excluded' END", $bindings];
    }

    private function emptyRow(string $key, string $label, array $reasons, ?string $group = null): array
    {
        $cells = [];
        foreach (self::KINDS as $kind => $name) {
            foreach (['excluded', 'not_excluded', ...array_keys($reasons)] as $bucket) {
                $cells[$kind.':'.$bucket] = 0;
            }
        }

        return ['key' => $key, 'label' => $label, 'group' => $group, 'count' => 0, 'cells' => $cells];
    }

    private function serviceAreaGroup(ServiceArea $area): string
    {
        $name = trim((string) $area->name);

        return $name !== '' ? $name : self::UNNAMED_GROUP;
    }

    private function region(?Branch $branch): array
    {
        $name = $branch?->regionRef?->name ?? $branch?->region ?? '';
        $identity = preg_replace('/[^A-Z]/', '', $this->coverage->normalize($name));
        $code = $branch?->regionRef?->code;

        return match (true) {
            $code === 'JATENGDIY', in_array($identity, ['JATENGDIY', 'REGIONJATENGDIY'], true) => ['jatengdiy', 'JATENG DIY'],
            $code === 'JATIM', in_array($identity, ['JATIM', 'REGIONJATIM', 'JAWATIMUR'], true) => ['jatim', 'JAWA TIMUR'],
            $code === 'BALNUS', in_array($identity, ['BALNUS', 'REGIONBALNUS', 'BALINUSRA', 'BALINUSATENGGARA'], true) => ['balnus', 'BALI NUSRA'],
            $name !== '' => ['region:'.$this->coverage->normalize($name), $name],
            default => ['unmapped', 'Wilayah belum terpetakan'],
        };
    }
}
