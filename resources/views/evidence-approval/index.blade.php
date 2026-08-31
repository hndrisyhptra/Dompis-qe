@extends('layouts.app')

@section('title', 'Approval Evidence')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <header class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.16em] text-brand-600 dark:text-brand-400">Quality assurance</p>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-900 dark:text-white">Approval Evidence per LOP</h1>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">{{ $isSuperAdmin ? 'Monitoring seluruh LOP lintas region dan branch.' : 'Menampilkan LOP yang Anda assign kepada teknisi.' }}</p>
        </div>
        <div class="inline-flex w-fit items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-bold text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300"><span class="h-2 w-2 {{ $stats['evidence_pending'] ? 'animate-pulse' : '' }} rounded-full bg-amber-500"></span>{{ $stats['evidence_pending'] }} evidence menunggu review</div>
    </header>

    <section class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        @foreach ([
            ['LOP Perlu Review', $stats['lop_pending'], 'warning', route('evidence-approval.index')],
            ['Evidence Pending', $stats['evidence_pending'], 'info', route('evidence-approval.index', ['status' => 'pending'])],
            ['LOP Approve 100%', $stats['lop_approved'], 'success', route('evidence-approval.index', ['status' => 'approved'])],
            ['Evidence Reject', $stats['evidence_rejected'], 'danger', route('evidence-approval.index', ['status' => 'rejected'])],
        ] as [$label, $value, $variant, $url])
            @php
                $tone = match ($variant) {
                    'warning' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-300',
                    'success' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-300',
                    'danger' => 'bg-brand-50 text-brand-600 dark:bg-brand-900/20 dark:text-brand-300',
                    default => 'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-300',
                };
            @endphp
            <a href="{{ $url }}" class="rounded-2xl border border-ink-100 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-ink-800 dark:bg-ink-900">
                <div class="flex items-center justify-between"><span class="grid h-10 w-10 place-items-center rounded-xl {{ $tone }}"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623"/></svg></span><strong class="text-2xl text-ink-900 dark:text-white">{{ $value }}</strong></div>
                <p class="mt-3 text-xs font-semibold text-ink-500">{{ $label }}</p>
            </a>
        @endforeach
    </section>

    <section class="rounded-2xl border border-ink-100 bg-white p-4 shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <form method="GET" action="{{ route('evidence-approval.index') }}" class="space-y-3">
            <div class="grid gap-3 lg:grid-cols-[minmax(260px,1fr)_180px_180px_auto]">
                <div class="relative"><svg class="absolute left-3.5 top-3 h-4 w-4 text-ink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z"/></svg><input name="q" value="{{ $search }}" placeholder="Cari LOP, teknisi, STO, atau designator..." class="min-h-10 w-full rounded-xl border border-ink-200 bg-ink-50 pl-10 pr-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800"></div>
                <select name="status" class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm dark:border-ink-700 dark:bg-ink-800"><option value="all" @selected($statusFilter === 'all')>Semua evidence</option>@foreach (\App\Enums\EvidenceStatus::cases() as $item)<option value="{{ $item->value }}" @selected($statusFilter === $item->value)>{{ $item->label() }}</option>@endforeach</select>
                <select name="step" class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm dark:border-ink-700 dark:bg-ink-800"><option value="">Semua step evidence</option>@foreach (\App\Enums\EvidenceStep::cases() as $item)<option value="{{ $item->value }}" @selected($step === $item->value)>{{ $item->label() }}</option>@endforeach</select>
                <div class="flex gap-2"><button class="min-h-10 rounded-xl bg-ink-900 px-4 text-sm font-bold text-white dark:bg-brand-600">Terapkan</button><a href="{{ route('evidence-approval.index') }}" class="grid min-h-10 place-items-center rounded-xl border border-ink-200 px-3 text-sm font-bold text-ink-500 dark:border-ink-700">Reset</a></div>
            </div>

            @if ($isSuperAdmin)
                <div class="grid gap-3 border-t border-ink-100 pt-3 dark:border-ink-800 sm:grid-cols-2 xl:grid-cols-4">
                    <select name="region" onchange="this.form.submit()" class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm dark:border-ink-700 dark:bg-ink-800"><option value="">Semua Region</option>@foreach($regions as $region)<option value="{{ $region }}" @selected($regionFilter === $region)>{{ $region }}</option>@endforeach</select>
                    <select name="branch" class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm dark:border-ink-700 dark:bg-ink-800"><option value="">Semua Branch</option>@foreach($branches->when($regionFilter, fn ($items) => $items->where('region', $regionFilter)) as $branch)<option value="{{ $branch->name }}" @selected($branchFilter === $branch->name)>{{ $branch->name }}</option>@endforeach</select>
                    <select name="wbs" class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm dark:border-ink-700 dark:bg-ink-800"><option value="">Semua WBS</option>@foreach(\App\Enums\WbsType::cases() as $item)<option value="{{ $item->value }}" @selected($wbsFilter === $item->value)>{{ $item->label() }}</option>@endforeach</select>
                    <select name="lop_status" class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm dark:border-ink-700 dark:bg-ink-800"><option value="">Semua Status LOP</option>@foreach(\App\Enums\LopStatus::cases() as $item)<option value="{{ $item->value }}" @selected($lopStatusFilter === $item->value)>{{ $item->label() }}</option>@endforeach</select>
                </div>
            @endif
        </form>
    </section>

    <x-table class="!rounded-2xl shadow-sm">
        <thead class="bg-ink-50/80 dark:bg-ink-800"><tr>
            @foreach (['LOP / Project', 'Teknisi', 'Evidence', 'Progress Approval', 'Status LOP'] as $heading)<th class="px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wider text-ink-500">{{ $heading }}</th>@endforeach
            <th class="px-5 py-3.5 text-right text-[11px] font-bold uppercase tracking-wider text-ink-500">Aksi</th>
        </tr></thead>
        <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
            @forelse ($lops as $lop)
                @php($approval = $lop->approval_summary)
                <tr class="transition hover:bg-ink-50/70 dark:hover:bg-ink-800/40">
                    <td class="px-5 py-4"><p class="text-xs font-extrabold uppercase tracking-wide text-brand-600">{{ $lop->incident }}</p><p class="mt-1 max-w-xs text-sm font-bold text-ink-900 dark:text-white">{{ $lop->nama_lop }}</p><p class="mt-1 text-xs text-ink-400">{{ $lop->sto ?: 'STO —' }} · {{ $lop->branch ?: 'Branch —' }} · {{ $lop->wbs_type->label() }}</p></td>
                    <td class="px-5 py-4"><div class="flex items-center gap-2.5"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-ink-100 text-xs font-extrabold dark:bg-ink-800">{{ strtoupper(substr($lop->activeAssignment?->technician?->name ?? '?', 0, 1)) }}</span><div><p class="whitespace-nowrap text-sm font-semibold">{{ $lop->activeAssignment?->technician?->name ?? 'Belum ditugaskan' }}</p><p class="mt-1 text-[10px] text-ink-400">{{ $isSuperAdmin ? 'Assigned oleh '.($lop->activeAssignment?->assigner?->name ?? '—') : 'Assignment Anda' }}</p></div></div></td>
                    <td class="px-5 py-4"><p class="text-sm font-extrabold">{{ $approval['total'] }} file</p><div class="mt-2 flex flex-wrap gap-1">@if($approval['pending'])<x-badge variant="warning">{{ $approval['pending'] }} pending</x-badge>@endif @if($approval['approved'])<x-badge variant="success">{{ $approval['approved'] }} approve</x-badge>@endif @if($approval['rejected'])<x-badge variant="danger">{{ $approval['rejected'] }} reject</x-badge>@endif</div></td>
                    <td class="min-w-56 px-5 py-4"><div class="mb-2 flex justify-between text-xs"><span class="text-ink-500">Evidence disetujui</span><strong>{{ $approval['approval_percentage'] }}%</strong></div><div class="h-2 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full rounded-full {{ $approval['rejected'] ? 'bg-brand-600' : ($approval['approval_percentage'] === 100 ? 'bg-emerald-500' : 'bg-amber-400') }}" style="width: {{ $approval['approval_percentage'] }}%"></div></div><p class="mt-1.5 text-[10px] text-ink-400">{{ $approval['approved'] }} dari {{ $approval['total'] }} evidence approve · {{ $approval['review_percentage'] }}% sudah direview</p></td>
                    <td class="px-5 py-4"><x-badge :variant="$lop->status_lop->badgeVariant()">{{ $lop->status_lop->label() }}</x-badge></td>
                    <td class="px-5 py-4 text-right"><a href="{{ route('evidence-approval.lop.review', $lop) }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-extrabold text-white shadow-lg shadow-brand-600/15"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Review Evidence</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-14 text-center"><div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-emerald-50 text-emerald-600">✓</div><p class="mt-3 text-sm font-bold">Tidak ada LOP untuk direview</p><p class="mt-1 text-xs text-ink-400">Tidak ada data yang sesuai dengan scope dan filter saat ini.</p></td></tr>
            @endforelse
        </tbody>
    </x-table>
    <div>{{ $lops->links() }}</div>
</div>
@endsection
