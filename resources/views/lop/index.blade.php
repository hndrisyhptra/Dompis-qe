@extends('layouts.app')

@section('title', 'Inbox LOP')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.16em] text-brand-600 dark:text-brand-400">Operations workspace</p>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-900 dark:text-white">Inbox Project</h1>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Pantau pekerjaan aktif, progres teknisi, dan project yang memerlukan tindakan.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('lop.history') }}" class="inline-flex min-h-10 items-center rounded-xl border border-ink-200 bg-white px-4 text-sm font-bold text-ink-700 hover:bg-ink-50 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200">History</a>
            @can('create', \App\Models\QeLop::class)
                <a href="{{ route('lop.create') }}" class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-brand-600 px-4 text-sm font-bold text-white shadow-lg shadow-brand-600/20"><span class="text-lg">+</span> Buat LOP</a>
            @endcan
        </div>
    </header>

    <section class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        @foreach ([
            ['Project Aktif', $stats['active'], 'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-300'],
            ['Waiting Review', $stats['waiting'], 'bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-300'],
            ['Perlu Perbaikan', $stats['rejected'], 'bg-brand-50 text-brand-600 dark:bg-brand-900/20 dark:text-brand-300'],
            ['Perlu Review Evidence', $stats['review'], 'bg-ink-100 text-ink-600 dark:bg-ink-800 dark:text-ink-300'],
        ] as [$label, $value, $tone])
            <article class="rounded-2xl border border-ink-100 bg-white p-4 shadow-sm dark:border-ink-800 dark:bg-ink-900">
                <div class="flex items-center justify-between"><span class="grid h-10 w-10 place-items-center rounded-xl {{ $tone }}"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5M12 3.75v16.5"/></svg></span><strong class="text-2xl text-ink-900 dark:text-white">{{ $value }}</strong></div>
                <p class="mt-3 text-xs font-semibold text-ink-500">{{ $label }}</p>
            </article>
        @endforeach
    </section>

    <section class="rounded-2xl border border-ink-100 bg-white p-4 shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <form method="GET" action="{{ route('lop.index') }}" class="grid gap-3 lg:grid-cols-[minmax(240px,1fr)_190px_190px_auto]">
            <div class="relative">
                <svg class="absolute left-3.5 top-3 h-4 w-4 text-ink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z"/></svg>
                <input name="q" value="{{ $search }}" placeholder="Cari incident, project, STO, atau branch..." class="min-h-10 w-full rounded-xl border border-ink-200 bg-ink-50 pl-10 pr-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800">
            </div>
            <select name="status" class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm dark:border-ink-700 dark:bg-ink-800">
                <option value="">Semua status</option>
                @foreach (\App\Enums\LopStatus::cases() as $status)
                    @if ($status !== \App\Enums\LopStatus::COMPLETED)<option value="{{ $status->value }}" @selected($statusFilter === $status->value)>{{ $status->label() }}</option>@endif
                @endforeach
            </select>
            <select name="program" class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm dark:border-ink-700 dark:bg-ink-800">
                <option value="">Semua Program</option>
                @foreach (\App\Enums\ProgramType::cases() as $type)<option value="{{ $type->value }}" @selected($programFilter === $type->value)>{{ $type->label() }}</option>@endforeach
            </select>
            <div class="flex gap-2"><button class="min-h-10 rounded-xl bg-ink-900 px-4 text-sm font-bold text-white dark:bg-brand-600">Terapkan</button>@if ($search || $statusFilter || $programFilter)<a href="{{ route('lop.index') }}" class="grid min-h-10 place-items-center rounded-xl border border-ink-200 px-3 text-sm font-bold text-ink-500 dark:border-ink-700">Reset</a>@endif</div>
        </form>
    </section>

    <x-table class="!rounded-2xl shadow-sm">
        <thead class="bg-ink-50/80 dark:bg-ink-800"><tr>
            @foreach (['Project', 'Teknisi', 'Progress', 'Status', 'Update'] as $heading)<th class="px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wider text-ink-500">{{ $heading }}</th>@endforeach
            <th class="px-5 py-3.5 text-right text-[11px] font-bold uppercase tracking-wider text-ink-500">Aksi</th>
        </tr></thead>
        <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
            @forelse ($lops as $lop)
                @php($summary = $lop->progress_summary)
                <tr class="transition hover:bg-ink-50/70 dark:hover:bg-ink-800/40">
                    <td class="px-5 py-4"><p class="text-xs font-extrabold uppercase tracking-wide text-brand-600">{{ $lop->incident }}</p><p class="mt-1 max-w-xs text-sm font-bold text-ink-900 dark:text-white">{{ $lop->nama_lop }}</p><p class="mt-1 text-xs text-ink-400">{{ $lop->sto ?: 'STO —' }} · {{ $lop->branch ?: 'Branch —' }} · {{ $lop->program_type->label() }}</p></td>
                    <td class="px-5 py-4"><div class="flex items-center gap-2"><span class="grid h-8 w-8 rounded-full bg-ink-100 place-items-center text-xs font-bold dark:bg-ink-800">{{ strtoupper(substr($lop->activeAssignment?->technician?->name ?? '?', 0, 1)) }}</span><span class="whitespace-nowrap text-sm font-semibold">{{ $lop->activeAssignment?->technician?->name ?? 'Not Assigned' }}</span></div></td>
                    <td class="min-w-52 px-5 py-4"><div class="mb-2 flex justify-between text-xs"><span class="text-ink-500">{{ $summary['completed_steps'] }}/{{ $summary['total_steps'] }} step</span><strong>{{ $summary['percentage'] }}%</strong></div><div class="h-2 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full rounded-full {{ $summary['review_key'] === 'rejected' ? 'bg-brand-600' : ($summary['review_key'] === 'approved' ? 'bg-emerald-500' : ($summary['review_key'] === 'waiting_review' ? 'bg-amber-400' : 'bg-blue-500')) }}" style="width: {{ $summary['percentage'] }}%"></div></div><p class="mt-1.5 text-[10px] text-ink-400">{{ $summary['evidence_count'] }} evidence</p></td>
                    <td class="px-5 py-4"><x-badge :variant="$summary['review_variant']">{{ $summary['review_label'] }}</x-badge></td>
                    <td class="whitespace-nowrap px-5 py-4 text-xs text-ink-500">{{ $lop->updated_at->diffForHumans() }}</td>
                    <td class="px-5 py-4">
                        <div class="flex items-center justify-end gap-1.5">
                            <x-table-action label="Detail LOP" onclick="document.getElementById('lop-detail-{{ $lop->id_qe_lops }}').showModal()">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><circle cx="12" cy="12" r="2.25" /></svg>
                            </x-table-action>
                            @can('assign', $lop)
                                <x-table-action :label="$lop->activeAssignment ? 'Reassign Teknisi' : 'Assign Teknisi'" tone="primary" onclick="document.getElementById('assign-technician-{{ $lop->id_qe_lops }}').showModal()">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-6.75-3.75A3.75 3.75 0 1 1 6.75 6.75a3.75 3.75 0 0 1 7.5 0ZM3 20.25a6.75 6.75 0 0 1 13.5 0v.75H3v-.75Z" /></svg>
                                </x-table-action>
                            @endcan
                            <x-table-action label="Tracking Riwayat" tone="info" onclick="document.getElementById('lop-tracking-{{ $lop->id_qe_lops }}').showModal()">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            </x-table-action>
                            @can('reviewEvidence', $lop)
                                <x-table-action label="Review Evidence" tone="success" :href="$summary['evidence_count'] ? route('evidence-approval.lop.review', $lop) : null" :disabled="! $summary['evidence_count']">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 12.75 2.25 2.25L15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                                </x-table-action>
                            @else
                                <x-table-action label="Review belum tersedia" tone="success" disabled>
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 12.75 2.25 2.25L15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                                </x-table-action>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-14 text-center"><p class="text-sm font-bold">Project tidak ditemukan</p><p class="mt-1 text-xs text-ink-400">Ubah filter atau buat LOP baru.</p></td></tr>
            @endforelse
        </tbody>
    </x-table>
    @foreach ($lops as $lop)
        <x-lop-detail-modal :id="'lop-detail-'.$lop->id_qe_lops" :$lop />
        <x-lop-tracking-modal :id="'lop-tracking-'.$lop->id_qe_lops" :$lop />
        @can('assign', $lop)
            <x-assign-technician-modal :id="'assign-technician-'.$lop->id_qe_lops" :$lop :$technicians return-to="index" />
        @endcan
    @endforeach
    <div>{{ $lops->links() }}</div>
</div>
@endsection
