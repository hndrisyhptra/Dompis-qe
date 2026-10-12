<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserImportRequest;
use App\Jobs\ProcessUserImport;
use App\Models\QeImportBatch;
use App\Models\User;
use App\Services\UserImportService;
use App\Services\UserImportTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class UserImportController extends Controller
{
    public function store(StoreUserImportRequest $request, UserImportService $service): RedirectResponse
    {
        $batch = $service->enqueue($request->file('file'), $request->user());
        try {
            ProcessUserImport::dispatch($batch->id_import_batch);
        } catch (Throwable $e) {
            $service->fail($batch, 'File belum dapat dimasukkan ke antrean. Periksa konfigurasi queue lalu upload ulang.');
        }

        return redirect()->route('users.imports.show', $batch);
    }

    public function template(Request $request, UserImportTemplateService $templates): StreamedResponse
    {
        $this->authorize('create', User::class);
        $options = Validator::make(['format' => $request->input('format', 'xlsx'), 'scope' => $request->input('scope', 'all')], [
            'format' => [Rule::in(['csv', 'xlsx'])],
            'scope' => [Rule::in(['all', 'area', 'region', 'branch', 'service_area', 'non_admin'])],
        ])->validate();
        $format = $options['format'];
        $scope = $options['scope'];

        return response()->streamDownload(function () use ($format, $scope, $templates) {
            if ($format === 'xlsx') {
                $book = $templates->workbook($scope);
                try {
                    (new Xlsx($book))->save('php://output');
                } finally {
                    $book->disconnectWorksheets();
                }
            } else {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                foreach ([UserImportService::HEADERS, ...$templates->samples($scope)] as $row) {
                    fputcsv($out, array_map(fn ($value) => preg_match('/^[=+@\-]/', (string) $value) ? "'".$value : $value, $row), ',', '"', '');
                }
                fclose($out);
            }
        }, "template-users-{$scope}.{$format}", [
            'Content-Type' => $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function show(Request $request, QeImportBatch $batch): View
    {
        $this->assertAccess($batch);

        return view('imports.show', ['batch' => $batch->load('uploader'), 'rows' => $batch->rows()->paginate(50),
            'backUrl' => route('users.index'), 'statusUrl' => route('users.imports.status', $batch, false)]);
    }

    public function status(QeImportBatch $batch): JsonResponse
    {
        $this->assertAccess($batch);

        return response()->json([
            'status' => $batch->status, 'total_rows' => $batch->total_rows, 'success_rows' => $batch->success_rows,
            'failed_rows' => $batch->failed_rows, 'processed_rows' => $batch->processedRows(),
            'percentage' => $batch->progressPercentage(), 'error_message' => $batch->error_message, 'finished' => $batch->isFinished(),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function assertAccess(QeImportBatch $batch): void
    {
        $this->authorize('create', User::class);
        abort_unless($batch->type === 'users', 404);
    }
}
