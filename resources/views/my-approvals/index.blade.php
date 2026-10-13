@extends('layouts.app')

@section('title', 'Approval Saya')

@section('content')
@php
    $tabs = [
        'actionable' => ['Perlu tindakan', $stats['actionable_lops']],
        'pending' => ['Pending', $stats['pending_lops']],
        'ready' => ['Siap diselesaikan', $stats['ready_lops']],
        'rejected' => ['Perbaikan', $stats['rejected_lops']],
        'completed' => ['Completed', $stats['completed_lops']],
        'empty' => ['Belum ada evidence', $stats['empty_lops']],
        'all' => ['Semua', $stats['total_lops']],
    ];
    $carry = array_filter(['q' => $search, 'program' => $programFilter, 'lop_status' => $lopStatusFilter]);
    $metrics = [
        ['Total LOP Saya', $stats['total_lops'], 'all', 'Penugasan aktif oleh Anda', 'text-ink-900 dark:text-white'],
        ['Perlu Review', $stats['pending_lops'], 'pending', $stats['pending_evidences'].' evidence pending', 'text-amber-600 dark:text-amber-400'],
        ['Siap Diselesaikan', $stats['ready_lops'], 'ready', 'Semua evidence approved; tutup review', 'text-blue-600 dark:text-blue-400'],
        ['Menunggu Perbaikan', $stats['rejected_lops'], 'rejected', 'Ada evidence rejected dari teknisi', 'text-red-600 dark:text-red-400'],
        ['Completed', $stats['completed_lops'], 'completed', 'Review dan pekerjaan selesai', 'text-emerald-600 dark:text-emerald-400'],
    ];
