<?php

namespace App\Services;

use App\Enums\EvidenceCategory;
use App\Enums\LopStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Monitoring aktivitas tercatat, dengan batas hari operasional WIB. */
class DashboardMonitoringService
{
    private const TIMEZONE = 'Asia/Jakarta';

    public function __construct(private readonly LopVisibilityService $visibility) {}

    public function summary(Builder $scopedLops, User $user, array $filters, ?string $date): array
    {
        [$day, $start, $end] = $this->dayBounds($date);
        $lops = $this->locatedLops($scopedLops, $end);
        $events = $this->events(clone $lops, $end);
        $totals = DB::query()->fromSub(clone $lops, 'lops')->select('monitoring_branch')->selectRaw('COUNT(*) as total_lops')
            ->groupBy('monitoring_branch')->get()->keyBy('monitoring_branch');

        $activity = DB::query()->fromSub($events, 'events')
            ->joinSub(clone $lops, 'lops', 'lops.id_qe_lops', '=', 'events.lop_id')
            ->select('lops.monitoring_branch')
            ->selectRaw('MAX(events.occurred_at) as last_update')
            ->selectRaw('SUM(CASE WHEN events.occurred_at >= ? THEN 1 ELSE 0 END) as activities', [$start])
            ->selectRaw('COUNT(DISTINCT CASE WHEN events.occurred_at >= ? THEN events.lop_id END) as moving_lops', [$start])
            ->selectRaw('COUNT(DISTINCT CASE WHEN events.occurred_at >= ? THEN events.actor_id END) as actors', [$start])
            ->groupBy('lops.monitoring_branch')->get()->keyBy('monitoring_branch');

        $latest = $this->latestEvents(DB::query()->fromSub($this->events(clone $lops, $end, true), 'events')
            ->joinSub(clone $lops, 'lops', 'lops.id_qe_lops', '=', 'events.lop_id'), 'lops.monitoring_branch');

        $branchQuery = Branch::query()->orderBy('region')->orderBy('name');
        if ($user->hasRole(UserRole::ADMIN)) {
            $branchQuery->whereIn('id_branch', $this->visibility->accessibleBranchIds($user));
        }
        if ($filters['region'] !== '') {
            $branchQuery->where('region', $filters['region']);
        }
        if ($filters['branch'] !== '') {
            $branchQuery->where('name', $filters['branch']);
        }
        $branches = $branchQuery->get()->map(fn (Branch $branch): array => [
            'name' => $branch->name, 'region' => $branch->region ?: 'REGION BELUM TERDATA',
        ]);
        if ($user->hasRole(UserRole::SUPER_ADMIN) && $filters['region'] === '' && $filters['branch'] === '') {
            foreach ($totals->keys()->diff($branches->pluck('name')) as $name) {
                $branches->push(['name' => $name, 'region' => 'REGION BELUM TERDATA']);
            }
        }

        $rows = $branches->map(function (array $branch) use ($totals, $activity, $day, $latest): array {
            $row = $activity->get($branch['name']);
            $total = (int) ($totals->get($branch['name'])?->total_lops ?? 0);
            $moving = (int) ($row?->moving_lops ?? 0);
            $last = $row?->last_update ? $this->localTime($row->last_update) : null;

            return [
                ...$branch,
                'total_lops' => $total,
                'moving_lops' => $moving,
                'idle_lops' => max(0, $total - $moving),
                'activities' => (int) ($row?->activities ?? 0),
                'actors' => (int) ($row?->actors ?? 0),
                'moving' => $moving > 0,
                'last_update' => $last?->format('d M Y H:i'),
                'last_activity' => $latest->get($branch['name']),
                'idle_days' => $last ? (int) $last->startOfDay()->diffInDays($day) : null,
            ];
        })->sortBy([['moving', 'asc'], ['name', 'asc']])->values();

        $activeActors = DB::query()->fromSub($this->events(clone $lops, $end), 'events')
            ->where('occurred_at', '>=', $start)->distinct()->count('actor_id');
        $lastUpdate = $activity->pluck('last_update')->filter()->max();

        return [
            'date' => $day->toDateString(),
            'date_label' => $day->locale('id')->translatedFormat('l, d F Y'),
            'previous_date' => $day->subDay()->toDateString(),
            'today' => CarbonImmutable::now(self::TIMEZONE)->toDateString(),
            'last_update' => $lastUpdate ? $this->localTime($lastUpdate)->format('d M Y H:i') : null,
            'rows' => $rows,
            'regions' => $rows->pluck('region')->unique()->sort()->values(),
            'stats' => [
                'branches' => $rows->count(),
                'moving_branches' => $rows->where('moving', true)->count(),
                'idle_branches' => $rows->where('moving', false)->count(),
                'moving_lops' => (int) $rows->sum('moving_lops'),
                'activities' => (int) $rows->sum('activities'),
                'actors' => $activeActors,
            ],
        ];
    }

