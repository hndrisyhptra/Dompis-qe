<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportDesignatorPricesRequest;
use App\Http\Requests\StoreDesignatorPriceRequest;
use App\Http\Requests\UpdateDesignatorPriceRequest;
use App\Models\Designator;
use App\Models\DesignatorPackagePrice;
use App\Models\Package;
use App\Services\DesignatorImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "KHS" - harga satuan per designator per package.
 */
class DesignatorPriceController extends Controller
{
    public function __construct(private readonly DesignatorImportService $importService) {}

    public function index(Request $request): View
    {
        $this->authorize('manage-master-data');

        $query = DesignatorPackagePrice::query()->with(['designator', 'package']);

        if ($packageId = $request->integer('package')) {
            $query->where('package_id', $packageId);
        }

        return view('designator-prices.index', [
            'prices' => $query->latest()->paginate(20)->withQueryString(),
            'packages' => Package::orderBy('name')->get(),
            'packageFilter' => $packageId,
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage-master-data');

        return view('designator-prices.create', [
            'designators' => Designator::orderBy('code')->get(),
            'packages' => Package::orderBy('name')->get(),
        ]);
    }

    public function store(StoreDesignatorPriceRequest $request): RedirectResponse
    {
        DesignatorPackagePrice::create([
            ...$request->validated(),
            'created_by' => $request->user()->id_user,
            'updated_by' => $request->user()->id_user,
        ]);

        return redirect()
            ->route('designator-prices.index')
            ->with('status', 'KHS berhasil ditambahkan.');
    }

    public function edit(DesignatorPackagePrice $designator_price): View
    {
        $this->authorize('manage-master-data');

        return view('designator-prices.edit', [
            'price' => $designator_price,
            'designators' => Designator::orderBy('code')->get(),
            'packages' => Package::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateDesignatorPriceRequest $request, DesignatorPackagePrice $designator_price): RedirectResponse
    {
        $designator_price->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id_user,
        ]);

        return redirect()
            ->route('designator-prices.index')
            ->with('status', 'KHS berhasil diperbarui.');
    }

    public function destroy(DesignatorPackagePrice $designator_price): RedirectResponse
    {
        $this->authorize('manage-master-data');

        $designator_price->delete();

        return redirect()
            ->route('designator-prices.index')
            ->with('status', 'KHS berhasil dihapus.');
    }

    public function importForm(): View
    {
        $this->authorize('manage-master-data');

        return view('designator-prices.import');
    }

    public function import(ImportDesignatorPricesRequest $request): RedirectResponse
    {
        $result = $this->importService->importPrices($request->file('file'), $request->user());

        if ($result['errors']) {
            return back()->withErrors(['file' => $result['errors']])->withInput();
        }

        return redirect()
            ->route('designator-prices.index')
            ->with('status', "{$result['imported']} KHS berhasil diimpor.");
    }
}
