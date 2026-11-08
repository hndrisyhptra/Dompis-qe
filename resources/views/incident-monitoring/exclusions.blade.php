@extends('layouts.app')

@section('title', 'Monitoring Incident · Ringkasan Exclude')

@section('content')
@php
    $baseQuery = array_filter($filters, fn ($value) => $value !== '');
    $detailReset = ['matrix_region' => '', 'detail_kind' => '', 'exclude_state' => 'all', 'reason' => ''];
    $detailUrl = fn (array $changes = []) => route('incident-monitoring.exclusions', array_filter(array_replace($baseQuery, $detailReset, $changes), fn ($value) => $value !== '')).'#ticket-details';
    $columnCount = 7 + count($kinds) * count($reasons);
    $matrixGroupLabel = $groupBySto ? 'SERVICE AREA / STO' : ($groupByBranch ? 'REGIONAL / BRANCH' : 'REGIONAL');
    $matrixSummaryLabel = $groupBySto ? 'service area/STO' : ($groupByBranch ? 'regional/branch' : 'regional');
    $matrixUnit = $groupByBranch ? 'Branch' : 'STO';
    $isGrouped = ($groupBySto || $groupByBranch) && $matrixGroups !== [];
    $groupOpenDefault = count($matrixRows) <= 10;
    $groupOpenState = collect($matrixGroups)->mapWithKeys(fn (array $group, int $index) => [$index => $groupOpenDefault])->all();
    $visibleScopeLabel = $scopeLabel !== 'Seluruh wilayah' ? $scopeLabel : null;
    $captureName = implode('-', array_filter([
        'monitoring-exclude-tiket',
        $visibleScopeLabel,
        $filters['date_from'],
        $filters['date_to'],
    ], fn ($value) => $value !== null && $value !== ''));
    $captureContext = implode(' · ', array_filter([
        $visibleScopeLabel,
        $filters['region'],
        $filters['branch'] !== '' ? ($branches->firstWhere('id_branch', (int) $filters['branch'])?->name ?? 'Branch di luar scope') : null,
        $filters['service_area_name'] !== '' ? 'Service Area: '.$filters['service_area_name'] : null,
        $filters['service_area'] !== '' ? ($serviceAreas->firstWhere('id_service_area', (int) $filters['service_area'])?->workzone ?? 'STO di luar scope') : null,
        $filters['kind'] !== '' ? ($kinds[$filters['kind']] ?? null) : null,
        $filters['q'] !== '' ? 'Pencarian: '.$filters['q'] : null,
    ], fn ($value) => $value !== null && $value !== ''));
@endphp

<style>
    .incident-ticket-table { border-collapse: separate; border-spacing: 0; }
    .incident-ticket-table th:not(:last-child),
    .incident-ticket-table td:not(:last-child) { border-right: 1px solid rgb(226 232 240); }
    .incident-ticket-table tbody tr:not(:last-child) > td { border-bottom: 1px solid rgb(226 232 240); }
    .dark .incident-ticket-table th:not(:last-child),
    .dark .incident-ticket-table td:not(:last-child) { border-right-color: rgb(55 65 81); }
    .dark .incident-ticket-table tbody tr:not(:last-child) > td { border-bottom-color: rgb(55 65 81); }
