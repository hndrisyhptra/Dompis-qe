<?php

namespace App\Http\Controllers;

use App\Enums\DesignatorType;
use App\Http\Requests\ImportDesignatorsRequest;
use App\Http\Requests\StoreDesignatorRequest;
use App\Http\Requests\UpdateDesignatorRequest;
use App\Models\Designator;
use App\Services\DesignatorImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DesignatorController extends Controller
{
    public function __construct(private readonly DesignatorImportService $importService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('manage-master-data');

        $query = Designator::query();

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('item_name', 'like', "%{$search}%");
            });
        }

        return view('designators.index', [
            'designators' => $query->latest()->paginate(20)->withQueryString(),
            'q' => $search,
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage-master-data');

        return view('designators.create');
    }

    public function store(StoreDesignatorRequest $request): RedirectResponse
    {
        Designator::create([
            ...$request->validated(),
            'created_by' => $request->user()->id_user,
            'updated_by' => $request->user()->id_user,
        ]);

        return redirect()
            ->route('designators.index')
            ->with('status', 'Designator berhasil ditambahkan.');
    }

    public function edit(Designator $designator): View
    {
        $this->authorize('manage-master-data');

        return view('designators.edit', ['designator' => $designator]);
    }

    public function update(UpdateDesignatorRequest $request, Designator $designator): RedirectResponse
    {
        $designator->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id_user,
        ]);

        return redirect()
            ->route('designators.index')
            ->with('status', 'Designator berhasil diperbarui.');
    }

    public function destroy(Designator $designator): RedirectResponse
    {
        $this->authorize('manage-master-data');

        $designator->delete();

        return redirect()
            ->route('designators.index')
            ->with('status', 'Designator berhasil dihapus.');
    }

    public function importForm(): View
    {
        $this->authorize('manage-master-data');

        return view('designators.import');
    }

    public function import(ImportDesignatorsRequest $request): RedirectResponse
    {
        $result = $this->importService->importDesignators($request->file('file'), $request->user());

        if ($result['errors']) {
            return back()->withErrors(['file' => $result['errors']])->withInput();
        }

        return redirect()
            ->route('designators.index')
            ->with('status', "{$result['imported']} designator berhasil diimpor.");
    }
}
