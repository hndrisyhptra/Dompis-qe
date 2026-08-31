@props([
    'label',
    'href' => null,
    'tone' => 'neutral',
    'disabled' => false,
])

@php
    $tones = [
        'neutral' => 'border-ink-200 bg-white text-ink-600 hover:border-ink-300 hover:bg-ink-50 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-300 dark:hover:bg-ink-800',
        'primary' => 'border-brand-200 bg-brand-50 text-brand-700 hover:border-brand-300 hover:bg-brand-100 dark:border-brand-900/60 dark:bg-brand-950/30 dark:text-brand-300',
        'info' => 'border-blue-200 bg-blue-50 text-blue-700 hover:border-blue-300 hover:bg-blue-100 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-300',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:border-emerald-300 hover:bg-emerald-100 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300',
    ];
    $classes = 'grid h-8 w-8 place-items-center rounded-lg border transition focus:outline-none focus:ring-2 focus:ring-brand-500/30 '.($tones[$tone] ?? $tones['neutral']);
@endphp

<span class="group relative inline-flex">
    @if ($href && ! $disabled)
        <a href="{{ $href }}" aria-label="{{ $label }}" title="{{ $label }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
    @else
        <button type="button" aria-label="{{ $label }}" title="{{ $label }}" @disabled($disabled)
                {{ $attributes->class([$classes, 'cursor-not-allowed opacity-40' => $disabled]) }}>{{ $slot }}</button>
    @endif
    <span class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-ink-950 px-2.5 py-1.5 text-[10px] font-semibold text-white opacity-0 shadow-lg transition group-hover:opacity-100 group-focus-within:opacity-100">
        {{ $label }}
        <span class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-ink-950"></span>
    </span>
</span>
