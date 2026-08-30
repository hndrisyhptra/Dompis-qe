<?php

namespace App\Http\Controllers;

use App\Enums\LopStatus;
use App\Http\Requests\StoreLopRequest;
use App\Http\Requests\TransitionLopStatusRequest;
use App\Http\Requests\UpdateLopRequest;
use App\Models\QeLop;
use App\Services\LopService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LopController extends Controller
{
    public function __construct(private readonly LopService $lopService)
    {
    }

    /**
     * Inbox - Active LOP (status belum completed/rejected).
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', QeLop::class);

        $query = QeLop::query()
            ->with(['creator', 'activeAssignment.technician'])
            ->whereNotIn('status_lop', [LopStatus::COMPLETED->value, LopStatus::REJECTED->value]);

        $query = $this->scopeForUser($query, $request);

        return view('lop.index', [
            'lops' => $query->latest()->paginate(20),
        ]);
    }

    /**
     * Inbox - History (LOP yang sudah completed/rejected).
     */
    public function history(Request $request): View
    {
        $this->authorize('viewAny', QeLop::class);

        $query = QeLop::query()
            ->with(['creator', 'activeAssignment.technician'])
            ->whereIn('status_lop', [LopStatus::COMPLETED->value, LopStatus::REJECTED->value]);

        $query = $this->scopeForUser($query, $request);

        return view('lop.history', [
            'lops' => $query->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', QeLop::class);

        return view('lop.create');
    }

    public function store(StoreLopRequest $request): RedirectResponse
    {
        $lop = $this->lopService->create($request->validated(), $request->user());

        return redirect()
            ->route('lop.show', $lop)
            ->with('status', 'LOP berhasil dibuat.');
    }

    public function show(QeLop $qe_lop): View
    {
        $this->authorize('view', $qe_lop);

        $qe_lop->load(['creator', 'assignments.technician', 'histories.user', 'evidences.designator', 'evidences.uploader']);

        $technicians = \App\Models\User::query()
            ->whereHas('role', fn ($q) => $q->where('code', \App\Enums\UserRole::TEKNISI->value))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $designators = \App\Models\Designator::orderBy('code')->get();

        return view('lop.show', [
            'lop' => $qe_lop,
            'technicians' => $technicians,
            'designators' => $designators,
        ]);
    }

    public function edit(QeLop $qe_lop): View
    {
        $this->authorize('update', $qe_lop);

        return view('lop.edit', ['lop' => $qe_lop]);
    }

    public function update(UpdateLopRequest $request, QeLop $qe_lop): RedirectResponse
    {
        $qe_lop->update($request->validated());

        return redirect()
            ->route('lop.show', $qe_lop)
            ->with('status', 'LOP berhasil diperbarui.');
    }

    /**
     * Transisi status LOP (survey, progress, waiting_approval, completed,
     * rejected, dst) - divalidasi lewat LopService::transitionStatus()
     * terhadap whitelist LopStatus::transitions().
     */
    public function transitionStatus(TransitionLopStatusRequest $request, QeLop $qe_lop): RedirectResponse
    {
        $target = LopStatus::from($request->validated('status'));

        $this->lopService->transitionStatus(
            $qe_lop,
            $target,
            $request->user(),
            $request->validated('note')
        );

        return redirect()
            ->route('lop.show', $qe_lop)
            ->with('status', "Status LOP diubah ke {$target->label()}.");
    }

    /**
     * Scoping data per-role: teknisi hanya melihat LOP yang ditugaskan
     * padanya, role lain melihat semua sesuai policy viewAny.
     */
    private function scopeForUser($query, Request $request)
    {
        $user = $request->user();

        if ($user->hasRole(\App\Enums\UserRole::TEKNISI)) {
            $query->whereHas('assignments', function ($q) use ($user) {
                $q->where('technician_id', $user->id_user);
            });
        }

        return $query;
    }
}
