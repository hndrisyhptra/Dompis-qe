@props(['variant' => 'dark'])

@php
    $isDark = $variant === 'dark';
    $isAdaptive = $variant === 'adaptive';
@endphp

<div {{ $attributes->class(['flex items-center gap-3']) }}>
    <div class="{{ $isDark || $isAdaptive ? 'h-11 w-11' : 'h-10 w-10' }} flex shrink-0 items-center justify-center rounded-xl border border-ink-100 bg-white p-1.5 shadow-sm shadow-ink-900/5 dark:border-white/10 dark:bg-white">
        <img
            src="{{ asset('images/logo-dompis-qe.webp') }}"
            alt="Logo {{ config('app.name') }}"
            class="h-full w-full object-contain"
        >
    </div>
    <div>
        <p class="{{ $isDark ? 'text-white text-lg' : ($isAdaptive ? 'text-ink-900 dark:text-white text-lg' : 'text-ink-900 dark:text-ink-50') }} font-bold leading-none tracking-tight">
            {{ config('app.name') }}
        </p>
        <p class="{{ $isDark ? 'text-ink-400 text-xs' : ($isAdaptive ? 'text-ink-500 dark:text-ink-400 text-xs' : 'text-ink-500 dark:text-ink-400 text-[11px]') }} mt-0.5">
            Quality Enhancement Operations
        </p>
    </div>
</div>
