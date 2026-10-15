@extends('layouts.app')

@section('title', 'Monitoring Incident')

@section('content')
@php
    $filterQuery = array_filter($filters, fn ($value) => $value !== '');
    $filterUrl = fn (array $changes = []) => route('incident-monitoring.tickets', array_filter(array_replace($filterQuery, $changes), fn ($value) => $value !== ''));
    $usageLabels = ['all' => 'Semua tiket', 'without_lop' => 'Belum memiliki LOP', 'with_lop' => 'Sudah memiliki LOP'];
    $showLopColumns = $filters['usage'] !== 'without_lop';
    $hasFilters = $filters['region'] !== '' || $filters['branch'] !== '' || $filters['service_area'] !== '' || $filters['kind'] !== '' || $filters['q'] !== '' || $filters['date_from'] !== '' || $filters['date_to'] !== '';
@endphp

<div class="mx-auto max-w-7xl space-y-5" x-data="{ loading: false, dateFrom: @js($filters['date_from']), filtersOpen: {{ $hasFilters ? 'true' : 'false' }} }" :aria-busy="loading" @pageshow.window="loading = false">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink-900 dark:text-white">Monitoring Incident</h1>
            <p class="mt-1.5 text-sm text-ink-600 dark:text-ink-300">{{ $scopeLabel }}</p>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Tiket INFRACARE dan yang mengandung GAMAS dari Dompis, termasuk yang belum memiliki LOP.</p>
        </div>
        <p x-show="loading" x-cloak role="status" class="text-sm text-ink-600 dark:text-ink-300">Memuat tiket…</p>
    </header>

    @include('incident-monitoring._tabs')

    @if ($scopeWarning)
        <div role="status" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">STO dalam scope akun belum tersedia. Hubungi Super Admin untuk melengkapi akses wilayah.</div>
    @endif

    <section aria-label="Filter tiket Dompis" class="rounded-md border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900 sm:p-5">
        <button type="button" @click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen" aria-controls="ticket-filters" class="mb-3 flex min-h-10 w-full items-center justify-between rounded-md text-sm font-medium focus-visible:outline-2 focus-visible:outline-brand-500 lg:hidden">
            Filter tiket <span x-text="filtersOpen ? 'Tutup' : 'Buka'" class="text-ink-500 dark:text-ink-400">Buka</span>
        </button>
        <form id="ticket-filters" method="GET" action="{{ route('incident-monitoring.tickets') }}" @submit="loading = true" :class="filtersOpen ? '' : 'hidden lg:block'">
            <input type="hidden" name="usage" value="{{ $filters['usage'] }}">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <x-input name="q" id="ticket-search" label="Cari nomor incident atau workzone" :value="$filters['q']" :use-old="false" placeholder="Nomor incident atau STO…" maxlength="100" class="!rounded-md !border-ink-200 dark:!border-ink-700" />
                <x-select name="kind" label="Jenis tiket" placeholder="INFRACARE dan GAMAS" class="!rounded-md !border-ink-200 dark:!border-ink-700">
                    <option value="gamas" @selected($filters['kind'] === 'gamas')>GAMAS (semua variasi)</option>
                    <option value="infracare" @selected($filters['kind'] === 'infracare')>INFRACARE</option>
                </x-select>
                <x-input name="date_from" label="Tanggal laporan dari" type="date" :value="$filters['date_from']" :use-old="false" x-model="dateFrom" class="!rounded-md !border-ink-200 dark:!border-ink-700" />
                <x-input name="date_to" label="Tanggal laporan sampai" type="date" :value="$filters['date_to']" :use-old="false" x-bind:min="dateFrom || undefined" class="!rounded-md !border-ink-200 dark:!border-ink-700" />
                <x-select name="region" label="Region" placeholder="Semua region dalam scope" class="!rounded-md !border-ink-200 dark:!border-ink-700">
                    @foreach ($regions as $region)
                        <option value="{{ $region }}" @selected($filters['region'] === $region)>{{ $region }}</option>
                    @endforeach
                    @if ($filters['region'] !== '' && ! $regions->contains($filters['region']))<option value="{{ $filters['region'] }}" selected>Region di luar pilihan</option>@endif
                </x-select>
                <x-select name="branch" label="Branch" placeholder="Semua branch dalam scope" class="!rounded-md !border-ink-200 dark:!border-ink-700">
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->getKey() }}" @selected((string) $filters['branch'] === (string) $branch->getKey())>{{ $branch->name }}</option>
                    @endforeach
                    @if ($filters['branch'] !== '' && ! $branches->contains('id_branch', (int) $filters['branch']))<option value="{{ $filters['branch'] }}" selected>Branch di luar pilihan</option>@endif
                </x-select>
                <x-select name="service_area" label="Service Area / STO" placeholder="Semua STO dalam scope" class="!rounded-md !border-ink-200 dark:!border-ink-700">
                    @foreach ($serviceAreas as $serviceArea)
                        <option value="{{ $serviceArea->getKey() }}" @selected((string) $filters['service_area'] === (string) $serviceArea->getKey())>{{ $serviceArea->workzone }}</option>
                    @endforeach
                    @if ($filters['service_area'] !== '' && ! $serviceAreas->contains('id_service_area', (int) $filters['service_area']))<option value="{{ $filters['service_area'] }}" selected>STO di luar pilihan</option>@endif
                </x-select>
                <div class="flex items-end gap-2">
                    <x-button x-bind:disabled="loading" class="!min-h-11 !w-auto flex-1 !rounded-md !shadow-none disabled:opacity-60"><span x-text="loading ? 'Memuat…' : 'Terapkan'">Terapkan</span></x-button>
                    <a href="{{ route('incident-monitoring.tickets') }}" @click="loading = true" class="inline-flex min-h-11 items-center rounded-md border border-ink-200 px-3 text-sm font-medium text-ink-600 hover:bg-ink-50 focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-ink-700 dark:text-ink-300 dark:hover:bg-ink-800">Reset</a>
                </div>
            </div>
            <p class="mt-3 text-xs text-ink-500 dark:text-ink-400">Berdasarkan reported date tiket Dompis. Kosongkan tanggal untuk melihat semua periode.</p>
        </form>
        @if ($hasFilters)
            <p class="mt-3 text-xs text-ink-500 dark:text-ink-400">Filter aktif: {{ $filters['q'] !== '' ? 'pencarian “'.$filters['q'].'”; ' : '' }}{{ $filters['kind'] !== '' ? strtoupper($filters['kind']).'; ' : '' }}{{ $filters['date_from'] !== '' ? 'Dari '.$filters['date_from'].'; ' : '' }}{{ $filters['date_to'] !== '' ? 'Sampai '.$filters['date_to'].'; ' : '' }}{{ $filters['region'] !== '' ? $filters['region'].'; ' : '' }}{{ $filters['branch'] !== '' ? 'Branch pilihan; ' : '' }}{{ $filters['service_area'] !== '' ? 'STO pilihan' : '' }}</p>
        @endif
    </section>

    @if ($sourceUnavailable)
        <div role="alert" class="flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-800 dark:bg-amber-950/30 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="font-medium text-amber-900 dark:text-amber-200">Data tiket Dompis belum dapat dimuat.</p><p class="mt-1 text-amber-800 dark:text-amber-300">Jumlah dan penggunaan tiket belum dapat dipastikan. Coba kembali setelah koneksi tersedia.</p></div>
            <a href="{{ $filterUrl() }}" @click="loading = true" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-md border border-amber-300 px-3 font-medium text-amber-900 focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-amber-700 dark:text-amber-200">Coba kembali</a>
        </div>
    @endif

    <section aria-label="Ringkasan seluruh penggunaan tiket sesuai filter" class="grid grid-cols-2 gap-3 lg:grid-cols-3">
        @foreach ([['total', 'Total tiket', 'all'], ['without_lop', 'Belum memiliki LOP', 'without_lop'], ['with_lop', 'Sudah memiliki LOP', 'with_lop']] as [$key, $label, $usage])
            <a href="{{ $filterUrl(['usage' => $usage]) }}" @click="loading = true" class="rounded-md border bg-white p-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:bg-ink-900 sm:p-5 {{ $loop->first ? 'col-span-2 lg:col-span-1' : '' }} {{ $filters['usage'] === $usage ? 'border-brand-300 dark:border-brand-600' : 'border-ink-100 hover:border-ink-300 dark:border-ink-800 dark:hover:border-ink-600' }}">
                <p class="text-sm font-medium text-ink-600 dark:text-ink-300">{{ $label }}</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight tabular-nums text-ink-900 dark:text-white" data-metric="{{ $key }}">{{ $stats === null ? '—' : number_format($stats[$key], 0, ',', '.') }}</p>
            </a>
        @endforeach
    </section>

    @if (! $sourceUnavailable)
        <section aria-labelledby="ticket-chart-heading" class="rounded-md border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900 sm:p-5">
            <h2 id="ticket-chart-heading" class="text-base font-semibold text-ink-900 dark:text-white">Jumlah tiket per jenis</h2>
            <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Ringkasan mencakup semua penggunaan tiket sesuai filter wilayah, jenis, tanggal, dan pencarian.</p>
            @if ($chartRows->isEmpty())<p class="py-5 text-sm text-ink-500 dark:text-ink-400">Tidak ada tiket yang sesuai filter.</p>@endif
            <ul class="mt-3 space-y-1" aria-label="Jumlah tiket GAMAS dan INFRACARE">
                @foreach ($chartRows as $row)
                    <li><a href="{{ $filterUrl(['kind' => $row['kind']]) }}" @click="loading = true" aria-label="{{ $row['label'] }}: {{ $row['count'] }} tiket. Filter jenis tiket."
                           class="grid min-h-11 grid-cols-[100px_minmax(0,1fr)_56px] items-center gap-3 rounded-md px-2 py-2 hover:bg-ink-50 focus-visible:outline-2 focus-visible:outline-brand-500 dark:hover:bg-ink-800">
                        <span class="text-sm font-medium">{{ $row['label'] }}</span>
                        <span aria-hidden="true" class="h-3 rounded-sm bg-ink-50 dark:bg-ink-800"><span class="block h-full rounded-sm bg-brand-600 dark:bg-brand-400" style="width: {{ $row['width'] }}%"></span></span>
                        <span class="text-right text-sm font-semibold tabular-nums">{{ number_format($row['count'], 0, ',', '.') }}</span>
                    </a></li>
                @endforeach
            </ul>
            @if ($checkedAt)
                <p class="mt-3 border-t border-ink-100 pt-3 text-xs text-ink-500 dark:border-ink-800 dark:text-ink-400">
                    Dibaca {{ $checkedAt->format('d/m/Y H:i') }} WIB.
                </p>
            @endif
        </section>
    @endif

    <section aria-labelledby="ticket-detail-heading" class="overflow-hidden rounded-md border border-ink-100 bg-white dark:border-ink-800 dark:bg-ink-900">
        <div class="border-b border-ink-100 p-4 dark:border-ink-800 sm:px-5">
            <h2 id="ticket-detail-heading" class="text-base font-semibold text-ink-900 dark:text-white">Daftar tiket Dompis</h2>
            <nav aria-label="Penggunaan tiket" class="mt-3 flex flex-wrap gap-2">
                @foreach ($usageLabels as $usage => $label)
                    <a href="{{ $filterUrl(['usage' => $usage]) }}" @click="loading = true" @if($filters['usage'] === $usage) aria-current="page" @endif
                       class="inline-flex min-h-10 items-center gap-2 rounded-md border px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-brand-500 {{ $filters['usage'] === $usage ? 'border-brand-200 bg-brand-50 text-brand-700 dark:border-brand-700 dark:bg-brand-900/30 dark:text-brand-200' : 'border-ink-200 text-ink-600 hover:bg-ink-50 dark:border-ink-700 dark:text-ink-300 dark:hover:bg-ink-800' }}">
                        {{ $label }}<span class="tabular-nums">{{ $stats === null ? '—' : number_format($stats[$usage === 'all' ? 'total' : $usage], 0, ',', '.') }}</span>
                    </a>
                @endforeach
            </nav>
            <p class="mt-3 text-sm text-ink-500 dark:text-ink-400">{{ $usageLabels[$filters['usage']] }}{{ $sourceUnavailable ? '' : ' · '.number_format($tickets->total(), 0, ',', '.').' tiket' }}</p>
        </div>
        @if ($sourceUnavailable)
            <p class="px-5 py-8 text-center text-sm text-ink-500 dark:text-ink-400">Daftar belum tersedia karena data sumber belum dapat dimuat.</p>
        @elseif ($tickets->isEmpty())
            <div class="px-5 py-8 text-center">
                <p class="text-sm font-medium">{{ $tickets->total() > 0 ? 'Halaman ini tidak memiliki data.' : 'Tidak ada tiket pada pilihan ini.' }}</p>
                <a href="{{ $tickets->total() > 0 ? $filterUrl() : $filterUrl(['usage' => 'all']) }}" class="mt-3 inline-flex min-h-10 items-center text-sm font-medium text-brand-600 hover:underline dark:text-brand-300">{{ $tickets->total() > 0 ? 'Kembali ke halaman pertama' : 'Lihat semua penggunaan tiket' }}</a>
            </div>
        @else
            <div class="divide-y divide-ink-100 dark:divide-ink-800 lg:hidden">
                @foreach ($tickets as $row)
                    <article class="space-y-3 p-4">
                        <x-copyable-value :value="$row['incident']" label="Incident" class="text-sm" />
                        <p class="text-xs text-ink-500 dark:text-ink-400">Tanggal laporan: {{ $row['reported_date'] ?? 'Belum terisi' }}</p>
                        <div class="flex flex-wrap items-center gap-2"><span class="text-sm">{{ $row['ticket_type'] }}</span><x-badge :variant="$row['usage'] === 'without_lop' ? 'neutral' : 'info'">{{ $usageLabels[$row['usage']] }}</x-badge></div>
                        <p class="text-sm text-ink-600 dark:text-ink-300">{{ $row['branch'] ?? 'Branch belum terpetakan' }} / {{ $row['workzone'] }}</p>
                        @if ($showLopColumns)
                            @if ($row['lop'])
                                <p class="break-words text-sm font-medium">{{ $row['lop']->nama_lop }}</p>
                                <div class="text-sm"><span class="text-xs text-ink-500 dark:text-ink-400">ID IHLD</span>@if(filled($row['lop']->ihld_id))<x-copyable-value :value="$row['lop']->ihld_id" label="ID IHLD" />@else<p class="mt-1 text-ink-500 dark:text-ink-400">Belum diisi</p>@endif</div>
                                @if ($row['usage'] === 'with_lop')
                                    <div class="flex flex-wrap items-center justify-between gap-2"><x-badge :variant="$row['lop']->status_lop->badgeVariant()">{{ $row['lop']->status_lop->label() }}</x-badge><a href="{{ route('lop.show', $row['lop']) }}" aria-label="Detail LOP {{ $row['incident'] }}" class="inline-flex min-h-10 items-center text-sm font-medium text-brand-600 hover:underline dark:text-brand-300">Detail LOP →</a></div>
                                @endif
                            @elseif ($row['usage'] !== 'without_lop')<p class="text-sm text-ink-500 dark:text-ink-400">Detail LOP tidak tersedia.</p>@endif
                        @endif
                    </article>
                @endforeach
            </div>
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full text-left text-sm {{ $showLopColumns ? 'min-w-5xl' : '' }}">
                    <caption class="sr-only">Tiket INFRACARE dan GAMAS beserta penggunaan LOP sesuai pilihan.</caption>
                    <thead class="border-b border-ink-100 bg-ink-50 text-xs font-medium text-ink-600 dark:border-ink-800 dark:bg-ink-800/50 dark:text-ink-300"><tr>
                        <th scope="col" class="px-4 py-3">Incident</th><th scope="col" class="px-3 py-3">Jenis tiket</th><th scope="col" class="px-3 py-3">Branch / STO</th><th scope="col" class="px-3 py-3">Penggunaan</th>
                        @if ($showLopColumns)<th scope="col" class="px-3 py-3">LOP / Status</th><th scope="col" class="px-3 py-3">ID IHLD</th><th scope="col" class="px-3 py-3">Aksi</th>@endif
                    </tr></thead>
                    <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                        @foreach ($tickets as $row)
                            <tr class="align-top hover:bg-ink-50/70 dark:hover:bg-ink-800/30">
                                <td class="px-4 py-3"><x-copyable-value :value="$row['incident']" label="Incident" /><p class="mt-1 text-xs text-ink-500 dark:text-ink-400" title="Tanggal laporan">{{ $row['reported_date'] ?? 'Tanggal laporan belum terisi' }}</p></td>
                                <td class="px-3 py-4">{{ $row['ticket_type'] }}</td>
                                <td class="px-3 py-4"><p>{{ $row['branch'] ?? 'Belum terpetakan' }}</p><p class="mt-1 text-xs text-ink-500 dark:text-ink-400">{{ $row['workzone'] }}</p></td>
                                <td class="px-3 py-4"><x-badge :variant="$row['usage'] === 'without_lop' ? 'neutral' : 'info'">{{ $usageLabels[$row['usage']] }}</x-badge></td>
                                @if ($showLopColumns)
                                    <td class="px-3 py-4">
                                        @if ($row['lop'])<p class="max-w-xs break-words font-medium">{{ $row['lop']->nama_lop }}</p>@if($row['usage'] === 'with_lop')<div class="mt-2"><x-badge :variant="$row['lop']->status_lop->badgeVariant()">{{ $row['lop']->status_lop->label() }}</x-badge></div>@endif
                                        @else<span class="text-ink-500 dark:text-ink-400">{{ $row['usage'] === 'without_lop' ? '—' : 'Detail LOP tidak tersedia' }}</span>@endif
                                    </td>
                                    <td class="px-3 py-3">@if($row['lop'] && filled($row['lop']->ihld_id))<x-copyable-value :value="$row['lop']->ihld_id" label="ID IHLD" />@else<span class="inline-block py-1 text-ink-500 dark:text-ink-400">{{ $row['lop'] ? 'Belum diisi' : '—' }}</span>@endif</td>
                                    <td class="px-3 py-4">@if($row['lop'] && $row['usage'] === 'with_lop')<a href="{{ route('lop.show', $row['lop']) }}" aria-label="Detail LOP {{ $row['incident'] }}" class="inline-flex min-h-8 items-center font-medium text-brand-600 hover:underline focus-visible:outline-2 focus-visible:outline-brand-500 dark:text-brand-300">Detail</a>@else<span class="text-ink-500 dark:text-ink-400">—</span>@endif</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if ($tickets->hasPages())<div class="border-t border-ink-100 px-4 py-4 dark:border-ink-800 sm:px-5">{{ $tickets->links() }}</div>@endif
    </section>
</div>
@endsection
