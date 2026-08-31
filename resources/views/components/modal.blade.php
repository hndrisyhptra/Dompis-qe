@props(['id', 'title' => null, 'size' => 'md'])

@php
    $modalSize = match ($size) {
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
        default => 'max-w-md',
    };
@endphp

<dialog id="{{ $id }}"
        {{ $attributes->class([
            "fixed inset-0 m-auto rounded-2xl shadow-2xl border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-900 p-0 w-[calc(100%-2rem)] {$modalSize} max-h-[90vh] backdrop:bg-ink-950/60 backdrop:backdrop-blur-sm",
        ]) }}>
    <div class="flex items-center justify-between px-5 py-4 border-b border-ink-100 dark:border-ink-700">
        @if ($title)
            <h3 class="text-sm font-semibold text-ink-900 dark:text-ink-50">{{ $title }}</h3>
        @endif
        <button type="button" onclick="document.getElementById('{{ $id }}').close()"
                class="ml-auto text-ink-400 hover:text-ink-700 dark:hover:text-ink-200 text-xl leading-none">
            &times;
        </button>
    </div>

    <div class="max-h-[calc(90vh-57px)] overflow-y-auto p-5">
        {{ $slot }}
    </div>
</dialog>
