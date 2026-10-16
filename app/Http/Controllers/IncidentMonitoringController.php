<?php

namespace App\Http\Controllers;

use App\Exports\TicketCoverageExport;
use App\Http\Requests\IncidentMonitoringRequest;
use App\Http\Requests\TicketCoverageDownloadRequest;
use App\Http\Requests\TicketCoverageRequest;
use App\Services\IncidentMonitoringService;
use App\Services\TicketCoverageService;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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

    public function tickets(TicketCoverageRequest $request): View
    {
        return view('incident-monitoring.tickets', $this->coverage->dashboard(
            $request->user(), $request->validated(),
        ));
    }

    public function download(TicketCoverageDownloadRequest $request): BinaryFileResponse
    {
        $filters = $request->validated();
        $filters['usage'] = $filters['download_usage'] ?? $filters['usage'] ?? 'all';
        unset($filters['download_usage']);
        $data = $this->coverage->dashboard($request->user(), $filters, forExport: true);
        abort_if($data['sourceUnavailable'], 503, 'Data tiket Dompis belum dapat dimuat. Unduhan dibatalkan; coba kembali setelah koneksi tersedia.');
        $category = match ($filters['usage']) {
            'without_lop' => 'belum-memiliki-lop', 'with_lop' => 'sudah-memiliki-lop', default => 'total-tiket'
        };
        $period = implode('_', array_filter([$filters['month'] ?? null, $filters['date_from'] ?? null, $filters['date_to'] ?? null])) ?: 'semua-periode';
        try {
            return Excel::download(new TicketCoverageExport($data), "monitoring-incident-{$category}-{$period}.xlsx", headers: ['Cache-Control' => 'private, no-store']);
        } catch (Throwable $exception) {
            report($exception);
            abort(503, 'Unduhan belum berhasil dibuat. Coba kembali; hubungi administrator jika berulang.');
        }
    }
}
