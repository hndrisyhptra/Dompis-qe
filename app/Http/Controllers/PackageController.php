<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePackageRequest;
use App\Http\Requests\UpdatePackageRequest;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PackageController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-master-data');

        $query = Package::query();

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return view('packages.index', [
            'packages' => $query->latest()->paginate(20)->withQueryString(),
            'q' => $search,
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage-master-data');

        return view('packages.create');
    }

    public function store(StorePackageRequest $request): RedirectResponse
    {
        Package::create([
            ...$request->validated(),
            'created_by' => $request->user()->id_user,
            'updated_by' => $request->user()->id_user,
        ]);

        return redirect()
            ->route('packages.index')
            ->with('status', 'Paket KHS berhasil ditambahkan.');
    }

    public function edit(Package $package): View
    {
        $this->authorize('manage-master-data');

        return view('packages.edit', ['package' => $package]);
    }

    public function update(UpdatePackageRequest $request, Package $package): RedirectResponse
    {
        $package->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id_user,
        ]);

        return redirect()
            ->route('packages.index')
            ->with('status', 'Paket KHS berhasil diperbarui.');
    }

    public function destroy(Package $package): RedirectResponse
    {
        $this->authorize('manage-master-data');

        $package->delete();

        return redirect()
            ->route('packages.index')
            ->with('status', 'Paket KHS berhasil dihapus.');
    }
}
