@extends('layouts.app')

@section('title', 'Pemetaan Program')

@section('content')
@php
    $carry = array_filter([
        'region' => $regionFilter,
        'branch' => $branchFilter,
    ], fn ($v) => $v !== '' && $v !== null);

@endphp

<div class="mx-auto max-w-6xl space-y-6">
    <header class="flex flex-col gap-4 border-b border-ink-200 pb-5 dark:border-ink-800 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.14em] text-brand-700 dark:text-brand-300">Monitoring <span class="px-1 text-ink-300">/</span> Pemetaan Program</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-ink-900 dark:text-white">Pemetaan Program</h1>
            <p class="mt-1.5 text-sm text-ink-500 dark:text-ink-400">Bandingkan jumlah LOP dan status pekerjaan di setiap program.</p>
        </div>
        <div class="flex items-center gap-2 self-start rounded-md border border-ink-200 bg-white px-3 py-2 text-xs dark:border-ink-700 dark:bg-ink-900 sm:self-auto">
            <span class="h-2 w-2 rounded-full bg-brand-500"></span>
            <span class="text-ink-500 dark:text-ink-400">Scope</span>
            <span class="font-semibold text-ink-800 dark:text-ink-100">{{ $scopeLabel }}</span>
        </div>
    </header>

    @if ($scopeWarning)
        <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300">
            <span class="mt-0.5 font-bold">!</span>
            <p>Akun Anda belum terhubung ke branch, jadi tidak ada data Program yang bisa ditampilkan. Hubungi admin untuk mengatur branch akun Anda.</p>
        </div>
    @endif

    @if ($canFilterLocation)
        <form method="GET" action="{{ route('program.index') }}" class="grid gap-3 rounded-lg border border-ink-200 bg-white p-4 dark:border-ink-700 dark:bg-ink-900 sm:grid-cols-[minmax(12rem,1fr)_minmax(12rem,1fr)_auto] sm:items-end">
            <div class="sm:col-span-3"><h2 class="text-sm font-semibold text-ink-900 dark:text-white">Cakupan lokasi</h2><p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">Pilih region atau branch untuk mempersempit ringkasan.</p></div>
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
            <div class="flex gap-2">
                <button class="min-h-10 rounded-md bg-ink-800 px-4 text-sm font-semibold text-white transition hover:bg-ink-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 dark:bg-ink-100 dark:text-ink-900 dark:hover:bg-white">Terapkan</button>
                @if ($regionFilter || $branchFilter)
                    <a href="{{ route('program.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-ink-300 px-3 text-sm font-semibold text-ink-600 hover:bg-ink-50 dark:border-ink-700 dark:text-ink-300 dark:hover:bg-ink-800">Reset</a>
                @endif
            </div>
        </form>
    @endif

    <section aria-label="Ringkasan per program" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($programSummaries as $program)
            <a href="{{ route('program.show', [$program['slug'], ...$carry]) }}" class="group flex min-h-full flex-col overflow-hidden rounded-lg border border-ink-200 bg-white transition hover:border-ink-300 hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:border-ink-700 dark:bg-ink-900 dark:hover:border-ink-600">
                <div class="flex flex-1 flex-col p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-ink-100 text-[10px] font-bold text-ink-500 dark:bg-ink-800 dark:text-ink-300">QE</span>
                            <div class="min-w-0"><h2 class="text-sm font-bold text-ink-900 dark:text-white">{{ $program['label'] }}</h2><p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">Jumlah LOP</p></div>
                        </div>
                        <span class="text-3xl font-bold leading-none tabular-nums tracking-tight text-ink-900 dark:text-white">{{ number_format($program['total']) }}</span>
                    </div>

                    <dl class="mt-5 grid grid-cols-2 gap-x-5 gap-y-2.5 border-t border-ink-100 pt-4 dark:border-ink-800">
                        @foreach ($program['buckets'] as $key => $count)
                            <div class="flex min-w-0 items-center justify-between gap-2 text-xs">
                                <dt class="min-w-0 truncate text-ink-500 dark:text-ink-400">{{ \App\Http\Controllers\ProgramController::BUCKETS[$key]['label'] }}</dt>
                                <dd class="font-semibold tabular-nums text-ink-800 dark:text-ink-200">{{ number_format($count) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
                <div class="flex items-center justify-between border-t border-ink-100 px-5 py-3 text-xs dark:border-ink-800">
                    <span class="font-semibold text-brand-700 dark:text-brand-300">Buka ringkasan</span>
                    <svg aria-hidden="true" class="h-4 w-4 text-ink-400 transition-transform group-hover:translate-x-1 group-hover:text-ink-700 dark:group-hover:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0-6-6m6 6-6 6"/></svg>
                </div>
            </a>
        @endforeach
    </section>
</div>
@endsection
