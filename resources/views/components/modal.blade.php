@props(['id', 'title' => null])

<dialog id="{{ $id }}"
        {{ $attributes->class([
            'fixed inset-0 m-auto rounded-2xl shadow-xl border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-900 p-0 w-full max-w-md max-h-[85vh] backdrop:bg-black/30',
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

    <div class="p-5">
        {{ $slot }}
    </div>
</dialog>
