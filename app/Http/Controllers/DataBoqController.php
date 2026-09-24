<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\UpdateBoqRequest;
use App\Models\Designator;
use App\Models\Package;
use App\Models\QeBoq;
use App\Services\BoqService;
use App\Services\LopVisibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DataBoqController extends Controller
{
    public function __construct(
        private readonly BoqService $boqService,
        private readonly LopVisibilityService $visibility,
    ) {}

    public function index(Request $request): View
    {
        $query = QeBoq::query()->with(['lop', 'package', 'items.designator.type']);

        if ($request->user()->hasRole(UserRole::ADMIN)) {
            $query->whereHas('lop', fn ($lop) => $this->visibility->apply($lop, $request->user()));
        }

        if ($search = $request->string('q')->trim()->value()) {
            $query->whereHas('lop', fn ($q) => $q->where('incident', 'like', "%{$search}%")
                ->orWhere('nama_lop', 'like', "%{$search}%"));
        }

        return view('data-boqs.index', [
            'boqs' => $query->latest()->paginate(15)->withQueryString(),
            'designators' => Designator::query()->with('type')->orderBy('code')->get(),
            'packages' => Package::query()->orderBy('code')->get(),
            'search' => $search ?? '',
        ]);
    }

    public function update(UpdateBoqRequest $request, QeBoq $boq): RedirectResponse
    {
        $package = $request->filled('package_id') ? Package::find($request->integer('package_id')) : null;
        $this->boqService->save($boq->lop, $request->validated('items'), $package, $request->user());

        return back()->with('status', 'BOQ berhasil diperbarui dan reservasi draft telah disinkronkan.');
    }

    public function destroy(Request $request, QeBoq $boq): RedirectResponse
    {
        $this->authorize('update', $boq->lop);
        $this->boqService->delete($boq, $request->user());

        return back()->with('status', 'Data BOQ berhasil dihapus.');
    }
}