@endphp
<div class="mx-auto max-w-7xl space-y-5">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.16em] text-brand-600">Inbox · Monitoring Approval</p>
            <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-ink-900 dark:text-white">Approval Saya</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-ink-500 dark:text-ink-400">Pantau evidence pada LOP dengan penugasan aktif yang Anda buat, sesuai scope lokasi akun. Dahulukan pending terlama, lalu selesaikan review yang sudah approved.</p>
        </div>
        <a href="{{ route('lop.my-approvals', ['tab' => $tab, ...$carry]) }}" title="Muat ulang monitoring approval" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-md border border-ink-200 bg-white px-4 text-xs font-bold text-ink-600 hover:border-brand-300 hover:text-brand-700 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-300">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10a9 9 0 1 1 2 8M3 4v6h6"/></svg>Perbarui
        </a>
    </header>

    <section class="grid grid-cols-2 gap-3 xl:grid-cols-5" aria-label="Ringkasan semua LOP penugasan saya">
        @foreach ($metrics as [$label, $value, $metricTab, $description, $color])
            <a href="{{ route('lop.my-approvals', ['tab' => $metricTab]) }}" class="rounded-md border border-ink-200 bg-white p-4 shadow-sm transition hover:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-900">
                <p class="text-[10px] font-extrabold uppercase tracking-wide text-ink-500">{{ $label }}</p>
                <p class="mt-2 text-2xl font-extrabold tabular-nums {{ $color }}">{{ number_format($value, 0, ',', '.') }}</p>
                <p class="mt-2 text-[11px] leading-5 text-ink-500 dark:text-ink-400">{{ $description }}</p>
            </a>
        @endforeach
    </section>

    <section class="rounded-md border border-ink-200 bg-white p-4 shadow-sm dark:border-ink-700 dark:bg-ink-900">
        <nav class="mb-4 flex gap-1.5 overflow-x-auto pb-1" aria-label="Filter monitoring approval">
            @foreach ($tabs as $tabKey => [$label, $count])
                <a href="{{ route('lop.my-approvals', ['tab' => $tabKey, ...$carry]) }}" @if ($tab === $tabKey) aria-current="page" @endif class="inline-flex min-h-9 shrink-0 items-center gap-2 rounded-md border px-3 text-xs font-bold transition {{ $tab === $tabKey ? 'border-brand-200 bg-brand-50 text-brand-700 dark:border-brand-900 dark:bg-brand-950/30 dark:text-brand-300' : 'border-ink-200 text-ink-500 hover:bg-ink-50 dark:border-ink-700 dark:hover:bg-ink-800' }}">
                    {{ $label }}<span class="rounded bg-white/70 px-1.5 py-0.5 text-[10px] tabular-nums dark:bg-ink-800">{{ $count }}</span>
                </a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('lop.my-approvals') }}" class="grid gap-3 md:grid-cols-[minmax(220px,1fr)_180px_180px_auto]">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label class="block text-[10px] font-bold uppercase tracking-wide text-ink-500">Pencarian
                <input type="search" name="q" maxlength="200" value="{{ $search }}" placeholder="Incident, nama LOP, teknisi, Branch / STO" class="mt-1.5 min-h-10 w-full rounded-md border border-ink-200 bg-white px-3 text-sm font-normal normal-case tracking-normal outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-950">
            </label>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-ink-500">Program
                <select name="program" class="mt-1.5 min-h-10 w-full rounded-md border border-ink-200 bg-white px-3 text-xs font-normal normal-case tracking-normal outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-950"><option value="">Semua program</option>@foreach ($programs as $program)<option value="{{ $program->value }}" @selected($programFilter === $program->value)>{{ $program->label() }}</option>@endforeach</select>
            </label>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-ink-500">Status LOP
                <select name="lop_status" class="mt-1.5 min-h-10 w-full rounded-md border border-ink-200 bg-white px-3 text-xs font-normal normal-case tracking-normal outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-950"><option value="">Semua status</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected($lopStatusFilter === $status->value)>{{ $status->label() }}</option>@endforeach</select>
            </label>
            <div class="flex items-end gap-2"><button class="min-h-10 rounded-md bg-ink-900 px-4 text-xs font-bold text-white dark:bg-brand-600">Terapkan</button><a href="{{ route('lop.my-approvals') }}" class="grid min-h-10 place-items-center rounded-md border border-ink-200 px-3 text-xs font-bold text-ink-500 dark:border-ink-700">Reset</a></div>
        </form>
        @if ($errors->any())<p role="alert" class="mt-3 text-xs font-semibold text-red-600 dark:text-red-400">{{ $errors->first() }}</p>@endif
    </section>

    <section class="overflow-hidden rounded-md border border-ink-200 bg-white shadow-sm dark:border-ink-700 dark:bg-ink-900">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ink-100 px-5 py-4 dark:border-ink-800"><div><h2 class="text-sm font-extrabold">{{ $tabs[$tab][0] }}</h2><p class="mt-1 text-xs text-ink-500">{{ number_format($lops->total(), 0, ',', '.') }} LOP sesuai filter · Pending terlama di atas</p></div><p class="text-[10px] text-ink-400">Surat Permintaan tidak dihitung sebagai evidence approval.</p></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-ink-100 text-sm dark:divide-ink-800">
                <thead class="bg-ink-50 text-left text-[10px] uppercase tracking-wide text-ink-500 dark:bg-ink-950/40"><tr><th class="px-5 py-3">LOP & Lokasi</th><th class="px-4 py-3">Teknisi</th><th class="px-4 py-3">Status LOP</th><th class="px-4 py-3">Evidence</th><th class="px-4 py-3">Approval</th><th class="px-4 py-3">Waktu</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                    @forelse ($lops as $lop)
                        @php
                            $summary = $lop->approval_summary;
                            $pendingSince = $lop->pending_since ? \Illuminate\Support\Carbon::parse($lop->pending_since) : null;
                            $lastUpload = $lop->last_evidence_at ? \Illuminate\Support\Carbon::parse($lop->last_evidence_at) : null;
                            $ready = $lop->approval_ready;
                            $reviewParams = ['qe_lop' => $lop, 'return_url' => request()->fullUrl()];
                            if ($lop->review_step !== null) { $reviewParams['step'] = $lop->review_step; }
                        @endphp
                        <tr class="align-top transition hover:bg-ink-50/60 dark:hover:bg-ink-800/30">
                            <td class="min-w-56 px-5 py-4"><p class="text-xs font-extrabold text-ink-900 dark:text-white">{{ $lop->incident }}</p><p class="mt-1 max-w-xs text-xs leading-5 text-ink-600 dark:text-ink-300">{{ $lop->nama_lop }}</p><p class="mt-2 text-[10px] font-semibold text-ink-500">{{ $lop->locationBranchName() }} · {{ $lop->locationServiceAreaName() }}</p><p class="mt-1 text-[10px] text-ink-400">{{ $lop->program_type->label() }}</p></td>
                            <td class="min-w-36 px-4 py-4"><p class="text-xs font-bold">{{ $lop->activeAssignment?->technician?->name ?? 'Teknisi tidak tersedia' }}</p><p class="mt-1 text-[10px] text-ink-400">Ditugaskan {{ $lop->activeAssignment?->assigned_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '—' }}</p></td>
                            <td class="whitespace-nowrap px-4 py-4"><x-badge :variant="$lop->status_lop->badgeVariant()">{{ $lop->status_lop->label() }}</x-badge>@if ($ready)<p class="mt-2 text-[10px] font-bold text-blue-600 dark:text-blue-400">Siap diselesaikan</p>@endif</td>
                            <td class="min-w-44 px-4 py-4"><p class="mb-2 text-[10px] font-semibold text-ink-500">{{ $summary['total'] }} evidence</p><div class="flex flex-wrap gap-1"><x-badge variant="warning">{{ $summary['pending'] }} pending</x-badge><x-badge variant="success">{{ $summary['approved'] }} approved</x-badge>@if ($summary['rejected'])<x-badge variant="danger">{{ $summary['rejected'] }} rejected</x-badge>@endif</div></td>
                            <td class="min-w-32 px-4 py-4"><div class="flex justify-between text-xs"><strong class="tabular-nums">{{ $summary['approval_percentage'] }}%</strong><span class="text-[10px] text-ink-400">{{ $summary['approved'] }}/{{ $summary['total'] }}</span></div><div role="progressbar" aria-label="Persentase evidence approved" aria-valuenow="{{ $summary['approval_percentage'] }}" aria-valuemin="0" aria-valuemax="100" class="mt-2 h-1.5 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full {{ $summary['rejected'] ? 'bg-red-500' : ($summary['approval_percentage'] === 100 ? 'bg-emerald-500' : 'bg-amber-400') }}" style="width: {{ $summary['approval_percentage'] }}%"></div></div><p class="mt-2 text-[10px] text-ink-400">{{ $summary['review_percentage'] }}% sudah direview</p></td>
                            <td class="min-w-40 px-4 py-4 text-[10px] leading-5">@if ($pendingSince && $lop->status_lop !== \App\Enums\LopStatus::COMPLETED)<p class="font-semibold text-amber-600 dark:text-amber-400">Pending {{ $pendingSince->diffForHumans() }}</p><p class="text-ink-400">{{ $pendingSince->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>@elseif ($lastUpload)<p class="text-ink-500">Aktivitas terakhir</p><p class="text-ink-400">{{ $lastUpload->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>@else<p class="text-ink-400">Belum ada upload evidence.</p>@endif</td>
                            <td class="whitespace-nowrap px-5 py-4 text-right"><a href="{{ route('evidence-approval.lop.review', $reviewParams) }}" title="Buka review evidence {{ $lop->incident }}" class="inline-flex min-h-9 items-center gap-1.5 rounded-md border border-brand-200 bg-brand-50 px-3 text-xs font-bold text-brand-700 hover:bg-brand-100 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-brand-900 dark:bg-brand-950/30 dark:text-brand-300"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="2.25"/></svg>{{ $ready ? 'Selesaikan Review' : ($lop->status_lop === \App\Enums\LopStatus::COMPLETED ? 'Lihat Review' : 'Review') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-14 text-center"><p class="text-sm font-bold">Tidak ada LOP pada filter ini</p><p class="mt-2 text-xs text-ink-500">LOP muncul jika assignment aktif dibuat oleh Anda dan lokasi masih dalam scope akun.</p><a href="{{ route('lop.my-approvals', ['tab' => 'all']) }}" class="mt-4 inline-flex rounded-md border border-ink-200 px-4 py-2 text-xs font-bold text-ink-600 dark:border-ink-700 dark:text-ink-300">Lihat semua penugasan saya</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-ink-100 px-5 py-3 dark:border-ink-800">{{ $lops->links() }}</div>
    </section>
</div>
@endsection