</style>
<div class="mx-auto max-w-7xl space-y-5" x-data="{ period: @js($filters['period']), dateFrom: @js($filters['date_from']), filtersOpen: false, loading: false }" :aria-busy="loading" @pageshow.window="loading = false">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink-900 dark:text-white">Monitoring Incident</h1>
            @if ($visibleScopeLabel)
                <p class="mt-1.5 text-sm text-ink-600 dark:text-ink-300">{{ $visibleScopeLabel }}</p>
            @endif
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Ringkasan status, distribusi tiket, matriks exclude, dan tindak lanjut dalam satu halaman.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('incident-monitoring.bulk-exclusions') }}" class="inline-flex min-h-11 items-center rounded-md border border-ink-200 px-3 text-sm font-semibold text-ink-700 hover:bg-ink-50 focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-ink-700 dark:text-ink-200 dark:hover:bg-ink-800">Exclude Massal</a>
            @if ($sourceUnavailable)
                <button type="button" disabled title="Sumber tiket belum tersedia. Coba kembali sebelum download." class="min-h-11 rounded-md border border-ink-200 px-4 text-sm font-semibold text-ink-400 disabled:cursor-not-allowed dark:border-ink-700">Download Excel</button>
            @else
                <details class="relative">
                    <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-md bg-brand-600 px-4 text-sm font-semibold text-white hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-brand-500">Download Excel <span aria-hidden="true">⌄</span></summary>
                    <nav aria-label="Pilihan data unduhan" class="absolute right-0 z-20 mt-2 w-60 rounded-md border border-ink-200 bg-white p-1 shadow-lg dark:border-ink-700 dark:bg-ink-900">
                        @foreach (['all' => 'Semua tiket', 'without_lop' => 'Belum memiliki LOP', 'with_lop' => 'Sudah memiliki LOP', 'excluded' => 'Tiket Exclude'] as $usage => $label)
                            <button type="submit" form="exclude-filters" formaction="{{ route('incident-monitoring.tickets.download', [], false) }}" formmethod="GET" name="download_usage" value="{{ $usage }}" data-ticket-download class="flex min-h-11 w-full items-center rounded-md px-3 py-2 text-left text-sm text-ink-700 hover:bg-ink-50 focus-visible:outline-2 focus-visible:outline-brand-500 dark:text-ink-200 dark:hover:bg-ink-800">{{ $label }}</button>
                        @endforeach
                    </nav>
                </details>
            @endif
        </div>
    </header>
    @include('incident-monitoring._tabs')

    @include('incident-monitoring._shared-filters')

    @if ($scopeWarning)
        <p role="status" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">STO dalam scope akun belum tersedia. Hubungi Super Admin untuk melengkapi akses wilayah.</p>
    @endif
    @if ($sourceUnavailable)
        <div role="alert" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
            <p class="font-semibold">Data tiket Dompis belum dapat dimuat.</p>
            <p class="mt-1">Jumlah tiket belum dapat dipastikan. <a class="underline" href="{{ route('incident-monitoring.exclusions', $baseQuery) }}">Coba kembali</a> setelah koneksi tersedia.</p>
        </div>
    @endif

    <section aria-label="Ringkasan status tiket" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([['total', 'Total tiket', 'all'], ['without_lop', 'Belum memiliki LOP', 'not_excluded'], ['with_lop', 'Sudah memiliki LOP', 'included'], ['excluded', 'Tiket Exclude', 'excluded']] as [$key, $label, $state])
            <a href="{{ $detailUrl(['exclude_state' => $state]) }}" class="rounded-md border bg-white p-4 transition hover:border-brand-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:bg-ink-900 sm:p-5 {{ $filters['exclude_state'] === $state ? 'border-brand-300 dark:border-brand-600' : 'border-ink-100 dark:border-ink-800' }}">
                <p class="text-sm font-medium text-ink-600 dark:text-ink-300">{{ $label }}</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight tabular-nums {{ $key === 'excluded' ? 'text-emerald-800 dark:text-emerald-300' : 'text-ink-900 dark:text-white' }}">{{ $stats === null ? '—' : number_format($stats[$key], 0, ',', '.') }}</p>
                @if ($key === 'total')<p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Klik untuk melihat seluruh tiket</p>@endif
            </a>
        @endforeach
    </section>

    <section aria-labelledby="ticket-chart-heading" class="rounded-md border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900 sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 id="ticket-chart-heading" class="text-base font-semibold text-ink-900 dark:text-white">Jumlah tiket per jenis</h2>
                <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Komposisi dari total tiket pada filter periode dan lokasi yang sama.</p>
            </div>
            @if ($filters['detail_kind'] !== '')<a href="{{ $detailUrl(['detail_kind' => '']) }}" class="inline-flex min-h-9 items-center text-sm font-medium text-brand-600 hover:underline dark:text-brand-300">Semua jenis</a>@endif
        </div>
        @if ($chartRows->isEmpty())
            <p class="py-5 text-sm text-ink-500 dark:text-ink-400">Tidak ada tiket yang sesuai filter.</p>
        @else
            <ul class="mt-3 space-y-1" aria-label="Jumlah tiket GAMAS, INFRACARE, dan PREMAN">
                @foreach ($chartRows as $row)
                    <li><a href="{{ $detailUrl(['detail_kind' => $row['kind']]) }}" class="grid min-h-11 grid-cols-[100px_minmax(0,1fr)_64px] items-center gap-3 rounded-md px-2 py-2 hover:bg-ink-50 focus-visible:outline-2 focus-visible:outline-brand-500 dark:hover:bg-ink-800">
                        <span class="text-sm font-medium">{{ $row['label'] }}</span>
                        <span aria-hidden="true" class="h-3 rounded-sm bg-ink-50 dark:bg-ink-800"><span class="block h-full rounded-sm bg-brand-600 dark:bg-brand-400" style="width: {{ $row['width'] }}%"></span></span>
                        <span class="text-right text-sm font-semibold tabular-nums">{{ number_format($row['count'], 0, ',', '.') }}</span>
                    </a></li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="exclude-matrix-title" x-data="dashboardRegionCapture(@js($captureName))" x-ref="captureArea" class="overflow-hidden rounded-md border border-ink-100 bg-white dark:border-ink-800 dark:bg-ink-900">
        <div class="flex flex-wrap items-start justify-between gap-3 p-4 sm:p-5">
            <div class="min-w-0 space-y-2">
                <h2 id="exclude-matrix-title" class="text-base font-semibold">Monitoring Exclude Tiket</h2>
                <p class="text-sm text-ink-600 dark:text-ink-300">Periode {{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d/m/Y') }}.<span data-capture-ignore> Klik angka untuk melihat detail tiket.</span></p>
                <p class="text-xs text-ink-500 dark:text-ink-400">{{ $captureContext }}</p>
            </div>
            @if (! $sourceUnavailable)
                <x-dashboard-capture-controls id="exclude-ticket-image-format" label="Monitoring Exclude Tiket" class="shrink-0" />
            @endif
        </div>
        <div @if ($isGrouped) x-data="{ openGroups: @js($groupOpenState) }" @endif>
            @if ($isGrouped)
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ink-200 bg-ink-50 px-4 py-2 text-xs dark:border-ink-700 dark:bg-ink-800">
                    <span class="text-ink-600 dark:text-ink-300">{{ count($matrixGroups) }} kelompok {{ $groupByBranch ? 'regional' : 'service area' }} · {{ count($matrixRows) }} {{ $matrixUnit }}</span>
                    <button type="button" @click="openGroups = Object.fromEntries(Object.keys(openGroups).map(k => [k, !Object.values(openGroups).every(Boolean)]))" data-capture-ignore
                            class="rounded-md border border-ink-200 px-2 py-1 font-bold text-ink-600 transition hover:border-emerald-300 hover:text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 dark:border-ink-700 dark:text-ink-300 dark:hover:border-emerald-800 dark:hover:text-emerald-300">
                        <span x-text="Object.values(openGroups).every(Boolean) ? 'Lipat semua' : 'Buka semua'">Buka semua</span>
                    </button>
                </div>
            @endif
            <div role="region" aria-label="Tabel {{ $matrixSummaryLabel }} tiket exclude, dapat digeser horizontal" tabindex="0" class="overflow-x-auto focus-visible:outline-2 focus-visible:outline-brand-500">
            <table class="w-full min-w-[1900px] border-collapse text-center text-xs tabular-nums">
                <caption class="sr-only">Jumlah tiket sudah dan belum exclude menurut {{ $matrixSummaryLabel }}, jenis tiket, dan alasan pengecualian.</caption>
                <thead class="bg-ink-50 text-ink-700 dark:bg-ink-800 dark:text-ink-200">
                    <tr>
                        <th rowspan="3" scope="col" class="sticky left-0 z-10 border border-ink-200 bg-ink-50 px-3 py-3 text-center dark:border-ink-700 dark:bg-ink-800 {{ $groupByBranch ? 'min-w-64 whitespace-nowrap' : 'min-w-40' }}">{{ $matrixGroupLabel }}</th>
                        <th colspan="{{ count($kinds) * 4 }}" scope="colgroup" class="border border-ink-200 px-3 py-3 dark:border-ink-700">JUMLAH TIKET</th>
                        <th colspan="{{ count($kinds) * count($reasons) }}" scope="colgroup" class="border border-ink-200 bg-emerald-50 px-3 py-3 text-center text-emerald-800 dark:border-ink-700 dark:bg-emerald-950/50 dark:text-emerald-200">SUDAH EXCLUDE</th>
                    </tr>
                    <tr>
                        @foreach ($kinds as $label)<th colspan="4" scope="colgroup" class="border border-ink-200 px-3 py-3 dark:border-ink-700">{{ $label }}</th>@endforeach
                        @foreach ($kinds as $label)<th colspan="{{ count($reasons) }}" scope="colgroup" class="border border-ink-200 px-3 py-3 dark:border-ink-700">{{ $label }}</th>@endforeach
                    </tr>
                    <tr>
                        @foreach ($kinds as $label)
                            <th scope="col" class="min-w-24 border border-ink-200 border-b-2 border-b-blue-500 bg-blue-50 px-2 py-3 font-semibold text-blue-800 dark:border-ink-700 dark:border-b-blue-500 dark:bg-blue-950/30 dark:text-blue-200">Sudah<br>Include</th>
                            <th scope="col" class="min-w-24 border border-ink-200 border-b-2 border-b-emerald-500 bg-emerald-50/70 px-2 py-3 font-semibold text-emerald-800 dark:border-ink-700 dark:border-b-emerald-500 dark:bg-emerald-950/30 dark:text-emerald-200">Sudah<br>Exclude</th>
                            <th scope="col" class="min-w-24 border border-ink-200 border-b-2 border-b-brand-700 bg-brand-500 px-2 py-3 font-semibold text-white dark:border-ink-700 dark:border-b-brand-500 dark:bg-brand-600 dark:text-white">Belum<br>Exclude</th>
                            <th scope="col" class="min-w-24 border border-ink-200 border-b-2 border-b-ink-500 bg-ink-100 px-2 py-3 font-semibold text-ink-800 dark:border-ink-700 dark:border-b-ink-500 dark:bg-ink-800 dark:text-ink-100">Total</th>
                        @endforeach
                        @foreach ($kinds as $label)
                            @foreach ($reasons as $reason => $reasonLabel)
                                <th scope="col" title="{{ $reasonLabel }}" class="min-w-24 border border-ink-200 px-2 py-3 dark:border-ink-700">{{ ['duplikat_open' => 'Duplikat Tiket', 'magu_ganti_pasif_odp' => 'MAGU'][$reason] ?? $reasonLabel }}</th>
                            @endforeach
                        @endforeach
                    </tr>
                </thead>
                @if ($isGrouped)
                    @foreach ($matrixGroups as $groupIndex => $group)
                        <tbody id="exclude-group-{{ $groupIndex }}" class="divide-y divide-ink-200 dark:divide-ink-700">
                            <tr class="bg-emerald-50/70 font-bold dark:bg-emerald-950/20">
                                <th scope="rowgroup" class="sticky left-0 z-10 border border-ink-200 bg-emerald-50 px-3 py-2.5 text-left text-emerald-800 dark:border-ink-700 dark:bg-ink-900 dark:text-emerald-300">
                                    <button type="button" @click="openGroups[{{ $groupIndex }}] = !openGroups[{{ $groupIndex }}]" :aria-expanded="!!openGroups[{{ $groupIndex }}]" aria-controls="exclude-group-{{ $groupIndex }}"
                                            class="flex w-full items-center gap-2 rounded-md px-1 py-1 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40">
                                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-emerald-100 dark:bg-emerald-900/50"><svg class="h-3 w-3 transition-transform" :class="openGroups[{{ $groupIndex }}] ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5.25 6.75 6.75L9 18.75"/></svg></span>
                                        <span class="{{ $groupByBranch ? 'shrink-0 whitespace-nowrap' : 'min-w-0 wrap-break-word' }}">{{ $group['name'] }}</span>
                                        <span class="ml-auto shrink-0 whitespace-nowrap text-[9px] font-semibold text-emerald-600 dark:text-emerald-400">{{ $group['row_count'] }} {{ $matrixUnit }}</span>
                                    </button>
                                </th>
                                @if ($groupByBranch)
                                    @include('incident-monitoring._exclude-matrix-row', ['matrixRow' => $group, 'showRowLabel' => false])
                                @else
                                    @foreach ($kinds as $kind => $label)
                                        @foreach (['included', 'excluded', 'not_excluded', 'total'] as $state)
                                            <td class="border border-ink-200 px-2 py-2.5 text-center tabular-nums text-ink-900 dark:border-ink-700 dark:text-white {{ ['included' => 'bg-blue-50/70 dark:bg-blue-950/20', 'excluded' => 'bg-emerald-50/70 dark:bg-emerald-950/20', 'not_excluded' => 'bg-brand-100/70 dark:bg-brand-900/30', 'total' => 'bg-ink-50 dark:bg-ink-800/50'][$state] }}">@if ($sourceUnavailable)<span class="inline-flex min-h-11 items-center px-3 text-ink-400">—</span>@else{{ number_format($group['cells'][$kind.':'.$state], 0, ',', '.') }}@endif</td>
                                        @endforeach
                                    @endforeach
                                    @foreach ($kinds as $kind => $label)
                                        @foreach ($reasons as $reason => $reasonLabel)
                                            <td class="border border-ink-200 px-2 py-2.5 text-center tabular-nums text-ink-900 dark:border-ink-700 dark:text-white">@if ($sourceUnavailable)<span class="inline-flex min-h-11 items-center px-3 text-ink-400">—</span>@else{{ number_format($group['cells'][$kind.':'.$reason], 0, ',', '.') }}@endif</td>
                                        @endforeach
                                    @endforeach
                                @endif
                            </tr>
                            @foreach ($group['rows'] as $matrixRow)
                                <tr x-show="openGroups[{{ $groupIndex }}]" x-cloak class="bg-white dark:bg-ink-900">
                                    @include('incident-monitoring._exclude-matrix-row', ['matrixRow' => $matrixRow])
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                    <tbody>
                        <tr class="bg-ink-50 font-bold dark:bg-ink-800">
                            @include('incident-monitoring._exclude-matrix-row', ['matrixRow' => $totals])
                        </tr>
                    </tbody>
                @else
                    <tbody class="divide-y divide-ink-200 dark:divide-ink-700">
                        @foreach ([...array_values($matrixRows), $totals] as $matrixRow)
                            <tr class="{{ $matrixRow['key'] === '' ? 'bg-ink-50 font-bold dark:bg-ink-800' : 'bg-white dark:bg-ink-900' }}">
                                @include('incident-monitoring._exclude-matrix-row', ['matrixRow' => $matrixRow])
                            </tr>
                        @endforeach
                        @if (count($matrixRows) === 0)
                            <tr><td colspan="{{ $columnCount }}" class="p-4 text-ink-500 dark:text-ink-400">Tidak ada {{ $groupBySto ? 'STO' : ($groupByBranch ? 'branch' : 'wilayah') }} dalam filter dan scope akun.</td></tr>
                        @endif
                    </tbody>
                @endif
            </table>
            </div>
        </div>
        @if ($checkedAt)<p class="p-4 text-xs text-ink-500 dark:text-ink-400">Dibaca {{ $checkedAt->format('d/m/Y H:i') }} WIB.</p>@endif
    </section>

    <section id="ticket-details" aria-labelledby="ticket-details-title" class="scroll-mt-24 overflow-hidden rounded-md border border-ink-100 bg-white dark:border-ink-800 dark:bg-ink-900">
        <header class="flex flex-wrap items-start justify-between gap-3 p-4 sm:p-5">
            <div>
                <h2 id="ticket-details-title" class="text-base font-semibold">Daftar Tiket Dompis <span class="text-ink-500 dark:text-ink-400">({{ $sourceUnavailable ? '—' : number_format($tickets->total(), 0, ',', '.') }})</span></h2>
                <p class="mt-1 text-sm text-ink-600 dark:text-ink-300">{{ $detailLabel }}</p>
            </div>
            @if ($filters['matrix_region'] !== '' || $filters['detail_kind'] !== '' || $filters['exclude_state'] !== 'all' || $filters['reason'] !== '')
                <a href="{{ $detailUrl() }}" class="inline-flex min-h-10 items-center rounded-md border border-ink-200 px-3 text-sm focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-ink-700">Semua detail sesuai periode</a>
            @endif
        </header>
        @if ($sourceUnavailable)
            <p class="px-5 pb-5 text-sm text-ink-500 dark:text-ink-400">Detail belum tersedia karena sumber tiket belum dapat dimuat.</p>
        @elseif ($tickets->isEmpty())
            <p role="status" class="px-5 pb-5 text-sm text-ink-500 dark:text-ink-400">Tidak ada tiket yang sesuai pilihan ini.</p>
        @else
            <div class="divide-y divide-ink-100 dark:divide-ink-800 lg:hidden">
                @foreach ($tickets as $ticket)
                    <article class="space-y-3 p-4" data-incident="{{ $ticket['incident'] }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div><p class="font-semibold text-ink-900 dark:text-white">{{ $ticket['incident'] }}</p><p class="mt-1 text-xs text-ink-500 dark:text-ink-400">{{ $ticket['reported_date'] ?? 'Tanggal laporan belum terisi' }} · {{ $ticket['ticket_type'] }}</p></div>
                            <span class="rounded-md px-2 py-1 text-xs font-medium {{ $ticket['is_included'] ? 'bg-blue-50 text-blue-800 dark:bg-blue-950/50 dark:text-blue-200' : ($ticket['is_excluded'] ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200' : 'bg-ink-100 text-ink-700 dark:bg-ink-800 dark:text-ink-200') }}">{{ $ticket['is_included'] ? 'Sudah include' : ($ticket['is_excluded'] ? 'Sudah exclude' : 'Belum exclude') }}</span>
                        </div>
                        <p class="text-sm text-ink-600 dark:text-ink-300">{{ $ticket['branch'] ?? 'Branch belum terpetakan' }} / {{ $ticket['workzone'] }}</p>
                        @if ($ticket['lop'])
                            <div class="space-y-1 text-sm"><p class="font-medium">{{ $ticket['lop']->nama_lop }}</p><x-badge :variant="$ticket['lop']->status_lop->badgeVariant()">{{ $ticket['lop']->status_lop->label() }}</x-badge><div class="text-xs text-ink-500 dark:text-ink-400">ID IHLD: {{ $ticket['lop']->ihld_id ?: 'Belum diisi' }}</div><a href="{{ route('lop.detail', $ticket['lop'], false) }}" class="inline-flex min-h-10 items-center font-medium text-brand-600 hover:underline dark:text-brand-300">Detail LOP →</a></div>
                        @elseif ($ticket['is_included'])<p class="text-xs text-ink-500 dark:text-ink-400">LOP terarsip atau detail di luar scope</p>@endif
                        @if ($ticket['is_excluded'])<p class="text-sm text-ink-600 dark:text-ink-300">Alasan: {{ $ticket['reason_label'] }}</p>@endif
                        @if ($ticket['is_excluded'])<x-unexclude-ticket-button :incident="$ticket['incident']" />@elseif (! $ticket['is_included'])<x-exclude-ticket-form :incident="$ticket['incident']" />@endif
                    </article>
                @endforeach
            </div>
            <div role="region" aria-label="Daftar detail tiket, dapat digeser horizontal" tabindex="0" class="hidden overflow-x-auto focus-visible:outline-2 focus-visible:outline-brand-500 lg:block">
                <table class="incident-ticket-table w-full min-w-[1744px] table-fixed text-left text-xs">
                    <colgroup><col style="width: 8rem"><col style="width: 7rem"><col style="width: 10rem"><col style="width: 8rem"><col style="width: 18rem"><col style="width: 10rem"><col style="width: 12rem"><col style="width: 12rem"><col style="width: 10rem"><col style="width: 14rem"></colgroup>
                    <thead class="bg-ink-50 text-[11px] font-bold uppercase tracking-wide text-ink-500 dark:bg-ink-800 dark:text-ink-300"><tr>
                        <th scope="col" class="px-3 py-3 text-center">Incident</th>
                        <th scope="col" class="px-3 py-3 text-center">Tanggal Laporan</th>
                        <th scope="col" class="px-3 py-3 text-center">Jenis Tiket</th>
                        <th scope="col" class="px-3 py-3 text-center">STO / Branch</th>
                        <th scope="col" class="px-3 py-3 text-center">LOP terkait</th>
                        <th scope="col" class="px-3 py-3 text-center">Status LOP</th>
                        <th scope="col" class="px-3 py-3 text-center">ID IHLD</th>
                        <th scope="col" class="px-3 py-3 text-center">Status exclude</th>
                        <th scope="col" class="px-3 py-3 text-center">Alasan</th>
                        <th scope="col" class="px-3 py-3 text-center">Aksi</th>
                    </tr></thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                        @foreach ($tickets as $ticket)
                            <tr class="align-middle transition-colors hover:bg-brand-50/40 dark:hover:bg-ink-800/50" data-incident="{{ $ticket['incident'] }}">
                                <td class="whitespace-nowrap px-3 py-3 font-mono font-semibold text-ink-900 dark:text-white">{{ $ticket['incident'] }}</td>
                                <td class="whitespace-nowrap px-3 py-3">{{ $ticket['reported_date'] ?? '—' }}</td>
                                <td class="break-words px-3 py-3 font-medium">{{ $ticket['ticket_type'] }}</td>
                                <td class="px-3 py-3"><span class="inline-flex rounded-sm bg-ink-100 px-1.5 py-0.5 font-mono font-semibold text-ink-700 dark:bg-ink-800 dark:text-ink-200">{{ $ticket['workzone'] }}</span><span class="mt-1 block text-[11px] leading-4 text-ink-500 dark:text-ink-400">{{ $ticket['branch'] ?? 'Belum terpetakan' }}</span></td>
                                <td class="min-w-0 px-3 py-3">@if ($ticket['lop'])<a href="{{ route('lop.detail', $ticket['lop'], false) }}" title="{{ $ticket['lop']->nama_lop }}" class="block max-w-full truncate font-medium leading-5 text-brand-600 hover:underline dark:text-brand-300">{{ $ticket['lop']->nama_lop }}</a>@elseif($ticket['is_included'])<span class="block truncate text-[11px] leading-4 text-ink-500 dark:text-ink-400" title="LOP terarsip atau detail di luar scope">LOP terarsip atau detail di luar scope</span>@else<span class="text-ink-400 dark:text-ink-500">—</span>@endif</td>
                                <td class="whitespace-nowrap px-3 py-3 text-center">@if($ticket['lop'])<x-badge :variant="$ticket['lop']->status_lop->badgeVariant()">{{ $ticket['lop']->status_lop->label() }}</x-badge>@else<span class="text-ink-400 dark:text-ink-500">—</span>@endif</td>
                                <td class="whitespace-nowrap px-3 py-3"><div class="flex justify-center">@if($ticket['lop'] && filled($ticket['lop']->ihld_id))<x-copyable-value :value="$ticket['lop']->ihld_id" label="ID IHLD" :nowrap="true" class="font-mono" />@else<span class="text-ink-400 dark:text-ink-500">{{ $ticket['lop'] ? 'Belum diisi' : '—' }}</span>@endif</div></td>
                                <td class="px-3 py-3 text-center"><span class="inline-flex min-w-28 justify-center whitespace-nowrap rounded-md px-2 py-1 text-[11px] font-semibold {{ $ticket['is_included'] ? 'bg-blue-50 text-blue-800 dark:bg-blue-950/50 dark:text-blue-200' : ($ticket['is_excluded'] ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200' : 'bg-ink-100 text-ink-700 dark:bg-ink-800 dark:text-ink-200') }}">{{ $ticket['is_included'] ? 'Sudah include' : ($ticket['is_excluded'] ? 'Sudah exclude' : 'Belum exclude') }}</span></td>
                                <td class="px-3 py-3 text-center">@if($ticket['is_excluded'])<span class="inline-flex min-w-24 justify-center whitespace-nowrap rounded-md bg-amber-50 px-2 py-1 text-[11px] font-medium text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">{{ $ticket['reason_label'] }}</span>@else<span class="text-ink-400 dark:text-ink-500">—</span>@endif</td>
                                <td class="whitespace-nowrap px-3 py-3"><div class="flex justify-center">@if ($ticket['is_excluded'])<x-unexclude-ticket-button :incident="$ticket['incident']" :compact="true" />@elseif (! $ticket['is_included'])<x-exclude-ticket-form :incident="$ticket['incident']" :compact="true" />@else<span class="text-ink-400 dark:text-ink-500">—</span>@endif</div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-ink-100 p-4 dark:border-ink-800">{{ $tickets->links() }}</div>
        @endif
    </section>
</div>
@endsection
