<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreBoqImportRequest;
use App\Jobs\ProcessBoqImport;
use App\Models\QeImportBatch;
use App\Models\QeLop;
use App\Services\ImportBatchService;
use App\Services\BoqImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BoqImportController extends Controller
{
    public function __construct(
        private readonly ImportBatchService $batchService,
        private readonly BoqImportService $boqImportService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('create', QeLop::class);

        return view('imports.boq', [
            'batches' => $this->batchScope($request)->where('type', 'boq')->latest()->limit(5)->get(),
        ]);
    }

    public function store(StoreBoqImportRequest $request): RedirectResponse|JsonResponse
    {
        $target = $request->input('target', 'actual');
        $replaceExisting = filter_var($request->input('replace_existing', false), FILTER_VALIDATE_BOOLEAN);

        // Parse file first to check project name and detect LOP existence
        // Parse file first to check project name and detect LOP existence
        $uploadedFile = $request->file('file');
        $tempPath = $uploadedFile->store('temp/boq-imports', 'local');
        $parsed = $this->boqImportService->parse(Storage::disk('local')->path($tempPath));
        $project = $parsed['meta']['project'];
        $lopMeta = $parsed['meta']['lop_meta'] ?? null;

        // Check if LOP exists or can be auto-created
        $actor = $request->user();
        try {
            $lop = $this->boqImportService->resolveOrCreateLop(
                $project,
                $lopMeta,
                $actor,
                $parsed['rows']
            );
        } catch (RuntimeException $e) {
            // If LOP not found and we have meta to auto-create, redirect to LOP create page
            if (str_contains($e->getMessage(), "tidak ditemukan") && $lopMeta !== null) {
                return redirect()->route('lops.create')
                    ->with('error', 'LOP tidak ditemukan. Silakan buat LOP terlebih dahulu atau tambahkan field LOP (STO, SEGMENT, dll) di file Excel.')
                    ->with('project_name', $project)
                    ->with('lop_meta', $lopMeta);
            }
            // Other errors (like incident duplicate)
            return redirect()->back()->with('error', $e->getMessage());
        }

        // Now create the batch and dispatch job
        $result = $this->batchService->create('boq', $request->file('file'), $request->user(), [
            'target' => $target,
            'replace_existing' => $replaceExisting,
        ]);

        $batch = $result['batch'];
        $isNew = $result['isNew'];

        // If file is duplicate, return warning to frontend
        if (!$isNew) {
            if ($replaceExisting) {
                // If replace is allowed, reset batch and re-dispatch ONLY if not already queued/processing
                $shouldDispatch = ! in_array($batch->status, ['queued', 'processing'], true);
                
                // Always update metadata with replace_existing flag
                $batch->update([
                    'status' => 'queued',
                    'error_message' => null,
                    'completed_at' => null,
                    'metadata' => array_merge($batch->metadata ?? [], [
                        'replace_existing' => true,
                        'target' => $target,
                    ]),
                ]);
                
                if ($shouldDispatch) {
                    ProcessBoqImport::dispatch($batch->id_import_batch);
                }
                
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'File BOQ sudah di-import sebelumnya. Batch telah di-reset dan diproses ulang.',
                        'duplicate' => false,
                        'result_url' => route('imports.show', $batch, false),
                    ], 202);
                }
                return redirect()->route('imports.show', $batch)
                    ->with('status', 'File BOQ sudah di-import sebelumnya, batch telah di-reset dan diproses ulang.');
            }

            $warning = 'File ini sudah pernah diunggah sebelumnya (duplikat). Batch ID: ' . $batch->id_import_batch . ' - Status: ' . $batch->status;
            
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $warning,
                    'duplicate' => true,
                    'result_url' => route('imports.show', $batch, false),
                ], 200);
            }

            return redirect()->route('imports.show', $batch)
                ->with('warning', $warning);
        }

        ProcessBoqImport::dispatch($batch->id_import_batch);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'File BOQ masuk antrean dan sedang diproses.',
                'result_url' => route('imports.show', $batch, false),
            ], 202);
        }

        return redirect()->route('imports.show', $batch)
            ->with('status', 'File BOQ masuk antrean dan sedang diproses.');
    }

    public function template(): StreamedResponse
    {
        $this->authorize('create', QeLop::class);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['PROJECT : NAMA_LOP_PERSIS']);
            fputcsv($out, ['STO : SDA']);
            fputcsv($out, ['SEGMENT : odp,feeder']);
            fputcsv($out, ['']);
            fputcsv($out, ['NO', 'DESIGNATOR', 'URAIAN PEKERJAAN', 'SATUAN', 'HARGA SATUAN (PAKET-5)', '', 'VOL']);
            fputcsv($out, ['', '', '', '', 'MATERIAL', 'JASA', '']);
            fputcsv($out, ['1', 'M-CONTOH', 'Contoh material', 'unit', '150000', '0', '2']);
            fputcsv($out, ['2', 'J-CONTOH', 'Contoh jasa', 'unit', '0', '50000', '2']);
            fclose($out);
        }, 'template-import-boq-existing.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function batchScope(Request $request)
    {
        $query = QeImportBatch::query()->with('uploader');
        if (! $request->user()->hasRole(UserRole::SUPER_ADMIN)) {
            $query->where('uploaded_by', $request->user()->id_user);
        }

        return $query;
    }
}
