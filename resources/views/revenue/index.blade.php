@extends('layouts.app')

@section('title', 'Revenue Overview')

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
    $maxBranchValue = max(1, (float) $branchRows->max(fn ($row) => max($row['plan_value'], $row['actual_value'])));
    $branchChartWidth = max(720, ($branchRows->count() * 112) + 40);
@endphp

<div class="mx-auto max-w-7xl space-y-6">
    <header class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-xs font-bold uppercase tracking-[.16em] text-brand-600 dark:text-brand-400">Revenue overview</p>
                <span class="h-1 w-1 rounded-full bg-ink-300 dark:bg-ink-600"></span>
                <span class="text-xs font-semibold text-ink-500 dark:text-ink-400">{{ $scopeLabel }}</span>
            </div>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-900 dark:text-white sm:text-3xl">Revenue Overview</h1>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-ink-500 dark:text-ink-400">Ringkasan nilai plan, realisasi, dan gap pekerjaan per program sesuai cakupan wilayah akun.</p>
        </div>
        <!-- <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-xs leading-5 text-blue-700 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-300">
            <strong>Metode:</strong> Plan hanya dihitung untuk QE Preventive dan QE Relok Utilitas. Realisasi memakai BOQ Plan atau reservasi aktual teknisi untuk LOP <strong>Completed</strong>. Reservasi JATIM/JATENG DIY memakai Paket 5 dan BALNUS memakai Paket 10; {{ $referencePackageLabel ?: 'paket terbaru' }} hanya menjadi fallback jika region belum terpetakan.
        </div> -->
    </header>

    @if ($scopeWarning)
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">Scope akun belum dikonfigurasi. Data Revenue Overview diamankan dan tidak ditampilkan.</section>
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
            ['Total Nilai Plan', $money($stats['plan_value']), 'Preventive + Relok Utilitas', 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'],
            ['Total Nilai Realisasi', $money($stats['actual_value']), 'Seluruh program berstatus Completed', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'],
            ['GAP', $money($stats['gap_value']), 'Sisa plan program terencana', 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'],
            ['Rasio', $stats['realization_percentage'] === null ? '—' : $stats['realization_percentage'].'%', 'Realisasi Preventive + Relok terhadap plan', 'bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300'],
        ] as [$label, $value, $helper, $tone])
            <article class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900"><span class="inline-flex rounded-lg px-2 py-1 text-[9px] font-extrabold uppercase tracking-wider {{ $tone }}">{{ $label }}</span><p class="mt-3 break-words text-xl font-extrabold tracking-tight text-ink-900 dark:text-white sm:text-2xl">{{ $value }}</p><p class="mt-1 text-[10px] leading-4 text-ink-400">{{ $helper }}</p></article>
        @endforeach
    </section>

    <section>
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div><h2 class="text-sm font-extrabold text-ink-900 dark:text-white">Revenue per Program</h2><p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Setiap program memiliki ringkasan dan grafik nilainya sendiri.</p></div>
            <div class="flex gap-3 text-[10px] font-bold text-ink-500"><span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm bg-blue-500"></i>Plan</span><span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></i>Realisasi</span></div>
        </div>
        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ($programRows as $row)
                <article class="overflow-hidden rounded-2xl border border-ink-100 bg-white shadow-sm dark:border-ink-800 dark:bg-ink-900">
                    <div class="flex items-start justify-between gap-3 border-b border-ink-100 p-5 dark:border-ink-800">
                        <div><p class="text-[10px] font-extrabold uppercase tracking-[.14em] text-brand-600 dark:text-brand-400">Program</p><h3 class="mt-1 text-base font-extrabold text-ink-900 dark:text-white">{{ $row['label'] }}</h3></div>
                        <span class="rounded-full bg-ink-100 px-2.5 py-1 text-[10px] font-extrabold text-ink-600 dark:bg-ink-800 dark:text-ink-300">{{ number_format($row['total_lops']) }} LOP</span>
                    </div>
                    <div class="p-5">
                        @if ($row['has_plan'])
                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-xl bg-blue-50 p-3 dark:bg-blue-950/30"><p class="text-[9px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-300">Nilai Plan</p><p class="mt-1 text-sm font-extrabold text-blue-900 dark:text-blue-100">{{ $money($row['plan_value']) }}</p></div>
                                <div class="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/30"><p class="text-[9px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-300">Realisasi</p><p class="mt-1 text-sm font-extrabold text-emerald-900 dark:text-emerald-100">{{ $money($row['actual_value']) }}</p></div>
                            </div>
                            <div class="mt-5 space-y-3">
                                <div><div class="mb-1.5 flex justify-between text-[9px] font-bold text-ink-500"><span>Plan</span><span>{{ $compactMoney($row['plan_value']) }}</span></div><div class="h-3 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full rounded-full bg-blue-500" style="width: {{ $row['plan_value'] > 0 ? max(2, $row['plan_bar_percentage']) : 0 }}%"></div></div></div>
                                <div><div class="mb-1.5 flex justify-between text-[9px] font-bold text-ink-500"><span>Realisasi</span><span>{{ $compactMoney($row['actual_value']) }}</span></div><div class="h-3 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $row['actual_value'] > 0 ? max(2, $row['actual_bar_percentage']) : 0 }}%"></div></div></div>
                            </div>
                            <div class="mt-5 grid grid-cols-2 gap-3 border-t border-ink-100 pt-4 text-xs dark:border-ink-800"><div><p class="text-[9px] font-bold uppercase text-ink-400">GAP</p><p class="mt-1 font-extrabold text-amber-600 dark:text-amber-400">{{ $money($row['gap_value']) }}</p></div><div class="text-right"><p class="text-[9px] font-bold uppercase text-ink-400">Rasio</p><p class="mt-1 font-extrabold text-violet-600 dark:text-violet-400">{{ $row['realization_percentage'] === null ? '—' : $row['realization_percentage'].'%' }}</p></div></div>
                        @else
                            <div class="rounded-xl bg-emerald-50 p-4 dark:bg-emerald-950/30"><p class="text-[9px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-300">Nilai Realisasi</p><p class="mt-2 text-xl font-extrabold text-emerald-900 dark:text-emerald-100">{{ $money($row['actual_value']) }}</p><p class="mt-1 text-[10px] text-emerald-700/70 dark:text-emerald-300/70">QE Recovery tidak menggunakan nilai plan.</p></div>
                            <div class="mt-5"><div class="mb-1.5 flex justify-between text-[9px] font-bold text-ink-500"><span>Realisasi Completed</span><span>{{ $compactMoney($row['actual_value']) }}</span></div><div class="h-4 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $row['actual_value'] > 0 ? max(2, $row['actual_bar_percentage']) : 0 }}%"></div></div></div>
                            <div class="mt-5 border-t border-ink-100 pt-4 text-[10px] leading-5 text-ink-400 dark:border-ink-800">Nilai berasal dari LOP QE Recovery berstatus Completed pada scope aktif.</div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div><h2 class="text-sm font-extrabold text-ink-900 dark:text-white">Nilai Plan dan Realisasi per Branch</h2><p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Perbandingan nilai BOQ setiap branch sesuai filter aktif.</p></div>
            <div class="flex gap-3 text-[10px] font-bold text-ink-500"><span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm bg-blue-500"></i>Plan</span><span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></i>Realisasi</span></div>
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
                                    $planHeight = $row['plan_value'] > 0 ? max(3, $row['plan_bar_percentage']) : 0;
                                    $actualHeight = $row['actual_value'] > 0 ? max(3, $row['actual_bar_percentage']) : 0;
                                @endphp
                                <div class="flex h-full w-24 shrink-0 flex-col justify-end">
                                    <div class="flex min-h-0 flex-1 items-end justify-center gap-1.5">
                                        <div title="Plan {{ $row['branch'] }}: {{ $money($row['plan_value']) }}" class="w-7 rounded-t-md bg-blue-500 transition hover:bg-blue-600" style="height: {{ $planHeight }}%"></div>
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
        <div class="border-b border-ink-100 px-5 py-4 dark:border-ink-800"><h2 class="text-sm font-extrabold text-ink-900 dark:text-white">Matrix Per Branch</h2><p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Seluruh branch pada scope ditampilkan. Buka branch untuk melihat breakdown setiap program.</p></div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-180 text-left text-xs">
                <thead class="bg-ink-50 text-[10px] font-extrabold uppercase tracking-wider text-ink-500 dark:bg-ink-800"><tr><th class="px-5 py-3">Branch</th><th class="px-4 py-3 text-center">Total LOP</th><th class="px-4 py-3 text-right">Plan</th><th class="px-4 py-3 text-right">Realisasi</th><th class="px-4 py-3 text-right">GAP</th><th class="px-5 py-3 text-center">Rasio</th></tr></thead>
                @forelse ($branchRows as $row)
                    <tbody x-data="{ open: false }" class="border-b border-ink-100 last:border-b-0 dark:border-ink-800">
                        <tr @click="open = !open" class="cursor-pointer transition hover:bg-ink-50/70 dark:hover:bg-ink-800/40">
                            <td class="px-5 py-4"><button type="button" @click.stop="open = !open" :aria-expanded="open" class="flex items-center gap-2 text-left font-extrabold text-ink-900 dark:text-white"><svg class="h-4 w-4 shrink-0 text-ink-400 transition-transform" :class="open && 'rotate-90'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg><span>{{ $row['branch'] }}</span></button><span class="ml-6 mt-1 block text-[9px] font-semibold text-ink-400">{{ $row['region'] }}</span></td>
                            <td class="px-4 py-4 text-center font-bold">{{ number_format($row['total_lops']) }}</td><td class="px-4 py-4 text-right font-semibold tabular-nums">{{ $money($row['plan_value']) }}</td><td class="px-4 py-4 text-right font-extrabold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $money($row['actual_value']) }}</td><td class="px-4 py-4 text-right tabular-nums">{{ $money($row['gap_value']) }}</td><td class="px-5 py-4 text-center"><span class="rounded-full bg-emerald-50 px-2.5 py-1 font-extrabold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">{{ $row['realization_percentage'] === null ? '—' : $row['realization_percentage'].'%' }}</span></td>
                        </tr>
                        <tr x-show="open" x-cloak x-transition.opacity>
                            <td colspan="6" class="bg-ink-50/70 px-5 py-4 dark:bg-ink-950/40">
                                <div class="overflow-hidden rounded-xl border border-ink-200 bg-white dark:border-ink-700 dark:bg-ink-900">
                                    <div class="border-b border-ink-100 px-4 py-3 text-[10px] font-extrabold uppercase tracking-wider text-ink-500 dark:border-ink-800">Breakdown Program · {{ $row['branch'] }}</div>
                                    <div class="overflow-x-auto"><table class="min-w-full text-xs"><thead class="bg-ink-50 text-[9px] font-bold uppercase tracking-wider text-ink-400 dark:bg-ink-800/70"><tr><th class="px-4 py-2.5 text-left">Program</th><th class="px-4 py-2.5 text-center">Total LOP</th><th class="px-4 py-2.5 text-right">Plan</th><th class="px-4 py-2.5 text-right">Realisasi</th><th class="px-4 py-2.5 text-right">GAP</th><th class="px-4 py-2.5 text-center">Rasio</th></tr></thead><tbody class="divide-y divide-ink-100 dark:divide-ink-800">@foreach ($row['programs'] as $program)<tr><td class="px-4 py-3 font-extrabold text-ink-800 dark:text-ink-100">{{ $program['label'] }}</td><td class="px-4 py-3 text-center">{{ number_format($program['total_lops']) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $program['has_plan'] ? $money($program['plan_value']) : '—' }}</td><td class="px-4 py-3 text-right font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $money($program['actual_value']) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ $program['has_plan'] ? $money($program['gap_value']) : '—' }}</td><td class="px-4 py-3 text-center font-bold">{{ $program['has_plan'] && $program['realization_percentage'] !== null ? $program['realization_percentage'].'%' : '—' }}</td></tr>@endforeach</tbody></table></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody><tr><td colspan="6" class="px-6 py-12 text-center text-sm text-ink-400">Belum ada branch dalam scope Revenue Overview.</td></tr></tbody>
                @endforelse
            </table>
        </div>
    </section>

    <section class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <div><h2 class="text-sm font-extrabold text-ink-900 dark:text-white">Trend Nilai Plan Berdasarkan Segmen</h2><p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Hanya menampilkan plan QE Preventive dan QE Relok Utilitas. LOP multi-segmen dihitung pada setiap segmen terkait.</p></div>
        <div class="mt-6 space-y-4">
            @forelse ($segmentRows as $row)
                <div class="grid gap-2 sm:grid-cols-[8rem_1fr_9rem] sm:items-center">
                    <div><p class="truncate text-xs font-extrabold text-ink-800 dark:text-ink-100">{{ $row['label'] }}</p><p class="mt-0.5 text-[9px] text-ink-400">{{ number_format($row['lop_count']) }} LOP</p></div>
                    <div class="h-3 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full rounded-full bg-violet-500" style="width: {{ $row['bar_percentage'] }}%"></div></div>
                    <p class="text-left text-[10px] font-extrabold tabular-nums text-violet-700 dark:text-violet-300 sm:text-right">{{ $money($row['plan_value']) }}</p>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-ink-200 px-5 py-12 text-center text-sm text-ink-400 dark:border-ink-700">Belum ada data segmen dengan nilai BOQ.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
