@php
    $disabled = $disabled ?? false;
    $display = $sourceUnavailable ? '—' : number_format((int) $value, 0, ',', '.');
@endphp
<td class="{{ $class }}">
    @if ($disabled || $sourceUnavailable)
        <span>{{ $display }}</span>
    @else
        <a href="{{ $url }}" data-monitoring-detail="{{ $url }}" data-monitoring-title="{{ $title }}" @click.prevent="openDetail($event)" title="Lihat {{ $title }}" class="inline-flex min-h-9 min-w-9 items-center justify-center rounded px-1.5 hover:bg-brand-100 hover:text-brand-900 focus-visible:outline-2 focus-visible:outline-brand-500 dark:hover:bg-brand-900/60 dark:hover:text-brand-50">{{ $display }}</a>
    @endif
</td>
