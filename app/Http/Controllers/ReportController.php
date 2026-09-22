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

    public function __construct(private readonly MaterialReportService $service) {}

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
        return $this->lopReport($request, $qe_lop, 'boq');
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

        // Per-LOP: paksa lop_id, view=per_lop, package default = lop->package_id
        $filters = $this->service->normalizeFilters($request);
        $filters['lop_id'] = $qe_lop->id_qe_lops;
        $filters['view'] = 'per_lop';
        $filters['region'] = $regionFilter;
        $filters['branch'] = $branchFilter;
        // default package = LOP package jika request tidak kirim
        $filters['package'] = $qe_lop->package_id ?? $filters['package'];

        $data = $this->service->perLop($filters, $user, null);

        // Jika request AJAX/JSON, kembalikan payload untuk modal Alpine
        if ($request->wantsJson() || $request->boolean('json')) {
            return response()->json([
                'report' => $report,
                'lop' => [
                    'id' => $qe_lop->id_qe_lops,
                    'incident' => $qe_lop->incident,
                    'nama_lop' => $qe_lop->nama_lop,
                    'branch' => $qe_lop->branch,
                    'program' => $qe_lop->program_type?->label(),
                ],
                'priced' => $data['priced'],
                'package' => $data['package'] ? ['id' => $data['package']->id, 'name' => $data['package']->name] : null,
                'columns' => $this->service->columns($report, 'per_lop', $data['priced']),
                'groups' => $data['groups'] instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $data['groups']->items() : $data['groups'],
                'grand' => $data['grand'],
            ]);
        }

        // Fallback: tampilkan halaman laporan single LOP (reuse view per-lop)
        return view('reports.lop-per-lop', [
            'title' => $report === 'boq' ? 'BOQ Actual' : 'Sisa Material',
            'report' => $report,
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
        $filters['package'] = $qe_lop->package_id ?? $filters['package'];

        $payload = $this->service->perLop($filters, $user, null);

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
