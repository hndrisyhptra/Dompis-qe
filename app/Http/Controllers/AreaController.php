<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAreaRequest;
use App\Http\Requests\UpdateAreaRequest;
use App\Models\Area;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AreaController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-master-data');

        $query = Area::query()->withCount('regions');

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return view('areas.index', [
            'areas' => $query->orderBy('code')->paginate(20)->withQueryString(),
            'q' => $search,
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage-master-data');

        return view('areas.create');
    }

    public function store(StoreAreaRequest $request): RedirectResponse
    {
        Area::create([
            ...$request->validated(),
            'created_by' => $request->user()->id_user,
            'updated_by' => $request->user()->id_user,
        ]);

        return redirect()->route('areas.index')->with('status', 'Area berhasil ditambahkan.');
    }

    public function edit(Area $area): View
    {
        $this->authorize('manage-master-data');

        return view('areas.edit', ['area' => $area]);
    }

    public function update(UpdateAreaRequest $request, Area $area): RedirectResponse
    {
        $area->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id_user,
        ]);

        return redirect()->route('areas.index')->with('status', 'Area berhasil diperbarui.');
    }

    public function destroy(Area $area): RedirectResponse
    {
        $this->authorize('manage-master-data');

        if ($area->regions()->exists()) {
            return redirect()->route('areas.index')
                ->with('status', 'Area tidak bisa dihapus: masih dipakai oleh region.');
        }

        $area->delete();

        return redirect()->route('areas.index')->with('status', 'Area berhasil dihapus.');
    }
}
