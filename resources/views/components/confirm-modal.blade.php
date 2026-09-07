@props([
    'state',                       // nama variabel boolean Alpine di scope pemanggil, mis. "showConfirm"
    'title',
    'message' => null,
    'action',                      // URL form tujuan
    'method' => 'POST',
    'confirmLabel' => 'Ya, lanjut',
    'cancelLabel' => 'Batal',
    'tone' => 'brand',             // brand | danger
])

<div x-show="{{ $state }}" x-cloak
     class="fixed inset-0 z-[90] flex items-end justify-center bg-ink-950/60 p-4 backdrop-blur-sm sm:items-center"
     @click.self="{{ $state }} = false" @keydown.escape.window="{{ $state }} = false"
     x-transition.opacity>
    <div x-show="{{ $state }}"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         role="dialog" aria-modal="true"
         class="w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl dark:bg-ink-900">
        <h3 class="text-base font-extrabold text-ink-900 dark:text-white">{{ $title }}</h3>
        @if ($message)
            <p class="mt-2 text-sm leading-6 text-ink-500 dark:text-ink-400">{{ $message }}</p>
        @endif

        {{ $slot }}

        <div class="mt-6 grid grid-cols-2 gap-3">
            <button type="button" @click="{{ $state }} = false"
                    class="min-h-11 rounded-2xl border border-ink-200 text-sm font-bold text-ink-600 dark:border-ink-700 dark:text-ink-300">{{ $cancelLabel }}</button>
            <form method="POST" action="{{ $action }}">
                @csrf
                @if (strtoupper($method) !== 'POST') @method($method) @endif
                <button type="submit"
                        class="min-h-11 w-full rounded-2xl text-sm font-extrabold text-white {{ $tone === 'danger' ? 'bg-red-600' : 'bg-brand-600 shadow-lg shadow-brand-600/20' }}">{{ $confirmLabel }}</button>
            </form>
        </div>
    </div>
</div>
