<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDesignatorCategoryRequest;
use App\Http\Requests\UpdateDesignatorCategoryRequest;
use App\Models\DesignatorCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DesignatorCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-master-data');

        $query = DesignatorCategory::query()->withCount('designators');

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return view('designator-categories.index', [
            'categories' => $query->orderBy('name')->paginate(20)->withQueryString(),
            'q' => $search,
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage-master-data');

        return view('designator-categories.create');
    }

    public function store(StoreDesignatorCategoryRequest $request): RedirectResponse
    {
        DesignatorCategory::create([
            ...$request->validated(),
            'created_by' => $request->user()->id_user,
            'updated_by' => $request->user()->id_user,
        ]);

        return redirect()->route('designator-categories.index')
            ->with('status', 'Kategori designator berhasil ditambahkan.');
    }

    public function edit(DesignatorCategory $designator_category): View
    {
        $this->authorize('manage-master-data');

        return view('designator-categories.edit', ['category' => $designator_category]);
    }

    public function update(UpdateDesignatorCategoryRequest $request, DesignatorCategory $designator_category): RedirectResponse
    {
        $designator_category->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id_user,
        ]);

        return redirect()->route('designator-categories.index')
            ->with('status', 'Kategori designator berhasil diperbarui.');
    }

    public function destroy(DesignatorCategory $designator_category): RedirectResponse
    {
        $this->authorize('manage-master-data');

        if ($designator_category->designators()->exists()) {
            return redirect()->route('designator-categories.index')
                ->with('status', 'Kategori tidak bisa dihapus: masih dipakai designator.');
        }

        $designator_category->delete();

        return redirect()->route('designator-categories.index')
            ->with('status', 'Kategori designator berhasil dihapus.');
    }
}
