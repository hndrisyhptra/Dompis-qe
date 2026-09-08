@extends('layouts.app')

@section('title', $programType->label())

@section('content')
@php
    // Nilai filter lokasi yang ikut dibawa di setiap link bucket / switcher.
    $carry = array_filter([
        'q' => $search,
        'region' => $regionFilter,
        'branch' => $branchFilter,
    ], fn ($v) => $v !== '' && $v !== null);
@endphp
<div class="mx-auto max-w-7xl space-y-6">
    @if ($scopeWarning)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300">
            Akun Anda belum terhubung ke branch, jadi tidak ada data Program yang bisa ditampilkan. Hubungi admin untuk mengatur branch akun Anda.
        </div>
    @endif
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.16em] text-brand-600 dark:text-brand-400">Pemetaan Program · {{ $scopeLabel }}</p>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-900 dark:text-white">{{ $programType->label() }}</h1>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">{{ $total }} LOP · dikelompokkan per bucket status.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach ($programTypes as $t)
                <a href="{{ route('program.show', [$t->value, ...array_diff_key($carry, ['q' => 1])]) }}"
                   class="inline-flex min-h-10 items-center rounded-xl border px-4 text-sm font-bold transition
                          {{ $t === $programType
                              ? 'border-brand-500 bg-brand-50 text-brand-700 dark:border-brand-500 dark:bg-brand-950/40 dark:text-brand-300'
                              : 'border-ink-200 bg-white text-ink-600 hover:bg-ink-50 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-300' }}">
                    {{ $t->label() }}
                </a>
            @endforeach
        </div>
    </header>

    {{-- Bucket status --}}
    <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-7">
        <a href="{{ route('program.show', [$programType->value, ...$carry]) }}"
           class="rounded-2xl border p-4 shadow-sm transition
                  {{ $bucketFilter === ''
                      ? 'border-ink-900 bg-ink-900 text-white dark:border-white dark:bg-ink-800'
                      : 'border-ink-100 bg-white text-ink-900 hover:border-ink-300 dark:border-ink-800 dark:bg-ink-900 dark:text-white' }}">
            <p class="text-xs font-semibold {{ $bucketFilter === '' ? 'text-white/70' : 'text-ink-500' }}">Semua</p>
            <p class="mt-1 text-2xl font-extrabold">{{ $total }}</p>
        </a>
        @foreach ($buckets as $bucket)
            <a href="{{ route('program.show', [$programType->value, 'bucket' => $bucket['key'], ...$carry]) }}"
               class="rounded-2xl border bg-white p-4 shadow-sm transition dark:bg-ink-900
                      {{ $bucket['active'] ? 'border-brand-500 ring-2 ring-brand-500/20' : 'border-ink-100 hover:border-ink-300 dark:border-ink-800' }}">
                <span class="inline-flex"><x-badge :variant="$bucket['variant']">{{ $bucket['label'] }}</x-badge></span>
                <p class="mt-2 text-2xl font-extrabold text-ink-900 dark:text-white">{{ $bucket['count'] }}</p>
            </a>
        @endforeach
    </section>

    {{-- Rincian per branch --}}
    <details class="rounded-2xl border border-ink-100 bg-white p-4 shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <summary class="cursor-pointer text-sm font-bold text-ink-700 dark:text-ink-200">Sebaran per branch ({{ $branchBreakdown->count() }})</summary>
        <div class="mt-3 flex flex-wrap gap-2">
            @forelse ($branchBreakdown as $row)
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-ink-50 px-2.5 py-1 text-xs font-semibold text-ink-700 dark:bg-ink-800 dark:text-ink-200">
                    {{ $row->branch ?: 'Branch —' }}
                    <span class="rounded bg-white px-1.5 text-ink-500 dark:bg-ink-900">{{ $row->c }}</span>
                </span>
            @empty
                <p class="text-xs text-ink-400">Belum ada data.</p>
            @endforelse
        </div>
    </details>

    {{-- Filter: region / branch (hanya SUPER_ADMIN) + pencarian --}}
    <section class="rounded-2xl border border-ink-100 bg-white p-4 shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <form method="GET" action="{{ route('program.show', $programType->value) }}"
              class="grid gap-3 {{ $canFilterLocation ? 'lg:grid-cols-[190px_190px_minmax(200px,1fr)_auto]' : 'sm:grid-cols-[minmax(200px,1fr)_auto]' }}">
            <input type="hidden" name="bucket" value="{{ $bucketFilter }}">

            @if ($canFilterLocation)
                <select name="region" onchange="this.form.branch.value=''; this.form.submit()"
                        class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm dark:border-ink-700 dark:bg-ink-800">
                    <option value="">Semua region</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region }}" @selected($regionFilter === $region)>{{ $region }}</option>
                    @endforeach
                </select>

                <select name="branch" onchange="this.form.submit()"
                        class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm dark:border-ink-700 dark:bg-ink-800">
                    <option value="">Semua branch</option>
                    @foreach ($branches->when($regionFilter, fn ($items) => $items->where('region', $regionFilter)) as $branch)
                        <option value="{{ $branch->name }}" @selected($branchFilter === $branch->name)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @endif

            <input name="q" value="{{ $search }}" placeholder="Cari incident, project, STO, atau branch..."
                   class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800">

            <div class="flex gap-2">
                <button class="min-h-10 rounded-xl bg-ink-900 px-4 text-sm font-bold text-white dark:bg-brand-600">Terapkan</button>
                @if ($search || $bucketFilter || $regionFilter || $branchFilter)
                    <a href="{{ route('program.show', $programType->value) }}" class="grid min-h-10 place-items-center rounded-xl border border-ink-200 px-3 text-sm font-bold text-ink-500 dark:border-ink-700">Reset</a>
                @endif
            </div>
        </form>
    </section>

    {{-- Tabel LOP (monitoring) --}}
    <x-table class="!rounded-2xl shadow-sm">
        <thead class="bg-ink-50/80 dark:bg-ink-800"><tr>
            @foreach (['Project', 'Teknisi', 'Progress', 'Status', 'Update'] as $heading)
                <th class="whitespace-nowrap px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wider text-ink-500">{{ $heading }}</th>
            @endforeach
            <th class="whitespace-nowrap px-5 py-3.5 text-right text-[11px] font-bold uppercase tracking-wider text-ink-500">Aksi</th>
        </tr></thead>
        <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
            @forelse ($lops as $lop)
                @php($summary = $lop->progress_summary)
                <tr class="align-top transition hover:bg-ink-50/70 dark:hover:bg-ink-800/40">
                    <td class="px-5 py-4">
                        <p class="text-xs font-extrabold uppercase tracking-wide text-brand-600">{{ $lop->incident }}</p>
                        <p class="mt-1 max-w-xs text-sm font-bold text-ink-900 dark:text-white">{{ $lop->nama_lop }}</p>
                        <p class="mt-1 text-xs text-ink-400">{{ $lop->sto ?: 'STO —' }} · {{ $lop->branch ?: 'Branch —' }}</p>
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-2">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-ink-100 text-xs font-bold dark:bg-ink-800">{{ strtoupper(substr($lop->activeAssignment?->technician?->name ?? '?', 0, 1)) }}</span>
                            <span class="text-sm font-semibold">{{ $lop->activeAssignment?->technician?->name ?? 'Belum ditugaskan' }}</span>
                        </div>
                    </td>
                    <td class="min-w-52 px-5 py-4">
                        <div class="mb-2 flex justify-between text-xs"><span class="text-ink-500">{{ $summary['completed_steps'] }}/{{ $summary['total_steps'] }} step</span><strong class="tabular-nums">{{ $summary['percentage'] }}%</strong></div>
                        <div class="h-2 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800">
                            <div class="h-full rounded-full {{ $summary['review_key'] === 'rejected' ? 'bg-brand-600' : ($summary['review_key'] === 'approved' ? 'bg-emerald-500' : ($summary['review_key'] === 'waiting_review' ? 'bg-amber-400' : 'bg-blue-500')) }}" style="width: {{ $summary['percentage'] }}%"></div>
                        </div>
                        <p class="mt-1.5 text-[10px] text-ink-400">{{ $summary['evidence_count'] }} evidence</p>
                    </td>
                    <td class="px-5 py-4"><x-badge :variant="$lop->status_lop->badgeVariant()">{{ $lop->status_lop->label() }}</x-badge></td>
                    <td class="whitespace-nowrap px-5 py-4 text-xs text-ink-500">{{ $lop->updated_at->diffForHumans() }}</td>
                    <td class="px-5 py-4">
                        <div class="flex flex-wrap items-center justify-end gap-1.5">
                            @if ($lop->status_lop === \App\Enums\LopStatus::COMPLETED)
                                <x-table-action label="Download evidence (.zip)" tone="success" :href="route('lop.evidence-archive', $lop)">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                </x-table-action>
                            @endif
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
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-14 text-center"><p class="text-sm font-bold">Tidak ada LOP</p><p class="mt-1 text-xs text-ink-400">Belum ada LOP untuk Program / bucket ini.</p></td></tr>
            @endforelse
        </tbody>
    </x-table>

    @foreach ($lops as $lop)
        <x-lop-detail-modal :id="'lop-detail-'.$lop->id_qe_lops" :$lop />
        <x-lop-tracking-modal :id="'lop-tracking-'.$lop->id_qe_lops" :$lop />
        @can('assign', $lop)
            <x-assign-technician-modal :id="'assign-technician-'.$lop->id_qe_lops" :$lop :$technicians :return-to="'program:'.$programType->value" />
        @endcan
    @endforeach

    <div>{{ $lops->links() }}</div>
</div>
@endsection
