<?php

namespace App\Services;

use App\Enums\ExcludeReason;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\IncidentExclude;
use App\Models\User;
use App\Support\TicketReportedDate;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class TicketExcludeMonitoringService
{
    public const KINDS = ['gamas' => 'SQM / GAMAS', 'preman' => 'PREMAN', 'infracare' => 'INFRACARE'];

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
        // Drill-down changes the detail list only; the regional matrix keeps its global filters.
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

        $regionByWorkzone = [];
        $regionLabels = [];
        $branchesById = $branches->keyBy('id_branch');
        foreach ($selectedAreas as $area) {
            [$key, $label] = $this->region($branchesById->get($area->branch_id));
            $regionByWorkzone[$this->coverage->normalize($area->workzone)] = $key;
            $regionLabels[$key] = $label;
        }
        $matrixRows = [];
        foreach (array_unique(['jatengdiy', 'jatim', 'balnus', ...array_keys($regionLabels)]) as $key) {
            if (isset($regionLabels[$key])) {
                $matrixRows[$key] = $this->emptyRow($key, $regionLabels[$key], $reasons);
            }
        }
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
                    $key = $regionByWorkzone[$group->normalized_workzone ?? ''] ?? 'unmapped';
                    $matrixRows[$key] ??= $this->emptyRow($key, 'Wilayah belum terpetakan', $reasons);
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
                    $zones = array_keys(array_filter($regionByWorkzone, fn ($key) => $key === $filters['matrix_region']));
                    if ($filters['matrix_region'] === 'unmapped') {
                        $query->where(fn (Builder $q) => $q->whereNull('workzone')->orWhereNotIn(DB::raw('UPPER(TRIM(workzone))'), array_keys($regionByWorkzone)));
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
        $detailLabel = implode(' · ', array_filter([
            $filters['matrix_region'] !== '' ? ($matrixRows[$filters['matrix_region']]['label'] ?? 'Wilayah pilihan') : 'Semua regional',
            self::KINDS[$filters['detail_kind']] ?? null,
            ['all' => 'Semua tiket', 'excluded' => 'Sudah exclude', 'not_excluded' => 'Belum exclude'][$filters['exclude_state']],
            $reasons[$filters['reason']] ?? null,
        ]));

        return [
            'filters' => $filters, 'branches' => $branches, 'serviceAreas' => $areas, 'regions' => $context['regions'],
            'matrixRows' => $matrixRows, 'totals' => $total, 'tickets' => $tickets, 'reasons' => $reasons,
            'kinds' => self::KINDS, 'sourceUnavailable' => $sourceUnavailable, 'checkedAt' => $checkedAt,
            'detailLabel' => $detailLabel,
            'scopeLabel' => $user->hasRole(UserRole::SUPER_ADMIN) ? 'Seluruh wilayah' : $this->visibility->label($user),
            'scopeWarning' => $user->hasRole(UserRole::ADMIN) && $areas->isEmpty(),
        ];
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

    private function emptyRow(string $key, string $label, array $reasons): array
    {
        $cells = [];
        foreach (self::KINDS as $kind => $name) {
            foreach (['excluded', 'not_excluded', ...array_keys($reasons)] as $bucket) {
                $cells[$kind.':'.$bucket] = 0;
            }
        }

        return ['key' => $key, 'label' => $label, 'count' => 0, 'cells' => $cells];
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
