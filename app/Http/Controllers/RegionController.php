<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegionRequest;
use App\Http\Requests\UpdateRegionRequest;
use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-master-data');

        $query = Region::query()->withCount('branches');

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return view('regions.index', [
            'regions' => $query->orderBy('name')->paginate(20)->withQueryString(),
            'q' => $search,
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage-master-data');

        return view('regions.create');
    }

    public function store(StoreRegionRequest $request): RedirectResponse
    {
        Region::create([
            ...$request->validated(),
            'created_by' => $request->user()->id_user,
            'updated_by' => $request->user()->id_user,
        ]);

        return redirect()->route('regions.index')->with('status', 'Region berhasil ditambahkan.');
    }

    public function edit(Region $region): View
    {
        $this->authorize('manage-master-data');

        return view('regions.edit', ['region' => $region]);
    }

    public function update(UpdateRegionRequest $request, Region $region): RedirectResponse
    {
        $region->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id_user,
        ]);

        // Jaga cache string branches.region tetap sinkron dengan nama region.
        $region->branches()->update(['region' => $region->name]);

        return redirect()->route('regions.index')->with('status', 'Region berhasil diperbarui.');
    }

    public function destroy(Region $region): RedirectResponse
    {
        $this->authorize('manage-master-data');

        if ($region->branches()->exists()) {
            return redirect()->route('regions.index')
                ->with('status', 'Region tidak bisa dihapus: masih dipakai oleh branch.');
        }

        $region->delete();

        return redirect()->route('regions.index')->with('status', 'Region berhasil dihapus.');
    }
}
