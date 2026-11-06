@php
    $isExcluded = ($cellQuery['exclude_state'] ?? '') === 'excluded';
    $isIncluded = ($cellQuery['exclude_state'] ?? '') === 'included';
    $isTotal = str_ends_with($cellKey, ':total');
    $numberClasses = $isExcluded
        ? 'text-emerald-800 decoration-emerald-300 hover:bg-emerald-50 focus-visible:outline-emerald-500 aria-[current=true]:bg-emerald-100 dark:text-emerald-300 dark:decoration-emerald-700 dark:hover:bg-emerald-950 dark:aria-[current=true]:bg-emerald-900'
        : ($isIncluded
            ? 'text-blue-800 decoration-blue-300 hover:bg-blue-200 focus-visible:outline-blue-500 aria-[current=true]:bg-blue-200 dark:text-blue-200 dark:decoration-blue-700 dark:hover:bg-blue-900/60 dark:aria-[current=true]:bg-blue-900'
            : ($isTotal
                ? 'text-ink-800 decoration-ink-300 hover:bg-ink-200 focus-visible:outline-ink-500 aria-[current=true]:bg-ink-200 dark:text-ink-100 dark:decoration-ink-600 dark:hover:bg-ink-700 dark:aria-[current=true]:bg-ink-700'
                : 'text-brand-800 decoration-brand-300 hover:bg-brand-200 focus-visible:outline-brand-500 aria-[current=true]:bg-brand-200 dark:text-brand-200 dark:decoration-brand-700 dark:hover:bg-brand-900/60 dark:aria-[current=true]:bg-brand-900'));
    $cellBackground = $isExcluded
        ? ''
        : ($isIncluded
            ? 'bg-blue-50/70 dark:bg-blue-950/20'
            : ($isTotal ? 'bg-ink-50 dark:bg-ink-800/50' : 'bg-brand-100/70 dark:bg-brand-900/30'));
    $cellClasses = 'border border-ink-200 p-0 dark:border-ink-700 '.$cellBackground;
@endphp
<td class="{{ $cellClasses }}">
    @if ($sourceUnavailable)
        <span class="inline-flex min-h-11 items-center px-3 text-ink-400">—</span>
    @else
        <a href="{{ $detailUrl($cellQuery) }}" data-exclude-cell="{{ $matrixRow['key'] }}|{{ $cellKey }}" data-count="{{ $matrixRow['cells'][$cellKey] }}"
           aria-label="{{ $matrixRow['label'] }} · {{ $cellLabel }}: {{ $matrixRow['cells'][$cellKey] }} tiket. Lihat detail."
           @if ($filters['matrix_region'] === $cellQuery['matrix_region'] && $filters['detail_kind'] === $cellQuery['detail_kind'] && $filters['exclude_state'] === $cellQuery['exclude_state'] && $filters['reason'] === ($cellQuery['reason'] ?? '')) aria-current="true" @endif
           class="flex min-h-11 items-center justify-center px-3 py-2 font-semibold underline underline-offset-4 focus-visible:outline-2 {{ $numberClasses }}">{{ number_format($matrixRow['cells'][$cellKey], 0, ',', '.') }}</a>
    @endif
</td>
