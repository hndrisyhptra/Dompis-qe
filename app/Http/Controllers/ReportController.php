<?php

namespace App\Http\Controllers;

use App\Enums\LopSegment;
use App\Enums\LopStatus;
use App\Enums\ProgramType;
use App\Enums\UserRole;
use App\Exports\BoqActualExport;
use App\Exports\SisaMaterialExport;
use App\Models\Package;
use App\Models\QeLop;
use App\Services\LopBoqProjectionService;
use App\Services\MaterialReportService;
use App\Support\LocationScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan operasional material: BOQ Actual & Sisa Material.
 * Read-only. Otorisasi lewat Gate `view-reports` (permission `reporting`).
 * SUPER_ADMIN + MANAGER lintas-branch (bisa filter region/branch);
 * ADMIN dikunci ke branch akunnya.
 */
class ReportController extends Controller
{
    use LocationScope;

    public function __construct(
        private readonly MaterialReportService $service,
        private readonly LopBoqProjectionService $lopBoqProjection,
    ) {}

    public function boqActual(Request $request): View
    {
        $this->authorize('view-reports');

        return $this->render($request, 'boq', 'reports.boq-actual', 'Tabel BOQ Actual');
    }

    public function sisaMaterial(Request $request): View
    {
        $this->authorize('view-reports');

        return $this->render($request, 'sisa', 'reports.sisa-material', 'Sisa Material');
    }

    public function boqActualExport(Request $request): Response
    {
        $this->authorize('view-reports');

        return $this->export($request, 'boq');
    }

    public function sisaMaterialExport(Request $request): Response
    {
        $this->authorize('view-reports');

        return $this->export($request, 'sisa');
    }

    // --- Per-LOP laporan (dipanggil dari kolom Aksi LOP, default package = lop->package_id) ---

    public function lopBoqActual(Request $request, QeLop $qe_lop): View|JsonResponse
    {
        return $this->lopReport($request, $qe_lop, 'actual');
    }

    public function lopBoqPlan(Request $request, QeLop $qe_lop): View|JsonResponse
    {
        return $this->lopReport($request, $qe_lop, 'plan');
    }

    public function lopSisaMaterial(Request $request, QeLop $qe_lop): View|JsonResponse
    {
        return $this->lopReport($request, $qe_lop, 'sisa');
    }

    public function lopBoqActualExport(Request $request, QeLop $qe_lop): Response
    {
        return $this->lopExport($request, $qe_lop, 'boq');
    }

    public function lopSisaMaterialExport(Request $request, QeLop $qe_lop): Response
    {
        return $this->lopExport($request, $qe_lop, 'sisa');
    }

    private function lopReport(Request $request, QeLop $qe_lop, string $report): View|JsonResponse
    {
        $this->authorize('view-reports');
        $this->authorize('view', $qe_lop);

        $user = $request->user();
        $isBroad = $user->hasRole(UserRole::SUPER_ADMIN, UserRole::MANAGER);
        [$regionFilter, $branchFilter] = $isBroad ? $this->resolveLocationFilters($request) : ['', ''];

        $filters = $this->service->normalizeFilters($request);
        $filters['lop_id'] = $qe_lop->id_qe_lops;
        $filters['view'] = 'per_lop';
        $filters['region'] = $regionFilter;
        $filters['branch'] = $branchFilter;
        $projection = $this->lopBoqProjection->report($qe_lop, $report);
        $data = $this->projectionPayload($qe_lop, $projection, $filters);

        // Jika request AJAX/JSON, kembalikan payload untuk modal Alpine
        if ($request->wantsJson() || $request->boolean('json')) {
            return response()->json([
                'report' => $report,
                'has_plan' => $projection['has_plan'],
                'lop' => [
                    'id' => $qe_lop->id_qe_lops,
                    'incident' => $qe_lop->incident,
                    'nama_lop' => $qe_lop->nama_lop,
                    'branch' => $qe_lop->branch,
                    'program' => $qe_lop->program_type?->label(),
                ],
                'priced' => $data['priced'],
                'package' => $data['package'] ? [
                    'id' => $data['package']->id_package,
                    'name' => $projection['package_label'],
                ] : null,
                'groups' => $data['groups'],
                'grand' => $data['grand'],
            ]);
        }

        // Fallback: tampilkan halaman laporan single LOP (reuse view per-lop)
        return view('reports.lop-per-lop', [
            'title' => match ($report) {
                'plan' => 'BOQ Plan',
                'sisa' => 'Sisa Material',
                default => 'BOQ Actual',
            },
            'report' => $report === 'actual' ? 'boq' : $report,
            'mode' => 'per_lop',
            'data' => $data,
            'priced' => $data['priced'],
            'package' => $data['package'],
            'filters' => $filters,
            'lop' => $qe_lop,
            ...$this->locationOptions($regionFilter, $branchFilter),
            ...$this->scopeContext($user, $isBroad, $regionFilter, $branchFilter),
        ]);
    }

