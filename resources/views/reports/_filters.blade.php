{{--
    Filter bar laporan. Di-include dari reports/boq-actual & reports/sisa-material.
    Var dari controller: $report, $mode, $filters, $packages, $programs, $segments,
    $statuses, $canFilterLocation, $regions, $branches, $regionFilter, $branchFilter.
--}}
@php
    $reportRoute = $report === 'boq' ? 'reports.boq-actual' : 'reports.sisa-material';
    $exportRoute = $reportRoute . '.export';
    $carry = request()->query();

    $advancedCount = collect([
        $filters['program'],
        $filters['segment'],
        count($filters['status']) ? 'y' : null,
        $filters['date_from'],
        $filters['date_to'],
        $regionFilter ?: null,
        $branchFilter ?: null,
    ])->filter()->count();

    $fieldClass = 'min-h-10 w-full rounded-xl border border-ink-200 bg-white px-3 text-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800 dark:text-white';
@endphp

<div class="no-print" x-data="{ adv: {{ $advancedCount > 0 ? 'true' : 'false' }}, exp: false }">
    <form method="GET" action="{{ route($reportRoute) }}" class="rounded-2xl border border-ink-100 bg-white p-3 shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <input type="hidden" name="view" value="{{ $mode }}">

        {{-- Baris utama --}}
        <div class="flex flex-wrap items-center gap-2">
            <label class="relative min-w-[200px] flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z" /></svg>
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Cari LOP, incident, atau STO…"
                       class="{{ $fieldClass }} pl-9">
            </label>

            <select name="package" onchange="this.form.submit()" class="{{ $fieldClass }} w-auto min-w-[180px] font-semibold">
                <option value="">Tanpa harga (qty saja)</option>
                @foreach ($packages as $pkg)
                    <option value="{{ $pkg->id_package }}" @selected((int) $filters['package'] === (int) $pkg->id_package)>Paket: {{ $pkg->name }}</option>
                @endforeach
            </select>

            <button type="button" @click="adv = !adv"
                    class="inline-flex min-h-10 items-center gap-1.5 rounded-xl border px-3 text-sm font-semibold transition
                           {{ $advancedCount > 0 ? 'border-brand-300 bg-brand-50 text-brand-700 dark:border-brand-800 dark:bg-brand-950/40 dark:text-brand-300' : 'border-ink-200 text-ink-600 hover:bg-ink-50 dark:border-ink-700 dark:text-ink-300 dark:hover:bg-ink-800' }}">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12h12M3 6h18M9 18h6" /></svg>
                Filter
                @if ($advancedCount > 0)<span class="grid h-5 min-w-5 place-items-center rounded-full bg-brand-600 px-1 text-[11px] font-bold text-white">{{ $advancedCount }}</span>@endif
                <svg class="h-3.5 w-3.5 transition" :class="adv && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
            </button>

            <button type="submit" class="min-h-10 rounded-xl bg-brand-600 px-4 text-sm font-bold text-white transition hover:bg-brand-700">Terapkan</button>

            {{-- Ekspor --}}
            <div class="relative" @click.outside="exp = false">
                <button type="button" @click="exp = !exp"
                        class="inline-flex min-h-10 items-center gap-1.5 rounded-xl border border-ink-200 px-3 text-sm font-semibold text-ink-600 transition hover:bg-ink-50 dark:border-ink-700 dark:text-ink-300 dark:hover:bg-ink-800">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    Ekspor
                </button>
                <div x-show="exp" x-cloak x-transition
                     class="absolute right-0 z-30 mt-1.5 w-44 overflow-hidden rounded-xl border border-ink-100 bg-white py-1 shadow-xl dark:border-ink-700 dark:bg-ink-800">
                    <a href="{{ route($exportRoute, array_merge($carry, ['format' => 'csv'])) }}" class="block px-3 py-2 text-sm text-ink-700 hover:bg-ink-50 dark:text-ink-200 dark:hover:bg-ink-700">Unduh CSV</a>
                    <a href="{{ route($exportRoute, array_merge($carry, ['format' => 'xlsx'])) }}" class="block px-3 py-2 text-sm text-ink-700 hover:bg-ink-50 dark:text-ink-200 dark:hover:bg-ink-700">Unduh Excel (.xlsx)</a>
                    <a href="{{ route($reportRoute, array_merge($carry, ['print' => 1])) }}" target="_blank" rel="noopener" class="block px-3 py-2 text-sm text-ink-700 hover:bg-ink-50 dark:text-ink-200 dark:hover:bg-ink-700">Cetak / PDF</a>
                </div>
            </div>
        </div>

        {{-- Filter lanjutan --}}
        <div x-show="adv" x-cloak x-transition class="mt-3 grid gap-3 border-t border-ink-100 pt-3 dark:border-ink-800 sm:grid-cols-2 lg:grid-cols-3">
            <label class="block">
                <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-ink-400">Program</span>
                <select name="program" class="{{ $fieldClass }}">
                    <option value="">Semua Program</option>
                    @foreach ($programs as $p)
                        <option value="{{ $p->value }}" @selected($filters['program'] === $p->value)>{{ $p->label() }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-ink-400">Segmen</span>
                <select name="segment" class="{{ $fieldClass }}">
                    <option value="">Semua Segmen</option>
                    @foreach ($segments as $s)
                        <option value="{{ $s->value }}" @selected($filters['segment'] === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </label>

            <div>
                <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-ink-400">Status LOP</span>
                <div class="flex flex-wrap gap-2">
                    @foreach ($statuses as $st)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="status[]" value="{{ $st->value }}" class="peer sr-only" @checked(in_array($st->value, $filters['status'], true))>
                            <span class="inline-flex min-h-10 items-center rounded-xl border border-ink-200 px-3 text-sm font-semibold text-ink-500 transition peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700 dark:border-ink-700 dark:text-ink-300 dark:peer-checked:bg-brand-950/40 dark:peer-checked:text-brand-300">
                                {{ $st->label() }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <label class="block">
                <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-ink-400">Submit dari</span>
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="{{ $fieldClass }}">
            </label>
            <label class="block">
                <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-ink-400">Submit sampai</span>
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="{{ $fieldClass }}">
            </label>

            @if ($canFilterLocation)
                <label class="block">
                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-ink-400">Region</span>
                    <select name="region" onchange="this.form.branch.value=''" class="{{ $fieldClass }}">
                        <option value="">Semua Region</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region }}" @selected($regionFilter === $region)>{{ $region }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-ink-400">Branch</span>
                    <select name="branch" class="{{ $fieldClass }}">
                        <option value="">Semua Branch</option>
                        @foreach ($branches->when($regionFilter, fn ($items) => $items->where('region', $regionFilter)) as $b)
                            <option value="{{ $b->name }}" @selected($branchFilter === $b->name)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-3">
                <button type="submit" class="min-h-10 rounded-xl bg-brand-600 px-4 text-sm font-bold text-white transition hover:bg-brand-700">Terapkan filter</button>
                @if ($advancedCount > 0 || $filters['q'] || $filters['package'])
                    <a href="{{ route($reportRoute, ['view' => $mode]) }}" class="min-h-10 rounded-xl border border-ink-200 px-4 py-2 text-sm font-semibold text-ink-500 dark:border-ink-700">Reset semua</a>
                @endif
            </div>
        </div>
    </form>
</div>
