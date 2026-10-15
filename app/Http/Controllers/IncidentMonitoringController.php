<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncidentMonitoringRequest;
use App\Http\Requests\TicketCoverageRequest;
use App\Services\IncidentMonitoringService;
use App\Services\TicketCoverageService;
use Illuminate\View\View;

class IncidentMonitoringController extends Controller
{
    public function __construct(
        private readonly IncidentMonitoringService $monitoring,
        private readonly TicketCoverageService $coverage,
    ) {}

    public function index(IncidentMonitoringRequest $request): View
    {
        return view('incident-monitoring.index', $this->monitoring->dashboard(
            $request->user(), $request->validated(),
        ));
    }

    public function tickets(TicketCoverageRequest $request): View
    {
        return view('incident-monitoring.tickets', $this->coverage->dashboard(
            $request->user(), $request->validated(),
        ));
    }
}
