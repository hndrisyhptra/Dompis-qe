<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDesignatorTypeRequest;
use App\Http\Requests\UpdateDesignatorTypeRequest;
use App\Models\DesignatorType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DesignatorTypeController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-master-data');

        $query = DesignatorType::query()->withCount('designators');

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return view('designator-types.index', [
            'types' => $query->orderBy('name')->paginate(20)->withQueryString(),
            'q' => $search,
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage-master-data');

        return view('designator-types.create');
    }

    public function store(StoreDesignatorTypeRequest $request): RedirectResponse
    {
        DesignatorType::create([
            ...$request->validated(),
            'created_by' => $request->user()->id_user,
            'updated_by' => $request->user()->id_user,
        ]);

        return redirect()->route('designator-types.index')
            ->with('status', 'Tipe designator berhasil ditambahkan.');
    }

    public function edit(DesignatorType $designator_type): View
    {
        $this->authorize('manage-master-data');

        return view('designator-types.edit', ['type' => $designator_type]);
    }

    public function update(UpdateDesignatorTypeRequest $request, DesignatorType $designator_type): RedirectResponse
    {
        $designator_type->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id_user,
        ]);

        return redirect()->route('designator-types.index')
            ->with('status', 'Tipe designator berhasil diperbarui.');
    }

    public function destroy(DesignatorType $designator_type): RedirectResponse
    {
        $this->authorize('manage-master-data');

        if ($designator_type->designators()->exists()) {
            return redirect()->route('designator-types.index')
                ->with('status', 'Tipe tidak bisa dihapus: masih dipakai designator.');
        }

        $designator_type->delete();

        return redirect()->route('designator-types.index')
            ->with('status', 'Tipe designator berhasil dihapus.');
    }
}
