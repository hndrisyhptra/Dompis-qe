<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Exports\DataBoqExport;
use App\Http\Requests\DataBoqFilterRequest;
use App\Http\Requests\ResetBoqRequest;
use App\Http\Requests\UpdateBoqRequest;
use App\Http\Requests\UpdateReservationBoqRequest;
use App\Models\Designator;
use App\Models\Package;
use App\Models\QeBoq;
use App\Models\QeLop;
use App\Services\BoqService;
use App\Services\DataBoqExportService;
use App\Services\IncidentReportedDateService;
use App\Services\LopBoqValueService;
use App\Services\LopService;
use App\Services\LopVisibilityService;
use App\Services\ReservationBoqService;
use App\Support\MaterialReservationRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DataBoqController extends Controller
{
    public function __construct(
        private readonly BoqService $boqService,
        private readonly LopVisibilityService $visibility,
        private readonly LopBoqValueService $boqValues,
    ) {}

    public function index(DataBoqFilterRequest $request, IncidentReportedDateService $reportedDates): View
    {
        $dateFrom = trim((string) $request->input('date_from'));
        $dateTo = trim((string) $request->input('date_to'));
        $hasDateFilter = $dateFrom !== '' || $dateTo !== '';
        // null = tanpa filter; [] = tak ada tiket pada rentang atau sumber tiket mati.
        $reportedIncidents = null;
        $sourceUnavailable = false;

        if ($hasDateFilter) {
            try {
                $reportedIncidents = $reportedDates->incidentsBetween($dateFrom, $dateTo);
            } catch (Throwable $exception) {
                report($exception);
                $sourceUnavailable = true;
                $reportedIncidents = [];
            }
        }

        $query = QeBoq::query()->whereHas('lop')->with(['lop', 'package', 'items.designator.type']);

        if ($request->user()->hasRole(UserRole::ADMIN)) {
            $query->whereHas('lop', fn ($lop) => $this->visibility->apply($lop, $request->user()));
        }

        if ($search = $request->string('q')->trim()->value()) {
            $query->whereHas('lop', fn ($q) => $q->where('incident', 'like', "%{$search}%")
                ->orWhere('nama_lop', 'like', "%{$search}%"));
        }

        $query->whereHas('lop', fn (Builder $lop) => $reportedDates->applyTo($lop, $reportedIncidents));

        $reservationQuery = QeLop::query()
            ->with([
                'package',
                'materialReservation.items.designator.type',
                'evidences:id_evidence,qe_lop_id,designator_id',
            ])
            ->whereDoesntHave('boq')
            ->whereHas('materialReservation.items');
        if ($request->user()->hasRole(UserRole::ADMIN)) {
            $this->visibility->apply($reservationQuery, $request->user());
        }
        if ($search ?? '') {
            $reservationQuery->where(fn ($q) => $q->where('incident', 'like', "%{$search}%")
                ->orWhere('nama_lop', 'like', "%{$search}%"));
        }
        $reportedDates->applyTo($reservationQuery, $reportedIncidents);

        $reservationBoqs = $reservationQuery->latest()->paginate(15, ['*'], 'reservation_page')->withQueryString();
        $canEditReservations = $request->user()->hasRole(...UserRole::adminLevel());
        $reservationBoqs->getCollection()->each(function (QeLop $lop) use ($canEditReservations): void {
            $lop->setAttribute('effective_boq', $this->boqValues->summarize($lop));
            $lop->setAttribute('reservation_version', ReservationBoqService::version($lop->materialReservation));
            // Seluruh LOP sudah difilter scope pada query; jangan query policy per baris Blade.
            $lop->setAttribute('reservation_editable', $canEditReservations && MaterialReservationRules::editable($lop));
            $lop->setAttribute('reservation_editor_items', $lop->materialReservation->items->map(fn ($item) => [
                'designator_id' => $item->designator_id,
                'qty' => (int) $item->qty,
                'qty_actual' => $item->qty_actual === null ? null : (float) $item->qty_actual,
                'code' => $item->designator?->code,
                'name' => $item->designator?->item_name,
                'existing' => true,
                'can_delete' => MaterialReservationRules::removable($item, $lop->evidences->contains('designator_id', $item->designator_id)),
            ])->values());
        });

        $boqs = $query->latest()->paginate(15)->withQueryString();
        $boqs->getCollection()->each(function (QeBoq $boq): void {
            $items = $boq->items->filter(fn ($item) => $boq->lop->program_type->allowsBoqType($item->type, $item->designator_code))->values();
            $boq->setAttribute('program_items', $items);
            $boq->setAttribute('program_total', (float) $items->sum('total_price'));
        });

        return view('data-boqs.index', [
            'boqs' => $boqs,
            'reservationBoqs' => $reservationBoqs,
            'designators' => Designator::query()->with('type')->orderBy('code')->get(),
            'packages' => Package::query()->orderBy('code')->get(),
            'search' => $search ?? '',
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'sourceUnavailable' => $sourceUnavailable,
        ]);
    }

    public function export(DataBoqFilterRequest $request, DataBoqExportService $service, IncidentReportedDateService $reportedDates): BinaryFileResponse|RedirectResponse
    {
        $this->authorize('export-data-boqs');

        $dateFrom = trim((string) $request->input('date_from'));
        $dateTo = trim((string) $request->input('date_to'));
        $reportedIncidents = null;

        if ($dateFrom !== '' || $dateTo !== '') {
            try {
                $reportedIncidents = $reportedDates->incidentsBetween($dateFrom, $dateTo);
            } catch (Throwable $exception) {
                report($exception);

                // Mengunduh tanpa filter akan menyesatkan, jadi unduhan dibatalkan.
                return back()->with('error', 'Sumber tiket Dompis sedang tidak tersedia, unduhan dengan filter tanggal tidak dapat diproses. Silakan coba lagi beberapa saat.');
            }
        }

        return Excel::download(
            new DataBoqExport($service, $request->user(), $reportedIncidents),
            'data-boq-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    public function update(UpdateBoqRequest $request, QeBoq $boq): RedirectResponse
    {
        $package = $request->filled('package_id') ? Package::find($request->integer('package_id')) : null;
        $this->boqService->save($boq->lop, $request->validated('items'), $package, $request->user());

        return back()->with('status', 'BOQ berhasil diperbarui dan reservasi draft telah disinkronkan.');
    }

    public function updateReservation(UpdateReservationBoqRequest $request, QeLop $qe_lop, ReservationBoqService $service): RedirectResponse
    {
        $service->update($qe_lop, $request->validated('items'), $request->validated('reservation_version'), $request->user());

        return back()->with('status', 'BOQ aktual diperbarui. Item reservasi teknisi sudah disesuaikan tanpa mengubah rekap aktual atau BOQ Plan.');
    }

    public function reset(ResetBoqRequest $request, QeLop $qe_lop, LopService $service): RedirectResponse
    {
        $service->resetCompletedForBoq($qe_lop, $request->user());

        return back()->with('status', 'LOP dibuka kembali ke Progress. BOQ dapat diedit; evidence dan rekap aktual tetap tersimpan. Nilai realisasi kembali dihitung setelah LOP Completed lagi.');
    }

    public function destroy(Request $request, QeBoq $boq): RedirectResponse
    {
        abort_if($boq->lop === null, 404);
        $this->authorize('update', $boq->lop);
        $this->boqService->delete($boq, $request->user());

        return back()->with('status', 'Data BOQ berhasil dihapus.');
    }
}
