<?php

namespace App\Http\Controllers;

use App\Services\AdminDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly AdminDashboardService $dashboardService) {}

    public function index(Request $request): View
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'tab' => ['nullable', 'in:program,status,summary'],
        ]);

        return view('dashboard.index', $this->dashboardService->dashboard(
            $request->user(),
            $request->only(['region', 'branch', 'program', 'status', 'date', 'tab']),
        ));
    }

    public function matrixLops(Request $request): JsonResponse
    {
        return response()->json($this->dashboardService->matrixLops(
            $request->user(),
            $request->only(['region', 'branch', 'program', 'status', 'metric']),
        ));
    }

    public function monitoringLops(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'branch' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'program' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'movement' => ['nullable', 'in:moving,idle'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($this->dashboardService->monitoringLops($request->user(), $filters));
    }

    public function monitoringActivities(Request $request, int $lopId): JsonResponse
    {
        $filters = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($this->dashboardService->monitoringActivities($request->user(), $lopId, $filters));
    }
}
