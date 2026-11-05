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
        <div class="rounded-2xl border border-emerald-100 dark:border-emerald-900 bg-white dark:bg-ink-900 shadow-[0_20px_50px_rgba(0,0,0,0.1)] dark:shadow-none overflow-hidden">
            <div class="flex items-center gap-4 p-4">
                <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </div>
                <div class="flex-1 min-w-0 ml-1">
                    <p class="text-sm font-bold text-ink-900 dark:text-ink-50 leading-snug">{{ session('status') }}</p>
                </div>
                <button @click="show = false" class="w-8 h-8 flex items-center justify-center rounded-lg text-ink-400 hover:bg-ink-50 dark:hover:bg-ink-800 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    </div>
@endif
