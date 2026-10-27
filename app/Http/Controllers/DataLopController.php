<?php

namespace App\Http\Controllers;

use App\Enums\LopStatus;
use App\Enums\ProgramType;
use App\Enums\UserRole;
use App\Exports\DataLopExport;
use App\Http\Requests\DataLopFilterRequest;
use App\Http\Requests\DeleteLopRequest;
use App\Http\Requests\StoreInseraEvidenceRequest;
use App\Models\Branch;
use App\Models\QeLop;
use App\Services\AissBoqExportService;
use App\Services\DataLopService;
use App\Services\EvidenceService;
use App\Services\LopService;
use App\Services\LopVisibilityService;
use App\Support\LopReturnDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataLopController extends Controller
{
    public function __construct(
        private readonly LopService $lopService,
        private readonly LopVisibilityService $visibility,
        private readonly DataLopService $data,
        private readonly EvidenceService $evidenceService,
    ) {}

    public function index(DataLopFilterRequest $request): View
    {
        $this->authorize('viewAny', QeLop::class);

        $filters = $this->data->filters($request->validated());
        $query = $this->data->query($request->user(), $filters)
            ->with(['creator', 'boq', 'materialReservation.items', 'evidences.uploader']);

        $branchQuery = Branch::query()->orderBy('name');
        if ($request->user()->hasRole(UserRole::ADMIN)) {
            $branchQuery->whereIn('id_branch', $this->visibility->accessibleBranchIds($request->user()));
        }

        $lops = $query->latest()->paginate(20)->withQueryString();
        $this->visibility->prepareForView($request->user(), $lops->getCollection());

        return view('data-lops.index', [
            'lops' => $lops,
            'programs' => ProgramType::cases(),
            'statuses' => LopStatus::cases(),
            'branches' => $branchQuery->get(),
            'filters' => $filters,
        ]);
    }

    public function export(DataLopFilterRequest $request): BinaryFileResponse
    {
        $filters = $this->data->filters($request->validated());

        return Excel::download(
            new DataLopExport($this->data->rows($request->user(), $filters)),
            'data-lop-'.now('Asia/Jakarta')->format('Ymd-His').'.xlsx',
            headers: ['Cache-Control' => 'private, no-store'],
        );
    }

    public function aissBoq(QeLop $qe_lop, AissBoqExportService $export): StreamedResponse
    {
        $this->authorize('view', $qe_lop);
        abort_unless($qe_lop->status_lop === LopStatus::COMPLETED, 404);
        $book = $export->workbook($qe_lop);
        $safeIncident = preg_replace('/[^a-z0-9_-]/i', '_', $qe_lop->incident ?: (string) $qe_lop->getKey());
        $safeLop = trim((string) preg_replace('/[^\p{L}\p{N}_-]+/u', '-', trim((string) $qe_lop->nama_lop)), '-');
        $filename = 'AISS-'.$safeIncident.($safeLop !== '' ? '-'.$safeLop : '').'.xlsx';

        return response()->streamDownload(function () use ($book): void {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function storeInsera(StoreInseraEvidenceRequest $request, QeLop $qe_lop): RedirectResponse
    {
        $this->authorize('uploadEvidence', $qe_lop);
        abort_if($qe_lop->status_lop === LopStatus::COMPLETED, 403, 'Capture Insera tidak dapat diubah setelah LOP completed.');

        $files = $request->file('files', []);
        $uploaded = $this->evidenceService->uploadInsera($qe_lop, $files, $request->user());

        return redirect()->to(LopReturnDestination::resolve($request, route('data-lops.index')))
            ->with('status', count($uploaded).' Capture Ticket Insera berhasil diupload. File akan tampil di Step 3 teknisi dan menunggu review.');
    }

    public function destroy(DeleteLopRequest $request, QeLop $qe_lop): RedirectResponse
    {
        $this->authorize('delete', $qe_lop);
        $this->lopService->delete($qe_lop, $request->user(), $request->validated('cancellation_reason'));

        return redirect()->to(LopReturnDestination::resolve($request, route('data-lops.index')))
            ->with('status', 'LOP dibatalkan dan diarsipkan. Riwayat serta file tetap tersimpan, tetapi LOP tidak lagi masuk perhitungan aktif.');
    }
}
