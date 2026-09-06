<?php

namespace App\Http\Controllers;

use App\Services\AdminDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly AdminDashboardService $dashboardService) {}

    public function index(Request $request): View
    {
        return view('dashboard.index', $this->dashboardService->dashboard(
            $request->user(),
            $request->only(['region', 'branch', 'program', 'status']),
        ));
    }
}
