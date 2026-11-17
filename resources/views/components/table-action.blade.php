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
        'danger' => 'border-red-200 bg-red-50 text-red-700 hover:border-red-300 hover:bg-red-100 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300',
    ];
    $classes = 'grid h-8 w-8 place-items-center rounded-lg border transition focus:outline-none focus:ring-2 focus:ring-brand-500/30 '.($tones[$tone] ?? $tones['neutral']);
@endphp

<span x-data="{ tooltipOpen: false, tooltipPosition: { top: 0, left: 0 }, positionTooltip() { const rect = $refs.action.getBoundingClientRect(); this.tooltipPosition = { top: rect.top < 40 ? rect.bottom + 6 : rect.top - 32, left: Math.min(window.innerWidth - 12, Math.max(12, rect.left + rect.width / 2)) }; this.tooltipOpen = true; } }" @pointerenter="positionTooltip()" @pointerleave="tooltipOpen = false" @focusin="positionTooltip()" @focusout="tooltipOpen = false" class="relative inline-flex">
    @if ($href && ! $disabled)
        <a x-ref="action" href="{{ $href }}" aria-label="{{ $label }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
    @else
        <button type="button" aria-label="{{ $label }}" @disabled($disabled)
                x-ref="action" {{ $attributes->class([$classes, 'cursor-not-allowed opacity-40' => $disabled]) }}>{{ $slot }}</button>
    @endif
    <template x-teleport="body">
    <span x-show="tooltipOpen" x-cloak aria-hidden="true" :style="`top:${tooltipPosition.top}px;left:${tooltipPosition.left}px`" x-transition:enter="transition ease-out duration-75" x-transition:enter-start="translate-y-0.5 opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-0.5 opacity-0" class="pointer-events-none fixed z-[200] max-w-[calc(100vw-1rem)] -translate-x-1/2 whitespace-nowrap rounded-md bg-ink-950 px-2.5 py-1.5 text-[11px] font-medium text-white shadow-lg">
        {{ $label }}
    </span>
    </template>
</span>
