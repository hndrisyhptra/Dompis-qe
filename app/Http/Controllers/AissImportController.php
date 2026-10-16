<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreAissImportRequest;
use App\Jobs\ProcessAissImport;
use App\Models\QeImportBatch;
use App\Services\AissImportService;
use App\Services\ImportBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AissImportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('import-aiss');

        return view('imports.aiss', [
            'schemaReady' => Schema::hasColumn('qe_lops', 'status_aiss'),
            'batches' => QeImportBatch::query()->where('type', 'aiss')->with('uploader')
                ->when(! $request->user()->hasRole(UserRole::SUPER_ADMIN), fn ($q) => $q->where('uploaded_by', $request->user()->getKey()))
                ->latest()->limit(5)->get(),
        ]);
    }

    public function store(StoreAissImportRequest $request, ImportBatchService $batches): JsonResponse|RedirectResponse
    {
        if (! Schema::hasColumn('qe_lops', 'status_aiss')) {
            throw ValidationException::withMessages(['file' => 'Kolom Status AISS belum tersedia. Minta administrator menjalankan migration Status AISS terlebih dahulu.']);
        }
        $batch = $batches->create('aiss', $request->file('file'), $request->user());
        try {
            ProcessAissImport::dispatch($batch->getKey());
        } catch (Throwable $exception) {
            report($exception);
            app(AissImportService::class)->fail($batch, 'File tidak berhasil dimasukkan ke antrean. Periksa konfigurasi queue sebelum upload ulang.');
            throw ValidationException::withMessages(['file' => 'File tidak berhasil dimasukkan ke antrean. Hubungi administrator.']);
        }
        if ($request->expectsJson()) {
            return response()->json(['message' => 'File AISS masuk antrean.', 'result_url' => route('imports.show', $batch, false)], 202);
        }

        return redirect()->route('imports.show', $batch)->with('status', 'File AISS masuk antrean.');
    }
}
