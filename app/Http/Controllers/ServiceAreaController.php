<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceAreaRequest;
use App\Http\Requests\UpdateServiceAreaRequest;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceAreaController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-master-data');

        $query = ServiceArea::query()->with(['branch.regionRef', 'region']);

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('workzone', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($branchFilter = $request->string('branch')->trim()->value()) {
            $query->whereHas('branch', fn ($q) => $q->where('name', $branchFilter));
        }

        if ($regionFilter = $request->string('region')->trim()->value()) {
            $query->whereHas('branch.regionRef', fn ($q) => $q->where('name', $regionFilter))
                ->orWhereHas('region', fn ($q) => $q->where('name', $regionFilter));
        }

        return view('service-areas.index', [
            'serviceAreas' => $query->orderBy('workzone')->paginate(20)->withQueryString(),
            'q' => $search ?? '',
            'branchFilter' => $branchFilter ?? '',
            'regionFilter' => $regionFilter ?? '',
            'branches' => Branch::orderBy('name')->get(),
            'regions' => Region::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage-master-data');

        return view('service-areas.create', [
            'branches' => $this->branchOptions(),
        ]);
    }

    public function store(StoreServiceAreaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $branch = Branch::find($data['branch_id']);

        ServiceArea::create([
            ...$data,
            'region_id' => $branch?->region_id,
            'created_by' => $request->user()->id_user,
            'updated_by' => $request->user()->id_user,
        ]);

        return redirect()->route('service-areas.index')->with('status', 'Service Area berhasil ditambahkan.');
    }

    public function edit(ServiceArea $service_area): View
    {
        $this->authorize('manage-master-data');

        return view('service-areas.edit', [
            'serviceArea' => $service_area,
            'branches' => $this->branchOptions(),
        ]);
    }

    public function update(UpdateServiceAreaRequest $request, ServiceArea $service_area): RedirectResponse
    {
        $data = $request->validated();
        $branch = Branch::find($data['branch_id']);

        $service_area->update([
            ...$data,
            'region_id' => $branch?->region_id,
            'updated_by' => $request->user()->id_user,
        ]);

        return redirect()->route('service-areas.index')->with('status', 'Service Area berhasil diperbarui.');
    }

    public function destroy(ServiceArea $service_area): RedirectResponse
    {
        $this->authorize('manage-master-data');

        if (QeLop::query()->where('sto', $service_area->workzone)->exists()) {
            return redirect()->route('service-areas.index')
                ->with('status', 'Service Area tidak bisa dihapus: masih dipakai oleh LOP (sto).');
        }

        $service_area->delete();

        return redirect()->route('service-areas.index')->with('status', 'Service Area berhasil dihapus.');
    }

    private function branchOptions()
    {
        return Branch::query()->with('regionRef')->where('is_active', true)->orderBy('name')->get();
    }
}
