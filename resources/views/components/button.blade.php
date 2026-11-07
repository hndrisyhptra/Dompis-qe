@props(['type' => 'submit', 'loadingLabel' => 'Memproses…'])

<button
    type="{{ $type }}"
    data-loading-label="{{ $loadingLabel }}"
    {{ $attributes->class([
        'inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition duration-150 hover:bg-brand-700 active:scale-[.99] active:bg-brand-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:cursor-not-allowed disabled:opacity-55',
    ]) }}
>
    <span data-submit-spinner class="hidden h-4 w-4 animate-spin" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" class="h-full w-full"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg></span>
    <span data-submit-label>{{ $slot }}</span>
</button>
