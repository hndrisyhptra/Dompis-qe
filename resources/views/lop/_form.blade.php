<form method="POST" action="{{ $formAction }}"
      x-data="lopForm({ initial: @js($initial), template: @js($nameTemplate), wbsCodes: @js($wbsCodes) })"
      class="space-y-5">
    @csrf
    @if ($formMethod !== 'POST') @method($formMethod) @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
            <p class="font-semibold">Ada data yang perlu diperbaiki</p>
            <p class="mt-1">Periksa kembali field yang ditandai sebelum menyimpan.</p>
        </div>
    @endif

    <section class="overflow-hidden rounded-2xl border border-ink-100 bg-white shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <div class="border-b border-gray-100 bg-white px-5 py-4 dark:border-neutral-800 dark:bg-neutral-900 sm:px-6">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-sm font-bold text-white">01</span>
                <div><h2 class="font-semibold text-ink-900 dark:text-white">Data pekerjaan</h2><p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">Informasi utama untuk mengenali lokasi dan lingkup LOP.</p></div>
            </div>
        </div>
        <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
            <x-input name="incident" label="Incident" placeholder="Contoh: INC123456" x-model="form.incident" autocomplete="off" />
            <x-input name="sto" label="STO" placeholder="Contoh: SDA" x-model="form.sto" autocomplete="off" />

            <x-select name="branch" label="Branch" placeholder="Pilih region dan branch" x-model="form.branch">
                @foreach ($branches->groupBy('region') as $region => $items)
                    <optgroup label="{{ $region ?: 'Region belum ditentukan' }}">
                        @foreach ($items as $branch)<option value="{{ $branch->name }}">{{ $branch->name }}</option>@endforeach
                    </optgroup>
                @endforeach
            </x-select>

            <x-select name="area" label="Area" x-model="form.area"><option value="3">Area 3</option></x-select>

            <x-select name="segment" label="Segmen" placeholder="Pilih segmen jaringan" x-model="form.segment">
                @foreach ($segments as $segment)<option value="{{ $segment->value }}">{{ $segment->label() }}</option>@endforeach
            </x-select>

            <div>
                <x-select name="wbs_type" label="WBS" placeholder="Pilih jenis WBS" x-model="form.wbs_type">
                    @foreach ($wbsTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach
                </x-select>
                <p class="mt-1.5 text-xs text-ink-400">Kode WBS akan ikut digunakan pada nama LOP.</p>
            </div>

            <div x-show="form.wbs_type === 'relok_utilitas'" x-transition class="sm:col-span-2">
                <label class="mb-2 block text-sm font-medium text-ink-700 dark:text-ink-300">Jenis Anggaran</label>
                <div class="grid grid-cols-2 gap-3">
                    @foreach ($budgetTypes as $budgetType)
                        <label class="cursor-pointer rounded-xl border p-4 transition" :class="form.budget_type === '{{ $budgetType->value }}' ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-500/15 dark:bg-brand-950/30' : 'border-ink-200 hover:border-ink-300 dark:border-ink-700'">
                            <input type="radio" name="budget_type" value="{{ $budgetType->value }}" x-model="form.budget_type" class="sr-only">
                            <span class="block text-sm font-bold text-ink-900 dark:text-white">{{ $budgetType->label() }}</span>
                            <span class="mt-1 block text-xs text-ink-500">{{ $budgetType->value === 'CAPEX' ? 'Belanja modal / investasi' : 'Biaya operasional' }}</span>
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
            <div><x-input name="ihld_id" label="ID IHLD (opsional)" placeholder="Dapat dilengkapi melalui Edit LOP nanti" x-model="form.ihld_id" autocomplete="off" /><p class="mt-1.5 text-xs text-ink-400">Kosongkan jika ID IHLD belum diterbitkan.</p></div>
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
