<?php

namespace App\Services;

use App\Models\QeLop;
use App\Models\User;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class DataLopService
{
    public function __construct(private readonly LopVisibilityService $visibility) {}

    public function filters(array $input): array
    {
        return [
            'search' => trim((string) ($input['q'] ?? '')),
            'status' => trim((string) ($input['status'] ?? '')),
            'program' => trim((string) ($input['program'] ?? '')),
            'branch' => trim((string) ($input['branch'] ?? '')),
        ];
    }

    public function query(User $user, array $filters): Builder
    {
        $query = $this->visibility->apply(QeLop::query(), $user);
        if ($filters['search'] !== '') {
            $search = '%'.$filters['search'].'%';
            $query->where(fn (Builder $q) => $q->where('incident', 'like', $search)
                ->orWhere('nama_lop', 'like', $search)->orWhere('sto', 'like', $search));
        }
        foreach (['status' => 'status_lop', 'program' => 'program_type', 'branch' => 'branch'] as $filter => $column) {
            if ($filters[$filter] !== '') {
                $query->where($column, $filters[$filter]);
            }
        }

        return $query;
    }

    public function rows(User $user, array $filters): Generator
    {
        $query = $this->query($user, $filters)->with([
            'creator:id_user,name',
            'serviceArea:id_service_area,workzone',
            'branchRef:id_branch,name',
            'boq:id_boq,qe_lop_id,item_count',
            'materialReservation' => fn ($query) => $query->select(['id_reservation', 'qe_lop_id'])->withCount('items'),
        ]);
        foreach ($query->lazyByIdDesc(250) as $lop) {
            $count = $lop->boq ? (int) $lop->boq->item_count : (int) ($lop->materialReservation?->items_count ?? 0);
            $boq = $lop->boq ? 'BOQ Plan' : ($count > 0 ? 'Reservasi Teknisi' : 'Belum ada');
            yield [
                $lop->ihld_id, $lop->incident, $lop->nama_lop,
                $lop->serviceArea?->workzone ?: $lop->sto,
                $lop->branchRef?->name ?: $lop->branch,
                $lop->area, $lop->program_type->label(), $lop->segmentLabel(),
                $lop->budget_type?->value, $lop->status_lop->label(), $lop->status_aiss,
                $boq, $count, $lop->job_description, $lop->creator?->name,
                $lop->created_at ? Date::dateTimeToExcel($lop->created_at->copy()->timezone('Asia/Jakarta')) : null,
            ];
        }
    }
}
