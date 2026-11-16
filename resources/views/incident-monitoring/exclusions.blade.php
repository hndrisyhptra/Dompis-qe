@extends('layouts.app')

@section('title', 'Monitoring Incident · Ringkasan Exclude')

@section('content')
@php
    $visibleScopeLabel = $scopeLabel !== 'Seluruh wilayah' ? $scopeLabel : null;
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
<div class="mx-auto max-w-7xl space-y-5" x-data="{ ...monitoringAutoRefresh(@js(route('incident-monitoring.exclusions.refresh', [], false)), @js($liveRevision), @js($checkedAt?->toIso8601String())), period: @js($filters['period']), dateFrom: @js($filters['date_from']), filtersOpen: false, loading: false }" :aria-busy="loading || monitoringRefreshing" @pageshow.window="loading = false; if ($event.persisted) refreshMonitoring()">
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
    <div data-monitoring-live-content data-monitoring-revision="{{ $liveRevision }}" x-ref="monitoringLiveContent">
        {!! $liveContentHtml !!}
    </div>
</div>
@endsection
