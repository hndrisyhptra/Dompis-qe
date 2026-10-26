@props([
    'incident',
    'compact' => false,
])

@php
    $dialogId = 'unexclude-'.(string) \Illuminate\Support\Str::uuid();
    $buttonClasses = $compact
        ? 'inline-flex h-8 w-8 items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 text-emerald-800 transition hover:border-emerald-300 hover:bg-emerald-100 focus-visible:outline-2 focus-visible:outline-emerald-500 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200 dark:hover:bg-emerald-900/40'
        : 'inline-flex h-10 w-10 items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 text-emerald-800 transition hover:border-emerald-300 hover:bg-emerald-100 focus-visible:outline-2 focus-visible:outline-emerald-500 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200 dark:hover:bg-emerald-900/40';
    $iconClasses = $compact ? 'h-3.5 w-3.5' : 'h-4 w-4';
    $tooltipClasses = $compact
        ? 'right-full top-1/2 mr-2 -translate-y-1/2'
        : 'bottom-full left-0 mb-1.5';
@endphp

<div x-data="{}" class="group relative inline-flex">
    <button type="button" @click="if (! $refs.unexcludeDialog.open) $refs.unexcludeDialog.showModal()" aria-haspopup="dialog" aria-controls="{{ $dialogId }}" aria-label="Batalkan exclude tiket {{ $incident }}"
            class="{{ $buttonClasses }}">
        <svg class="{{ $iconClasses }} shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/>
        </svg>
    </button>
    <span aria-hidden="true" class="pointer-events-none absolute z-20 whitespace-nowrap rounded-md bg-ink-900 px-2 py-1 text-[11px] font-semibold text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100 group-focus-within:opacity-100 dark:bg-ink-100 dark:text-ink-900 {{ $tooltipClasses }}">Batalkan</span>

    <dialog x-ref="unexcludeDialog" id="{{ $dialogId }}" aria-labelledby="{{ $dialogId }}-title" aria-describedby="{{ $dialogId }}-description"
            class="m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-md border border-ink-200 bg-white p-0 text-left text-ink-900 shadow-2xl backdrop:bg-ink-950/60 dark:border-ink-700 dark:bg-ink-900 dark:text-white"
            @click="if ($event.target === $el) $el.close()">
        <div class="p-6">
            <div class="flex items-start gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300" aria-hidden="true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/>
                    </svg>
                </span>
                <div class="min-w-0">
                    <h2 id="{{ $dialogId }}-title" class="text-lg font-extrabold text-ink-900 dark:text-white">Batalkan exclude tiket?</h2>
                    <p id="{{ $dialogId }}-description" class="mt-2 text-sm leading-6 text-ink-500 dark:text-ink-400">
                        Tiket <span class="font-semibold text-ink-800 dark:text-ink-100">{{ $incident }}</span> akan kembali ke daftar
                        <span class="font-semibold">Belum memiliki LOP</span> dan dapat digunakan lagi. Alasan exclude akan dihapus.
                    </p>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-2 gap-3">
                <form method="dialog">
                    <button type="submit" class="min-h-11 w-full rounded-md border border-ink-200 text-sm font-bold text-ink-600 transition hover:bg-ink-50 focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-ink-700 dark:text-ink-300 dark:hover:bg-ink-800">Tidak</button>
                </form>
                <form method="POST" action="{{ route('incident-monitoring.tickets.unexclude', $incident) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="min-h-11 w-full rounded-md bg-emerald-600 text-sm font-extrabold text-white transition hover:bg-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500">Ya, batalkan</button>
                </form>
            </div>
        </div>
    </dialog>
</div>
