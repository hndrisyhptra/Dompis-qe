<td class="border border-ink-200 p-0 dark:border-ink-700">
    @if ($sourceUnavailable)
        <span class="inline-flex min-h-11 items-center px-3 text-ink-400">—</span>
    @else
        <a href="{{ $detailUrl($cellQuery) }}" data-exclude-cell="{{ $matrixRow['key'] }}|{{ $cellKey }}" data-count="{{ $matrixRow['cells'][$cellKey] }}"
           aria-label="{{ $matrixRow['label'] }} · {{ $cellLabel }}: {{ $matrixRow['cells'][$cellKey] }} tiket. Lihat detail."
           @if ($filters['matrix_region'] === $cellQuery['matrix_region'] && $filters['detail_kind'] === $cellQuery['detail_kind'] && $filters['exclude_state'] === $cellQuery['exclude_state'] && $filters['reason'] === ($cellQuery['reason'] ?? '')) aria-current="true" @endif
           class="flex min-h-11 items-center justify-center px-3 py-2 font-semibold text-brand-700 underline decoration-brand-200 underline-offset-4 hover:bg-brand-50 focus-visible:outline-2 focus-visible:outline-brand-500 aria-[current=true]:bg-brand-100 dark:text-brand-300 dark:decoration-brand-700 dark:hover:bg-brand-900 dark:aria-[current=true]:bg-brand-900">{{ number_format($matrixRow['cells'][$cellKey], 0, ',', '.') }}</a>
    @endif
</td>
