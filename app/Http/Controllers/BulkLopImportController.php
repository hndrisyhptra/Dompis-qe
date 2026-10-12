<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreBulkImportRequest;
use App\Jobs\ProcessBulkLopImport;
use App\Models\QeImportBatch;
use App\Models\QeLop;
use App\Services\BulkLopTemplateService;
use App\Services\ImportBatchService;
use App\Services\LopVisibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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

    public function template(Request $request, BulkLopTemplateService $templates): StreamedResponse
    {
        $this->authorize('create', QeLop::class);
        $format = $request->validate(['format' => ['sometimes', Rule::in(['xlsx', 'csv'])]])['format'] ?? 'xlsx';
        $actor = $request->user();

        return response()->streamDownload(function () use ($format, $templates, $actor) {
            if ($format === 'xlsx') {
                $book = $templates->workbook($actor);
                try {
                    (new Xlsx($book))->save('php://output');
                } finally {
                    $book->disconnectWorksheets();
                }

                return;
            }
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ([BulkLopTemplateService::HEADERS, ...$templates->samples($actor)] as $row) {
                fputcsv($out, array_map(fn ($value) => preg_match('/^[=+@\-]/', (string) $value) ? "'".$value : $value, $row), ',', '"', '');
            }
            fclose($out);
        }, "template-bulk-lop.{$format}", [
            'Content-Type' => $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
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
