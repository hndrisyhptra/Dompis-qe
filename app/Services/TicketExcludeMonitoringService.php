<?php

namespace App\Services;

use App\Enums\AdminScopeType;
use App\Enums\ExcludeReason;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeLop;
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
        ['filters' => $filters, 'branches' => $branches, 'serviceAreas' => $areas, 'serviceAreaNames' => $serviceAreaNames,
            'selectedAreas' => $selectedAreas, 'restrictWorkzones' => $restricted] = $context;
        // Drill-down changes the detail list only; the matrix keeps its global filters.
        $legacyUsage = $input['usage'] ?? null;
        $legacyState = [
            'all' => 'all',
            'without_lop' => 'not_excluded',
            'with_lop' => 'included',
            'excluded' => 'excluded',
        ][$legacyUsage] ?? null;
        $filters = [...$filters, 'period' => $period, 'month' => $month,
            'matrix_region' => $input['matrix_region'] ?? '', 'matrix_group' => $input['matrix_group'] ?? '',
            'detail_kind' => $input['detail_kind'] ?? '',
            'exclude_state' => $input['exclude_state'] ?? $legacyState ?? 'all', 'reason' => $input['reason'] ?? ''];
        unset($filters['usage']);
        if ($legacyUsage !== null) {
            $filters['usage'] = $legacyUsage;
        }
        if ($filters['reason'] !== '') {
            $filters['exclude_state'] = 'excluded';
        }
        $reasons = collect(ExcludeReason::cases())->mapWithKeys(fn (ExcludeReason $reason) => [$reason->value => $reason->label()])->all();
        $excluded = DB::table('incident_excludes')->get(['incident', 'keterangan_exclude'])
            ->mapWithKeys(fn ($row) => [$this->coverage->normalize($row->incident) => $row->keterangan_exclude])->all();
        $lopOccupancy = [];
        foreach (QeLop::withTrashed()->select(['id_qe_lops', 'incident'])->toBase()->cursor() as $lop) {
            $incident = $this->coverage->normalize($lop->incident);
            if ($incident !== '') {
                $lopOccupancy[$incident] = ['id' => (int) $lop->id_qe_lops, 'usage' => 'with_lop'];
            }
        }
        $included = array_keys($lopOccupancy);
        $buckets = [];
        foreach ($excluded as $incident => $reason) {
            if (isset($lopOccupancy[$incident])) {
                continue;
            }
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
                if (($filters['service_area'] !== '' || $filters['service_area_name'] !== '')
                    && ! $selectedAreas->contains('branch_id', $branch->getKey())) {
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
        $stats = ['total' => 0, 'without_lop' => 0, 'with_lop' => 0, 'excluded' => 0];
        $chartRows = collect();
        $rawTickets = collect();
        $detailTotal = 0;
        $sourceUnavailable = false;
        $checkedAt = null;
        $manualLopRows = [];
        $manualLopTotal = 0;
        $page = (int) ($input['page'] ?? 1);

        if (! $restricted || $selectedAreas->isNotEmpty()) {
            try {
                [$base, $reportedDate] = $this->coverage->sourceQuery($filters, $selectedAreas, $restricted);
                [$classification, $bindings] = $this->classification($included, $buckets);
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
                        $groupBySto ? self::UNMAPPED_GROUP : ($groupByBranch ? 'Belum terpetakan' : null),
                    ), 'region_key' => $groupByBranch ? 'region:unmapped' : null];
                    $kind = $this->coverage->ticketKind($group->jenis_tiket_2);
                    $bucket = $group->exclude_bucket;
                    $state = in_array($bucket, ['included', 'not_excluded'], true) ? $bucket : 'excluded';
                    foreach ([$key, 'total'] as $target) {
                        $row = $target === 'total' ? $total : $matrixRows[$target];
                        $row['cells'][$kind.':'.$state] += (int) $group->total;
                        $row['cells'][$kind.':total'] += (int) $group->total;
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

                $stats = [
                    'total' => (int) $total['count'],
                    'without_lop' => (int) collect(self::KINDS)->keys()->sum(fn (string $kind) => $total['cells'][$kind.':not_excluded']),
                    'with_lop' => (int) collect(self::KINDS)->keys()->sum(fn (string $kind) => $total['cells'][$kind.':included']),
                    'excluded' => (int) collect(self::KINDS)->keys()->sum(fn (string $kind) => $total['cells'][$kind.':excluded']),
                ];
                $chartRows = collect(['gamas' => 'GAMAS', 'infracare' => 'INFRACARE', 'preman' => 'PREMAN'])
                    ->map(function (string $label, string $kind) use ($total): array {
                        return ['kind' => $kind, 'label' => $label, 'count' => (int) $total['cells'][$kind.':total']];
                    })
                    ->filter(fn (array $row) => $row['count'] > 0)
                    ->sortByDesc('count')->values();
                $maxCount = max(1, (int) $chartRows->max('count'));
                $chartRows = $chartRows->map(fn (array $row) => [...$row, 'width' => $row['count'] / $maxCount * 100]);

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
                if ($filters['matrix_group'] !== '') {
                    if ($filters['matrix_group'] === self::UNMAPPED_GROUP) {
                        $query->where(fn (Builder $q) => $q->whereNull('workzone')
                            ->orWhereNotIn(DB::raw('UPPER(TRIM(workzone))'), $knownWorkzones));
                    } else {
                        $groupZones = array_keys(array_filter(
                            $rowByWorkzone,
                            fn ($key) => ($rowGroups[$key] ?? null) === $filters['matrix_group'],
                            ARRAY_FILTER_USE_BOTH,
                        ));
                        $query->whereIn(DB::raw('UPPER(TRIM(workzone))'), $groupZones);
                    }
                }
                if ($filters['detail_kind'] !== '') {
                    $types = $groups->pluck('jenis_tiket_2')->unique()
                        ->filter(fn ($type) => $this->coverage->ticketKind($type) === $filters['detail_kind'])->values();
                    $query->whereIn('jenis_tiket_2', $types);
                }
                if ($filters['reason'] !== '') {
                    $query->whereIn(DB::raw('UPPER(TRIM(incident))'), $buckets[$filters['reason']] ?? []);
                    $query->whereNotIn(DB::raw('UPPER(TRIM(incident))'), $included);
                } elseif ($filters['exclude_state'] === 'included') {
                    $query->whereIn(DB::raw('UPPER(TRIM(incident))'), $included);
                } elseif ($filters['exclude_state'] === 'mapping') {
                    $query->where(function (Builder $mapping) use ($included, $excluded) {
                        $mapping->whereIn(DB::raw('UPPER(TRIM(incident))'), $included)
                            ->orWhere(function (Builder $excludedOnly) use ($excluded, $included) {
                                $excludedOnly->whereIn(DB::raw('UPPER(TRIM(incident))'), array_keys($excluded))
                                    ->whereNotIn(DB::raw('UPPER(TRIM(incident))'), $included);
                            });
                    });
                } elseif ($filters['exclude_state'] === 'excluded') {
                    $query->whereIn(DB::raw('UPPER(TRIM(incident))'), array_keys($excluded));
                    $query->whereNotIn(DB::raw('UPPER(TRIM(incident))'), $included);
                } elseif ($filters['exclude_state'] === 'not_excluded') {
                    $query->whereNotIn(DB::raw('UPPER(TRIM(incident))'), array_keys($excluded));
                    $query->whereNotIn(DB::raw('UPPER(TRIM(incident))'), $included);
                }
                $detailTotal = $query->count();
                $rawTickets = $query->select(['id_ticket', 'incident', 'jenis_tiket_2', 'workzone'])
                    ->selectRaw("{$reportedDate} AS reported_day")->orderByDesc('id_ticket')->forPage($page, 25)->get();
                $checkedAt = now('Asia/Jakarta');
                [$manualLopRows, $manualLopTotal] = $this->manualLopCounts(
                    $user, $filters, $areas, $selectedAreas, $context['selectedBranches'], $branchesById,
                    $matrixRows, $groupByBranch, $groupBySto,
                );
            } catch (Throwable $exception) {
                report($exception);
                $sourceUnavailable = true;
                $stats = null;
                $chartRows = collect();
                $rawTickets = collect();
                $detailTotal = 0;
            }
        }
        $rows = $this->coverage->presentRows($rawTickets, $lopOccupancy, $excluded, $areas, $branches, $user)
            ->map(fn ($row) => [...$row, 'is_included' => isset($lopOccupancy[$row['incident']]),
                'is_excluded' => ! isset($lopOccupancy[$row['incident']]) && array_key_exists($row['incident'], $excluded),
                'reason_label' => $reasons[$row['exclude_reason'] ?? ''] ?? 'Alasan lainnya']);
        $tickets = new LengthAwarePaginator($rows, $detailTotal, 25, $page, [
            'path' => route('incident-monitoring.exclusions'),
            'query' => array_filter($filters, fn ($value) => $value !== ''),
            'fragment' => 'ticket-details',
        ]);
        foreach ($matrixRows as $rowKey => $row) {
            $matrixRows[$rowKey]['monitoring'] = $this->monitoringBreakdown($row['cells'], (int) ($manualLopRows[$rowKey] ?? 0));
        }
        $total['monitoring'] = $this->monitoringBreakdown($total['cells'], $manualLopTotal);
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
                        'monitoring' => $this->monitoringBreakdown(
                            $cells,
                            (int) $rows->sum(fn (array $row) => $row['monitoring']['total']['manual']),
                        ),
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
            $filters['matrix_group'] !== '' && $filters['matrix_region'] === '' ? $filters['matrix_group'] : null,
            $filters['service_area_name'] !== '' ? $filters['service_area_name'] : null,
            self::KINDS[$filters['detail_kind']] ?? null,
            ['all' => 'Semua tiket', 'included' => 'Sudah include', 'excluded' => 'Sudah exclude', 'not_excluded' => 'Belum exclude', 'mapping' => 'Sudah mapping'][$filters['exclude_state']],
            $reasons[$filters['reason']] ?? null,
        ]));

        return [
            'filters' => $filters, 'branches' => $branches, 'serviceAreas' => $areas,
            'serviceAreaNames' => $serviceAreaNames, 'regions' => $context['regions'],
            'matrixRows' => $matrixRows, 'matrixGroups' => $matrixGroups, 'totals' => $total,
            'tickets' => $tickets, 'stats' => $stats, 'chartRows' => $chartRows, 'reasons' => $reasons,
            'kinds' => self::KINDS, 'sourceUnavailable' => $sourceUnavailable, 'checkedAt' => $checkedAt,
            'detailLabel' => $detailLabel, 'groupBySto' => $groupBySto, 'groupByBranch' => $groupByBranch,
            'scopeLabel' => $user->hasRole(UserRole::SUPER_ADMIN) ? 'Seluruh wilayah' : $this->visibility->label($user),
            'scopeWarning' => $user->hasRole(UserRole::ADMIN) && $areas->isEmpty(),
        ];
    }

    /** Detail list untuk satu angka pada tabel monitoring tiket. */
    public function cellDetail(User $user, array $input): array
    {
        $cell = $input['cell'] ?? 'ticket_qe';
        if ($cell === 'manual') {
            return $this->manualCellDetail($user, $input);
        }
        $reasons = array_column(ExcludeReason::cases(), 'value');
        $input['exclude_state'] = match ($cell) {
            'belum_mapping' => 'not_excluded',
            'create_lop', 'insera' => 'included',
            'sudah_mapping' => 'mapping',
            default => in_array($cell, $reasons, true) ? 'excluded' : 'all',
        };
        if (in_array($cell, $reasons, true)) {
            $input['reason'] = $cell;
        }
        $data = $this->dashboard($user, $input);
        // Keep pagination on the browser's current origin. Absolute route URLs can
        // point at APP_URL or an internal proxy host in production, causing the
        // modal's fetch for page 2+ to fail with a cross-origin/network error.
        $data['tickets']->setPath(route('incident-monitoring.exclusions.detail', [], false))
            ->appends(['cell' => $cell]);

        return [
            'isManual' => false, 'isLopList' => in_array($cell, ['create_lop', 'insera'], true),
            'cell' => $cell, 'tickets' => $data['tickets'],
            'manualLops' => collect(), 'filters' => $data['filters'],
            'sourceUnavailable' => $data['sourceUnavailable'], 'detailLabel' => $data['detailLabel'],
            'cellLabel' => $this->cellLabel($cell), 'kinds' => $data['kinds'], 'reasons' => $data['reasons'],
        ];
    }

    private function cellLabel(string $cell): string
    {
        return [
            'ticket_qe' => 'Tiket QE',
            'sudah_mapping' => 'Tiket sudah mapping',
            'belum_mapping' => 'Tiket belum mapping',
            'create_lop' => 'Create LOP',
            'temporer' => 'Pareto · Temporer Gamas',
            'non_material' => 'Pareto · Tidak ada material',
            'duplikat_open' => 'Pareto · Double tiket',
            'magu_ganti_pasif_odp' => 'Pareto · MAGU pasif',
            'insera' => 'Tiket Insera',
            'manual' => 'Tiket manual (LOP INP)',
        ][$cell] ?? 'Detail tiket';
    }

    private function manualCellDetail(User $user, array $input): array
    {
        $period = $input['period'] ?? 'range';
        $month = $input['month'] ?? now('Asia/Jakarta')->format('Y-m');
        if ($period === 'month') {
            $start = Carbon::createFromFormat('!Y-m', $month);
            $input['date_from'] = max($start->toDateString(), TicketReportedDate::floor());
            $input['date_to'] = $start->endOfMonth()->toDateString();
        }
        $context = $this->coverage->monitoringContext($user, $input);
        $filters = [...$context['filters'], 'period' => $period, 'month' => $month,
            'matrix_region' => $input['matrix_region'] ?? '', 'matrix_group' => $input['matrix_group'] ?? ''];
        $groupBySto = $this->groupsBySto($user);
        $groupByBranch = $user->hasRole(UserRole::SUPER_ADMIN)
            || ($user->hasRole(UserRole::ADMIN) && $user->admin_scope_type === AdminScopeType::AREA);
        [$lops, $meta] = $this->manualLopMeta(
            $user, $filters, $context['serviceAreas'], $context['selectedAreas'],
            $context['selectedBranches'], $context['branches']->keyBy('id_branch'), $groupByBranch, $groupBySto,
        );
        $row = $filters['matrix_region'];
        $group = $filters['matrix_group'];
        $lops = $lops->filter(function (QeLop $lop) use ($meta, $row, $group, $groupByBranch): bool {
            $entry = $meta[$lop->getKey()] ?? null;
            if ($entry === null) {
                return false;
            }
            if ($group !== '' && $entry['group'] !== $group) {
                return false;
            }
            if ($row === '') {
                return true;
            }

            return $entry['row'] === $row || ($groupByBranch && $entry['region'] === $row);
        })->values();
        $page = max(1, (int) ($input['page'] ?? 1));
        $manualLops = new LengthAwarePaginator(
            $lops->forPage($page, 25)->values()->load(['branchRef:id_branch,name', 'serviceArea:id_service_area,workzone']),
            $lops->count(),
            25,
            $page,
            [
                'path' => route('incident-monitoring.exclusions.detail', [], false),
                'query' => array_filter([...$filters, 'cell' => 'manual'], fn ($value) => $value !== '' && $value !== null),
            ],
        );

        return [
            'isManual' => true, 'isLopList' => false, 'cell' => 'manual', 'tickets' => collect(),
            'manualLops' => $manualLops, 'filters' => $filters,
            'sourceUnavailable' => false, 'detailLabel' => 'LOP manual (incident INP)',
            'cellLabel' => $this->cellLabel('manual'), 'kinds' => self::KINDS, 'reasons' => [],
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

    private function classification(array $included, array $buckets): array
    {
        $sql = 'CASE';
        $bindings = [];
        if ($included !== []) {
            $sql .= ' WHEN UPPER(TRIM(incident)) IN ('.implode(',', array_fill(0, count($included), '?')).") THEN 'included'";
            $bindings = [...$bindings, ...$included];
        }
        foreach ($buckets as $reason => $incidents) {
            $sql .= ' WHEN UPPER(TRIM(incident)) IN ('.implode(',', array_fill(0, count($incidents), '?')).') THEN ?';
            $bindings = [...$bindings, ...$incidents, $reason];
        }

        return $included === [] && $buckets === [] ? ["'not_excluded'", []] : [$sql." ELSE 'not_excluded' END", $bindings];
    }

    private function emptyRow(string $key, string $label, array $reasons, ?string $group = null): array
    {
        $cells = [];
        foreach (self::KINDS as $kind => $name) {
            foreach (['included', 'excluded', 'not_excluded', 'total', ...array_keys($reasons)] as $bucket) {
                $cells[$kind.':'.$bucket] = 0;
            }
        }

        return ['key' => $key, 'label' => $label, 'group' => $group, 'count' => 0, 'cells' => $cells];
    }

    /**
     * Ringkasan tiga tabel Monitoring Tiket dari sel matriks yang sudah dihitung.
     * LOP manual tidak punya jenis tiket dan hanya dihitung pada kolom TOTAL.
     */
    private function monitoringBreakdown(array $cells, int $manualLops): array
    {
        $rows = [];
        foreach (array_keys(self::KINDS) as $kind) {
            $included = (int) ($cells[$kind.':included'] ?? 0);
            $excluded = (int) ($cells[$kind.':excluded'] ?? 0);
            $rows[$kind] = [
                'ticket_qe' => (int) ($cells[$kind.':total'] ?? 0),
                'sudah_mapping' => $included + $excluded,
                'belum_mapping' => (int) ($cells[$kind.':not_excluded'] ?? 0),
                'create_lop' => $included,
                'temporer' => (int) ($cells[$kind.':'.ExcludeReason::TEMPORER->value] ?? 0),
                'non_material' => (int) ($cells[$kind.':'.ExcludeReason::NON_MATERIAL->value] ?? 0),
                'dobel_tiket' => (int) ($cells[$kind.':'.ExcludeReason::DUPLIKAT_OPEN->value] ?? 0),
                'magu_pasif' => (int) ($cells[$kind.':'.ExcludeReason::MAGU_GANTI_PASIF_ODP->value] ?? 0),
                'insera' => $included,
                'manual' => 0,
            ];
        }
        $totals = [];
        foreach (array_keys($rows[array_key_first($rows)]) as $field) {
            $totals[$field] = (int) array_sum(array_column($rows, $field));
        }
        $totals['manual'] = $manualLops;
        $rows['total'] = $totals;

        return $rows;
    }

    /**
     * LOP manual (incident INP) tidak berasal dari sumber tiket Dompis sehingga
     * tidak muncul pada agregasi tiket. Dihitung terpisah, tetap mengikuti scope
     * lokasi serta periode efektif reported_date lalu tanggal pembuatan.
     *
     * @return array{0: array<string, int>, 1: int}
     */
    private function manualLopCounts(
        User $user,
        array $filters,
        Collection $areas,
        Collection $selectedAreas,
        Collection $selectedBranches,
        Collection $branchesById,
        array $matrixRows,
        bool $groupByBranch,
        bool $groupBySto,
    ): array {
        [$lops, $meta] = $this->manualLopMeta($user, $filters, $areas, $selectedAreas, $selectedBranches, $branchesById, $groupByBranch, $groupBySto);
        $counts = [];
        foreach ($lops as $lop) {
            $key = $meta[$lop->getKey()]['row'] ?? null;
            if ($key !== null && isset($matrixRows[$key])) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return [$counts, $lops->count()];
    }

    /**
     * @return array{0: Collection<int, QeLop>, 1: array<int, array{row: ?string, group: ?string, region: ?string}>}
     */
    private function manualLopMeta(
        User $user,
        array $filters,
        Collection $areas,
        Collection $selectedAreas,
        Collection $selectedBranches,
        Collection $branchesById,
        bool $groupByBranch,
        bool $groupBySto,
    ): array {
        $query = QeLop::withTrashed()
            ->where('incident', 'like', 'INP%')
            ->where(function ($period) use ($filters) {
                $period->where(function ($reported) use ($filters) {
                    $reported->whereNotNull('reported_date')
                        ->whereBetween('reported_date', [$filters['date_from'], $filters['date_to']]);
                })->orWhere(function ($created) use ($filters) {
                    $created->whereNull('reported_date')
                        ->whereRaw('DATE(created_at) BETWEEN ? AND ?', [$filters['date_from'], $filters['date_to']]);
                });
            });
        $areaById = $areas->keyBy('id_service_area');
        $areaByWorkzone = $areas->keyBy(fn (ServiceArea $area) => $this->coverage->normalize($area->workzone));
        $selectedAreaIds = $selectedAreas->modelKeys();
        $selectedBranchIds = $selectedBranches->modelKeys();
        $lops = (new QeLop)->newCollection();
        $meta = [];

        foreach ($this->visibility->apply($query, $user)->get(['id_qe_lops', 'incident', 'nama_lop', 'program_type', 'status_lop', 'branch_id', 'service_area_id', 'branch', 'sto', 'reported_date', 'created_at']) as $lop) {
            $area = $areaById->get((int) $lop->service_area_id)
                ?? $areaByWorkzone->get($this->coverage->normalize($lop->sto));
            if ($area !== null && ! in_array((int) $area->id_service_area, $selectedAreaIds, true)) {
                continue;
            }
            $branch = $area ? $branchesById->get($area->branch_id) : null;
            $branch ??= $branchesById->first(fn (Branch $candidate) => $this->coverage->normalize($candidate->name) === $this->coverage->normalize($lop->branch));
            if ($branch !== null && ! in_array((int) $branch->id_branch, $selectedBranchIds, true)) {
                continue;
            }
            [$regionKey, $regionLabel] = $branch ? $this->region($branch) : [null, null];
            $rowKey = match (true) {
                $groupByBranch => $branch ? 'branch:'.$branch->id_branch : null,
                $groupBySto => $area ? 'sto:'.$this->coverage->normalize($area->workzone) : null,
                default => $regionKey,
            };
            $lops->push($lop);
            $meta[(int) $lop->getKey()] = [
                'row' => $rowKey,
                'group' => match (true) {
                    $groupByBranch => $regionLabel,
                    $groupBySto => $area ? $this->serviceAreaGroup($area) : null,
                    default => null,
                },
                'region' => $regionKey === 'unmapped' ? 'region:unmapped' : $regionKey,
            ];
        }

        return [$lops, $meta];
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
            default => ['unmapped', 'Belum terpetakan'],
        };
    }
}
