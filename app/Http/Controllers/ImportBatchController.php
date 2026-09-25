<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\QeImportBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImportBatchController extends Controller
{
    public function show(Request $request, QeImportBatch $batch): View
    {
        $this->assertAccess($request, $batch);

        $rows = $batch->rows();
        if ($batch->type === 'boq') {
            // Baris tanpa volume tetap tercatat untuk audit dan ringkasan,
            // tetapi tidak perlu memenuhi tabel hasil yang dibaca pengguna.
            $rows->where('status', '!=', 'skipped');
        }

        return view($batch->type === 'boq' ? 'imports.boq-result' : 'imports.show', [
            'batch' => $batch->load('uploader'),
            'rows' => $rows->paginate(50),
        ]);
    }

    public function status(Request $request, QeImportBatch $batch): JsonResponse
    {
        $this->assertAccess($request, $batch);
        $batch->refresh();

        return response()->json([
            'status' => $batch->status,
            'total_rows' => $batch->total_rows,
            'success_rows' => $batch->success_rows,
            'failed_rows' => $batch->failed_rows,
            'processed_rows' => $batch->processedRows(),
            'percentage' => $batch->progressPercentage(),
            'error_message' => $batch->error_message,
            'finished' => $batch->isFinished(),
        ]);
    }

    private function assertAccess(Request $request, QeImportBatch $batch): void
    {
        abort_unless(
            $request->user()->hasRole(UserRole::SUPER_ADMIN)
            || $batch->uploaded_by === $request->user()->id_user,
            403
        );
    }
}