    public function branchLops(Builder $scopedLops, array $filters): array
    {
        [, $start, $end] = $this->dayBounds($filters['date'] ?? null);
        $lops = $this->locatedLops($scopedLops, $end);
        $events = $this->events(clone $lops, $end);
        $activity = DB::query()->fromSub($events, 'events')->select('lop_id')
            ->selectRaw('MAX(occurred_at) as last_update')
            ->selectRaw('SUM(CASE WHEN occurred_at >= ? THEN 1 ELSE 0 END) as activities', [$start])
            ->selectRaw('COUNT(DISTINCT CASE WHEN occurred_at >= ? THEN actor_id END) as actors', [$start])
            ->groupBy('lop_id');

        $query = QeLop::query()->whereIn('id_qe_lops', (clone $lops)->select('qe_lops.id_qe_lops'))
            ->leftJoinSub($activity, 'activity', 'activity.lop_id', '=', 'qe_lops.id_qe_lops')
            ->select('qe_lops.*', 'activity.last_update', 'activity.activities', 'activity.actors')
            ->with(['branchRef', 'serviceArea', 'activeAssignment.technician'])
            ->orderByRaw('CASE WHEN activity.activities > 0 THEN 0 ELSE 1 END')
            ->orderByDesc('activity.last_update')->orderBy('qe_lops.id_qe_lops');
        if (($filters['movement'] ?? '') === 'moving') {
            $query->where('activity.activities', '>', 0);
        } elseif (($filters['movement'] ?? '') === 'idle') {
            $query->whereRaw('COALESCE(activity.activities, 0) = 0');
        }
        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(fn (Builder $q) => $q->where('incident', 'like', "%{$search}%")
                ->orWhere('nama_lop', 'like', "%{$search}%"));
        }
        $page = $query->paginate(20);
        $pageLops = (clone $lops)->whereIn('qe_lops.id_qe_lops', $page->getCollection()->modelKeys());
        $latest = $this->latestEvents(DB::query()->fromSub($this->events($pageLops, $end, true), 'events'), 'events.lop_id');

