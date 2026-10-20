<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\QeImportBatch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ImportBatchController extends Controller
{
    public function show(Request $request, QeImportBatch $batch): View
    {
        $this->assertAccess($request, $batch);

        if ($batch->isFinished() && ! session()->has('import_shown_summary_' . $batch->id_import_batch)) {
            $message = sprintf(
                'Import selesai. Total: %d baris, Berhasil: %d, Gagal: %d.',
                $batch->total_rows,
                $batch->success_rows,
                $batch->failed_rows
            );
            if ($batch->status === 'partial') {
                $message .= ' Beberapa baris gagal diproses.';
            }
            session()->flash('status', $message);
            session()->put('import_shown_summary_' . $batch->id_import_batch, true);
        }

        $rows = $batch->rows();
        $rowStatus = 'all';
        $storedRowCount = null;
        if ($batch->type === 'bulk_lop') {
            $rowStatus = $request->validate(['row_status' => ['sometimes', 'string', Rule::in(['all', 'failed', 'success'])]])['row_status'] ?? 'all';
            $storedRowCount = $batch->rows()->count();
            if ($rowStatus !== 'all') {
                $rows->where('status', $rowStatus);
            }
        }
        if ($batch->type === 'boq') {
            // Baris tanpa volume tetap tercatat untuk audit dan ringkasan,
            // tetapi tidak perlu memenuhi tabel hasil yang dibaca pengguna.
            $rows->where('status', '!=', 'skipped');
        }

        // Show all rows if total is reasonable, otherwise paginate with large page size
        return view($batch->type === 'boq' ? 'imports.boq-result' : 'imports.show', [
            'batch' => $batch->load('uploader'),
            'rows' => $rows->paginate(50)->withQueryString(),
            'rowStatus' => $rowStatus,
            'storedRowCount' => $storedRowCount,
        ]);
    }

    public function status(Request $request, QeImportBatch $batch): JsonResponse
    {
        $this->assertAccess($request, $batch);
        $batch->refresh();

        return response()->json([
            'status' => $batch->status,
            'skipped_rows' => (int) data_get($batch->metadata, 'skipped_rows', 0),
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
        if ($batch->type === 'aiss') {
            $this->authorize('import-aiss');
        }
        if ($batch->type === 'users') {
            $this->authorize('create', User::class);
        }
        abort_unless(
            $request->user()->hasRole(UserRole::SUPER_ADMIN)
            || $batch->uploaded_by === $request->user()->id_user,
            403
        );
    }
}
