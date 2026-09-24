<?php

namespace App\Http\Controllers;

use App\Enums\LopStatus;
use App\Enums\ProgramType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeLop;
use App\Services\LopService;
use App\Services\LopVisibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
            ->with(['creator', 'boq']);

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

    public function destroy(Request $request, QeLop $qe_lop): RedirectResponse
    {
        $this->authorize('delete', $qe_lop);
        $this->lopService->delete($qe_lop, $request->user());

        return redirect()->route('data-lops.index')->with('status', 'Data LOP berhasil dihapus.');
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
