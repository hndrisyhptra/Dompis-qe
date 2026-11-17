@extends('layouts.app')

@section('title', 'Approval Evidence')

@section('content')
<div class="mx-auto max-w-screen-2xl space-y-6">
    <header class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.14em] text-brand-800 dark:text-brand-300">Quality assurance <span class="px-1 text-brand-500/60 dark:text-brand-400/60">/</span> antrean review</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-ink-900 dark:text-white">Approval Evidence</h1>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">{{ $isSuperAdmin ? 'Tinjau evidence LOP lintas region dan branch.' : 'Tinjau evidence dari LOP yang ditugaskan kepada teknisi.' }}</p>
        </div>
    </header>

    <section aria-label="Ringkasan antrean review" class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        @foreach ([
            ['LOP Perlu Review', $stats['lop_pending'], route('evidence-approval.index')],
            ['Evidence Pending', $stats['evidence_pending'], route('evidence-approval.index', ['status' => 'pending'])],
            ['LOP Approve 100%', $stats['lop_approved'], route('evidence-approval.index', ['status' => 'approved'])],
            ['Evidence Reject', $stats['evidence_rejected'], route('evidence-approval.index', ['status' => 'rejected'])],
        ] as [$label, $value, $url])
            <a href="{{ $url }}" class="group rounded-lg border border-ink-200 bg-white px-4 py-3.5 transition-colors hover:bg-ink-50 focus-visible:outline focus-visible:outline-offset-2 focus-visible:outline-brand-700 dark:border-ink-700 dark:bg-ink-900 dark:hover:bg-ink-800/70">
                <div class="flex items-center justify-between gap-3"><p class="text-xs font-semibold text-ink-500 dark:text-ink-400">{{ $label }}</p><svg aria-hidden="true" class="h-3.5 w-3.5 shrink-0 text-ink-300 transition-transform group-hover:translate-x-0.5 group-hover:text-ink-500 dark:text-ink-600 dark:group-hover:text-ink-300" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.69L10.22 5.03a.75.75 0 1 1 1.06-1.06l5.5 5.5a.75.75 0 0 1 0 1.06l-5.5 5.5a.75.75 0 1 1-1.06-1.06l4.22-4.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg></div>
                <strong class="mt-2 block text-2xl font-semibold tabular-nums tracking-tight text-ink-900 dark:text-white">{{ number_format($value) }}</strong>
            </a>
        @endforeach
    </section>

    <section class="rounded-xl border border-ink-200 bg-white p-4 dark:border-ink-700 dark:bg-ink-900">
        <form method="GET" action="{{ route('evidence-approval.index') }}" class="space-y-3">
            <div class="grid gap-3 lg:grid-cols-[minmax(280px,1fr)_190px_190px_auto]">
                <div class="relative"><svg class="absolute left-3.5 top-3 h-4 w-4 text-ink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z"/></svg><input name="q" value="{{ $search }}" placeholder="Cari LOP, teknisi, STO, atau designator..." class="min-h-10 w-full rounded-xl border border-ink-200 bg-ink-50 pl-10 pr-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800"></div>
                <select name="status" class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm dark:border-ink-700 dark:bg-ink-800"><option value="all" @selected($statusFilter === 'all')>Semua evidence</option>@foreach (\App\Enums\EvidenceStatus::cases() as $item)<option value="{{ $item->value }}" @selected($statusFilter === $item->value)>{{ $item->label() }}</option>@endforeach</select>
                <select name="step" class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm dark:border-ink-700 dark:bg-ink-800"><option value="">Semua step evidence</option>@foreach (\App\Enums\EvidenceStep::cases() as $item)<option value="{{ $item->value }}" @selected($step === $item->value)>{{ $item->label() }}</option>@endforeach</select>
                <div class="flex gap-2"><button class="min-h-10 rounded-lg bg-ink-900 px-4 text-sm font-semibold text-white hover:bg-ink-800 dark:bg-brand-600 dark:hover:bg-brand-500">Terapkan</button><a href="{{ route('evidence-approval.index') }}" class="grid min-h-10 place-items-center rounded-lg border border-ink-200 px-3 text-sm font-semibold text-ink-600 hover:bg-ink-50 dark:border-ink-700 dark:text-ink-300 dark:hover:bg-ink-800">Reset</a></div>
            </div>

            @if ($isSuperAdmin)
                <div class="grid gap-3 border-t border-ink-100 pt-3 dark:border-ink-800 sm:grid-cols-2 xl:grid-cols-4">
                    <select name="region" onchange="this.form.submit()" class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm dark:border-ink-700 dark:bg-ink-800"><option value="">Semua Region</option>@foreach($regions as $region)<option value="{{ $region }}" @selected($regionFilter === $region)>{{ $region }}</option>@endforeach</select>
                    <select name="branch" class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm dark:border-ink-700 dark:bg-ink-800"><option value="">Semua Branch</option>@foreach($branches->when($regionFilter, fn ($items) => $items->where('region', $regionFilter)) as $branch)<option value="{{ $branch->name }}" @selected($branchFilter === $branch->name)>{{ $branch->name }}</option>@endforeach</select>
                    <select name="program" class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm dark:border-ink-700 dark:bg-ink-800"><option value="">Semua Program</option>@foreach(\App\Enums\ProgramType::cases() as $item)<option value="{{ $item->value }}" @selected($programFilter === $item->value)>{{ $item->label() }}</option>@endforeach</select>
                    <select name="lop_status" class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm dark:border-ink-700 dark:bg-ink-800"><option value="">Semua Status LOP</option>@foreach(\App\Enums\LopStatus::cases() as $item)<option value="{{ $item->value }}" @selected($lopStatusFilter === $item->value)>{{ $item->label() }}</option>@endforeach</select>
                </div>
            @endif
        </form>
    </section>

    <x-table class="rounded-xl! [&>table]:w-full [&>table]:min-w-300 [&>table]:table-fixed">
        <colgroup>
            <col style="width: 30%">
            <col style="width: 14%">
            <col style="width: 14%">
            <col style="width: 14%">
            <col style="width: 14%">
            <col style="width: 14%">
        </colgroup>
        <thead class="bg-ink-50 dark:bg-ink-800"><tr>
            @foreach (['LOP / Project', 'Teknisi', 'Ringkasan Evidence', 'Progress Approval', 'Status LOP', 'Aksi'] as $heading)
                <th class="px-5 py-3.5 text-center text-[11px] font-bold uppercase tracking-wider text-ink-600 dark:text-ink-300 {{ $loop->first ? '' : 'border-l border-ink-200 dark:border-ink-700' }}">{{ $heading }}</th>
            @endforeach
        </tr></thead>
        <tbody class="divide-y divide-ink-100 dark:divide-ink-800 [&>tr>td+td]:border-l [&>tr>td+td]:border-ink-200 dark:[&>tr>td+td]:border-ink-700 [&>tr>td:last-child]:text-center">
            @forelse ($lops as $lop)
                @php($approval = $lop->approval_summary)
                @php($reuploaded = (int) ($lop->reuploaded_pending_count ?? 0))
                <tr class="transition-colors hover:bg-ink-50/70 dark:hover:bg-ink-800/40">
                    <td class="px-5 py-4">
                        <div class="flex min-w-0 items-center gap-2">
                            <p class="truncate text-xs font-bold uppercase tracking-wide text-brand-700 dark:text-brand-300">{{ $lop->incident }}</p>
                            @if ($reuploaded > 0)
                                <span role="status" aria-label="{{ $reuploaded }} evidence diperbaiki teknisi dan perlu review ulang" title="{{ $reuploaded }} evidence diperbaiki teknisi - perlu review ulang" class="inline-flex h-4 shrink-0 items-center gap-1 text-[11px] font-semibold leading-none text-amber-800 dark:text-amber-300">
                                    <span aria-hidden="true" class="relative grid h-1.5 w-1.5 shrink-0 place-items-center"><span class="absolute h-full w-full animate-ping rounded-full bg-amber-500/50 motion-reduce:animate-none"></span><span class="relative h-1.5 w-1.5 rounded-full bg-amber-600 dark:bg-amber-400"></span></span>
                                    <svg aria-hidden="true" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.02 9.35h5.25V4.1M20.1 8.1A9 9 0 1 0 21 12"/></svg>
                                    Review ulang - {{ $reuploaded }} evidence
                                </span>
                            @endif
                        </div>
                        <p class="mt-1 max-w-xs truncate text-sm font-semibold text-ink-900 dark:text-white" title="{{ $lop->nama_lop }}">{{ $lop->nama_lop }}</p>
                        <p class="mt-1 truncate text-xs text-ink-400" title="{{ $lop->sto ?: 'STO —' }} · {{ $lop->branch ?: 'Branch —' }} · {{ $lop->program_type->label() }}">{{ $lop->sto ?: 'STO —' }} · {{ $lop->branch ?: 'Branch —' }} · {{ $lop->program_type->label() }}</p>
                    </td>
                    <td class="px-5 py-4"><div class="flex items-center gap-2.5"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-full border border-ink-200 bg-ink-50 text-xs font-bold text-ink-600 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-300">{{ strtoupper(substr($lop->activeAssignment?->technician?->name ?? '?', 0, 1)) }}</span><div><p class="whitespace-nowrap text-sm font-medium">{{ $lop->activeAssignment?->technician?->name ?? 'Belum ditugaskan' }}</p><p class="mt-1 text-[10px] text-ink-400">{{ $isSuperAdmin ? 'Assigned oleh '.($lop->activeAssignment?->assigner?->name ?? '—') : 'Assignment Anda' }}</p></div></div></td>
                    <td class="px-5 py-4">
                        <div class="flex items-baseline justify-between gap-2 border-b border-ink-100 pb-2 dark:border-ink-800">
                            <span class="text-xs font-medium text-ink-500 dark:text-ink-400">Total evidence</span>
                            <strong class="text-sm font-semibold tabular-nums text-ink-900 dark:text-white">{{ $approval['total'] }}</strong>
                        </div>
                        <dl class="mt-2 space-y-1.5 text-[11px] leading-4">
                            @if($approval['pending'])<div class="flex items-center justify-between gap-2"><dt class="flex min-w-0 items-center gap-1.5 text-ink-600 dark:text-ink-300"><span class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"></span><span>Perlu review</span></dt><dd class="font-semibold tabular-nums text-ink-800 dark:text-ink-100">{{ $approval['pending'] }}</dd></div>@endif
                            @if($approval['approved'])<div class="flex items-center justify-between gap-2"><dt class="flex min-w-0 items-center gap-1.5 text-ink-600 dark:text-ink-300"><span class="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-600"></span><span>Disetujui</span></dt><dd class="font-semibold tabular-nums text-ink-800 dark:text-ink-100">{{ $approval['approved'] }}</dd></div>@endif
                            @if($approval['rejected'])<div class="flex items-center justify-between gap-2"><dt class="flex min-w-0 items-center gap-1.5 text-ink-600 dark:text-ink-300"><span class="h-1.5 w-1.5 shrink-0 rounded-full bg-brand-700"></span><span>Ditolak</span></dt><dd class="font-semibold tabular-nums text-brand-800 dark:text-brand-300">{{ $approval['rejected'] }}</dd></div>@endif
                        </dl>
                    </td>
                    <td class="px-5 py-4"><div class="mb-2 flex justify-between text-xs"><span class="text-ink-500">Disetujui</span><strong class="tabular-nums">{{ $approval['approval_percentage'] }}%</strong></div><div class="h-1.5 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full rounded-full {{ $approval['rejected'] ? 'bg-brand-700' : ($approval['approval_percentage'] === 100 ? 'bg-emerald-600' : 'bg-amber-500') }}" style="width: {{ $approval['approval_percentage'] }}%"></div></div><p class="mt-1.5 text-[10px] text-ink-500 dark:text-ink-400">{{ $approval['approved'] }} dari {{ $approval['total'] }} disetujui · {{ $approval['review_percentage'] }}% sudah diperiksa</p></td>
                    <td class="px-5 py-4 text-center"><x-lop-status-badge :status="$lop->status_lop" /></td>
                    <td class="px-5 py-4 text-right"><a href="{{ route('evidence-approval.lop.review', [$lop, 'return_url' => request()->fullUrl()]) }}" class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-ink-900 px-3.5 text-xs font-semibold text-white transition hover:bg-ink-700 focus-visible:outline focus-visible:outline-offset-2 focus-visible:outline-brand-600 dark:bg-brand-600 dark:hover:bg-brand-500"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12z"/><circle cx="12" cy="12" r="2.25"/></svg>Mulai review</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-14 text-center"><div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-emerald-50 text-emerald-600">✓</div><p class="mt-3 text-sm font-bold">Tidak ada LOP untuk direview</p><p class="mt-1 text-xs text-ink-400">Tidak ada data yang sesuai dengan scope dan filter saat ini.</p></td></tr>
            @endforelse
        </tbody>
    </x-table>
    <div>{{ $lops->links() }}</div>
</div>
@endsection
