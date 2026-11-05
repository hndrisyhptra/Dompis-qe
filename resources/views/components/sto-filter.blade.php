@props(['branches', 'selected' => []])

<div x-data="{ 
    open: false, 
    selected: @js($selected),
    query: '',
    toggle(val) {
        if (this.selected.includes(val)) {
            this.selected = this.selected.filter(i => i !== val);
        } else {
            this.selected.push(val);
        }
    }
}" @click.outside="open = false" class="relative">
    <button type="button" @click="open = !open" 
        class="flex min-h-11 w-full items-center justify-between gap-2 rounded-md border border-ink-200 bg-white px-3 text-sm shadow-sm transition hover:border-brand-500 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-900">
        <span class="truncate text-ink-600 dark:text-ink-300" x-text="selected.length > 0 ? selected.length + ' STO terpilih' : 'Semua STO'"></span>
        <svg class="h-4 w-4 text-ink-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
    </button>

    <div x-show="open" x-cloak class="absolute z-50 mt-2 max-h-80 w-72 overflow-y-auto rounded-xl border border-ink-200 bg-white p-2 shadow-2xl dark:border-ink-700 dark:bg-ink-800" x-transition>
        <input type="text" x-model="query" placeholder="Cari STO..." class="mb-2 w-full rounded-lg border border-ink-200 bg-ink-50 px-3 py-2 text-sm dark:border-ink-700 dark:bg-ink-900 dark:text-white">
        
        @foreach($branches as $branch)
            <div class="mb-1 text-[10px] font-bold uppercase text-ink-400 px-2 pt-2">{{ $branch->name }}</div>
            @foreach($branch->serviceAreas as $sa)
                <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-ink-50 dark:hover:bg-ink-700 cursor-pointer" x-show="'{{ strtolower($sa->workzone . ' ' . $sa->name) }}'.includes(query.toLowerCase())">
                    <input type="checkbox" name="sto_filter[]" value="{{ $sa->workzone }}" :checked="selected.includes('{{ $sa->workzone }}')" @change="toggle('{{ $sa->workzone }}')" class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-ink-700 dark:text-ink-200">{{ $sa->workzone }} - {{ $sa->name }}</span>
                </label>
            @endforeach
        @endforeach
    </div>
</div>