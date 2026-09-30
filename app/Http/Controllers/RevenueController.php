<?php

namespace App\Http\Controllers;

use App\Services\RevenueService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RevenueController extends Controller
{
    public function __construct(private readonly RevenueService $revenueService) {}

    public function index(Request $request): View
    {
        return view('revenue.index', $this->revenueService->dashboard(
            $request->user(),
            $request->only(['region', 'branch', 'service_area', 'program', 'status']),
        ));
    }
}
