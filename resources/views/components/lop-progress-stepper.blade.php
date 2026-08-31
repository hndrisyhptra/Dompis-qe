@props(['current' => 1, 'state'])

@php
    $steps = [
        1 => ['Reservasi', $state['step1Complete']],
        2 => ['Pra', $state['step2Complete']],
        3 => ['Progress', $state['step3Complete']],
        4 => ['After', $state['step4Complete']],
    ];
@endphp

<div class="overflow-x-auto pb-1">
    <div class="flex min-w-[350px] items-start justify-between">
        @foreach ($steps as $number => [$label, $complete])
            <a href="{{ request()->url() }}?step={{ $number }}" class="relative flex flex-1 flex-col items-center text-center">
                @if ($number < 4)<span class="absolute left-1/2 top-4 h-0.5 w-full {{ $complete ? 'bg-brand-500' : 'bg-ink-200 dark:bg-ink-700' }}"></span>@endif
                <span class="relative z-10 grid h-8 w-8 place-items-center rounded-full border-2 text-xs font-bold
                    {{ $complete ? 'border-brand-600 bg-brand-600 text-white' : ($current === $number ? 'border-brand-600 bg-white text-brand-600 dark:bg-ink-900' : 'border-ink-200 bg-white text-ink-400 dark:border-ink-700 dark:bg-ink-900') }}">
                    @if ($complete)
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    @else{{ $number }}@endif
                </span>
                <span class="mt-2 text-[10px] font-semibold {{ $current === $number ? 'text-brand-600 dark:text-brand-400' : 'text-ink-500 dark:text-ink-400' }}">{{ $label }}</span>
            </a>
        @endforeach
    </div>
</div>
