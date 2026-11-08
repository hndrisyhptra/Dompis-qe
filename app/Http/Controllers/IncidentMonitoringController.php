<?php

namespace App\Http\Controllers;

use App\Exports\TicketCoverageExport;
use App\Enums\ExcludeReason;
use App\Http\Requests\ExcludeTicketRequest;
use App\Http\Requests\IncidentMonitoringRequest;
use App\Http\Requests\TicketCoverageDownloadRequest;
use App\Http\Requests\TicketCoverageRequest;
use App\Http\Requests\TicketExcludeMonitoringRequest;
use App\Http\Requests\UnexcludeTicketRequest;
use App\Services\IncidentMonitoringService;
use App\Services\TicketCoverageService;
use App\Services\TicketExcludeMonitoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class IncidentMonitoringController extends Controller
{
    public function __construct(
        private readonly IncidentMonitoringService $monitoring,
        private readonly TicketCoverageService $coverage,
    ) {}

    public function index(IncidentMonitoringRequest $request): View
    {
        return view('incident-monitoring.index', $this->monitoring->dashboard(
            $request->user(), $request->validated(),
        ));
    }

    public function tickets(TicketCoverageRequest $request, TicketExcludeMonitoringService $exclusions): View
    {
        $filters = $request->validated();
        $filters['usage'] ??= 'without_lop';
        $filters['exclude_state'] = [
            'all' => 'all',
            'without_lop' => 'not_excluded',
            'with_lop' => 'included',
            'excluded' => 'excluded',
        ][$filters['usage']];

        return view('incident-monitoring.exclusions', $exclusions->dashboard($request->user(), $filters));
    }

    public function exclusions(TicketExcludeMonitoringRequest $request, TicketExcludeMonitoringService $exclusions): View
    {
        return view('incident-monitoring.exclusions', $exclusions->dashboard($request->user(), $request->validated()));
    }

    public function bulkExclusions(): View
    {
        return view('incident-monitoring.bulk-exclusions', [
            'excludeReasons' => collect(ExcludeReason::cases())
                ->mapWithKeys(fn (ExcludeReason $reason) => [$reason->value => $reason->label()]),
            'preview' => null,
            'incidentsText' => '',
            'selectedReason' => '',
        ]);
    }

    public function previewBulkExclusions(ExcludeTicketRequest $request): View
    {
        $preview = $this->guardTicketSource(fn () => $this->coverage->previewMany(
            $request->validated('incidents'), $request->user()
        ));

        return view('incident-monitoring.bulk-exclusions', [
            'excludeReasons' => collect(ExcludeReason::cases())
                ->mapWithKeys(fn (ExcludeReason $reason) => [$reason->value => $reason->label()]),
            'preview' => $preview,
            'incidentsText' => $request->validated('incidents_text'),
            'selectedReason' => $request->validated('reason'),
        ]);
    }

    public function applyBulkExclusions(ExcludeTicketRequest $request): RedirectResponse
    {
        $stats = $this->guardTicketSource(fn () => $this->coverage->excludeMany(
            $request->validated('incidents'), $request->validated('reason'), $request->user()
        ));

        return redirect()->route('incident-monitoring.bulk-exclusions')->with(
            'status', 'Exclude massal selesai: '.$stats['excluded'].' berhasil, '.$stats['already_excluded'].' sudah exclude, '
                .$stats['has_lop'].' memiliki LOP, '.$stats['not_found'].' tidak ditemukan, '.$stats['out_of_scope'].' di luar scope.'
        );
    }

    public function download(TicketCoverageDownloadRequest $request): BinaryFileResponse
    {
        $filters = $request->validated();
        $filters['usage'] = $filters['download_usage'] ?? $filters['usage'] ?? 'all';
        unset($filters['download_usage']);
        $data = $this->coverage->dashboard($request->user(), $filters, forExport: true);
        abort_if($data['sourceUnavailable'], 503, 'Data tiket Dompis belum dapat dimuat. Unduhan dibatalkan; coba kembali setelah koneksi tersedia.');
        $category = match ($filters['usage']) {
            'without_lop' => 'belum-memiliki-lop', 'with_lop' => 'sudah-memiliki-lop', 'excluded' => 'tiket-exclude', default => 'total-tiket'
        };
        $period = implode('_', array_filter([$data['filters']['date_from'] ?? null, $data['filters']['date_to'] ?? null])) ?: 'semua-periode';
        try {
            return Excel::download(new TicketCoverageExport($data), "monitoring-incident-{$category}-{$period}.xlsx", headers: ['Cache-Control' => 'private, no-store']);
        } catch (Throwable $exception) {
            report($exception);
            abort(503, 'Unduhan belum berhasil dibuat. Coba kembali; hubungi administrator jika berulang.');
        }
    }

    public function exclude(ExcludeTicketRequest $request): RedirectResponse
    {
        $this->guardTicketSource(fn () => $this->coverage->exclude(
            $request->validated('incident'),
            $request->validated('reason'),
            $request->user()
        ));

        return back()->with('status', 'Tiket berhasil di-exclude.');
    }

    public function unexclude(UnexcludeTicketRequest $request): RedirectResponse
    {
        $removed = $this->guardTicketSource(fn () => $this->coverage->unexclude(
            $request->validated('incident'),
            $request->user()
        ));

        return back()->with(
            'status',
            $removed
                ? 'Exclude tiket dibatalkan. Tiket kembali ke daftar belum memiliki LOP.'
                : 'Tiket sudah tidak berada dalam daftar exclude.'
        );
    }

    private function guardTicketSource(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            abort(503, 'Sumber tiket Dompis belum dapat dimuat. Coba kembali setelah koneksi tersedia.');
        }
    }
}
