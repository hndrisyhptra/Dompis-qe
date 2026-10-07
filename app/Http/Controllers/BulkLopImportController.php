<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreBulkImportRequest;
use App\Jobs\ProcessBulkLopImport;
use App\Models\QeImportBatch;
use App\Models\QeLop;
use App\Services\ImportBatchService;
use App\Services\LopVisibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkLopImportController extends Controller
{
    public function __construct(
        private readonly ImportBatchService $batchService,
        private readonly LopVisibilityService $visibility,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('create', QeLop::class);

        return view('imports.bulk-lop', [
            'batches' => $this->batchesFor($request)->where('type', 'bulk_lop')->latest()->limit(5)->get(),
            'hasImportScope' => $this->hasImportScope($request),
        ]);
    }

    public function store(StoreBulkImportRequest $request): RedirectResponse|JsonResponse
    {
        if (! $this->hasImportScope($request)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Scope lokasi akun Admin belum dikonfigurasi. Hubungi Super Admin sebelum melakukan Bulk Import LOP.',
                ], 422);
            }

            return back()->withErrors(['file' => 'Scope lokasi akun Admin belum dikonfigurasi. Hubungi Super Admin sebelum melakukan Bulk Import LOP.']);
        }

        $batch = $this->batchService->create('bulk_lop', $request->file('file'), $request->user());
        ProcessBulkLopImport::dispatch($batch->id_import_batch);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'File Bulk LOP masuk antrean dan sedang diproses.',
                'result_url' => route('imports.show', $batch, false),
            ], 202);
        }

        return redirect()->route('imports.show', $batch)
            ->with('status', 'File Bulk LOP masuk antrean dan sedang diproses.');
    }

    public function template(): StreamedResponse
    {
        $this->authorize('create', QeLop::class);

        $headers = ['incident', 'sto', 'branch', 'area', 'program_type', 'segment', 'budget_type', 'job_description', 'ihld_id', 'nama_lop', 'package_code'];
        $sample = ['INC123456', 'SDA', 'SIDOARJO', '3', 'recovery', 'odp', '', 'Penggantian box ODP', '', '', '5'];

        return response()->streamDownload(function () use ($headers, $sample) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            fputcsv($out, $sample);
            fclose($out);
        }, 'template-bulk-lop.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function batchesFor(Request $request)
    {
        $query = QeImportBatch::query()->with('uploader');

        if (! $request->user()->hasRole(UserRole::SUPER_ADMIN)) {
            $query->where('uploaded_by', $request->user()->id_user);
        }

        return $query;
    }

    private function hasImportScope(Request $request): bool
    {
        return $request->user()->hasRole(UserRole::SUPER_ADMIN)
            || $this->visibility->accessibleServiceAreaIds($request->user())->isNotEmpty();
    }
}
