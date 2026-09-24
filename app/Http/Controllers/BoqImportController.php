<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreBoqImportRequest;
use App\Jobs\ProcessBoqImport;
use App\Models\QeImportBatch;
use App\Models\QeLop;
use App\Services\ImportBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BoqImportController extends Controller
{
    public function __construct(private readonly ImportBatchService $batchService) {}

    public function index(Request $request): View
    {
        $this->authorize('create', QeLop::class);

        return view('imports.boq', [
            'batches' => $this->batchScope($request)->where('type', 'boq')->latest()->limit(5)->get(),
        ]);
    }

    public function store(StoreBoqImportRequest $request): RedirectResponse|JsonResponse
    {
        $batch = $this->batchService->create('boq', $request->file('file'), $request->user());
        ProcessBoqImport::dispatch($batch->id_import_batch);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'File BOQ masuk antrean dan sedang diproses.',
                'result_url' => route('imports.show', $batch),
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
            fputcsv($out, ['PROJECT : NAMA_LOP_PERSIS', '', '', '', '', '', '']);
            fputcsv($out, ['', '', '', '', '', '', '']);
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
