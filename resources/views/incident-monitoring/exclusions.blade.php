@extends('layouts.app')

@section('title', 'Monitoring Exclude Tiket')

@section('content')
@php
    $baseQuery = array_filter($filters, fn ($value) => $value !== '');
    $detailReset = ['matrix_region' => '', 'detail_kind' => '', 'exclude_state' => 'all', 'reason' => ''];
    $detailUrl = fn (array $changes = []) => route('incident-monitoring.exclusions', array_filter(array_replace($baseQuery, $detailReset, $changes), fn ($value) => $value !== '')).'#ticket-details';
    $reportedFrom = \App\Support\TicketReportedDate::floor();
    $columnCount = 7 + count($kinds) * count($reasons);
    $captureName = 'monitoring-exclude-tiket-'.$scopeLabel.'-'.$filters['date_from'].'-'.$filters['date_to'];
    $captureContext = implode(' · ', array_filter([
        $scopeLabel,
        $filters['region'],
        $filters['branch'] !== '' ? ($branches->firstWhere('id_branch', (int) $filters['branch'])?->name ?? 'Branch di luar scope') : null,
        $filters['service_area'] !== '' ? ($serviceAreas->firstWhere('id_service_area', (int) $filters['service_area'])?->workzone ?? 'STO di luar scope') : null,
        $filters['kind'] !== '' ? ($kinds[$filters['kind']] ?? null) : null,
        $filters['q'] !== '' ? 'Pencarian: '.$filters['q'] : null,
    ], fn ($value) => $value !== null && $value !== ''));
