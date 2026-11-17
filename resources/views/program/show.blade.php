@extends('layouts.app')

@section('title', $programType->label())

@section('content')
@php
    // Nilai filter lokasi yang ikut dibawa di setiap link bucket / switcher.
    $carry = array_filter([
        'q' => $search,
        'region' => $regionFilter,
        'branch' => $branchFilter,
        'project_status' => $projectStatusFilter?->value,
    ], fn ($v) => $v !== '' && $v !== null);
    $modalCarry = array_filter([...$carry, 'bucket' => $bucketFilter], fn ($v) => $v !== '' && $v !== null);
@endphp
<div class="mx-auto max-w-7xl space-y-6">
    @if ($scopeWarning)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300">
            Scope lokasi akun Anda belum diatur, jadi data Program belum dapat ditampilkan. Hubungi Super Admin untuk mengatur Area, Region, Branch, atau Service Area akun Anda.
        </div>
    @endif
    <header class="overflow-hidden rounded-lg border border-ink-200 bg-white dark:border-ink-700 dark:bg-ink-900">
        <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-start gap-3">
                <a href="{{ route('program.index', $carry) }}" aria-label="Kembali ke ringkasan program" title="Kembali ke ringkasan program" class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-md border border-ink-200 text-ink-600 transition hover:bg-ink-50 dark:border-ink-700 dark:text-ink-300 dark:hover:bg-ink-800">
                    <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15m0 0 6 6m-6-6 6-6"/></svg>
                </a>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider opacity-80">Pemetaan Program <span class="px-1 opacity-50">/</span> {{ $scopeLabel }}</p>
                    <h1 class="mt-1 text-xl font-bold tracking-tight text-red-700 dark:text-red-300 sm:text-2xl">{{ $programType->label() }}</h1>
                    <p class="mt-1 text-sm text-ink-600 dark:text-ink-300">{{ number_format($total) }} LOP pada pilihan status dan lokasi ini.</p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2 self-end rounded-md border border-ink-200 bg-white px-3 py-2 dark:border-ink-700 dark:bg-ink-950 sm:self-auto">
                <span class="text-[10px] font-semibold uppercase tracking-wide text-ink-500">Total LOP</span>
                <span class="text-lg font-bold tabular-nums text-ink-900 dark:text-white">{{ number_format($total) }}</span>
            </div>
        </div>

        @if ($programType->usesProjectStatus())
            <nav class="flex flex-col gap-2 border-t border-ink-100 bg-ink-50/70 px-5 py-3 dark:border-ink-800 dark:bg-ink-950/30 sm:flex-row sm:items-center" aria-label="Status project">
                <span class="shrink-0 text-xs font-semibold text-ink-500 dark:text-ink-400">Tahap project</span>
                <div class="flex flex-wrap gap-2">
                    @foreach ($projectStatuses as $projectStatus)
                        <a href="{{ route('program.show', [$programType->value, ...array_merge($carry, ['project_status' => $projectStatus->value])]) }}"
                           @class([
                               'inline-flex min-h-9 items-center gap-2 rounded-md border px-3 text-xs font-semibold transition',
                               'border-brand-300 bg-white text-brand-800 shadow-sm dark:border-brand-800 dark:bg-ink-900 dark:text-brand-300' => $projectStatusFilter === $projectStatus,
                               'border-ink-200 bg-white/60 text-ink-600 hover:bg-white dark:border-ink-700 dark:bg-ink-900/50 dark:text-ink-300 dark:hover:bg-ink-900' => $projectStatusFilter !== $projectStatus,
                           ])>
                            {{ $projectStatus->label() }}
                            <span class="rounded bg-ink-100 px-1.5 py-0.5 text-[10px] tabular-nums text-ink-600 dark:bg-ink-800 dark:text-ink-300">{{ number_format($projectStatusCounts[$projectStatus->value] ?? 0) }}</span>
                        </a>
                    @endforeach
                </div>
            </nav>
        @endif
    </header>

    {{-- Bucket status --}}
    <section aria-label="Filter status LOP" class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-7">
        <a href="{{ route('program.show', [$programType->value, ...$carry]) }}"
           class="group rounded-lg border p-4 transition hover:-translate-y-0.5 hover:shadow-sm
                  {{ $bucketFilter === ''
                      ? 'border-ink-800 bg-ink-800 text-white dark:border-ink-200 dark:bg-ink-100 dark:text-ink-900'
                      : 'border-ink-200 bg-white text-ink-900 hover:border-ink-300 dark:border-ink-700 dark:bg-ink-900 dark:text-white' }}">
            <p class="text-xs font-semibold {{ $bucketFilter === '' ? 'text-white/70 dark:text-ink-500' : 'text-ink-500' }}">Semua status</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($total) }}</p>
        </a>
        @foreach ($buckets as $bucket)
            <a href="{{ route('program.show', [$programType->value, 'bucket' => $bucket['key'], ...$carry]) }}"
               @class([
                   'group rounded-lg border bg-white p-4 transition hover:border-ink-300 hover:shadow-sm dark:bg-ink-900',
                   'border-brand-400 ring-1 ring-brand-400/30' => $bucket['active'],
                   'border-ink-200 dark:border-ink-700' => ! $bucket['active'],
               ])>
                <div class="flex items-center justify-between gap-2"><span class="text-xs font-semibold text-ink-600 dark:text-ink-300">{{ $bucket['label'] }}</span>@if ($bucket['active'])<span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>@endif</div>
                <p class="mt-2 text-2xl font-bold tabular-nums text-ink-900 dark:text-white">{{ number_format($bucket['count']) }}</p>
            </a>
        @endforeach
    </section>

    {{-- Rincian per branch --}}
    <details class="rounded-lg border border-ink-200 bg-white dark:border-ink-700 dark:bg-ink-900">
        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-4 text-sm font-semibold text-ink-700 marker:hidden dark:text-ink-200">
            <span>Sebaran per branch</span><span class="rounded-md bg-ink-100 px-2 py-1 text-xs tabular-nums text-ink-600 dark:bg-ink-800 dark:text-ink-300">{{ $branchBreakdown->count() }} branch</span>
        </summary>
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
    <section class="rounded-lg border border-ink-200 bg-white p-4 dark:border-ink-700 dark:bg-ink-900">
        <div class="mb-4 flex items-start justify-between gap-3 border-b border-ink-100 pb-3 dark:border-ink-800">
            <div><h2 class="text-sm font-semibold text-ink-900 dark:text-white">Cari dan saring LOP</h2><p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">Filter berlaku pada daftar di bawah.</p></div>
            @if ($search || $bucketFilter || $regionFilter || $branchFilter)
                <a href="{{ route('program.show', $programType->value) }}" class="shrink-0 text-xs font-semibold text-brand-700 hover:underline dark:text-brand-300">Reset filter</a>
            @endif
        </div>
        <form method="GET" action="{{ route('program.show', $programType->value) }}"
              class="grid gap-3 {{ $canFilterLocation ? 'lg:grid-cols-[minmax(10rem,0.8fr)_minmax(10rem,0.8fr)_minmax(16rem,1.6fr)_auto]' : 'sm:grid-cols-[minmax(200px,1fr)_auto]' }}">
            <input type="hidden" name="bucket" value="{{ $bucketFilter }}">
            @if ($projectStatusFilter)
                <input type="hidden" name="project_status" value="{{ $projectStatusFilter->value }}">
            @endif

            @if ($canFilterLocation)
                <label class="block"><span class="mb-1.5 block text-xs font-medium text-ink-600 dark:text-ink-300">Region</span>
                    <select name="region" onchange="this.form.branch.value=''; this.form.submit()" class="min-h-10 w-full rounded-md border border-ink-300 bg-white px-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950">
                        <option value="">Semua region</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region }}" @selected($regionFilter === $region)>{{ $region }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block"><span class="mb-1.5 block text-xs font-medium text-ink-600 dark:text-ink-300">Branch</span>
                    <select name="branch" onchange="this.form.submit()" class="min-h-10 w-full rounded-md border border-ink-300 bg-white px-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950">
                        <option value="">Semua branch</option>
                        @foreach ($branches->when($regionFilter, fn ($items) => $items->where('region', $regionFilter)) as $branch)
                            <option value="{{ $branch->name }}" @selected($branchFilter === $branch->name)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="block"><span class="mb-1.5 block text-xs font-medium text-ink-600 dark:text-ink-300">Pencarian</span>
                <input name="q" value="{{ $search }}" placeholder="Incident, nama project, STO, atau branch" class="min-h-10 w-full rounded-md border border-ink-300 bg-white px-3.5 text-sm outline-none placeholder:text-ink-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950">
            </label>

            <div class="flex items-end">
                <button class="min-h-10 w-full rounded-md bg-ink-800 px-4 text-sm font-semibold text-white transition hover:bg-ink-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 dark:bg-ink-100 dark:text-ink-900 dark:hover:bg-white">Terapkan</button>
            </div>
        </form>
    </section>

    {{-- Tabel LOP (monitoring) --}}
    <header class="flex flex-wrap items-center justify-between gap-3 px-1 md:hidden">
        <div><h2 class="text-sm font-semibold text-ink-900 dark:text-white">Daftar LOP</h2><p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">Status pekerjaan dan aktivitas terbaru.</p></div>
        <p class="text-xs tabular-nums text-ink-500 dark:text-ink-400">{{ $lops->firstItem() ?? 0 }}–{{ $lops->lastItem() ?? 0 }} dari {{ number_format($lops->total()) }}</p>
    </header>
    <div class="divide-y divide-ink-200 overflow-hidden rounded-lg border border-ink-200 bg-white dark:divide-ink-700 dark:border-ink-700 dark:bg-ink-900 md:hidden">
        @forelse ($lops as $lop)
            @php($summary = $lop->progress_summary)
            @php($baDocument = \App\Support\BaBongkarRules::document($lop))
            <article class="p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex min-w-0 items-center gap-2"><p class="truncate text-[11px] font-bold uppercase tracking-wide text-brand-700 dark:text-brand-300">{{ $lop->incident }}</p>@if ($lop->status_project)<span class="shrink-0 rounded-md bg-ink-100 px-1.5 py-0.5 text-[9px] font-semibold text-ink-600 dark:bg-ink-800 dark:text-ink-300">{{ $lop->status_project->label() }}</span>@endif</div>
                        <h3 class="mt-1 truncate text-sm font-semibold leading-5 text-ink-900 dark:text-white" title="{{ $lop->nama_lop }}">{{ $lop->nama_lop }}</h3>
                        <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">{{ $lop->locationServiceAreaName() ?: 'STO —' }} · {{ $lop->locationBranchName() ?: 'Branch —' }}</p>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 border-t border-ink-100 pt-3 dark:border-ink-800">
                    <div><p class="text-[10px] font-medium text-ink-500 dark:text-ink-400">Teknisi</p><p class="mt-0.5 text-xs font-semibold text-ink-800 dark:text-ink-200">{{ $lop->activeAssignment?->technician?->name ?? 'Belum ditugaskan' }}</p></div>
                    <div><p class="text-[10px] font-medium text-ink-500 dark:text-ink-400">Status Dompis</p><div class="mt-1"><x-lop-status-badge :status="$lop->status_lop" /></div></div>
                    <div><p class="text-[10px] font-medium text-ink-500 dark:text-ink-400">Status AISS</p><div class="mt-1"><x-aiss-status :status="$lop->status_aiss" /></div></div>
                    <div><p class="text-[10px] font-medium text-ink-500 dark:text-ink-400">Pembaruan</p><p class="mt-0.5 text-xs text-ink-700 dark:text-ink-300" title="{{ $lop->updated_at->format('d/m/Y H:i') }}">{{ $lop->updated_at->diffForHumans() }}</p></div>
                </div>
                <div class="mt-4 rounded-md bg-ink-50 p-3 dark:bg-ink-950/50">
                    <div class="mb-2 flex justify-between text-xs"><span class="text-ink-500 dark:text-ink-400">Progress · {{ $summary['completed_steps'] }}/{{ $summary['total_steps'] }} step · {{ $summary['evidence_count'] }} evidence</span><strong class="tabular-nums text-ink-800 dark:text-ink-200">{{ $summary['percentage'] }}%</strong></div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-ink-200 dark:bg-ink-800"><div class="h-full rounded-full {{ $summary['review_key'] === 'rejected' ? 'bg-rose-500' : ($summary['review_key'] === 'approved' ? 'bg-emerald-500' : ($summary['review_key'] === 'waiting_review' ? 'bg-amber-400' : 'bg-sky-500')) }}" style="width: {{ $summary['percentage'] }}%"></div></div>
                </div>
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-ink-100 pt-3 dark:border-ink-800">
                    <span class="text-[10px] font-semibold uppercase tracking-wide text-ink-400">Aksi LOP</span>
                    @include('program.partials.lop-actions', ['presentation' => 'mobile'])
                </div>
            </article>
        @empty
            <div class="px-6 py-12 text-center"><p class="text-sm font-semibold text-ink-800 dark:text-ink-200">Tidak ada LOP</p><p class="mt-1 text-xs text-ink-500">Belum ada LOP untuk program dan filter yang dipilih.</p></div>
        @endforelse
    </div>
    <div class="border-t border-ink-100 px-4 py-3 dark:border-ink-800 md:hidden">{{ $lops->links() }}</div>

    <section class="hidden overflow-hidden rounded-lg border border-ink-200 bg-white dark:border-ink-700 dark:bg-ink-900 md:block">
        <style>
            .program-lop-table table { width: 100%; min-width: 68rem; table-layout: fixed; }
            .program-lop-table th + th, .program-lop-table td + td { border-left: 1px solid rgb(226 232 240 / .72); }
            .dark .program-lop-table th + th, .dark .program-lop-table td + td { border-left-color: rgb(51 65 85 / .72); }
        </style>
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-ink-200 px-4 py-3 dark:border-ink-700 sm:px-5">
            <div><h2 class="text-sm font-semibold text-ink-900 dark:text-white">Daftar LOP</h2><p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">Status pekerjaan dan aktivitas terbaru.</p></div>
            <p class="text-xs tabular-nums text-ink-500 dark:text-ink-400">{{ $lops->firstItem() ?? 0 }}–{{ $lops->lastItem() ?? 0 }} dari {{ number_format($lops->total()) }}</p>
        </header>
    <x-table class="program-lop-table !rounded-none !border-0">
        <colgroup>
            <col style="width: 28%">
            @for ($column = 0; $column < 6; $column++)
                <col style="width: 12%">
            @endfor
        </colgroup>
        <thead class="bg-ink-100 text-ink-600 dark:bg-ink-800 dark:text-ink-300"><tr>
            @foreach (['Project', 'Teknisi', 'Progress', 'Status Dompis', 'Status AISS', 'Update'] as $heading)
                <th class="whitespace-nowrap px-2 py-3 text-center text-[10px] font-bold uppercase tracking-wide">{{ $heading }}</th>
            @endforeach
            <th class="whitespace-nowrap px-2 py-3 text-center text-[10px] font-bold uppercase tracking-wide">Aksi</th>
        </tr></thead>
        <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
            @forelse ($lops as $lop)
                @php($summary = $lop->progress_summary)
                @php($baDocument = \App\Support\BaBongkarRules::document($lop))
                <tr class="align-middle transition hover:bg-brand-50/40 dark:hover:bg-ink-800/40">
                    <td class="px-4 py-4">
                        <div class="flex min-w-0 items-center gap-2"><p class="truncate text-xs font-extrabold uppercase tracking-wide text-brand-600">{{ $lop->incident }}</p>@if ($lop->status_project)<span class="shrink-0 rounded-md bg-ink-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-ink-600 dark:bg-ink-800 dark:text-ink-300">{{ $lop->status_project->label() }}</span>@endif</div>
                        <p class="mt-1 max-w-full truncate text-sm font-bold text-ink-900 dark:text-white" title="{{ $lop->nama_lop }}">{{ $lop->nama_lop }}</p>
                        <p class="mt-1 text-xs text-ink-400">{{ $lop->locationServiceAreaName() ?: 'STO —' }} · {{ $lop->locationBranchName() ?: 'Branch —' }}</p>
                    </td>
                    <td class="px-2 py-4">
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-ink-100 text-[10px] font-bold dark:bg-ink-800">{{ strtoupper(substr($lop->activeAssignment?->technician?->name ?? '?', 0, 1)) }}</span>
                            <span class="min-w-0 truncate text-xs font-semibold" title="{{ $lop->activeAssignment?->technician?->name ?? 'Belum ditugaskan' }}">{{ $lop->activeAssignment?->technician?->name ?? 'Belum ditugaskan' }}</span>
                        </div>
                    </td>
                    <td class="px-2 py-4">
                        <div class="mb-2 flex items-center justify-between gap-1 text-[10px]"><span class="whitespace-nowrap text-ink-500">{{ $summary['completed_steps'] }}/{{ $summary['total_steps'] }} step</span><strong class="tabular-nums">{{ $summary['percentage'] }}%</strong></div>
                        <div class="h-2 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800">
                            <div class="h-full rounded-full {{ $summary['review_key'] === 'rejected' ? 'bg-brand-600' : ($summary['review_key'] === 'approved' ? 'bg-emerald-500' : ($summary['review_key'] === 'waiting_review' ? 'bg-amber-400' : 'bg-blue-500')) }}" style="width: {{ $summary['percentage'] }}%"></div>
                        </div>
                        <p class="mt-1.5 text-[10px] text-ink-400">{{ $summary['evidence_count'] }} evidence</p>
                    </td>
                    <td class="px-2 py-4 text-center"><x-lop-status-badge :status="$lop->status_lop" compact /></td>
                    <td class="px-2 py-4 text-center"><x-aiss-status :status="$lop->status_aiss" class="max-w-full truncate" /></td>
                    <td class="whitespace-nowrap px-2 py-4 text-center text-xs text-ink-500" title="{{ $lop->updated_at->format('d/m/Y H:i') }}">{{ $lop->updated_at->diffForHumans() }}</td>
                    <td class="px-2 py-4 text-center">
                        @include('program.partials.lop-actions', ['presentation' => 'desktop'])
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-6 py-14 text-center"><p class="text-sm font-semibold text-ink-800 dark:text-ink-200">Tidak ada LOP</p><p class="mt-1 text-xs text-ink-500">Belum ada LOP untuk program dan filter yang dipilih.</p></td></tr>
            @endforelse
        </tbody>
    </x-table>

    <div class="border-t border-ink-100 px-4 py-3 dark:border-ink-800">{{ $lops->links() }}</div>
    </section>
</div>
@endsection
