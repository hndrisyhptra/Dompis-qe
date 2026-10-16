@props(['status' => null])
@if (filled($status))
    <span class="inline-flex max-w-48 rounded-md border border-ink-200 bg-ink-50 px-2 py-1 text-[10px] font-bold text-ink-700 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-200" title="{{ $status }}">{{ $status }}</span>
@else
    <span class="text-xs text-ink-400" title="Belum ada hasil import AISS">—</span>
@endif
