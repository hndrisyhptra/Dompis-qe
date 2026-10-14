<?php

namespace App\Http\Controllers;

use App\Enums\LopStatus;
use App\Enums\ProgramType;
use App\Enums\UserRole;
use App\Http\Requests\DeleteLopRequest;
use App\Models\Branch;
use App\Models\QeLop;
use App\Services\AissBoqExportService;
use App\Services\LopService;
use App\Services\LopVisibilityService;
use App\Support\LopReturnDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataLopController extends Controller
{
    public function __construct(
        private readonly LopService $lopService,
        private readonly LopVisibilityService $visibility,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', QeLop::class);

        $query = $this->scope($request)
            ->with(['creator', 'boq', 'materialReservation.items', 'evidences.uploader']);

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(fn ($q) => $q->where('incident', 'like', "%{$search}%")
                ->orWhere('nama_lop', 'like', "%{$search}%")
                ->orWhere('sto', 'like', "%{$search}%"));
        }
        if ($status = $request->string('status')->trim()->value()) {
            $query->where('status_lop', $status);
        }
        if ($program = $request->string('program')->trim()->value()) {
            $query->where('program_type', $program);
        }
        if ($branch = $request->string('branch')->trim()->value()) {
            $query->where('branch', $branch);
        }

        $branchQuery = Branch::query()->orderBy('name');
        if ($request->user()->hasRole(UserRole::ADMIN)) {
            $branchQuery->whereIn('id_branch', $this->visibility->accessibleBranchIds($request->user()));
        }

        return view('data-lops.index', [
            'lops' => $query->latest()->paginate(20)->withQueryString(),
            'programs' => ProgramType::cases(),
            'statuses' => LopStatus::cases(),
            'branches' => $branchQuery->get(),
            'filters' => compact('search', 'status', 'program', 'branch'),
        ]);
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

    public function destroy(DeleteLopRequest $request, QeLop $qe_lop): RedirectResponse
    {
        $this->authorize('delete', $qe_lop);
        $this->lopService->delete($qe_lop, $request->user(), $request->validated('cancellation_reason'));

        return redirect()->to(LopReturnDestination::resolve($request, route('data-lops.index')))
            ->with('status', 'LOP dibatalkan dan diarsipkan. Riwayat serta file tetap tersimpan, tetapi LOP tidak lagi masuk perhitungan aktif.');
    }

    private function scope(Request $request)
    {
        $query = QeLop::query();
        if ($request->user()->hasRole(UserRole::ADMIN)) {
            $this->visibility->apply($query, $request->user());
        }

        return $query;
    }
}
