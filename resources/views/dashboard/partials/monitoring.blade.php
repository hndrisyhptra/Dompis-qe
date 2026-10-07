<div class="space-y-4" x-data="dashboardMonitoring()">
    <section class="flex flex-col gap-4 rounded-md border border-ink-200 bg-white p-4 shadow-sm dark:border-ink-800 dark:bg-ink-900 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[.16em] text-ink-400">Tanggal monitoring · WIB</p>
            <h2 class="mt-1 text-lg font-extrabold text-ink-900 dark:text-white">{{ $monitoring['date_label'] }}</h2>
            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Aktivitas terakhir sampai tanggal terpilih: <strong class="text-ink-700 dark:text-ink-200">{{ $monitoring['last_update'] ?? 'Belum ada aktivitas' }}</strong></p>
        </div>
        <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-2">
            @foreach ($matrixScopeFilters as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <input type="hidden" name="tab" value="summary">
            <a href="{{ route('dashboard', [...$matrixScopeFilters, 'tab' => 'summary', 'date' => $monitoring['previous_date']]) }}" class="inline-flex min-h-10 items-center gap-1 rounded-md border border-ink-200 px-3 text-xs font-semibold text-ink-600 hover:bg-ink-50 dark:border-ink-700 dark:text-ink-300 dark:hover:bg-ink-800"><span aria-hidden="true">←</span> Hari sebelumnya</a>
            <label class="sr-only" for="monitoring-date">Tanggal monitoring</label>
            <input id="monitoring-date" type="date" name="date" value="{{ $monitoring['date'] }}" max="{{ $monitoring['today'] }}" required class="min-h-10 rounded-md border border-ink-200 bg-white px-3 text-xs font-semibold outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800">
            <button type="submit" class="min-h-10 rounded-md bg-ink-900 px-4 text-xs font-bold text-white hover:bg-ink-700 dark:bg-brand-600 dark:hover:bg-brand-700">Tampilkan</button>
        </form>
    </section>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6" aria-label="Ringkasan aktivitas harian">
        @foreach ([
            ['Total Branch', 'branches', 'text-ink-900 dark:text-white', 'border-ink-200 dark:border-ink-800'],
            ['Branch Bergerak', 'moving_branches', 'text-emerald-700 dark:text-emerald-300', 'border-emerald-200 dark:border-emerald-900'],
            ['Tanpa Pergerakan', 'idle_branches', 'text-rose-700 dark:text-rose-300', 'border-rose-200 dark:border-rose-900'],
            ['LOP Bergerak', 'moving_lops', 'text-blue-700 dark:text-blue-300', 'border-blue-200 dark:border-blue-900'],
            ['Total Aktivitas', 'activities', 'text-ink-900 dark:text-white', 'border-violet-200 dark:border-violet-900'],
            ['Aktor Aktif', 'actors', 'text-ink-900 dark:text-white', 'border-amber-200 dark:border-amber-900'],
        ] as [$label, $key, $tone, $border])
            <article class="rounded-md border bg-white p-4 shadow-sm dark:bg-ink-900 {{ $border }}">
                <h3 class="text-[9px] font-bold uppercase tracking-wider text-ink-500 dark:text-ink-400">{{ $label }}</h3>
                <p class="mt-2 text-2xl font-extrabold {{ $tone }}">{{ number_format($monitoring['stats'][$key]) }}</p>
            </article>
        @endforeach
    </section>

    <section class="overflow-hidden rounded-md border border-ink-200 bg-white shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <div class="flex flex-col gap-4 border-b border-ink-100 p-4 dark:border-ink-800 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h2 class="text-sm font-extrabold uppercase tracking-wide text-ink-900 dark:text-white">Summary per Branch</h2>
                <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Branch tanpa pergerakan ditampilkan lebih dahulu. Buka baris untuk melihat LOP.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex gap-1 rounded-md border border-ink-200 bg-ink-50 p-1 dark:border-ink-700 dark:bg-ink-800" aria-label="Filter aktivitas branch">
                    @foreach (['all' => 'Semua', 'idle' => 'Tidak Bergerak', 'moving' => 'Bergerak'] as $key => $label)
                        <button type="button" @click="movement = '{{ $key }}'" :aria-pressed="movement === '{{ $key }}'" :class="movement === '{{ $key }}' ? 'bg-white text-brand-700 shadow-sm dark:bg-ink-900 dark:text-brand-300' : 'text-ink-500 dark:text-ink-400'" class="min-h-9 rounded-md px-2.5 text-[10px] font-bold">{{ $label }}</button>
                    @endforeach
                </div>
                <label class="sr-only" for="monitoring-region">Filter region dalam scope</label>
                <select id="monitoring-region" x-model="region" class="min-h-10 rounded-md border border-ink-200 bg-white px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800">
                    <option value="">Semua Region</option>
                    @foreach ($monitoring['regions'] as $regionName)
                        <option value="{{ $regionName }}">{{ $regionName }}</option>
                    @endforeach
                </select>
                <label class="sr-only" for="monitoring-search">Cari branch</label>
                <input id="monitoring-search" type="search" x-model="search" placeholder="Cari branch…" class="min-h-10 w-40 rounded-md border border-ink-200 bg-white px-3 text-xs outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800">
            </div>
        </div>

        <div class="divide-y divide-ink-100 dark:divide-ink-800">
            @foreach ($monitoring['rows'] as $branch)
                <article x-show="visible(@js($branch['name']), @js($branch['region']), @js($branch['moving']))"
                    x-data="dashboardBranchMonitoring(@js(route('dashboard.monitoring-lops', [], false)), @js([...$matrixScopeFilters, 'branch' => $branch['name'], 'date' => $monitoring['date']]), @js($branch['moving'] ? 'moving' : ''))">
                    <button type="button" @click="toggle()" :aria-expanded="expanded" aria-controls="monitoring-branch-{{ $loop->index }}" class="w-full p-4 text-left transition hover:bg-ink-50 dark:hover:bg-ink-800/40">
                        <div class="grid grid-cols-2 items-center gap-4 sm:grid-cols-3 xl:grid-cols-[1.6fr_repeat(5,minmax(0,0.7fr))_1.5fr_auto]">
                            <div class="col-span-2 min-w-0 sm:col-span-3 xl:col-span-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-xs font-extrabold text-ink-900 dark:text-white">{{ $branch['name'] }}</h3>
                                    <span class="rounded-md px-1.5 py-1 text-[8px] font-extrabold uppercase {{ $branch['moving'] ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300' }}">{{ $branch['moving'] ? 'Bergerak' : 'Tidak Bergerak' }}</span>
                                </div>
                                <p class="mt-1 text-[10px] text-ink-400">{{ $branch['region'] }}</p>
                            </div>
                            @foreach ([
                                ['Total LOP', 'total_lops', 'text-ink-900 dark:text-white'],
                                ['LOP Bergerak', 'moving_lops', 'text-emerald-700 dark:text-emerald-300'],
                                ['Belum Bergerak', 'idle_lops', 'text-rose-700 dark:text-rose-300'],
                                ['Aktivitas', 'activities', 'text-ink-900 dark:text-white'],
                                ['Aktor', 'actors', 'text-ink-900 dark:text-white'],
                            ] as [$label, $key, $tone])
                                <div><p class="text-[9px] font-bold uppercase tracking-wide text-ink-400">{{ $label }}</p><p class="mt-1 text-sm font-extrabold {{ $tone }}">{{ number_format($branch[$key]) }}</p></div>
                            @endforeach
                            <div>
                                <p class="text-[9px] font-bold uppercase tracking-wide text-ink-400">Last Update · WIB</p>
                                <p class="mt-1 text-[11px] font-bold text-ink-700 dark:text-ink-200">{{ $branch['last_update'] ?? 'Belum ada aktivitas' }}</p>
                                @if ($branch['last_activity'])
                                    <span class="mt-1 inline-flex rounded-md bg-amber-50 px-1.5 py-1 text-[9px] font-bold text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">{{ $branch['last_activity']['actor_label'] }}</span>
                                @endif
                                @if (! $branch['moving'] && $branch['idle_days'] !== null)
                                    <p class="mt-1 text-[9px] text-rose-600 dark:text-rose-400">{{ $branch['idle_days'] }} hari tanpa aktivitas sampai tanggal ini</p>
                                @endif
                            </div>
                            <svg class="h-4 w-4 justify-self-end text-ink-400 transition-transform" :class="expanded && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                        </div>
                    </button>
                    <div id="monitoring-branch-{{ $loop->index }}" x-show="expanded" x-cloak class="border-t border-ink-100 bg-ink-50/50 p-4 dark:border-ink-800 dark:bg-ink-950/20">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs font-bold text-ink-700 dark:text-ink-200">Rincian LOP <span class="text-ink-400" x-text="'(' + total.toLocaleString('id-ID') + ')'"></span></p>
                            <form @submit.prevent="load(1)" class="flex flex-wrap gap-2">
                                <label class="sr-only" for="branch-movement-{{ $loop->index }}">Aktivitas LOP</label>
                                <select id="branch-movement-{{ $loop->index }}" x-model="lopMovement" @change="load(1)" class="min-h-9 rounded-md border border-ink-200 bg-white px-2 text-xs focus:border-brand-500 dark:border-ink-700 dark:bg-ink-800"><option value="">Semua LOP</option><option value="moving">Bergerak</option><option value="idle">Belum bergerak</option></select>
                                <label class="sr-only" for="branch-search-{{ $loop->index }}">Cari Incident atau nama LOP</label>
                                <input id="branch-search-{{ $loop->index }}" type="search" x-model="lopSearch" placeholder="Incident / nama LOP" maxlength="100" class="min-h-9 rounded-md border border-ink-200 bg-white px-3 text-xs focus:border-brand-500 dark:border-ink-700 dark:bg-ink-800">
                                <button type="submit" class="rounded-md border border-ink-200 bg-white px-3 text-xs font-bold hover:bg-ink-100 dark:border-ink-700 dark:bg-ink-800">Cari</button>
                            </form>
                        </div>
                        <p x-show="loading" role="status" class="py-6 text-center text-xs text-ink-500">Memuat rincian LOP…</p>
                        <div x-show="error && !loading" class="rounded-md border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300"><span x-text="error"></span><button type="button" @click="load(page)" class="ml-2 font-bold underline">Coba lagi</button></div>
                        <p x-show="!loading && !error && !lops.length" class="py-6 text-center text-xs text-ink-500">Tidak ada LOP sesuai tanggal dan filter ini.</p>
                        <div x-show="!loading && !error && lops.length" class="space-y-3">
                            <template x-for="lop in lops" :key="lop.id">
                                <article x-data="dashboardLopActivity(lop.activities_url, @js($monitoring['date']))" class="overflow-hidden rounded-md border border-ink-200 bg-white dark:border-ink-700 dark:bg-ink-900">
                                    <button type="button" @click="toggleActivity()" :aria-expanded="activityExpanded" :aria-controls="'monitoring-lop-' + lop.id" class="w-full p-4 text-left transition hover:bg-ink-50 dark:hover:bg-ink-800/40 sm:p-5">
                                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-[2fr_1.2fr_0.8fr_1.6fr_auto] xl:items-center">
                                            <div class="min-w-0">
                                                <h4 class="text-sm font-extrabold leading-5 text-ink-900 dark:text-white" x-text="lop.name"></h4>
                                                <p class="mt-1 text-[10px] font-semibold text-ink-400" x-text="lop.incident"></p>
                                            </div>
                                            <div><p class="text-[9px] font-bold uppercase tracking-wide text-ink-400">Program / Service Area</p><p class="mt-1 text-xs font-bold text-ink-700 dark:text-ink-200" x-text="lop.program + ' / ' + (lop.service_area || '—')"></p><p class="mt-1 text-[10px] text-ink-400" x-text="lop.technician"></p></div>
                                            <div><p class="text-[9px] font-bold uppercase tracking-wide text-ink-400">Status Kini</p><p class="mt-1 text-xs font-bold" :class="lop.status_key === 'completed' ? 'text-emerald-700 dark:text-emerald-300' : (lop.status_key === 'rejected' ? 'text-rose-700 dark:text-rose-300' : 'text-blue-700 dark:text-blue-300')" x-text="lop.status"></p></div>
                                            <div>
                                                <p class="text-[9px] font-bold uppercase tracking-wide text-ink-400">Last Update · WIB</p>
                                                <p class="mt-1 text-xs font-bold text-ink-700 dark:text-ink-200" x-text="lop.last_update"></p>
                                                <p class="mt-1 text-[10px] text-ink-500 dark:text-ink-400" x-text="lop.last_activity?.label || 'Belum ada aktivitas'"></p>
                                                <span x-show="lop.last_activity" class="mt-2 inline-flex rounded-md bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-800 dark:bg-amber-950/40 dark:text-amber-300" x-text="lop.last_activity?.actor_label"></span>
                                            </div>
                                            <div class="flex items-center gap-3 sm:col-span-2 xl:col-span-1 xl:justify-end">
                                                <span class="text-xs font-extrabold text-ink-700 dark:text-ink-200" x-text="lop.activities + ' aktivitas'"></span>
                                                <svg class="h-4 w-4 text-ink-400 transition-transform" :class="activityExpanded && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                                            </div>
                                        </div>
                                    </button>
                                    <div :id="'monitoring-lop-' + lop.id" x-show="activityExpanded" x-cloak class="border-t border-ink-100 bg-ink-50/50 p-4 dark:border-ink-800 dark:bg-ink-950/20">
                                        <div class="mb-3 flex items-center justify-between gap-3"><h5 class="text-xs font-bold text-ink-700 dark:text-ink-200">Riwayat aktivitas tanggal terpilih</h5><a :href="lop.detail_url" title="Lihat Detail LOP" class="rounded-md border border-ink-200 bg-white px-3 py-2 text-[10px] font-bold text-brand-600 hover:bg-ink-50 dark:border-ink-700 dark:bg-ink-900 dark:text-brand-400">Detail LOP ↗</a></div>
                                        <p x-show="activityLoading" role="status" class="py-4 text-center text-xs text-ink-500">Memuat riwayat aktivitas…</p>
                                        <div x-show="activityError && !activityLoading" class="rounded-md border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300"><span x-text="activityError"></span><button type="button" @click="loadActivity(activityPage)" class="ml-2 font-bold underline">Coba lagi</button></div>
                                        <p x-show="!activityLoading && !activityError && !activityEvents.length" class="py-4 text-center text-xs text-ink-500">Tidak ada aktivitas tercatat pada tanggal ini.</p>
                                        <ol x-show="!activityLoading && !activityError && activityEvents.length" class="divide-y divide-ink-200 dark:divide-ink-700">
                                            <template x-for="event in activityEvents" :key="event.key">
                                                <li class="flex flex-col gap-2 py-3 sm:flex-row sm:gap-5">
                                                    <time class="shrink-0 text-[10px] font-semibold text-ink-500 dark:text-ink-400 sm:w-32" x-text="event.time"></time>
                                                    <div class="min-w-0 flex-1"><p class="text-xs font-bold text-ink-800 dark:text-ink-100" x-text="event.label"></p><p x-show="event.note" class="mt-1 whitespace-pre-line break-words text-[11px] text-ink-500 dark:text-ink-400" x-text="event.note"></p></div>
                                                    <span class="w-fit self-start rounded-md bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-800 dark:bg-amber-950/40 dark:text-amber-300" x-text="event.actor_label"></span>
                                                </li>
                                            </template>
                                        </ol>
                                        <div x-show="!activityLoading && !activityError && activityLastPage > 1" class="mt-3 flex items-center justify-end gap-3 text-[10px]">
                                            <button type="button" @click="loadActivity(activityPage - 1)" :disabled="activityPage <= 1" class="min-h-9 rounded-md border border-ink-200 px-3 font-bold disabled:opacity-40 dark:border-ink-700">Sebelumnya</button>
                                            <span class="text-ink-500" x-text="activityPage + ' / ' + activityLastPage"></span>
                                            <button type="button" @click="loadActivity(activityPage + 1)" :disabled="activityPage >= activityLastPage" class="min-h-9 rounded-md border border-ink-200 px-3 font-bold disabled:opacity-40 dark:border-ink-700">Berikutnya</button>
                                        </div>
                                    </div>
                                </article>
                            </template>
                        </div>
                        <div x-show="!loading && !error && lastPage > 1" class="mt-3 flex items-center justify-end gap-3 text-xs">
                            <button type="button" @click="load(page - 1)" :disabled="page <= 1" class="min-h-9 rounded-md border border-ink-200 px-3 font-semibold disabled:opacity-40 dark:border-ink-700">Sebelumnya</button>
                            <span class="text-ink-500" x-text="page + ' / ' + lastPage"></span>
                            <button type="button" @click="load(page + 1)" :disabled="page >= lastPage" class="min-h-9 rounded-md border border-ink-200 px-3 font-semibold disabled:opacity-40 dark:border-ink-700">Berikutnya</button>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <p x-show="!hasVisible(@js($monitoring['rows']))" x-cloak class="p-8 text-center text-xs text-ink-500">Tidak ada branch sesuai scope dan filter ini.</p>
        <footer class="border-t border-ink-100 p-4 text-[10px] leading-5 text-ink-500 dark:border-ink-800 dark:text-ink-400">
            Bergerak = memiliki aktivitas tercatat pada tanggal terpilih. Sumber: riwayat LOP, pembuatan LOP, upload/review evidence, dan riwayat BOQ. Aktor aktif dihitung unik, bukan penjumlahan aktor per branch.
            Jumlah LOP mencakup data yang dibuat sampai tanggal terpilih; status pada rincian mengikuti kondisi terkini.
        </footer>
    </section>
</div>
