<?php

namespace App\Http\Controllers;

use App\Enums\LopSegment;
use App\Http\Requests\StoreTicketSegmentMapRequest;
use App\Http\Requests\UpdateTicketSegmentMapRequest;
use App\Models\TicketSegmentMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketSegmentMapController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-master-data');

        $query = TicketSegmentMap::query();

        if ($search = $request->string('q')->trim()->value()) {
            $query->where('source_value', 'like', "%{$search}%");
        }

        return view('ticket-segment-maps.index', [
            'maps' => $query->orderBy('source_value')->paginate(20)->withQueryString(),
            'q' => $search,
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage-master-data');

        return view('ticket-segment-maps.create', [
            'segments' => LopSegment::cases(),
        ]);
    }

    public function store(StoreTicketSegmentMapRequest $request): RedirectResponse
    {
        TicketSegmentMap::create($request->validated());

        return redirect()
            ->route('ticket-segment-maps.index')
            ->with('status', 'Pemetaan segment tiket berhasil ditambahkan.');
    }

    public function edit(TicketSegmentMap $ticket_segment_map): View
    {
        $this->authorize('manage-master-data');

        return view('ticket-segment-maps.edit', [
            'map' => $ticket_segment_map,
            'segments' => LopSegment::cases(),
        ]);
    }

    public function update(UpdateTicketSegmentMapRequest $request, TicketSegmentMap $ticket_segment_map): RedirectResponse
    {
        $ticket_segment_map->update($request->validated());

        return redirect()
            ->route('ticket-segment-maps.index')
            ->with('status', 'Pemetaan segment tiket berhasil diperbarui.');
    }

    public function destroy(TicketSegmentMap $ticket_segment_map): RedirectResponse
    {
        $this->authorize('manage-master-data');

        $ticket_segment_map->delete();

        return redirect()
            ->route('ticket-segment-maps.index')
            ->with('status', 'Pemetaan segment tiket berhasil dihapus.');
    }
}
