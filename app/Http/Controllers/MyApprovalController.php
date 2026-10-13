<?php

namespace App\Http\Controllers;

use App\Services\MyApprovalService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyApprovalController extends Controller
{
    public function index(Request $request, MyApprovalService $service): View
    {
        $this->authorize('view-my-approvals');

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'tab' => ['nullable', 'string', 'max:40'],
            'program' => ['nullable', 'string', 'max:40'],
            'lop_status' => ['nullable', 'string', 'max:40'],
        ]);

        return view('my-approvals.index', $service->indexData($request->user(), $filters));
    }
}
