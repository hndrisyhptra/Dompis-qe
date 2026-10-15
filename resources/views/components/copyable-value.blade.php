@props(['value', 'label'])

<div x-data="copyableValue(@js((string) $value))" {{ $attributes }}>
    <div class="flex items-center gap-1">
        <span class="break-all font-medium tabular-nums">{{ $value }}</span>
        <button type="button" @click="copy()" :disabled="copying" title="Salin {{ $label }}" aria-label="Salin {{ $label }} {{ $value }}"
                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-ink-500 hover:bg-ink-100 hover:text-ink-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:opacity-50 dark:text-ink-400 dark:hover:bg-ink-800 dark:hover:text-white">
            <svg x-show="!copied" aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="8" y="8" width="12" height="12" rx="2"/><path stroke-linecap="round" d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></svg>
            <svg x-show="copied" x-cloak aria-hidden="true" class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>
        </button>
    </div>
    <span x-show="copied" x-cloak role="status" class="sr-only">{{ $label }} disalin.</span>
    <p x-show="failed" x-cloak role="status" class="mt-1 text-xs text-ink-600 dark:text-ink-300">Tidak dapat menyalin. Pilih teks secara manual.</p>
</div>
