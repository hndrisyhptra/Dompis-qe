<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QueueMonitorController extends Controller
{
    public function status(): JsonResponse
    {
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        $reservedJobs = DB::table('jobs')->whereNotNull('reserved_at')->count();
        $lastProcessedAt = Cache::get('queue_last_processed_at', null);

        return response()->json([
            'status' => $pendingJobs > 0 ? 'processing' : 'idle',
            'pending_jobs' => $pendingJobs,
            'reserved_jobs' => $reservedJobs,
            'failed_jobs' => $failedJobs,
            'last_processed_at' => $lastProcessedAt,
        ]);
    }
}