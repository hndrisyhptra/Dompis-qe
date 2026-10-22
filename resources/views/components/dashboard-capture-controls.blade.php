@props(['id', 'label'])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1.5']) }} data-capture-ignore>
    <label class="sr-only" for="{{ $id }}">Format gambar {{ $label }}</label>
    <select id="{{ $id }}" x-model="format" :disabled="capturing"
            class="h-8 rounded-md border border-ink-200 bg-white px-2 text-[10px] font-bold text-ink-600 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:opacity-60 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-200">
        <option value="png">PNG</option>
        <option value="jpeg">JPG / JPEG</option>
    </select>
    <button type="button" @click="download()" :disabled="capturing" :aria-busy="capturing"
            title="Capture gambar {{ $label }}"
            class="inline-flex h-8 items-center gap-1.5 rounded-md border border-ink-200 bg-white px-2.5 text-[10px] font-bold text-ink-600 transition hover:border-emerald-300 hover:text-emerald-700 disabled:cursor-wait disabled:opacity-60 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-200 dark:hover:border-emerald-800 dark:hover:text-emerald-300">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/>
        </svg>
        <span x-text="capturing ? 'Membuat…' : 'Capture'">Capture</span>
    </button>
    <span x-show="captureError" x-cloak x-text="captureError" class="text-[10px] font-semibold text-red-600 dark:text-red-400"></span>
</div>
