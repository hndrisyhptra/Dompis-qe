@php
    $sharedMonitoringQuery = request()->only([
        'q', 'kind', 'period', 'month', 'date_from', 'date_to', 'region', 'branch', 'service_area', 'service_area_name', 'sto_filter',
    ]);
    $sharedMonitoringQuery = array_filter($sharedMonitoringQuery, fn ($value) => $value !== '' && $value !== null && $value !== []);
    if (request()->routeIs('incident-monitoring.tickets')) {
        $sharedMonitoringQuery['exclude_state'] = [
            'all' => 'all', 'without_lop' => 'not_excluded', 'with_lop' => 'included', 'excluded' => 'excluded',
        ][request('usage', 'without_lop')] ?? 'not_excluded';
    }
    $isMonitoringWorkspace = request()->routeIs('incident-monitoring.tickets', 'incident-monitoring.exclusions');
    $isBulkWorkspace = request()->routeIs('incident-monitoring.bulk-exclusions*');
    $isLopWorkspace = request()->routeIs('incident-monitoring.index');
@endphp

<div class="space-y-3">
    <nav aria-label="Navigasi Monitoring Incident" class="flex flex-wrap items-end gap-x-1 border-b border-ink-200 dark:border-ink-700">
        <a href="{{ route('incident-monitoring.exclusions', $sharedMonitoringQuery) }}" @if($isMonitoringWorkspace) aria-current="page" @endif
           class="inline-flex min-h-11 items-center border-b-2 px-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-brand-500 {{ $isMonitoringWorkspace ? 'border-brand-600 text-brand-700 dark:border-brand-400 dark:text-brand-200' : 'border-transparent text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white' }}">Monitoring Exclude</a>
        <span class="hidden h-6 self-center border-l border-ink-200 sm:block dark:border-ink-700" aria-hidden="true"></span>
        <a href="{{ route('incident-monitoring.bulk-exclusions') }}" @if($isBulkWorkspace) aria-current="page" @endif
           class="inline-flex min-h-11 items-center border-b-2 px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-brand-500 {{ $isBulkWorkspace ? 'border-brand-600 text-brand-700 dark:border-brand-400 dark:text-brand-200' : 'border-transparent text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white' }}">Exclude Massal</a>
        <a href="{{ route('incident-monitoring.index') }}" @if($isLopWorkspace) aria-current="page" @endif
           class="inline-flex min-h-11 items-center border-b-2 px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-brand-500 {{ $isLopWorkspace ? 'border-brand-600 text-brand-700 dark:border-brand-400 dark:text-brand-200' : 'border-transparent text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white' }}">Incident ber LOP</a>
    </nav>
    @if ($isMonitoringWorkspace)
        <p class="text-xs text-ink-500 dark:text-ink-400">Ringkasan status, matriks wilayah, dan satu daftar tiket menggunakan filter periode dan lokasi yang sama.</p>
    @endif
</div>
