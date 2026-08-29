@props(['variant' => 'dark'])

@php
    $isDark = $variant === 'dark';
@endphp

<div {{ $attributes->class(['flex items-center gap-3']) }}>
    <div class="{{ $isDark ? 'w-10 h-10' : 'w-9 h-9' }} rounded-lg bg-brand-600 flex items-center justify-center shrink-0">
        <svg viewBox="0 0 24 24" class="{{ $isDark ? 'w-6 h-6' : 'w-5 h-5' }} text-white" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12 18.75h.008v.008H12v-.008z" />
        </svg>
    </div>
    <div>
        <p class="{{ $isDark ? 'text-white text-lg' : 'text-ink-900 dark:text-ink-50' }} font-bold leading-none tracking-tight">
            {{ config('app.name') }}
        </p>
        <p class="{{ $isDark ? 'text-ink-400 text-xs' : 'text-ink-500 dark:text-ink-400 text-[11px]' }} mt-0.5">
            Quality Enhancement Operations
        </p>
    </div>
</div>