        return [
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'data' => $page->getCollection()->map(fn (QeLop $lop): array => [
                'id' => $lop->id_qe_lops,
                'incident' => $lop->incident,
                'name' => $lop->nama_lop,
                'program' => $lop->program_type->label(),
                'service_area' => $lop->locationServiceAreaName(),
                'status' => $lop->status_lop->label(),
                'status_key' => $lop->status_lop->value,
                'technician' => $lop->activeAssignment?->technician?->name ?? 'Belum ditugaskan',
                'activities' => (int) ($lop->activities ?? 0),
                'actors' => (int) ($lop->actors ?? 0),
                'last_update' => $lop->last_update ? $this->localTime($lop->last_update)->format('d M Y H:i') : '—',
                'last_activity' => $latest->get($lop->getKey()),
                'activities_url' => route('dashboard.monitoring-activities', ['lopId' => $lop->getKey()], false),
                'detail_url' => route('lop.show', $lop),
            ])->values(),
        ];
    }

    public function lopActivities(Builder $scopedLops, array $filters): array
    {
        [, $start, $end] = $this->dayBounds($filters['date']);
        $lops = $this->locatedLops($scopedLops, $end);
        $page = $this->orderEvents($this->actorEvents(DB::query()->fromSub($this->events($lops, $end, true), 'events'))
            ->where('events.occurred_at', '>=', $start))->paginate(20);

        return [
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'data' => $page->getCollection()->map(fn (object $event) => $this->presentEvent($event))->values(),
        ];
    }

    /** Satu aktivitas terbaru per kelompok, dengan urutan stabil saat waktu sama. */
    private function latestEvents(QueryBuilder $events, string $group): Collection
    {
        $latest = (clone $events)->selectRaw("{$group} as group_key, MAX(events.occurred_at) as last_update")->groupBy($group);
        $candidates = (clone $events)->joinSub($latest, 'latest', fn ($join) => $join
            ->on($group, '=', 'latest.group_key')->on('events.occurred_at', '=', 'latest.last_update'));

        return $this->orderEvents($this->actorEvents($candidates)->selectRaw("{$group} as group_key"))
            ->get()->unique('group_key')->keyBy('group_key')
            ->map(fn (object $event) => $this->presentEvent($event));
    }

    private function actorEvents(QueryBuilder $events): QueryBuilder
    {
        return $events->leftJoin('users as actors', 'actors.id_user', '=', 'events.actor_id')
            ->leftJoin('roles as actor_roles', 'actor_roles.id', '=', 'actors.role_id')
            ->select('events.*', 'actors.name as actor_name', 'actor_roles.code as actor_role_code');
    }

    private function orderEvents(QueryBuilder $events): QueryBuilder
    {
        // Saat waktu hanya sampai detik, keputusan review didahulukan dari upload.
        return $events->orderByDesc('events.occurred_at')
            ->orderByRaw("CASE events.source WHEN 'lop_history' THEN 5 WHEN 'evidence_review' THEN 4 WHEN 'boq_history' THEN 3 WHEN 'evidence_upload' THEN 2 ELSE 1 END DESC")
            ->orderByDesc('events.source_id');
    }

    private function presentEvent(object $event): array
    {
        $category = EvidenceCategory::tryFrom((string) $event->category)?->label() ?? 'Evidence';
        $label = match ($event->source) {
            'evidence_upload' => 'Upload '.$category,
            'evidence_review' => match ($event->event_type) {
                'approved' => 'Approve '.$category,
                'rejected' => 'Reject '.$category,
                default => 'Review '.$category,
            },
            'boq_history' => match ($event->event_type) {
                'created' => 'Input / Import BOQ', 'updated' => 'Update BOQ', 'deleted' => 'Hapus BOQ',
                default => 'Aktivitas BOQ',
            },
            default => match ($event->event_type) {
                'created' => 'LOP dibuat', 'reassigned' => 'Reassign Teknisi', 'deleted' => 'LOP dihapus',
                'status_change', 'status_changed' => 'Status → '.(LopStatus::tryFrom((string) $event->status_key)?->label() ?? 'Diperbarui'),
                default => 'Aktivitas LOP',
            },
        };
        $name = $event->actor_name ?? ($event->actor_id ? 'Pengguna tidak tersedia' : 'Sistem');
        $role = UserRole::tryFrom((string) $event->actor_role_code)?->label();

        return [
            'key' => $event->source.'-'.$event->source_id,
            'time' => $this->localTime($event->occurred_at)->format('d M Y H:i'),
            'label' => $label,
            'note' => $event->note,
            'actor_name' => $name,
            'actor_role' => $role,
            'actor_label' => $name.($role ? ' — '.$role : ''),
        ];
    }

    /** Lokasi memakai FK branch, dengan fallback untuk data lama. */
    private function locatedLops(Builder $query, string $end): Builder
    {
        return (clone $query)->leftJoin('branches as monitoring_branches', 'monitoring_branches.id_branch', '=', 'qe_lops.branch_id')
            ->where('qe_lops.created_at', '<', $end)
            ->select('qe_lops.id_qe_lops')
            ->selectRaw("COALESCE(monitoring_branches.name, NULLIF(qe_lops.branch, ''), 'BRANCH BELUM TERDATA') as monitoring_branch");
    }

    /**
     * UNION ALL mempertahankan setiap aktivitas, tetapi pembuatan LOP dihitung
     * sekali: gunakan audit created bila ada, atau tanggal record untuk import lama.
     * Tidak memakai updated_at sebagai aktivitas karena tidak mencatat aktor/event.
     */
    private function events(Builder $lops, string $end, bool $details = false): QueryBuilder
    {
        $ids = (clone $lops)->select('qe_lops.id_qe_lops');
        $history = DB::table('qe_lop_histories')->selectRaw("qe_lop_id as lop_id, user_id as actor_id, created_at as occurred_at, 'lop_history' as source, id_qe_lop_histories as source_id, event_type, note, status_after as status_key, NULL as category")
            ->whereIn('qe_lop_id', clone $ids)->where('created_at', '<', $end);
        $created = DB::table('qe_lops')->selectRaw("id_qe_lops as lop_id, created_by as actor_id, created_at as occurred_at, 'lop_created' as source, id_qe_lops as source_id, 'created' as event_type, NULL as note, NULL as status_key, NULL as category")
            ->whereIn('id_qe_lops', clone $ids)->where('created_at', '<', $end)
            ->whereNotExists(fn (QueryBuilder $q) => $q->selectRaw('1')->from('qe_lop_histories')
                ->whereColumn('qe_lop_histories.qe_lop_id', 'qe_lops.id_qe_lops')->where('event_type', 'created')->where('created_at', '<', $end));
        $uploads = DB::table('qe_evidences')->selectRaw("qe_lop_id as lop_id, uploaded_by as actor_id, created_at as occurred_at, 'evidence_upload' as source, id_evidence as source_id, 'upload' as event_type, note, NULL as status_key, category")
            ->whereIn('qe_lop_id', clone $ids)->where('created_at', '<', $end);
        $reviews = DB::table('qe_evidences')->selectRaw("qe_lop_id as lop_id, reviewed_by as actor_id, reviewed_at as occurred_at, 'evidence_review' as source, id_evidence as source_id, status as event_type, review_note as note, NULL as status_key, category")
            ->whereIn('qe_lop_id', clone $ids)->whereNotNull('reviewed_at')->where('reviewed_at', '<', $end);
        $boq = DB::table('qe_boq_histories as histories')->join('qe_boqs as boqs', 'boqs.id_boq', '=', 'histories.qe_boq_id')
            ->selectRaw("boqs.qe_lop_id as lop_id, histories.user_id as actor_id, histories.created_at as occurred_at, 'boq_history' as source, histories.id_boq_history as source_id, histories.event_type, histories.note, NULL as status_key, NULL as category")
            ->whereIn('boqs.qe_lop_id', clone $ids)->where('histories.created_at', '<', $end);

        if (! $details) {
            // Agregat tidak membawa teks catatan/identitas ke UNION agar tetap ringan.
            $history->select([])->selectRaw('qe_lop_id as lop_id, user_id as actor_id, created_at as occurred_at');
            $created->select([])->selectRaw('id_qe_lops as lop_id, created_by as actor_id, created_at as occurred_at');
            $uploads->select([])->selectRaw('qe_lop_id as lop_id, uploaded_by as actor_id, created_at as occurred_at');
            $reviews->select([])->selectRaw('qe_lop_id as lop_id, reviewed_by as actor_id, reviewed_at as occurred_at');
            $boq->select([])->selectRaw('boqs.qe_lop_id as lop_id, histories.user_id as actor_id, histories.created_at as occurred_at');
        }

        return $history->unionAll($created)->unionAll($uploads)->unionAll($reviews)->unionAll($boq);
    }

    private function dayBounds(?string $date): array
    {
        $day = $date ? CarbonImmutable::createFromFormat('!Y-m-d', $date, self::TIMEZONE) : CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        $timezone = config('app.timezone', 'UTC');

        return [$day, $day->setTimezone($timezone)->toDateTimeString(), $day->addDay()->setTimezone($timezone)->toDateTimeString()];
    }

    private function localTime(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, config('app.timezone', 'UTC'))->setTimezone(self::TIMEZONE);
    }
}
