@props(['variant' => 'neutral'])

@php
    $variants = [
        'success' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800',
        'danger' => 'bg-brand-50 text-brand-700 border-brand-200 dark:bg-brand-900/30 dark:text-brand-300 dark:border-brand-800',
        'neutral' => 'bg-ink-50 text-ink-600 border-ink-100 dark:bg-ink-800 dark:text-ink-300 dark:border-ink-700',
        'info' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800',
        'warning' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
    ];
    $classes = $variants[$variant] ?? $variants['neutral'];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border',
    $classes,
]) }}>
    {{ $slot }}
</span>
