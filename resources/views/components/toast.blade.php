@if (session('status'))
    <div
        x-data="{ show: true }"
        x-init="setTimeout(() => show = false, 3000)"
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed top-5 right-5 z-[9999] w-full max-w-sm"
    >
        <div class="rounded-2xl border border-emerald-200 dark:border-emerald-800 bg-white dark:bg-ink-900 shadow-2xl overflow-hidden">
            <div class="flex items-start gap-4 p-4">
                <div class="w-11 h-11 rounded-2xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 flex items-center justify-center text-lg font-bold shrink-0">
                    &check;
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-bold text-ink-900 dark:text-ink-50">Berhasil</h3>
                    <p class="text-sm text-ink-500 dark:text-ink-400 mt-1 leading-relaxed">{{ session('status') }}</p>
                </div>
                <button @click="show = false" class="text-ink-400 hover:text-ink-600 dark:hover:text-ink-200">
                    &times;
                </button>
            </div>
            <div class="h-1 bg-emerald-500"></div>
        </div>
    </div>
@endif