@endphp
<div class="mx-auto max-w-7xl space-y-5" x-data="{ period: @js($filters['period']), dateFrom: @js($filters['date_from']), loading: false }" :aria-busy="loading" @pageshow.window="loading = false">
    <header>
        <h1 class="text-2xl font-semibold tracking-tight text-ink-900 dark:text-white">Monitoring Incident</h1>
        <p class="mt-1.5 text-sm text-ink-600 dark:text-ink-300">{{ $scopeLabel }}</p>
    </header>
    @include('incident-monitoring._tabs')

    <section aria-label="Filter monitoring exclude" class="rounded-md border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900 sm:p-5">
        <form method="GET" action="{{ route('incident-monitoring.exclusions', [], false) }}" @submit="loading = true" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-select name="period" label="Periode laporan" x-model="period">
                    <option value="range" @selected($filters['period'] === 'range')>Rentang tanggal</option>
                    <option value="month" @selected($filters['period'] === 'month')>Bulanan</option>
                </x-select>
                <div x-show="period === 'month'" @if ($filters['period'] !== 'month') x-cloak @endif>
                    <x-input name="month" type="month" label="Bulan laporan" :value="$filters['month']" :use-old="false" min="{{ substr($reportedFrom, 0, 7) }}" x-bind:disabled="period !== 'month'" />
                </div>
                <div x-show="period === 'range'" @if ($filters['period'] !== 'range') x-cloak @endif>
                    <x-input name="date_from" type="date" label="Tanggal laporan dari" :value="$filters['date_from']" :use-old="false" min="{{ $reportedFrom }}" x-model="dateFrom" x-bind:disabled="period !== 'range'" />
                </div>
                <div x-show="period === 'range'" @if ($filters['period'] !== 'range') x-cloak @endif>
                    <x-input name="date_to" type="date" label="Tanggal laporan sampai" :value="$filters['date_to']" :use-old="false" min="{{ $reportedFrom }}" x-bind:min="dateFrom || '{{ $reportedFrom }}'" x-bind:disabled="period !== 'range'" />
                </div>
                <x-input name="q" label="Cari incident / STO" :value="$filters['q']" :use-old="false" placeholder="Nomor incident atau STO…" maxlength="100" />
                <x-select name="region" label="Region" placeholder="Semua region">
                    @foreach ($regions as $region)
                        <option value="{{ $region }}" @selected($filters['region'] === $region)>{{ $region }}</option>
                    @endforeach
                    @if ($filters['region'] !== '' && ! $regions->contains($filters['region']))<option value="{{ $filters['region'] }}" selected>Region di luar pilihan</option>@endif
                </x-select>
                <x-select name="branch" label="Branch" placeholder="Semua branch">
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->getKey() }}" @selected((string) $filters['branch'] === (string) $branch->getKey())>{{ $branch->name }}</option>
                    @endforeach
                    @if ($filters['branch'] !== '' && ! $branches->contains('id_branch', (int) $filters['branch']))<option value="{{ $filters['branch'] }}" selected>Branch di luar pilihan</option>@endif
                </x-select>
                <x-select name="service_area" label="Service Area / STO" placeholder="Semua STO">
                    @foreach ($serviceAreas as $area)
                        <option value="{{ $area->getKey() }}" @selected((string) $filters['service_area'] === (string) $area->getKey())>{{ $area->workzone }}</option>
                    @endforeach
                    @if ($filters['service_area'] !== '' && ! $serviceAreas->contains('id_service_area', (int) $filters['service_area']))<option value="{{ $filters['service_area'] }}" selected>STO di luar pilihan</option>@endif
                </x-select>
                <div class="flex items-end gap-2">
                    <x-button x-bind:disabled="loading" class="min-h-11! w-auto! flex-1 rounded-md! shadow-none! disabled:opacity-60"><span x-text="loading ? 'Memuat…' : 'Terapkan'">Terapkan</span></x-button>
                    <a href="{{ route('incident-monitoring.exclusions') }}" class="inline-flex min-h-11 items-center rounded-md border border-ink-200 px-3 text-sm font-medium hover:bg-ink-50 focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-ink-700 dark:hover:bg-ink-800">Reset</a>
                </div>
            </div>
            @foreach ($errors->all() as $error)<p role="alert" class="text-sm text-red-600 dark:text-red-400">{{ $error }}</p>@endforeach
            <p class="text-xs text-ink-500 dark:text-ink-400">Periode mengikuti tanggal laporan tiket (reported date), mulai {{ \Illuminate\Support\Carbon::parse($reportedFrom)->format('d/m/Y') }}. Rentang default sampai hari ini; pilihan bulanan mencakup seluruh bulan.</p>
        </form>
    </section>

    @if ($scopeWarning)
        <p role="status" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">STO dalam scope akun belum tersedia. Hubungi Super Admin untuk melengkapi akses wilayah.</p>
    @endif
    @if ($sourceUnavailable)
        <div role="alert" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
            <p class="font-semibold">Data tiket Dompis belum dapat dimuat.</p>
            <p class="mt-1">Jumlah tiket belum dapat dipastikan. <a class="underline" href="{{ route('incident-monitoring.exclusions', $baseQuery) }}">Coba kembali</a> setelah koneksi tersedia.</p>
        </div>
    @endif

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
        <div role="region" aria-label="Tabel regional tiket exclude, dapat digeser horizontal" tabindex="0" class="overflow-x-auto focus-visible:outline-2 focus-visible:outline-brand-500">
            <table class="w-full min-w-[1700px] border-collapse text-center text-xs tabular-nums">
                <caption class="sr-only">Jumlah tiket sudah dan belum exclude menurut regional, jenis tiket, dan alasan pengecualian.</caption>
                <thead class="bg-ink-50 text-ink-700 dark:bg-ink-800 dark:text-ink-200">
                    <tr>
                        <th rowspan="3" scope="col" class="sticky left-0 z-10 min-w-40 border border-ink-200 bg-ink-50 px-3 py-3 text-center dark:border-ink-700 dark:bg-ink-800">REGIONAL</th>
                        <th colspan="6" scope="colgroup" class="border border-ink-200 px-3 py-3 dark:border-ink-700">JUMLAH TIKET</th>
                        <th colspan="{{ count($kinds) * count($reasons) }}" scope="colgroup" class="border border-ink-200 bg-brand-50 px-3 py-3 text-center text-brand-800 dark:border-ink-700 dark:bg-brand-900 dark:text-brand-200">SUDAH MAPPING</th>
                    </tr>
                    <tr>
                        @foreach ($kinds as $label)<th colspan="2" scope="colgroup" class="border border-ink-200 px-3 py-3 dark:border-ink-700">{{ $label }}</th>@endforeach
                        @foreach ($kinds as $label)<th colspan="{{ count($reasons) }}" scope="colgroup" class="border border-ink-200 px-3 py-3 dark:border-ink-700">{{ $label }}</th>@endforeach
                    </tr>
                    <tr>
                        @foreach ($kinds as $label)
                            <th scope="col" class="min-w-24 border border-ink-200 px-2 py-3 dark:border-ink-700">Sudah<br>Exclude</th>
                            <th scope="col" class="min-w-24 border border-ink-200 px-2 py-3 dark:border-ink-700">Belum<br>Exclude</th>
                        @endforeach
                        @foreach ($kinds as $label)
                            @foreach ($reasons as $reason => $reasonLabel)
                                <th scope="col" title="{{ $reasonLabel }}" class="min-w-24 border border-ink-200 px-2 py-3 dark:border-ink-700">{{ ['duplikat_open' => 'Tiket Open', 'magu_ganti_pasif_odp' => 'MAGU'][$reason] ?? $reasonLabel }}</th>
                            @endforeach
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200 dark:divide-ink-700">
                    @foreach ([...array_values($matrixRows), $totals] as $matrixRow)
                        <tr class="{{ $matrixRow['key'] === '' ? 'bg-ink-50 font-bold dark:bg-ink-800' : 'bg-white dark:bg-ink-900' }}">
                            <th scope="row" class="sticky left-0 z-10 border border-ink-200 bg-inherit px-3 py-2 text-left dark:border-ink-700">{{ $matrixRow['label'] }}</th>
                            @foreach ($kinds as $kind => $label)
                                @foreach (['excluded' => 'Sudah exclude', 'not_excluded' => 'Belum exclude'] as $state => $stateLabel)
                                    @include('incident-monitoring._exclude-count', ['cellKey' => $kind.':'.$state, 'cellLabel' => $label.' · '.$stateLabel, 'cellQuery' => ['matrix_region' => $matrixRow['key'], 'detail_kind' => $kind, 'exclude_state' => $state]])
                                @endforeach
                            @endforeach
                            @foreach ($kinds as $kind => $label)
                                @foreach ($reasons as $reason => $reasonLabel)
                                    @include('incident-monitoring._exclude-count', ['cellKey' => $kind.':'.$reason, 'cellLabel' => $label.' · '.$reasonLabel, 'cellQuery' => ['matrix_region' => $matrixRow['key'], 'detail_kind' => $kind, 'exclude_state' => 'excluded', 'reason' => $reason]])
                                @endforeach
                            @endforeach
                        </tr>
                    @endforeach
                    @if (count($matrixRows) === 0)
                        <tr><td colspan="{{ $columnCount }}" class="p-4 text-ink-500 dark:text-ink-400">Tidak ada wilayah dalam filter dan scope akun.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
        @if ($checkedAt)<p class="p-4 text-xs text-ink-500 dark:text-ink-400">Dibaca {{ $checkedAt->format('d/m/Y H:i') }} WIB.</p>@endif
    </section>

    <section id="ticket-details" aria-labelledby="ticket-details-title" class="scroll-mt-24 overflow-hidden rounded-md border border-ink-100 bg-white dark:border-ink-800 dark:bg-ink-900">
        <header class="flex flex-wrap items-start justify-between gap-3 p-4 sm:p-5">
            <div>
                <h2 id="ticket-details-title" class="text-base font-semibold">Detail Tiket <span class="text-ink-500 dark:text-ink-400">({{ $sourceUnavailable ? '—' : number_format($tickets->total(), 0, ',', '.') }})</span></h2>
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
            <div role="region" aria-label="Daftar detail tiket" tabindex="0" class="overflow-x-auto focus-visible:outline-2 focus-visible:outline-brand-500">
                <table class="w-full min-w-[850px] text-left text-sm">
                    <thead class="bg-ink-50 text-xs text-ink-500 dark:bg-ink-800 dark:text-ink-300"><tr>
                        @foreach (['Incident', 'Tanggal Laporan', 'Jenis Tiket', 'STO / Branch', 'Status Exclude', 'Alasan'] as $label)<th scope="col" class="px-4 py-3 font-semibold">{{ $label }}</th>@endforeach
                    </tr></thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                        @foreach ($tickets as $ticket)
                            <tr data-incident="{{ $ticket['incident'] }}">
                                <td class="px-4 py-3 font-medium">{{ $ticket['incident'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $ticket['reported_date'] ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $ticket['ticket_type'] }}</td>
                                <td class="px-4 py-3">{{ $ticket['workzone'] }}<span class="mt-1 block text-xs text-ink-500 dark:text-ink-400">{{ $ticket['branch'] ?? 'Belum terpetakan' }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3"><span class="rounded-md px-2 py-1 text-xs font-medium {{ $ticket['is_excluded'] ? 'bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200' : 'bg-ink-100 text-ink-700 dark:bg-ink-800 dark:text-ink-200' }}">{{ $ticket['is_excluded'] ? 'Sudah exclude' : 'Belum exclude' }}</span></td>
                                <td class="px-4 py-3">{{ $ticket['is_excluded'] ? $ticket['reason_label'] : '—' }}</td>
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
