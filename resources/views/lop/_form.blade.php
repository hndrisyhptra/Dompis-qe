<form method="POST" action="{{ $formAction }}"
      x-data="lopForm({ initial: @js($initial), template: @js($nameTemplate), programCodes: @js($programCodes), segmentLabels: @js(collect($segments)->mapWithKeys(fn ($s) => [$s->value => $s->label()])), areas: @js($areas->map(fn($a) => ['code' => $a->code, 'name' => $a->name])), branches: @js($branches->map(fn($b) => ['name' => $b->name, 'region' => $b->region, 'region_id' => $b->region_id, 'area_id' => $b->regionRef?->area_id, 'area_code' => $b->regionRef?->area?->code])), serviceAreas: @js($serviceAreas->map(fn($sa) => ['workzone' => $sa->workzone, 'name' => $sa->name, 'branch' => $sa->branch?->name, 'branch_id' => $sa->branch_id])) })"
      class="space-y-5">
    @csrf
    @if ($formMethod !== 'POST') @method($formMethod) @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
            <p class="font-semibold">Ada data yang perlu diperbaiki</p>
            <ul class="mt-1.5 list-disc space-y-0.5 pl-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="rounded-2xl border border-ink-100 bg-white shadow-sm dark:border-ink-800 dark:bg-ink-900 overflow-visible isolate">
        <div class="border-b border-gray-100 bg-white px-5 py-4 dark:border-neutral-800 dark:bg-neutral-900 sm:px-6 overflow-hidden rounded-t-2xl">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-sm font-bold text-white">01</span>
                <div><h2 class="font-semibold text-ink-900 dark:text-white">Data pekerjaan</h2><p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">Informasi utama untuk mengenali lokasi dan lingkup LOP.</p></div>
            </div>
        </div>
        <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6 overflow-visible">
            @if (($formMethod ?? 'POST') === 'POST')
                {{-- Input LOP Baru: incident memicu lookup ke DB tiket untuk auto-fill STO/Branch/Segmen. --}}
                <div>
                    <label for="incident" class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1.5">Incident</label>
                    <div class="flex gap-2">
                        <input id="incident" name="incident" type="text" placeholder="Contoh: INC123456" autocomplete="off"
                               x-model="form.incident" x-on:blur="lookupTicket()" x-on:keydown.enter.prevent="lookupTicket()"
                               class="min-w-0 flex-1 rounded-lg border border-ink-100 bg-white px-3.5 py-2.5 text-sm text-ink-900 shadow-sm transition placeholder:text-ink-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-50 dark:placeholder:text-ink-500">
                        <button type="button" @click="lookupTicket()" :disabled="lookup.loading"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-ink-100 bg-white px-3 py-2.5 text-sm font-medium text-ink-700 transition hover:bg-ink-50 disabled:opacity-50 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-200 dark:hover:bg-ink-700">
                            <svg x-show="!lookup.loading" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z" /></svg>
                            <svg x-show="lookup.loading" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 3a9 9 0 1 0 9 9" /></svg>
                            <span x-text="lookup.loading ? 'Mencari...' : 'Cari Tiket'"></span>
                        </button>
                    </div>
                    @error('incident')<p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>@enderror
                    <p x-show="lookup.message" x-cloak class="mt-1.5 text-xs"
                       :class="lookup.error ? 'text-brand-600 dark:text-brand-400' : 'text-emerald-600 dark:text-emerald-400'"
                       x-text="lookup.message"></p>
                    <template x-for="(w, i) in lookup.warnings" :key="i">
                        <p class="mt-1.5 rounded-lg bg-amber-50 px-2.5 py-1.5 text-xs font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300" x-text="w"></p>
                    </template>

                    {{-- Incident sudah dipakai LOP lain di dompis_qe --}}
                    <div x-show="lookup.duplicate" x-cloak
                         class="mt-2 rounded-lg border border-red-200 bg-red-50 p-2.5 text-xs text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                        <p class="font-bold">Nomor tiket sudah dipakai</p>
                        <p class="mt-0.5">
                            Incident ini sudah terpakai pada LOP
                            <span class="font-mono font-semibold" x-text="lookup.duplicate?.nama_lop"></span><span x-show="lookup.duplicate?.status"> (status: <span x-text="lookup.duplicate?.status"></span>)</span><span x-show="lookup.duplicate?.trashed"> — LOP tersebut sudah dihapus</span>.
                            Nomor tidak dapat dipakai lagi; gunakan nomor lain atau buat nomor tiket manual.
                        </p>
                    </div>

                    {{-- Status pencarian tiket / nomor manual (ringkas) --}}
                    <div x-show="lookup.mode" x-cloak x-transition
                         class="mt-3 flex items-start gap-2 rounded-lg border p-2.5 text-xs"
                         :class="lookup.mode === 'manual'
                            ? 'border-amber-200 bg-amber-50/70 dark:border-amber-900/60 dark:bg-amber-950/30'
                            : 'border-emerald-200 bg-emerald-50/70 dark:border-emerald-900/60 dark:bg-emerald-950/30'">
                        <svg class="mt-0.5 h-3.5 w-3.5 shrink-0"
                             :class="lookup.mode === 'manual' ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        <div class="min-w-0">
                            <p class="font-bold" :class="lookup.mode === 'manual' ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-300'" x-text="lookup.summaryTitle"></p>
                            <p x-show="lookup.summaryText" class="mt-0.5 text-ink-500 dark:text-ink-400" x-text="lookup.summaryText"></p>
                        </div>
                    </div>

                    <div x-show="lookup.notFound" x-cloak class="mt-2 rounded-lg border border-ink-200 bg-ink-50/70 p-3 dark:border-ink-700 dark:bg-ink-800/50">
                        <button type="button" @click="generateManualIncident()" :disabled="!form.program_type || manualGen.loading"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">
                            <svg x-show="!manualGen.loading" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            <svg x-show="manualGen.loading" class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 3a9 9 0 1 0 9 9" /></svg>
                            <span x-text="manualGen.loading ? 'Membuat...' : 'Generate Ticket Manual'"></span>
                        </button>
                        <p x-show="!form.program_type" class="mt-1.5 text-xs text-ink-500 dark:text-ink-400">Pilih Program terlebih dahulu untuk membuat nomor tiket manual.</p>
                        <p x-show="manualGen.message" x-cloak class="mt-1.5 text-xs text-brand-600 dark:text-brand-400" x-text="manualGen.message"></p>
                    </div>
                </div>
            @else
                {{-- Edit LOP: nomor tiket manual (INP…) boleh diubah; nomor tiket asli (INC…) dikunci. --}}
                @if (isset($lop) && $lop->usesManualIncident())
                    <div>
                        <x-input name="incident" label="Incident" placeholder="Contoh: INP3102092601" x-model="form.incident" autocomplete="off" />
                        <p class="mt-1.5 text-xs text-ink-400">Nomor tiket manual — boleh diubah, tetap memakai format <span class="font-mono">INP…</span>.</p>
                    </div>
                @else
                    <div>
                        <label for="incident" class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1.5">Incident</label>
                        <input id="incident" type="text" x-model="form.incident" readonly tabindex="-1"
                               class="w-full cursor-not-allowed rounded-lg border border-ink-100 bg-ink-50 px-3.5 py-2.5 text-sm text-ink-500 shadow-sm dark:border-ink-700 dark:bg-ink-800/60 dark:text-ink-400">
                        <p class="mt-1.5 text-xs text-ink-400">Nomor tiket dari sistem tiket, tidak dapat diubah.</p>
                    </div>
                @endif
            @endif
            <x-select name="area" label="Area" placeholder="Pilih area" x-model="form.area" @change="onAreaChange()">
                @foreach ($areas as $area)<option value="{{ $area->code }}">{{ $area->name }}</option>@endforeach
            </x-select>

            <div>
                <label for="branch" class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1.5">Branch</label>
                <select id="branch" name="branch" x-model="form.branch" @change="onBranchChange()"
                    :disabled="!form.area"
                    class="w-full rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition disabled:bg-ink-50 disabled:text-ink-400 disabled:cursor-not-allowed">
                    <option value="">Pilih branch</option>
                    <template x-for="b in filteredBranches" :key="b.name"><option :value="b.name" x-text="b.name"></option></template>
                </select>
                @error('branch')<p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>@enderror
                <p x-show="!form.area" class="mt-1.5 text-xs text-ink-400">Pilih Area terlebih dahulu.</p>
                <p x-show="form.area && filteredBranches.length===0" class="mt-1.5 text-xs text-amber-600 dark:text-amber-400">Tidak ada branch untuk area ini.</p>
            </div>

            <div>
                <label for="sto" class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1.5">STO / Service Area</label>
                <select id="sto" name="sto" x-model="form.sto"
                    :disabled="!form.branch"
                    class="w-full rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition disabled:bg-ink-50 disabled:text-ink-400 disabled:cursor-not-allowed">
                    <option value="">Pilih STO</option>
                    <template x-for="sa in filteredServiceAreas" :key="sa.workzone"><option :value="sa.workzone" x-text="`${sa.workzone} — ${sa.name}`"></option></template>
                </select>
                @error('sto')<p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>@enderror
                <p x-show="!form.branch" class="mt-1.5 text-xs text-ink-400">Pilih Branch terlebih dahulu.</p>
                <p x-show="form.branch && filteredServiceAreas.length===0" class="mt-1.5 text-xs text-amber-600 dark:text-amber-400">Tidak ada STO untuk branch ini.</p>
            </div>

            {{-- Segmen: single untuk recovery/preventive, combobox multi (max 3) khusus relok_utilitas --}}
            <div x-show="form.program_type !== 'relok_utilitas'">
                <x-select name="segment" label="Segmen" placeholder="Pilih segmen jaringan" x-model="form.segment" x-bind:disabled="form.program_type === 'relok_utilitas'">
                    @foreach ($segments as $segment)<option value="{{ $segment->value }}">{{ $segment->label() }}</option>@endforeach
                </x-select>
            </div>

            <div x-show="form.program_type === 'relok_utilitas'" x-cloak>
                <label class="block text-sm font-medium text-ink-700 dark:text-ink-300 mb-1.5">Segmen <span class="font-normal text-ink-400">(maks 3, nama LOP akan menyesuaikan)</span></label>
                <div class="relative" x-ref="segmentTrigger" @click.outside="segmentOpen = false">
                    {{-- Hidden inputs untuk submit array segment[] — disabled saat bukan relok agar tidak ikut ter-submit --}}
                    <template x-for="seg in form.segments" :key="seg">
                        <input type="hidden" name="segment[]" :value="seg" x-bind:disabled="form.program_type !== 'relok_utilitas'">
                    </template>

                    {{-- Trigger --}}
                    <button type="button" x-ref="segmentBtn" @click="segmentOpen = !segmentOpen; $nextTick(() => updateSegmentPos())"
                            class="flex min-h-10.5 w-full items-center justify-between gap-2 rounded-lg border bg-white px-3 py-2 text-left text-sm shadow-sm transition focus:outline-none focus:ring-2 focus:ring-brand-500/30 dark:bg-ink-800"
                            :class="segmentOpen ? 'border-brand-500 ring-2 ring-brand-500/20' : 'border-ink-100 dark:border-ink-700'">
                        <span class="flex flex-1 flex-wrap gap-1.5">
                            <template x-if="form.segments.length === 0">
                                <span class="text-ink-400">Pilih segmen (maks 3)</span>
                            </template>
                            <template x-for="seg in form.segments" :key="seg">
                                <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700 dark:bg-brand-950/40 dark:text-brand-300">
                                    <span x-text="segmentLabel(seg)"></span>
                                    <span @click.stop="toggleSegment(seg)" class="ml-1 cursor-pointer rounded-full p-0.5 hover:bg-brand-100 dark:hover:bg-brand-900">×</span>
                                </span>
                            </template>
                        </span>
                        <svg class="h-4 w-4 shrink-0 text-ink-400 transition" :class="segmentOpen && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>

                    {{-- Dropdown — teleported ke body agar tidak terpotong overflow section (Opsi B) --}}
                    <template x-teleport="body">
                        <div x-show="segmentOpen && form.program_type === 'relok_utilitas'" x-transition
                             @click.outside="segmentOpen = false"
                             class="fixed z-50 overflow-hidden rounded-xl border border-ink-200 bg-white shadow-2xl dark:border-ink-700 dark:bg-ink-800"
                             :style="`top:${segmentDropdownPos.top}px; left:${segmentDropdownPos.left}px; width:${segmentDropdownPos.width}px`">
                            <div class="border-b border-ink-100 p-2 dark:border-ink-700">
                                <input type="text" x-model="segmentQuery" placeholder="Cari segmen..."
                                       class="w-full rounded-lg border border-ink-100 bg-ink-50 px-3 py-2 text-sm placeholder:text-ink-400 focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-900 dark:text-white">
                                <p class="mt-1.5 text-xs" :class="form.segments.length >= 3 ? 'text-amber-600 dark:text-amber-400' : 'text-ink-400'">
                                    <span x-text="`${form.segments.length}/3 terpilih`"></span>
                                    <span x-show="form.segments.length >= 3"> — maksimal tercapai</span>
                                </p>
                            </div>
                            <div class="max-h-56 overflow-y-auto p-1.5">
                                <template x-for="opt in filteredSegments()" :key="opt.value">
                                    <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm transition hover:bg-ink-50 dark:hover:bg-ink-700"
                                           :class="isSegmentSelected(opt.value) ? 'bg-brand-50 dark:bg-brand-950/30' : ''">
                                        <input type="checkbox" :checked="isSegmentSelected(opt.value)"
                                               @change="toggleSegment(opt.value)"
                                               :disabled="!isSegmentSelected(opt.value) && form.segments.length >= 3"
                                               class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500/30 disabled:opacity-40">
                                        <span class="flex-1 font-medium text-ink-900 dark:text-white" x-text="opt.label"></span>
                                        <span x-show="isSegmentSelected(opt.value)" class="text-xs font-bold text-brand-600 dark:text-brand-400">#<span x-text="form.segments.indexOf(opt.value)+1"></span></span>
                                    </label>
                                </template>
                                <p x-show="filteredSegments().length === 0" class="px-3 py-6 text-center text-sm text-ink-400">Tidak ada segmen cocok</p>
                            </div>
                            <div class="flex items-center justify-between border-t border-ink-100 bg-ink-50 px-3 py-2 dark:border-ink-700 dark:bg-ink-900/50">
                                <button type="button" @click="form.segments = []; segmentQuery = ''; $nextTick(() => updateSegmentPos())"
                                        class="text-xs font-semibold text-ink-500 hover:text-ink-700 dark:text-ink-400">Bersihkan</button>
                                <button type="button" @click="segmentOpen = false"
                                        class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-700">Selesai</button>
                            </div>
                        </div>
                    </template>
                </div>
                @error('segment')<p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>@enderror
                @error('segment.*')<p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>@enderror
                <p class="mt-1.5 text-xs text-ink-400">Urutan dipilih akan jadi urutan di Nama LOP dengan join <span class="font-mono">_</span> (contoh: ODP_TIANG).</p>
            </div>

            <div>
                <x-select name="program_type" label="Program" placeholder="Pilih jenis Program" x-model="form.program_type">
                    @foreach ($programTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach
                </x-select>
                <p class="mt-1.5 text-xs text-ink-400">Kode Program akan ikut digunakan pada nama LOP.</p>
            </div>

            <div x-show="form.program_type === 'relok_utilitas'" x-transition class="sm:col-span-2">
                <label class="mb-2 block text-sm font-medium text-ink-700 dark:text-ink-300">Jenis Anggaran</label>
                <div class="grid grid-cols-2 gap-3">
                    @foreach ($budgetTypes as $budgetType)
                        <label class="cursor-pointer rounded-xl border p-4 transition" :class="form.budget_type === '{{ $budgetType->value }}' ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-500/15 dark:bg-brand-950/30' : 'border-ink-200 hover:border-ink-300 dark:border-ink-700'">
                            <input type="radio" name="budget_type" value="{{ $budgetType->value }}" x-model="form.budget_type" class="sr-only">
                            <span class="block text-sm font-bold text-ink-900 dark:text-white">{{ $budgetType->label() }}</span>
                            <span class="mt-1 block text-xs text-ink-500">{{ $budgetType->description() }}</span>
                        </label>
                    @endforeach
                </div>
                @error('budget_type')<p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-ink-100 bg-white shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <div class="border-b border-ink-100 px-5 py-4 dark:border-ink-800 sm:px-6">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-ink-900 text-sm font-bold text-white dark:bg-ink-700">02</span>
                <div><h2 class="font-semibold text-ink-900 dark:text-white">Detail pekerjaan</h2><p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">Jelaskan pekerjaan dan lengkapi ID IHLD jika sudah tersedia.</p></div>
            </div>
        </div>
        <div class="grid gap-5 p-5 sm:p-6">
            <div>
                <label for="job_description" class="mb-1.5 block text-sm font-medium text-ink-700 dark:text-ink-300">Deskripsi Pekerjaan</label>
                <textarea id="job_description" name="job_description" rows="4" maxlength="2000" x-model="form.job_description" placeholder="Contoh: Penggantian BOX ODP" class="w-full resize-y rounded-xl border border-ink-200 bg-white px-3.5 py-3 text-sm text-ink-900 shadow-sm transition placeholder:text-ink-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800 dark:text-white"></textarea>
                <div class="mt-1.5 flex items-center justify-between gap-3">
                    @error('job_description')<p class="text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>@else<p class="text-xs text-ink-400">Gunakan deskripsi singkat dan spesifik.</p>@enderror
                    <span class="text-xs text-ink-400" x-text="`${form.job_description.length}/2000`"></span>
                </div>
            </div>
            <div>
                <label for="ticket_summary" class="mb-1.5 block text-sm font-medium text-ink-700 dark:text-ink-300">Ringkasan Tiket</label>
                <textarea id="ticket_summary" name="ticket_summary" rows="5" maxlength="5000" x-model="form.ticket_summary"
                          placeholder="Terisi otomatis dari data tiket eksternal saat menekan Cari Tiket. Boleh dikosongkan atau diubah."
                          class="w-full resize-y rounded-xl border border-ink-200 bg-white px-3.5 py-3 font-mono text-xs leading-relaxed text-ink-800 shadow-sm transition placeholder:text-ink-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-100"></textarea>
                <div class="mt-1.5 flex items-center justify-between gap-3">
                    @error('ticket_summary')<p class="text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>@else<p class="text-xs text-ink-400">Snapshot data tiket dari database operasional. Tersimpan bersama LOP.</p>@enderror
                    <span class="text-xs text-ink-400" x-text="`${form.ticket_summary.length}/5000`"></span>
                </div>
            </div>
            <div><x-input name="ihld_id" label="ID IHLD (opsional)" placeholder="Dapat dilengkapi melalui Edit LOP nanti" x-model="form.ihld_id" autocomplete="off" /><p class="mt-1.5 text-xs text-ink-400">Kosongkan jika ID IHLD belum diterbitkan.</p></div>
        </div>
    </section>

    <section x-show="form.ticket_summary || !datekKosong()" x-cloak
             class="overflow-hidden rounded-2xl border border-ink-100 bg-white shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <div class="border-b border-ink-100 px-5 py-4 dark:border-ink-800 sm:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-ink-900 text-sm font-bold text-white dark:bg-ink-700">02a</span>
                    <div><h2 class="font-semibold text-ink-900 dark:text-white">Data jaringan</h2><p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">Terisi otomatis dari ringkasan tiket. Kosongkan bila tidak terdampak.</p></div>
                </div>
                <button type="button" @click="parseDatekUlang()" :disabled="datekParsing || !form.ticket_summary"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3 py-2 text-xs font-semibold text-ink-700 transition hover:bg-ink-50 disabled:opacity-50 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-200">
                    <svg class="h-3.5 w-3.5" :class="datekParsing && 'animate-spin'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356m-1.498 5.493A9 9 0 1 0 21 12.75" /></svg>
                    <span x-text="datekParsing ? 'Memproses...' : 'Isi otomatis'"></span>
                </button>
            </div>
        </div>
        <div class="grid gap-5 p-5 sm:p-6">
            <input type="hidden" name="datek" :value="JSON.stringify(datekForSubmit())">

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="datek_odc" class="mb-1.5 block text-sm font-medium text-ink-700 dark:text-ink-300">ODC</label>
                    <textarea id="datek_odc" rows="3" x-model="datekText.odc" placeholder="ODC-PME-FBK"
                              class="w-full resize-y rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 font-mono text-xs text-ink-800 shadow-sm transition placeholder:text-ink-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-100"></textarea>
                </div>
                <div>
                    <label for="datek_odp" class="mb-1.5 block text-sm font-medium text-ink-700 dark:text-ink-300">ODP</label>
                    <textarea id="datek_odp" rows="3" x-model="datekText.odp" placeholder="ODP-PME-FBK/29"
                              class="w-full resize-y rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 font-mono text-xs text-ink-800 shadow-sm transition placeholder:text-ink-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-100"></textarea>
                </div>
                <div>
                    <label for="datek_gpon" class="mb-1.5 block text-sm font-medium text-ink-700 dark:text-ink-300">GPON</label>
                    <textarea id="datek_gpon" rows="3" x-model="datekText.gpon" placeholder="GPON01-D5-SMP-3 | | 2/10,2/11"
                              class="w-full resize-y rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 font-mono text-xs text-ink-800 shadow-sm transition placeholder:text-ink-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-100"></textarea>
                    <p class="mt-1 text-xs text-ink-400">Format: <span class="font-mono">nama | ip | port</span></p>
                </div>
                <div>
                    <label for="datek_kabel" class="mb-1.5 block text-sm font-medium text-ink-700 dark:text-ink-300">Kabel</label>
                    <textarea id="datek_kabel" rows="3" x-model="datekText.kabel" placeholder="DS-SMP-FE-14-01-04/01-10"
                              class="w-full resize-y rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 font-mono text-xs text-ink-800 shadow-sm transition placeholder:text-ink-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-100"></textarea>
                </div>
            </div>

            <label class="flex items-start gap-2 text-sm text-ink-700 dark:text-ink-300">
                <input type="checkbox" x-model="form.datek.olt" class="mt-0.5 h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500/30">
                <span>OLT ikut terdampak<span class="block text-xs font-normal text-ink-400">Centang bila alarm OLT ikut menyala, bukan hanya 1 ODP.</span></span>
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="datek_rca" class="mb-1.5 block text-sm font-medium text-ink-700 dark:text-ink-300">Penyebab</label>
                    <input id="datek_rca" type="text" x-model="form.datek.rca" placeholder="PON PORT DOWN"
                           class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm text-ink-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800 dark:text-white">
                </div>
                <div>
                    <label for="datek_est" class="mb-1.5 block text-sm font-medium text-ink-700 dark:text-ink-300">Estimasi selesai</label>
                    <input id="datek_est" type="text" x-model="form.datek.est" placeholder="22/06/2026 15:00"
                           class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm text-ink-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800 dark:text-white">
                </div>
                <div>
                    <label for="datek_pic_nama" class="mb-1.5 block text-sm font-medium text-ink-700 dark:text-ink-300">Nama PIC</label>
                    <input id="datek_pic_nama" type="text" x-model="form.datek.pic.nama" placeholder="RANU"
                           class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm text-ink-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800 dark:text-white">
                </div>
                <div>
                    <label for="datek_pic_telp" class="mb-1.5 block text-sm font-medium text-ink-700 dark:text-ink-300">No. HP PIC</label>
                    <input id="datek_pic_telp" type="text" x-model="form.datek.pic.telp" placeholder="082139794255"
                           class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm text-ink-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800 dark:text-white">
                </div>
            </div>

            @error('datek')<p class="text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>@enderror
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-brand-200 bg-white shadow-sm dark:border-brand-900/60 dark:bg-ink-900">
        <div class="border-b border-gray-100 bg-white px-5 py-4 dark:border-neutral-800 dark:bg-neutral-900 sm:px-6">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-sm font-bold text-white">03</span>
                <div><h2 class="font-semibold text-ink-900 dark:text-white">Nama LOP</h2><p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">Dibuat otomatis dari data di atas, tetapi masih dapat disesuaikan.</p></div>
            </div>
        </div>
        <div class="p-5 sm:p-6">
            <label for="nama_lop" class="mb-1.5 block text-sm font-medium text-ink-700 dark:text-ink-300">Nama LOP yang akan disimpan</label>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input id="nama_lop" name="nama_lop" type="text" x-model="form.nama_lop" @input="markNameEdited()" class="min-w-0 flex-1 rounded-xl border border-ink-200 bg-white px-3.5 py-3 font-mono text-sm font-semibold text-ink-900 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800 dark:text-white">
                <button type="button" @click="resetGeneratedName()" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-ink-200 px-4 py-3 text-sm font-semibold text-ink-600 transition hover:border-brand-300 hover:text-brand-700 dark:border-ink-700 dark:text-ink-300">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356m-1.498 5.493A9 9 0 1 0 21 12.75" /></svg> Generate Ulang
                </button>
            </div>
            @error('nama_lop')<p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>@enderror
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded-full bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">Preview aktif</span>
                <span x-show="nameManuallyEdited" class="rounded-full bg-amber-50 px-2.5 py-1 font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">Diedit manual</span>
                <span class="text-ink-400">ID IHLD tidak menjadi bagian nama.</span>
            </div>
        </div>
    </section>

    <div class="sticky bottom-3 z-10 flex flex-col-reverse gap-3 rounded-2xl border border-ink-200 bg-white/95 p-3 shadow-xl backdrop-blur dark:border-ink-700 dark:bg-ink-900/95 sm:static sm:flex-row sm:items-center sm:justify-end sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none">
        <a href="{{ $cancelUrl }}" class="inline-flex items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold text-ink-600 transition hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800">Batal</a>
        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-brand-600/20 transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg> {{ $submitLabel }}
        </button>
    </div>
</form>
