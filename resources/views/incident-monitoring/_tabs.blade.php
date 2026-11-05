<nav aria-label="Sumber monitoring" class="flex flex-wrap gap-1 border-b border-ink-200 dark:border-ink-700">
    @foreach (['incident-monitoring.tickets' => 'Tiket Dompis', 'incident-monitoring.exclusions' => 'Monitoring Exclude', 'incident-monitoring.bulk-exclusions' => 'Exclude Massal', 'incident-monitoring.index' => 'Incident ber LOP'] as $route => $label)
        @php $active = $route === 'incident-monitoring.bulk-exclusions' ? request()->routeIs('incident-monitoring.bulk-exclusions*') : request()->routeIs($route); @endphp
        <a href="{{ route($route) }}" @if($active) aria-current="page" @endif
           class="inline-flex min-h-11 items-center border-b-2 px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-brand-500 {{ $active ? 'border-brand-600 text-brand-700 dark:border-brand-400 dark:text-brand-200' : 'border-transparent text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white' }}">{{ $label }}</a>
    @endforeach
</nav>
