<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignLopRequest;
use App\Models\QeLop;
use App\Models\User;
use App\Services\LopService;
use Illuminate\Http\RedirectResponse;

class LopAssignmentController extends Controller
{
    public function __construct(private readonly LopService $lopService)
    {
    }

    public function store(AssignLopRequest $request, QeLop $qe_lop): RedirectResponse
    {
        $technician = User::findOrFail($request->validated('technician_id'));

        $this->lopService->assign($qe_lop, $technician, $request->user());

        return redirect()
            ->route('lop.show', $qe_lop)
            ->with('status', "Teknisi {$technician->name} berhasil ditugaskan.");
    }
}
