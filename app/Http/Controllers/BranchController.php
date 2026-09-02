<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-master-data');

        $query = Branch::query()->with('regionRef')->withCount('users');

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return view('branches.index', [
            'branches' => $query->orderBy('name')->paginate(20)->withQueryString(),
            'q' => $search,
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage-master-data');

        return view('branches.create', ['regions' => $this->regionOptions()]);
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['region'] = Region::whereKey($data['region_id'])->value('name');

        Branch::create([
            ...$data,
            'created_by' => $request->user()->id_user,
            'updated_by' => $request->user()->id_user,
        ]);

        return redirect()->route('branches.index')->with('status', 'Branch berhasil ditambahkan.');
    }

    public function edit(Branch $branch): View
    {
        $this->authorize('manage-master-data');

        return view('branches.edit', [
            'branch' => $branch,
            'regions' => $this->regionOptions(),
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $data = $request->validated();
        $data['region'] = Region::whereKey($data['region_id'])->value('name');

        $branch->update([
            ...$data,
            'updated_by' => $request->user()->id_user,
        ]);

        return redirect()->route('branches.index')->with('status', 'Branch berhasil diperbarui.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('manage-master-data');

        if ($branch->users()->exists() || QeLop::query()->where('branch', $branch->name)->exists()) {
            return redirect()->route('branches.index')
                ->with('status', 'Branch tidak bisa dihapus: masih dipakai oleh user atau LOP.');
        }

        $branch->delete();

        return redirect()->route('branches.index')->with('status', 'Branch berhasil dihapus.');
    }

    private function regionOptions()
    {
        return Region::query()->where('is_active', true)->orderBy('name')->get();
    }
}
