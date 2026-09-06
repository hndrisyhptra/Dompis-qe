<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignLopRequest;
use App\Models\QeLop;
use App\Models\User;
use App\Services\LopService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LopAssignmentController extends Controller
{
    public function __construct(private readonly LopService $lopService) {}

    public function store(AssignLopRequest $request, QeLop $qe_lop): RedirectResponse
    {
        $technician = User::findOrFail($request->validated('technician_id'));

        $this->lopService->assign($qe_lop, $technician, $request->user());

        $returnTo = (string) $request->validated('return_to');
        $flash = "Teknisi {$technician->name} berhasil ditugaskan.";

        if (str_starts_with($returnTo, 'wbs:')) {
            return redirect()->route('wbs.show', substr($returnTo, 4))->with('status', $flash);
        }

        $route = $returnTo === 'index' ? 'lop.index' : 'lop.show';

        return redirect()
            ->route($route, $route === 'lop.show' ? $qe_lop : [])
            ->with('status', $flash);
    }

    public function destroy(Request $request, QeLop $qe_lop): RedirectResponse
    {
        $this->authorize('unassign', $qe_lop);

        $technicianName = $qe_lop->activeAssignment?->technician?->name ?? 'Teknisi';
        $this->lopService->unassign($qe_lop, $request->user());

        return redirect()
            ->route('lop.index')
            ->with('status', "Assignment {$technicianName} berhasil dibatalkan.");
    }
}