    private function lopExport(Request $request, QeLop $qe_lop, string $report): Response
    {
        $this->authorize('view-reports');
        $this->authorize('view', $qe_lop);

        $user = $request->user();
        $isBroad = $user->hasRole(UserRole::SUPER_ADMIN, UserRole::MANAGER);
        [$regionFilter, $branchFilter] = $isBroad ? $this->resolveLocationFilters($request) : ['', ''];

        $filters = $this->service->normalizeFilters($request);
        $filters['lop_id'] = $qe_lop->id_qe_lops;
        $filters['view'] = 'per_lop';
        $filters['region'] = $regionFilter;
        $filters['branch'] = $branchFilter;
        $projection = $this->lopBoqProjection->report($qe_lop, $report === 'boq' ? 'actual' : $report);
        $payload = $this->projectionPayload($qe_lop, $projection, $filters);

        $slug = $report === 'boq' ? 'boq-actual' : 'sisa-material';
        $safeLop = preg_replace('/[^\w\-]+/', '_', $qe_lop->incident ?: $qe_lop->nama_lop);
        $name = "{$slug}-{$safeLop}-".now()->format('Ymd-His');
        $format = $request->string('format')->lower()->value() === 'xlsx' ? 'xlsx' : 'csv';

        if ($format === 'xlsx') {
            $export = $report === 'boq'
                ? new BoqActualExport($this->service, $payload, 'boq', 'per_lop')
                : new SisaMaterialExport($this->service, $payload, 'sisa', 'per_lop');

            return Excel::download($export, "{$name}.xlsx");
        }

        return $this->streamCsv($payload, $report, 'per_lop', "{$name}.csv");
    }

    /** @return array<string, mixed> */
    private function projectionPayload(QeLop $lop, array $projection, array $filters): array
    {
        $group = [
            'lop' => [
                'id' => $lop->id_qe_lops,
                'name' => $lop->nama_lop,
                'incident' => $lop->incident,
                'branch' => $lop->branch,
                'program' => $lop->program_type?->label(),
                'segment' => $lop->segmentLabel(),
                'status' => $lop->status_lop?->label(),
                'status_raw' => $lop->status_lop?->value,
            ],
            'lines' => $projection['lines'],
            'subtotal' => $projection['grand'],
        ];

        return [
            'mode' => 'per_lop',
            'priced' => $projection['priced'],
            'package' => $projection['package'],
            'filters' => $filters,
            'groups' => $projection['lines'] === [] ? [] : [$group],
            'grand' => $projection['grand'] + ['lop_count' => $projection['lines'] === [] ? 0 : 1],
        ];
    }

    // ---------------------------------------------------------------------

    private function render(Request $request, string $report, string $view, string $title): View
    {
        $user = $request->user();
        $isBroad = $user->hasRole(UserRole::SUPER_ADMIN, UserRole::MANAGER);
        [$regionFilter, $branchFilter] = $isBroad ? $this->resolveLocationFilters($request) : ['', ''];

        $filters = $this->service->normalizeFilters($request);
        $filters['region'] = $regionFilter;
        $filters['branch'] = $branchFilter;

        $print = $request->boolean('print');
        $perPage = $print ? null : ($filters['view'] === 'rekap' ? 50 : 20);

        $data = $filters['view'] === 'rekap'
            ? $this->service->rekap($filters, $user, $perPage)
            : $this->service->perLop($filters, $user, $perPage);

        return view($view, [
            'title' => $title,
            'report' => $report,
            'mode' => $filters['view'],
            'data' => $data,
            'priced' => $data['priced'],
            'package' => $data['package'],
            'filters' => $filters,
            'print' => $print,
            'packages' => Package::query()->orderBy('name')->get(),
            'programs' => ProgramType::cases(),
            'segments' => LopSegment::cases(),
            'statuses' => [LopStatus::WAITING_APPROVAL, LopStatus::COMPLETED],
            ...$this->locationOptions($regionFilter, $branchFilter),
            ...$this->scopeContext($user, $isBroad, $regionFilter, $branchFilter),
        ]);
    }

    private function export(Request $request, string $report): Response
    {
        $user = $request->user();
        $isBroad = $user->hasRole(UserRole::SUPER_ADMIN, UserRole::MANAGER);
        [$regionFilter, $branchFilter] = $isBroad ? $this->resolveLocationFilters($request) : ['', ''];

        $filters = $this->service->normalizeFilters($request);
        $filters['region'] = $regionFilter;
        $filters['branch'] = $branchFilter;

        $payload = $filters['view'] === 'rekap'
            ? $this->service->rekap($filters, $user, null)
            : $this->service->perLop($filters, $user, null);

        $slug = $report === 'boq' ? 'boq-actual' : 'sisa-material';
        $name = "{$slug}-{$filters['view']}-".now()->format('Ymd-His');
        $format = $request->string('format')->lower()->value() === 'xlsx' ? 'xlsx' : 'csv';

        if ($format === 'xlsx') {
            $export = $report === 'boq'
                ? new BoqActualExport($this->service, $payload, 'boq', $filters['view'])
                : new SisaMaterialExport($this->service, $payload, 'sisa', $filters['view']);

            return Excel::download($export, "{$name}.xlsx");
        }

        return $this->streamCsv($payload, $report, $filters['view'], "{$name}.csv");
    }

    private function streamCsv(array $payload, string $report, string $mode, string $filename): StreamedResponse
    {
        $cols = $this->service->columns($report, $mode, $payload['priced']);
        $keys = array_keys($cols);
        $rows = $this->service->flatten($payload, $report, $mode);

        return response()->streamDownload(function () use ($cols, $keys, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_values($cols));
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($k) => $row[$k] ?? '', $keys));
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
