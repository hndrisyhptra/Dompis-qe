@props(['status', 'compact' => false])

@php
    $appearance = match ($status) {
        \App\Enums\LopStatus::DRAFT => [
            'classes' => 'border-ink-300 bg-ink-50 text-ink-700 dark:border-ink-600 dark:bg-ink-800/70 dark:text-ink-200',
            'icon' => 'draft',
        ],
        \App\Enums\LopStatus::ASSIGNED => [
            'classes' => 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/35 dark:text-blue-300',
            'icon' => 'assigned',
        ],
        \App\Enums\LopStatus::PICKED_UP => [
            'classes' => 'border-cyan-300 bg-cyan-50 text-cyan-800 dark:border-cyan-800 dark:bg-cyan-950/35 dark:text-cyan-300',
            'icon' => 'picked-up',
        ],
        \App\Enums\LopStatus::SURVEY => [
            'classes' => 'border-indigo-300 bg-indigo-50 text-indigo-700 dark:border-indigo-800 dark:bg-indigo-950/35 dark:text-indigo-300',
            'icon' => 'survey',
        ],
        \App\Enums\LopStatus::PROGRESS => [
            'classes' => 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/35 dark:text-blue-300',
            'icon' => 'progress',
        ],
        \App\Enums\LopStatus::WAITING_APPROVAL => [
            'classes' => 'border-violet-300 bg-violet-50 text-violet-700 dark:border-violet-800 dark:bg-violet-950/35 dark:text-violet-300',
            'icon' => 'submitted',
        ],
        \App\Enums\LopStatus::COMPLETED => [
            'classes' => 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/35 dark:text-emerald-300',
            'icon' => 'completed',
        ],
        \App\Enums\LopStatus::REJECTED => [
            'classes' => 'border-red-300 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-950/35 dark:text-red-300',
            'icon' => 'rejected',
        ],
    };
@endphp

<span {{ $attributes->class([
    'inline-flex shrink-0 items-center whitespace-nowrap rounded-full border py-0.5 font-semibold leading-4',
    $compact ? 'gap-0.5 px-1 text-[10px]' : 'gap-1.5 px-2.5 text-[11px]',
    $appearance['classes'],
]) }}>
    <svg aria-hidden="true" @class(['shrink-0' => true, 'h-3 w-3' => $compact, 'h-3.5 w-3.5' => ! $compact]) viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        @switch($appearance['icon'])
            @case('draft')
                <circle cx="12" cy="12" r="8.25"/><path d="M8.5 12h7"/>
                @break
            @case('assigned')
                <circle cx="9" cy="8" r="3.25"/><path d="M3.5 19.5a5.5 5.5 0 0 1 11 0M18 7v6m-3-3h6"/>
                @break
            @case('picked-up')
                <path d="M4 8.5h16v11H4zM8 8.5V5h8v3.5M8 13h8m-8 3h5"/>
                @break
            @case('survey')
                <path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.25"/>
                @break
            @case('progress')
                <path d="M20 7v5h-5M4.5 16a8 8 0 0 0 13.4 2M4 17v-5h5M19.5 8A8 8 0 0 0 6.1 6"/>
                @break
            @case('submitted')
                <path d="m21 3-7.2 18-3.7-7.1L3 10.2 21 3Z"/><path d="m10.1 13.9 4-4"/>
                @break
            @case('completed')
                <circle cx="12" cy="12" r="9"/><path d="m8 12.2 2.6 2.6 5.5-5.6"/>
                @break
            @case('rejected')
                <circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/>
                @break
        @endswitch
    </svg>
    <span>{{ $status->label() }}</span>
</span>
