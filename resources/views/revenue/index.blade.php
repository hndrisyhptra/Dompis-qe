@extends('layouts.app')

@section('title', 'Financial Overview')

@section('content')
@php
    $money = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $compactMoney = function ($value) {
        $value = (float) $value;
        if ($value >= 1_000_000_000) return 'Rp '.number_format($value / 1_000_000_000, 1, ',', '.').' M';
        if ($value >= 1_000_000) return 'Rp '.number_format($value / 1_000_000, 1, ',', '.').' Jt';
        return 'Rp '.number_format($value / 1_000, 0, ',', '.').' Rb';
    };
    $hasFilters = collect($filters)->contains(fn ($value) => filled($value));
    $selectedBranch = $branches->firstWhere('id_branch', $filters['branch']);
    $maxBranchValue = max(1, (float) $branchRows->max('proposal_value'));
    $branchChartWidth = max(720, ($branchRows->count() * 112) + 40);
@endphp

<div class="mx-auto max-w-7xl space-y-6">
    <header class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-xs font-bold uppercase tracking-[.16em] text-brand-600 dark:text-brand-400">Financial overview</p>
                <span class="h-1 w-1 rounded-full bg-ink-300 dark:bg-ink-600"></span>
                <span class="text-xs font-semibold text-ink-500 dark:text-ink-400">{{ $scopeLabel }}</span>
            </div>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-900 dark:text-white sm:text-3xl">Financial Overview</h1>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-ink-500 dark:text-ink-400">Ringkasan nilai usulan, realisasi, dan gap pekerjaan untuk mendukung pemantauan performa setiap branch.</p>
        </div>
        <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-xs leading-5 text-blue-700 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-300">
            <strong>Metode:</strong> Nilai realisasi dihitung dari BOQ pada LOP berstatus <strong>Completed</strong>.
        </div>
    </header>

    @if ($scopeWarning)
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">Scope akun belum dikonfigurasi. Data Financial Overview diamankan dan tidak ditampilkan.</section>
    @endif

    <section class="rounded-2xl border border-ink-100 bg-white p-4 shadow-sm dark:border-ink-800 dark:bg-ink-900 sm:p-5">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div><h2 class="text-sm font-extrabold text-ink-900 dark:text-white">Filter Revenue</h2><p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Pilihan lokasi tetap dibatasi oleh scope akun.</p></div>
            @if ($hasFilters)<x-badge variant="info">Filter aktif</x-badge>@endif
        </div>

        <form method="GET" action="{{ route('revenue.index') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_1fr_auto]">
            <label class="space-y-1.5"><span class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Region</span><select name="region" class="min-h-11 w-full rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm font-semibold outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800"><option value="">Semua Region</option>@foreach ($regions as $region)<option value="{{ $region }}" @selected($filters['region'] === $region)>{{ $region }}</option>@endforeach</select></label>
            <label class="space-y-1.5"><span class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Branch</span><select name="branch" class="min-h-11 w-full rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm font-semibold outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800"><option value="">Semua Branch</option>@foreach ($branches->when($filters['region'], fn ($items) => $items->where('region', $filters['region'])) as $branch)<option value="{{ $branch->id_branch }}" @selected((int) $filters['branch'] === (int) $branch->id_branch)>{{ $branch->name }}</option>@endforeach</select></label>
            <label class="space-y-1.5"><span class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Service Area</span><select name="service_area" class="min-h-11 w-full rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm font-semibold outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800"><option value="">Semua Service Area</option>@foreach ($serviceAreas->when($selectedBranch, fn ($items) => $items->where('branch_id', $selectedBranch->id_branch)) as $serviceArea)<option value="{{ $serviceArea->id_service_area }}" @selected((int) $filters['service_area'] === (int) $serviceArea->id_service_area)>{{ $serviceArea->workzone }} · {{ $serviceArea->branch?->name }}</option>@endforeach</select></label>
            <label class="space-y-1.5"><span class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Program</span><select name="program" class="min-h-11 w-full rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm font-semibold outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800"><option value="">Semua Program</option>@foreach (\App\Enums\ProgramType::cases() as $program)<option value="{{ $program->value }}" @selected($filters['program'] === $program->value)>{{ $program->label() }}</option>@endforeach</select></label>
            <label class="space-y-1.5"><span class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Status LOP</span><select name="status" class="min-h-11 w-full rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm font-semibold outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800"><option value="">Semua Status</option>@foreach (\App\Enums\LopStatus::cases() as $status)<option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
            <div class="flex items-end gap-2 md:col-span-2 xl:col-span-1"><button class="min-h-11 flex-1 rounded-xl bg-ink-900 px-4 text-sm font-bold text-white transition hover:bg-ink-700 dark:bg-brand-600 dark:hover:bg-brand-700">Terapkan</button>@if ($hasFilters)<a href="{{ route('revenue.index') }}" title="Reset filter" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-ink-200 text-ink-500 transition hover:text-brand-600 dark:border-ink-700"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.02 9.35h5.25V4.1M20.1 8.1A9 9 0 1 0 21 12"/></svg></a>@endif</div>
        </form>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Nilai Usulan', $money($stats['proposal_value']), 'Total nilai BOQ dalam cakupan', 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'],
            ['Nilai Realisasi', $money($stats['actual_value']), 'BOQ dari LOP Completed', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'],
            ['GAP', $money($stats['gap_value']), 'Nilai yang belum terealisasi', 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'],
            ['Rasio', $stats['realization_percentage'].'%', 'Realisasi dibanding usulan', 'bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300'],
        ] as [$label, $value, $helper, $tone])
            <article class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900"><span class="inline-flex rounded-lg px-2 py-1 text-[9px] font-extrabold uppercase tracking-wider {{ $tone }}">{{ $label }}</span><p class="mt-3 break-words text-xl font-extrabold tracking-tight text-ink-900 dark:text-white sm:text-2xl">{{ $value }}</p><p class="mt-1 text-[10px] leading-4 text-ink-400">{{ $helper }}</p></article>
        @endforeach
    </section>

    <section class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div><h2 class="text-sm font-extrabold text-ink-900 dark:text-white">Nilai Usulan dan Realisasi per Branch</h2><p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Perbandingan nilai BOQ setiap branch sesuai filter aktif.</p></div>
            <div class="flex gap-3 text-[10px] font-bold text-ink-500"><span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm bg-blue-500"></i>Usulan</span><span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></i>Realisasi</span></div>
        </div>

        @if ($branchRows->isNotEmpty())
            <div class="mt-6 overflow-x-auto pb-2">
                <div style="min-width: {{ $branchChartWidth }}px">
                    <div class="mb-2 flex items-center justify-between text-[10px] text-ink-400"><span>Skala maksimum</span><strong class="text-ink-600 dark:text-ink-300">{{ $compactMoney($maxBranchValue) }}</strong></div>
                    <div class="relative h-72 border-b border-l border-ink-200 dark:border-ink-700">
                        @foreach ([25, 50, 75, 100] as $line)<span class="absolute left-0 right-0 border-t border-dashed border-ink-100 dark:border-ink-800" style="bottom: {{ $line }}%"></span>@endforeach
                        <div class="absolute inset-0 flex items-end gap-4 px-5">
                            @foreach ($branchRows as $row)
                                @php
                                    $proposalHeight = $row['proposal_value'] > 0 ? max(3, $row['proposal_bar_percentage']) : 0;
                                    $actualHeight = $row['actual_value'] > 0 ? max(3, $row['actual_bar_percentage']) : 0;
                                @endphp
                                <div class="flex h-full w-24 shrink-0 flex-col justify-end">
                                    <div class="flex min-h-0 flex-1 items-end justify-center gap-1.5">
                                        <div title="Usulan {{ $row['branch'] }}: {{ $money($row['proposal_value']) }}" class="w-7 rounded-t-md bg-blue-500 transition hover:bg-blue-600" style="height: {{ $proposalHeight }}%"></div>
                                        <div title="Realisasi {{ $row['branch'] }}: {{ $money($row['actual_value']) }}" class="w-7 rounded-t-md bg-emerald-500 transition hover:bg-emerald-600" style="height: {{ $actualHeight }}%"></div>
                                    </div>
                                    <p class="mt-2 h-9 text-center text-[9px] font-bold leading-3 text-ink-600 dark:text-ink-300">{{ $row['branch'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="mt-5 rounded-xl border border-dashed border-ink-200 px-5 py-12 text-center text-sm text-ink-400 dark:border-ink-700">Belum ada nilai BOQ pada cakupan ini.</div>
        @endif
    </section>

    <section class="overflow-hidden rounded-2xl border border-ink-100 bg-white shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <div class="border-b border-ink-100 px-5 py-4 dark:border-ink-800"><h2 class="text-sm font-extrabold text-ink-900 dark:text-white">Matrix Per Branch</h2><p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Ringkasan nilai usulan dan realisasi untuk setiap branch.</p></div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-180 text-left text-xs">
                <thead class="bg-ink-50 text-[10px] font-extrabold uppercase tracking-wider text-ink-500 dark:bg-ink-800"><tr><th class="px-5 py-3">Branch</th><th class="px-4 py-3 text-center">Total LOP</th><th class="px-4 py-3 text-right">Usulan</th><th class="px-4 py-3 text-right">Realisasi</th><th class="px-4 py-3 text-right">GAP</th><th class="px-5 py-3 text-center">Rasio</th></tr></thead>
                <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                    @forelse ($branchRows as $row)
                        <tr class="transition hover:bg-ink-50/70 dark:hover:bg-ink-800/40"><td class="px-5 py-4 font-extrabold text-ink-900 dark:text-white">{{ $row['branch'] }}</td><td class="px-4 py-4 text-center">{{ number_format($row['total_lops']) }}</td><td class="px-4 py-4 text-right font-semibold tabular-nums">{{ $money($row['proposal_value']) }}</td><td class="px-4 py-4 text-right font-extrabold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $money($row['actual_value']) }}</td><td class="px-4 py-4 text-right tabular-nums">{{ $money($row['gap_value']) }}</td><td class="px-5 py-4 text-center"><span class="rounded-full bg-emerald-50 px-2.5 py-1 font-extrabold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">{{ $row['realization_percentage'] }}%</span></td></tr>
                    @empty<tr><td colspan="6" class="px-6 py-12 text-center text-sm text-ink-400">Belum ada data Financial Overview.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <div><h2 class="text-sm font-extrabold text-ink-900 dark:text-white">Trend Nilai Usulan Berdasarkan Segmen</h2><p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Segmen diurutkan berdasarkan akumulasi nilai BOQ terbesar. LOP multi-segmen dihitung pada setiap segmen terkait.</p></div>
        <div class="mt-6 space-y-4">
            @forelse ($segmentRows as $row)
                <div class="grid gap-2 sm:grid-cols-[8rem_1fr_9rem] sm:items-center">
                    <div><p class="truncate text-xs font-extrabold text-ink-800 dark:text-ink-100">{{ $row['label'] }}</p><p class="mt-0.5 text-[9px] text-ink-400">{{ number_format($row['lop_count']) }} LOP</p></div>
                    <div class="h-3 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full rounded-full bg-violet-500" style="width: {{ $row['bar_percentage'] }}%"></div></div>
                    <p class="text-left text-[10px] font-extrabold tabular-nums text-violet-700 dark:text-violet-300 sm:text-right">{{ $money($row['proposal_value']) }}</p>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-ink-200 px-5 py-12 text-center text-sm text-ink-400 dark:border-ink-700">Belum ada data segmen dengan nilai BOQ.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
