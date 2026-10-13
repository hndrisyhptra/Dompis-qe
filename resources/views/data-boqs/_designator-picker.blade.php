<p class="text-[10px] font-bold uppercase text-ink-500">Designator</p>
<input type="hidden" :name="'items[' + index + '][designator_id]'" :value="item.designator_id" required>
<div class="relative mt-1.5" @click.outside="item.open = false; item.search = ''">
    <button type="button" @click="toggleOptions(item)" :aria-expanded="item.open" class="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-ink-200 bg-white px-3 text-left text-sm shadow-sm outline-none transition hover:border-ink-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950">
        <span class="min-w-0 flex-1 truncate" :class="item.designator_id ? 'font-semibold text-ink-800 dark:text-ink-100' : 'text-ink-400'" x-text="selectedOptionLabel(item.designator_id)"></span>
        <svg class="h-4 w-4 shrink-0 text-ink-400 transition" :class="item.open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
    </button>

    <template x-if="item.open">
        <div class="relative z-20 mt-2 w-full overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-xl dark:border-ink-700 dark:bg-ink-900">
            <div class="border-b border-ink-100 p-3 dark:border-ink-800">
                <div class="relative"><svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" x-model.debounce.150ms="item.search" placeholder="Cari kode atau nama designator..." class="min-h-11 w-full rounded-xl border border-ink-200 bg-ink-50 pl-9 pr-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950"></div>
                <p class="mt-2 text-[10px] normal-case tracking-normal text-ink-400" x-text="searchHint(item.search, item.designator_id)"></p>
            </div>
            <div class="max-h-64 overflow-y-auto p-2">
                <template x-for="option in filteredOptions(item.search, item.designator_id)" :key="option.id">
                    <button type="button" @click="selectOption(item, option)" :disabled="optionUsedByOther(option.id, index)" class="flex w-full items-start justify-between gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-brand-950/30">
                        <span class="min-w-0"><span class="block text-xs font-extrabold text-ink-900 dark:text-white" x-text="option.code"></span><span class="mt-0.5 block text-[11px] leading-4 text-ink-500" x-text="option.name"></span></span>
                        <span class="shrink-0 rounded-lg bg-ink-100 px-2 py-1 text-[9px] font-bold text-ink-500 dark:bg-ink-800" x-text="option.unit"></span>
                    </button>
                </template>
                <div x-show="filteredOptions(item.search, item.designator_id).length === 0" class="px-3 py-8 text-center"><p class="text-xs font-bold text-ink-500">Designator tidak ditemukan</p><p class="mt-1 text-[10px] text-ink-400">Coba gunakan kode atau kata kunci yang berbeda.</p></div>
            </div>
        </div>
    </template>
</div>
