@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $hasFilters = collect($filters)->contains(fn ($value) => $value !== '');
@endphp

<div class="mx-auto max-w-7xl space-y-6">
    <header class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-xs font-bold uppercase tracking-[.16em] text-brand-600 dark:text-brand-400">Operational overview</p>
                <span class="h-1 w-1 rounded-full bg-ink-300 dark:bg-ink-600"></span>
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s6.75-4.35 6.75-11.25a6.75 6.75 0 1 0-13.5 0C5.25 16.65 12 21 12 21Z"/><circle cx="12" cy="9.75" r="2.25"/></svg>
                    {{ $scopeLabel }}
                </span>
            </div>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-900 dark:text-white sm:text-3xl">Dashboard Operasional</h1>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-ink-500 dark:text-ink-400">
                Ringkasan kondisi LOP, kesiapan pekerjaan, dan proses approval berdasarkan data terkini.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @can('create', \App\Models\QeLop::class)
                <a href="{{ route('lop.create') }}" class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-brand-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-brand-700">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Input LOP
                </a>
            @endcan
            @if (auth()->user()?->hasPermission('approve_evidence'))
                <a href="{{ route('evidence-approval.index') }}" class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-ink-200 bg-white px-4 text-sm font-bold text-ink-700 transition hover:border-brand-200 hover:text-brand-700 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200 dark:hover:border-brand-800 dark:hover:text-brand-300">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 12.75 2.25 2.25L15 9.75m-3-7.036A11.96 11.96 0 0 1 3.6 6 12 12 0 0 0 3 9.75c0 5.59 3.82 10.29 9 11.62 5.18-1.33 9-6.03 9-11.62 0-1.31-.21-2.57-.6-3.75h-.15c-3.2 0-6.1-1.25-8.25-3.29Z"/></svg>
                    Approval Evidence
                </a>
            @endif
        </div>
    </header>

    @if ($scopeWarning)
        <section class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/60 dark:bg-amber-950/30">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9.3 3.75L14.6 4.9a3 3 0 0 0-5.2 0L2.7 16.5A3 3 0 0 0 5.3 21h13.4a3 3 0 0 0 2.6-4.5ZM12 16.5h.01"/></svg>
            </span>
            <div>
                <p class="text-sm font-bold text-amber-900 dark:text-amber-200">Branch akun belum dikonfigurasi</p>
                <p class="mt-1 text-xs leading-5 text-amber-700 dark:text-amber-300">Dashboard diamankan tanpa menampilkan LOP. Hubungi Super Admin untuk menghubungkan akun ini ke branch yang sesuai.</p>
            </div>
        </section>
    @endif

    @if ($isSuperAdmin)
        <section class="rounded-2xl border border-ink-100 bg-white p-4 shadow-sm dark:border-ink-800 dark:bg-ink-900 sm:p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-extrabold text-ink-900 dark:text-white">Filter Dashboard</h2>
                    <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Seluruh widget, grafik, dan daftar prioritas mengikuti filter berikut.</p>
                </div>
                @if ($hasFilters)
                    <span class="rounded-full bg-brand-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-brand-700 dark:bg-brand-950/40 dark:text-brand-300">Filter aktif</span>
                @endif
            </div>

            <form method="GET" action="{{ route('dashboard') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_auto]">
                <label class="space-y-1.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Region</span>
                    <select name="region" onchange="this.form.submit()" class="min-h-11 w-full rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm font-semibold outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800">
                        <option value="">Semua Region</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region }}" @selected($filters['region'] === $region)>{{ $region }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Branch</span>
                    <select name="branch" class="min-h-11 w-full rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm font-semibold outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800">
                        <option value="">Semua Branch</option>
                        @foreach ($branches->when($filters['region'], fn ($items) => $items->where('region', $filters['region'])) as $branch)
                            <option value="{{ $branch->name }}" @selected($filters['branch'] === $branch->name)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Program</span>
                    <select name="program" class="min-h-11 w-full rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm font-semibold outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800">
                        <option value="">Semua Program</option>
                        @foreach (\App\Enums\ProgramType::cases() as $program)
                            <option value="{{ $program->value }}" @selected($filters['program'] === $program->value)>{{ $program->label() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Status LOP</span>
                    <select name="status" class="min-h-11 w-full rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm font-semibold outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800">
                        <option value="">Semua Status</option>
                        @foreach (\App\Enums\LopStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="flex items-end gap-2 md:col-span-2 xl:col-span-1">
                    <button class="min-h-11 flex-1 rounded-xl bg-ink-900 px-4 text-sm font-bold text-white transition hover:bg-ink-700 dark:bg-brand-600 dark:hover:bg-brand-700">Terapkan</button>
                    @if ($hasFilters)
                        <a href="{{ route('dashboard') }}" title="Reset filter" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-ink-200 text-ink-500 transition hover:border-brand-200 hover:text-brand-600 dark:border-ink-700 dark:hover:border-brand-800">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.02 9.35h5.25V4.1M20.1 8.1A9 9 0 1 0 21 12"/></svg>
                        </a>
                    @endif
                </div>
            </form>
        </section>
    @else
        <section class="flex flex-col gap-3 rounded-2xl border border-blue-100 bg-blue-50 p-4 dark:border-blue-900/50 dark:bg-blue-950/20 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21V4.5A1.5 1.5 0 0 1 5.25 3h9A1.5 1.5 0 0 1 15.75 4.5V21m-12 0h16.5m-13.5-14.25h.01m3-.01h.01m3-.01h.01m-6.01 3.02h.01m3-.01h.01m3-.01h.01M9 21v-4.5h1.5V21"/></svg>
                </span>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-300">Cakupan data Admin</p>
                    <p class="mt-1 text-sm font-extrabold text-ink-900 dark:text-white">Branch {{ $scopeLabel }}</p>
                </div>
            </div>
            <p class="text-xs text-blue-700 dark:text-blue-300">Data lintas branch tidak ditampilkan.</p>
        </section>
    @endif

    <section class="grid grid-cols-2 gap-3 xl:grid-cols-5">
        @foreach ([
            ['total', 'Total LOP', $stats['total'], 'Seluruh project dalam cakupan', 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'],
            ['active', 'LOP Aktif', $stats['active'], 'Belum berstatus completed', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'],
            ['review', 'Waiting Review', $stats['waiting_review'], 'Menunggu approval evidence', 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'],
            ['completed', 'Completed', $stats['completed'], 'Pekerjaan telah diselesaikan', 'bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300'],
            ['ihld', 'Belum Ada ID IHLD', $stats['missing_ihld'], $stats['ihld_completion_percentage'].'% data IHLD lengkap', 'bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300'],
        ] as [$key, $label, $value, $helper, $tone])
            <article class="group rounded-2xl border border-ink-100 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-ink-200 hover:shadow-md dark:border-ink-800 dark:bg-ink-900 dark:hover:border-ink-700 sm:p-5 {{ $loop->last ? 'col-span-2 xl:col-span-1' : '' }}">
                <div class="flex items-start justify-between gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-xl {{ $tone }}">
                        @switch($key)
                            @case('total')<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 12 3l9 4.5-9 4.5-9-4.5Zm0 4.5 9 4.5 9-4.5M3 16.5l9 4.5 9-4.5"/></svg>@break
                            @case('active')<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75 9 17.25 19.5 6.75"/></svg>@break
                            @case('review')<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7.5V12l3 1.5"/></svg>@break
                            @case('completed')<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m6-3.75A12 12 0 0 1 12 2.7 12 12 0 0 1 3 6v3.75c0 5.59 3.82 10.29 9 11.62 5.18-1.33 9-6.03 9-11.62V6Z"/></svg>@break
                            @default<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.75h4.5m-7.5 0h10.5A1.5 1.5 0 0 1 18.75 5.25v15H5.25v-15a1.5 1.5 0 0 1 1.5-1.5ZM8.25 9h7.5m-7.5 4.5h4.5"/></svg>
                        @endswitch
                    </span>
                    <strong class="text-2xl font-extrabold tracking-tight text-ink-900 dark:text-white sm:text-3xl">{{ number_format($value) }}</strong>
                </div>
                <p class="mt-4 text-xs font-extrabold text-ink-800 dark:text-ink-100 sm:text-sm">{{ $label }}</p>
                <p class="mt-1 text-[10px] leading-4 text-ink-400 dark:text-ink-500">{{ $helper }}</p>
            </article>
        @endforeach
    </section>

    <section class="space-y-6">
        <article class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-sm font-extrabold text-ink-900 dark:text-white">Matrix Program</h2>
                    <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Total, assignment aktif, review, dan penyelesaian setiap Program.</p>
                </div>
                <div class="flex flex-wrap gap-2 text-[10px] font-semibold text-ink-500 dark:text-ink-400"><span class="rounded-lg bg-ink-50 px-2.5 py-1 dark:bg-ink-800">Assign = teknisi aktif</span><span class="rounded-lg bg-ink-50 px-2.5 py-1 dark:bg-ink-800">Persentase = Complete ÷ Total</span></div>
            </div>

            <div class="mt-5 space-y-3">
                @forelse ($matrixRegions as $region)
                    @php($groupLabel = $isSuperAdmin ? $region['name'] : ($region['branches'][0]['name'] ?? $scopeLabel))
                    <div class="overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm dark:border-emerald-900 dark:bg-ink-900">
                        <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6h16.5M3.75 12h16.5m-16.5 6h16.5"/></svg>
                                </span>
                                <div class="min-w-0"><p class="truncate text-sm font-extrabold text-ink-900 dark:text-white">{{ $groupLabel }}</p><p class="mt-1 text-[10px] text-ink-400">Ringkasan performa {{ count($region['branches']) }} branch</p></div>
                            </div>
                            <div class="grid grid-cols-4 gap-2 sm:min-w-[420px]">
                                @foreach ([
                                    ['Total', $region['summary']['total'], 'text-ink-900 dark:text-white'],
                                    ['Assign', $region['summary']['assigned'], 'text-blue-700 dark:text-blue-300'],
                                    ['In Review', $region['summary']['in_review'], 'text-amber-700 dark:text-amber-300'],
                                    ['Complete', $region['summary']['percentage'].'%', 'text-emerald-700 dark:text-emerald-300'],
                                ] as [$label, $value, $tone])
                                    <div class="rounded-xl bg-ink-50 px-2 py-2 text-center dark:bg-ink-800"><p class="text-sm font-extrabold {{ $tone }}">{{ $value }}</p><p class="mt-0.5 text-[8px] font-bold uppercase tracking-wide text-ink-400">{{ $label }}</p></div>
                                @endforeach
                            </div>
                        </div>

                        <div class="border-t border-ink-100 dark:border-ink-800">
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[720px] border-separate border-spacing-0 text-xs">
                                    <thead class="bg-ink-50 dark:bg-ink-800/80">
                                        <tr>
                                            <th class="sticky left-0 z-10 min-w-52 border-b border-r border-ink-200 bg-ink-50 px-4 py-3 text-left text-[10px] font-extrabold uppercase tracking-wider text-ink-500 dark:border-ink-700 dark:bg-ink-800">Branch / Program</th>
                                            @foreach (['Total LOP', 'Assign', 'In Review', 'Complete', 'Persentase'] as $heading)<th class="min-w-24 border-b border-r border-ink-200 px-3 py-3 text-center text-[10px] font-extrabold uppercase tracking-wider text-ink-500 last:border-r-0 dark:border-ink-700">{{ $heading }}</th>@endforeach
                                        </tr>
                                    </thead>
                                    @foreach ($region['branches'] as $branch)
                                        <tbody x-data="{ branchOpen: {{ ! $isSuperAdmin ? 'true' : 'false' }} }" class="divide-y divide-ink-100 dark:divide-ink-800">
                                            <tr class="bg-emerald-50/70 font-bold dark:bg-emerald-950/20">
                                                <td class="sticky left-0 z-10 border-r border-ink-200 bg-emerald-50 px-3 py-2.5 text-emerald-800 dark:border-ink-700 dark:bg-ink-900 dark:text-emerald-300">
                                                    <button type="button" @click="branchOpen = !branchOpen" :aria-expanded="branchOpen" class="flex w-full items-center gap-2 rounded-lg px-1 py-1 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40">
                                                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-emerald-100 dark:bg-emerald-900/50"><svg class="h-3 w-3 transition-transform" :class="branchOpen ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5.25 6.75 6.75L9 18.75"/></svg></span>
                                                        <span>{{ $branch['name'] }}</span><span class="ml-auto text-[9px] font-semibold text-emerald-500">{{ count($branch['program']) }} Program</span>
                                                    </button>
                                                </td>
                                                <td class="border-r border-ink-200 px-3 py-3 text-center dark:border-ink-700">{{ $branch['summary']['total'] }}</td>
                                                <td class="border-r border-ink-200 px-3 py-3 text-center dark:border-ink-700">{{ $branch['summary']['assigned'] }}</td>
                                                <td class="border-r border-ink-200 px-3 py-3 text-center dark:border-ink-700">{{ $branch['summary']['in_review'] }}</td>
                                                <td class="border-r border-ink-200 px-3 py-3 text-center dark:border-ink-700">{{ $branch['summary']['complete'] }}</td>
                                                <td class="px-3 py-3 text-center"><span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-extrabold text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200">{{ $branch['summary']['percentage'] }}%</span></td>
                                            </tr>
                                            @foreach ($branch['program'] as $program)
                                                <tr x-show="branchOpen" x-cloak class="transition hover:bg-ink-50 dark:hover:bg-ink-800/40">
                                                    <td class="sticky left-0 z-10 border-r border-ink-100 bg-white px-5 py-3 font-semibold text-ink-600 dark:border-ink-800 dark:bg-ink-900 dark:text-ink-300"><span class="mr-2 text-ink-300 dark:text-ink-700">•</span>{{ $program['label'] }}</td>
                                                    <td class="border-r border-ink-100 px-3 py-3 text-center font-semibold dark:border-ink-800">{{ $program['total'] }}</td>
                                                    <td class="border-r border-ink-100 px-3 py-3 text-center text-ink-600 dark:border-ink-800 dark:text-ink-300">{{ $program['assigned'] }}</td>
                                                    <td class="border-r border-ink-100 px-3 py-3 text-center text-ink-600 dark:border-ink-800 dark:text-ink-300">{{ $program['in_review'] }}</td>
                                                    <td class="border-r border-ink-100 px-3 py-3 text-center text-ink-600 dark:border-ink-800 dark:text-ink-300">{{ $program['complete']  }}</td>
                                                    <td class="px-3 py-3 text-center"><span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold {{ $program['percentage'] === 100 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-ink-50 text-ink-600 dark:bg-ink-800 dark:text-ink-300' }}">{{ $program['percentage'] }}%</span></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    @endforeach
                                </table>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-ink-200 px-6 py-12 text-center text-sm text-ink-400 dark:border-ink-700">Tidak ada data matrix pada cakupan ini.</div>
                @endforelse
            </div>
        </article>

        <article class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-sm font-extrabold text-ink-900 dark:text-white">Matrix Status LOP</h2>
                    <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Matrix status per Region, Branch, dan Program berdasarkan workflow operasional.</p>
                </div>
                <span class="rounded-lg bg-ink-50 px-2.5 py-1 text-xs font-bold text-ink-600 dark:bg-ink-800 dark:text-ink-300">{{ number_format($stats['total']) }} LOP</span>
            </div>

            <p class="mt-3 text-[10px] text-ink-400 sm:hidden">Geser tabel ke samping untuk melihat seluruh tahapan.</p>
            <div class="mt-5 space-y-3">
                @forelse ($matrixRegions as $region)
                    @php($groupLabel = $isSuperAdmin ? $region['name'] : ($region['branches'][0]['name'] ?? $scopeLabel))
                    <div class="overflow-hidden rounded-2xl border border-blue-200 bg-white shadow-sm dark:border-blue-900 dark:bg-ink-900">
                        <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6h16.5M3.75 12h16.5m-16.5 6h16.5"/></svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-extrabold text-ink-900 dark:text-white">{{ $groupLabel }}</p>
                                    <p class="mt-1 text-[10px] text-ink-400">{{ count($region['branches']) }} branch</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-4 gap-2 sm:min-w-[390px]">
                                @foreach ([
                                    ['Total', $region['summary']['total'], 'text-ink-900 dark:text-white'],
                                    ['Assigned', $region['summary']['pipeline']['assigned'], 'text-blue-700 dark:text-blue-300'],
                                    ['In Review', $region['summary']['pipeline']['waiting_approval'], 'text-amber-700 dark:text-amber-300'],
                                    ['Complete', $region['summary']['pipeline']['completed'], 'text-emerald-700 dark:text-emerald-300'],
                                ] as [$label, $value, $tone])
                                    <div class="rounded-xl bg-ink-50 px-2 py-2 text-center dark:bg-ink-800">
                                        <p class="text-sm font-extrabold {{ $tone }}">{{ $value }}</p>
                                        <p class="mt-0.5 text-[8px] font-bold uppercase tracking-wide text-ink-400">{{ $label }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="border-t border-ink-100 dark:border-ink-800">
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[1080px] border-separate border-spacing-0 text-xs">
                                    <thead class="bg-ink-50 dark:bg-ink-800/80">
                                        <tr>
                                            <th class="sticky left-0 z-10 min-w-48 border-b border-r border-ink-200 bg-ink-50 px-4 py-3 text-left text-[10px] font-extrabold uppercase tracking-wider text-ink-500 dark:border-ink-700 dark:bg-ink-800">Branch / Program</th>
                                            @foreach ($pipelineStatuses as $status)<th class="min-w-24 border-b border-r border-ink-200 px-3 py-3 text-center text-[10px] font-extrabold uppercase tracking-wider text-ink-500 dark:border-ink-700">{{ $status['label'] }}</th>@endforeach
                                            <th class="min-w-20 border-b border-ink-200 bg-ink-100 px-3 py-3 text-center text-[10px] font-extrabold uppercase tracking-wider text-ink-700 dark:border-ink-700 dark:bg-ink-700 dark:text-ink-200">Total</th>
                                        </tr>
                                    </thead>
                                    @foreach ($region['branches'] as $branch)
                                        <tbody x-data="{ branchOpen: {{ ! $isSuperAdmin ? 'true' : 'false' }} }" class="divide-y divide-ink-100 dark:divide-ink-800">
                                            <tr class="bg-blue-50/70 font-bold dark:bg-blue-950/20">
                                                <td class="sticky left-0 z-10 border-r border-ink-200 bg-blue-50 px-3 py-2.5 text-blue-800 dark:border-ink-700 dark:bg-ink-900 dark:text-blue-300">
                                                    <button type="button" @click="branchOpen = !branchOpen" :aria-expanded="branchOpen" class="flex w-full items-center gap-2 rounded-lg px-1 py-1 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40">
                                                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-blue-100 dark:bg-blue-900/50"><svg class="h-3 w-3 transition-transform" :class="branchOpen ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5.25 6.75 6.75L9 18.75"/></svg></span>
                                                        <span>{{ $branch['name'] }}</span>
                                                        <span class="ml-auto text-[9px] font-semibold text-blue-500">{{ count($branch['program']) }} Program</span>
                                                    </button>
                                                </td>
                                                @foreach ($pipelineStatuses as $status)<td class="border-r border-ink-200 px-3 py-3 text-center font-extrabold dark:border-ink-700">{{ $branch['summary']['pipeline'][$status['value']] }}</td>@endforeach
                                                <td class="bg-blue-100/60 px-3 py-3 text-center font-extrabold text-blue-900 dark:bg-blue-950/40 dark:text-blue-200">{{ $branch['summary']['total'] }}</td>
                                            </tr>
                                            @foreach ($branch['program'] as $program)
                                                <tr x-show="branchOpen" x-cloak class="transition hover:bg-ink-50 dark:hover:bg-ink-800/40">
                                                    <td class="sticky left-0 z-10 border-r border-ink-100 bg-white px-5 py-3 font-semibold text-ink-600 dark:border-ink-800 dark:bg-ink-900 dark:text-ink-300"><span class="mr-2 text-ink-300 dark:text-ink-700">•</span>{{ $program['label'] }}</td>
                                                    @foreach ($pipelineStatuses as $status)<td class="border-r border-ink-100 px-3 py-3 text-center font-medium text-ink-600 dark:border-ink-800 dark:text-ink-300">{{ $program['pipeline'][$status['value']] }}</td>@endforeach
                                                    <td class="bg-ink-50 px-3 py-3 text-center font-extrabold text-ink-900 dark:bg-ink-800/60 dark:text-white">{{ $program['total'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    @endforeach
                                </table>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-ink-200 px-6 py-12 text-center text-sm text-ink-400 dark:border-ink-700">Tidak ada data matrix pada cakupan ini.</div>
                @endforelse
            </div>
        </article>

        <article class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900">
            <div>
                <h2 class="text-sm font-extrabold text-ink-900 dark:text-white">Approval Evidence</h2>
                <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Kondisi review seluruh file evidence.</p>
            </div>

            <div class="mt-6 flex items-end justify-between gap-4">
                <div>
                    <p class="text-3xl font-extrabold tracking-tight text-ink-900 dark:text-white">{{ $evidenceStats['approval_percentage'] }}%</p>
                    <p class="mt-1 text-xs font-semibold text-ink-500">Approval rate</p>
                </div>
                <p class="text-right text-xs text-ink-400"><strong class="text-sm text-ink-700 dark:text-ink-200">{{ number_format($evidenceStats['total']) }}</strong><br>total evidence</p>
            </div>
            <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800">
                <div class="h-full rounded-full bg-emerald-500 transition-all duration-500" style="width: {{ $evidenceStats['approval_percentage'] }}%"></div>
            </div>

            <div class="mt-6 grid grid-cols-3 gap-2">
                @foreach ([
                    ['Pending', $evidenceStats['pending'], 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'],
                    ['Approved', $evidenceStats['approved'], 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'],
                    ['Rejected', $evidenceStats['rejected'], 'bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300'],
                ] as [$label, $value, $tone])
                    <div class="rounded-xl p-3 text-center {{ $tone }}">
                        <p class="text-lg font-extrabold">{{ number_format($value) }}</p>
                        <p class="mt-1 text-[9px] font-bold uppercase tracking-wide">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </article>
    </section>

    <section class="space-y-6">
        <article class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900">
            <div>
                <h2 class="text-sm font-extrabold text-ink-900 dark:text-white">Kesiapan Data & Penugasan</h2>
                <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Kelengkapan dasar sebelum pekerjaan berjalan.</p>
            </div>

            <div class="mt-6 space-y-6">
                <div>
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6.75a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 21a7.5 7.5 0 0 1 15 0"/></svg></span>
                            <div><p class="text-xs font-bold text-ink-800 dark:text-ink-200">Assignment teknisi</p><p class="mt-1 text-[10px] text-ink-400">{{ $stats['assigned'] }} assigned · {{ $stats['unassigned'] }} belum assigned</p></div>
                        </div>
                        <strong class="text-xl font-extrabold text-ink-900 dark:text-white">{{ $stats['assignment_percentage'] }}%</strong>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full rounded-full bg-blue-500" style="width: {{ $stats['assignment_percentage'] }}%"></div></div>
                </div>

                <div class="border-t border-ink-100 pt-6 dark:border-ink-800">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3.75h12A1.5 1.5 0 0 1 19.5 5.25v15H4.5v-15A1.5 1.5 0 0 1 6 3.75ZM8.25 9h7.5m-7.5 4.5h7.5"/></svg></span>
                            <div><p class="text-xs font-bold text-ink-800 dark:text-ink-200">Kelengkapan ID IHLD</p><p class="mt-1 text-[10px] text-ink-400">{{ $stats['missing_ihld'] }} LOP masih perlu dilengkapi</p></div>
                        </div>
                        <strong class="text-xl font-extrabold text-ink-900 dark:text-white">{{ $stats['ihld_completion_percentage'] }}%</strong>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $stats['ihld_completion_percentage'] }}%"></div></div>
                </div>

                @if ($stats['rejected'] > 0)
                    <div class="flex items-center justify-between gap-4 rounded-xl border border-brand-100 bg-brand-50 p-4 dark:border-brand-900/50 dark:bg-brand-950/20">
                        <div><p class="text-xs font-extrabold text-brand-800 dark:text-brand-200">Perlu perbaikan segera</p><p class="mt-1 text-[10px] text-brand-600 dark:text-brand-300">Terdapat LOP berstatus reject.</p></div>
                        <span class="grid h-9 min-w-9 place-items-center rounded-lg bg-brand-600 px-2 text-sm font-extrabold text-white">{{ $stats['rejected'] }}</span>
                    </div>
                @endif
            </div>
        </article>
    </section>

    <section class="overflow-hidden rounded-2xl border border-ink-100 bg-white shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <div class="flex flex-col gap-3 border-b border-ink-100 p-5 dark:border-ink-800 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-extrabold text-ink-900 dark:text-white">Prioritas Operasional</h2>
                <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">LOP reject, waiting review, unassigned, dan update terbaru ditampilkan lebih dahulu.</p>
            </div>
            @unless ($isSuperAdmin)
                <a href="{{ route('lop.index') }}" class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-700 dark:text-brand-400">Lihat Inbox <span aria-hidden="true">→</span></a>
            @endunless
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px]">
                <thead class="bg-ink-50/80 text-left dark:bg-ink-800/70">
                    <tr>
                        @foreach (['Project', 'Lokasi & Program', 'Teknisi', 'Progress', 'Status', 'Aksi'] as $heading)
                            <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wider text-ink-400 {{ $loop->last ? 'text-right' : '' }}">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                    @forelse ($priorityLops as $lop)
                        @php($summary = $lop->progress_summary)
                        <tr class="transition hover:bg-ink-50/70 dark:hover:bg-ink-800/40">
                            <td class="px-5 py-4">
                                <p class="text-[10px] font-extrabold uppercase tracking-wide text-brand-600 dark:text-brand-400">{{ $lop->incident }}</p>
                                <p class="mt-1 max-w-xs text-xs font-bold text-ink-900 dark:text-white">{{ $lop->nama_lop }}</p>
                                @unless ($lop->ihld_id)<p class="mt-1.5 inline-flex rounded-md bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">ID IHLD belum ada</p>@endunless
                            </td>
                            <td class="px-5 py-4"><p class="text-xs font-semibold text-ink-700 dark:text-ink-200">{{ $lop->branch ?: 'Branch —' }}</p><p class="mt-1 text-[10px] text-ink-400">{{ $lop->sto ?: 'STO —' }} · {{ $lop->program_type->label() }}</p></td>
                            <td class="px-5 py-4"><p class="max-w-36 truncate text-xs font-semibold text-ink-700 dark:text-ink-200">{{ $lop->activeAssignment?->technician?->name ?? 'Belum ditugaskan' }}</p></td>
                            <td class="min-w-36 px-5 py-4"><div class="mb-1.5 flex justify-between text-[10px]"><span class="text-ink-400">{{ $summary['completed_steps'] }}/{{ $summary['total_steps'] }} step</span><strong>{{ $summary['percentage'] }}%</strong></div><div class="h-1.5 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full rounded-full {{ $summary['review_key'] === 'rejected' ? 'bg-brand-600' : ($summary['review_key'] === 'approved' ? 'bg-emerald-500' : ($summary['review_key'] === 'waiting_review' ? 'bg-amber-400' : 'bg-blue-500')) }}" style="width: {{ $summary['percentage'] }}%"></div></div></td>
                            <td class="px-5 py-4"><x-badge :variant="$summary['review_variant']">{{ $summary['review_label'] }}</x-badge></td>
                            <td class="px-5 py-4"><div class="flex justify-end gap-1.5"><x-table-action label="Detail LOP" onclick="document.getElementById('dashboard-lop-detail-{{ $lop->id_qe_lops }}').showModal()"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="2.25"/></svg></x-table-action>@can('reviewEvidence', $lop)<x-table-action label="Review Evidence" tone="success" :href="$summary['evidence_count'] ? route('evidence-approval.lop.review', $lop) : null" :disabled="! $summary['evidence_count']"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 12.75 2.25 2.25L15 9.75m-3-7.04A11.96 11.96 0 0 1 3.6 6 12 12 0 0 0 3 9.75c0 5.59 3.82 10.29 9 11.62 5.18-1.33 9-6.03 9-11.62 0-1.31-.21-2.57-.6-3.75-3.2 0-6.1-1.25-8.25-3.29Z"/></svg></x-table-action>@endcan</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-14 text-center"><span class="mx-auto grid h-11 w-11 place-items-center rounded-xl bg-ink-50 text-ink-400 dark:bg-ink-800"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 12 3l9 4.5-9 4.5-9-4.5Zm0 4.5 9 4.5 9-4.5M3 16.5l9 4.5 9-4.5"/></svg></span><p class="mt-3 text-sm font-bold text-ink-700 dark:text-ink-200">Belum ada data LOP</p><p class="mt-1 text-xs text-ink-400">Ubah filter atau tambahkan LOP baru.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @foreach ($priorityLops as $lop)
        <x-lop-detail-modal :id="'dashboard-lop-detail-'.$lop->id_qe_lops" :$lop />
    @endforeach
</div>
@endsection
