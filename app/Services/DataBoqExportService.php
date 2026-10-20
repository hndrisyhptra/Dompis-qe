<?php

namespace App\Services;

use App\Models\QeLop;
use App\Models\User;
use Generator;
use Illuminate\Database\Eloquent\Builder;

class DataBoqExportService
{
    public function __construct(
        private readonly LopVisibilityService $visibility,
        private readonly LopBoqProjectionService $projection,
        private readonly IncidentReportedDateService $reportedDates,
    ) {}

    /**
     * @param  array<int, string>|null  $reportedIncidents  daftar incident hasil
     *                                                      incidentsBetween(); null = tanpa filter tanggal
     */
    public function rows(User $user, ?array $reportedIncidents = null): Generator
    {
        $query = QeLop::query()
            ->where(fn (Builder $query) => $query->whereHas('boq')->orWhereHas('materialReservation.items'))
            ->with([
                'package',
                'boq.package',
                'boq.items.designator.type',
                'materialReservation.items.designator.type',
                'branchRef.regionRef',
                'serviceArea.region',
                'serviceArea.branch.regionRef',
            ]);
        $this->visibility->apply($query, $user);
        $this->reportedDates->applyTo($query, $reportedIncidents);

        foreach ($query->lazyById(100) as $lop) {
            foreach ($this->projection->masterDataLines($lop) as $line) {
                yield [
                    $lop->ihld_id,
                    $lop->incident,
                    $lop->nama_lop,
                    $line['designator_code'],
                    $line['designator_name'],
                    $line['type'],
                    $line['qty'],
                    $line['qty_actual'],
                    $line['price'],
                    $line['total_actual'],
                ];
            }
        }
    }
}
