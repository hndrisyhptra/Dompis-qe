@php
    $data = $lop->ba_bongkar_data ?? [];
    $form = old('form', $data['form'] ?? []);
    $rows = $form['materials'] ?? [];
    $groups = \App\Support\BaBongkarRules::MATERIALS;
    $photos = $state['evidences']->where('category', \App\Enums\EvidenceCategory::BA_BONGKAR_PHOTO);
    $canSubmit = $state['step2Complete'] && $state['step3Complete'] && $state['step4Complete'] && $state['step5Complete'] && $state['step6Complete'];
    $hasSavedBa = \App\Support\BaBongkarRules::document($lop) !== null || $lop->ba_bongkar_required === false;
    $hasBaErrors = $errors->any() && in_array(old('action'), ['draft', 'generate'], true) && session()->hasOldInput('required');
    $editing = ! $hasSavedBa || request()->boolean('edit_ba') || $hasBaErrors;
    $summaryUrl = route('technician.projects.show', [$lop, 'step' => 6]);
@endphp
<section class="mt-5 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><p class="text-xs font-bold text-brand-600">Step 6</p><h2 class="mt-1 text-lg font-extrabold">{{ $editing && $hasSavedBa ? 'Edit BA Bongkar' : 'BA Bongkar' }}</h2></div>
        @if (! $editing)<a href="{{ route('technician.projects.show', [$lop, 'step' => 6, 'edit_ba' => 1]) }}" class="inline-flex min-h-11 items-center rounded-md border border-ink-300 bg-white px-4 text-sm font-bold dark:border-ink-600 dark:bg-ink-900">{{ $lop->ba_bongkar_required === false ? 'Ubah pilihan BA Bongkar' : 'Edit BA Bongkar' }}</a>@endif
    </div>
    @if (! $editing)
    @include('ba-bongkar.summary', ['showHeading' => false])
    @endif
    @if ($errors->any())
        <ul class="list-inside list-disc rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    @endif
    @if ($editing)
    <form method="POST" enctype="multipart/form-data" action="{{ route('technician.projects.ba-bongkar', $lop) }}"
        class="space-y-4 rounded-2xl border border-ink-200 bg-white p-4 dark:border-ink-700 dark:bg-ink-900"
        x-data="baBongkarForm(@js(old('required', $lop->ba_bongkar_required)), @js($rows), @js($groups))" @submit="saving = true">
        @csrf @method('PUT')
        <input type="hidden" name="action" value="draft" x-ref="action">
        <input type="hidden" name="revision" value="{{ $data['revision'] ?? '' }}">
        <fieldset><legend class="text-sm font-bold">Apakah pekerjaan ini memerlukan BA Bongkar?</legend>
            <div class="mt-3 flex gap-5">@foreach (['1' => 'Ya', '0' => 'Tidak'] as $value => $label)
                <label class="flex min-h-11 items-center gap-2 text-sm"><input type="radio" name="required" value="{{ $value }}" x-model="choice" class="text-brand-600">{{ $label }}</label>
            @endforeach</div>
        </fieldset>
        <fieldset x-show="choice === '1'" :disabled="choice !== '1'" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (['number' => ['Nomor BA (opsional)', 'text', ''], 'date' => ['Tanggal serah terima', 'date', now()->timezone('Asia/Jakarta')->toDateString()], 'witel' => ['Branch', 'text', $lop->locationBranchName()], 'sto_asal' => ['STO asal', 'text', $lop->locationServiceAreaName()], 'lokasi' => ['Lokasi bongkaran', 'text', $lop->nama_lop], 'sto_tujuan' => ['STO tujuan / gudang penerima', 'text', ''], 'mitra' => ['Mitra teknisi (opsional)', 'text', ''], 'barcode_scmt' => ['Barcode SCMT (opsional)', 'text', ''], 'receiver_name' => ['Nama penerima (opsional)', 'text', ''], 'receiver_position' => ['Jabatan penerima (opsional)', 'text', ''], 'receiver_phone' => ['Telepon penerima (opsional)', 'text', ''], 'photo_caption' => ['Judul lampiran foto (opsional)', 'text', '']] as $key => [$label, $type, $default])
                    <x-input :name="'form['.$key.']'" :label="$label" :type="$type" :value="$form[$key] ?? $default" :use-old="false" maxlength="{{ $key === 'lokasi' ? 1000 : 255 }}" />
                @endforeach
                <label class="text-sm font-medium">Usulan tindak lanjut (opsional)<select name="form[tindak_lanjut]" class="mt-1.5 min-h-11 w-full rounded-md border border-ink-300 bg-white p-2 dark:border-ink-600 dark:bg-ink-800"><option value="">Belum ditentukan</option>@foreach (['penghapusan' => 'Penghapusan', 'refurbished' => 'Refurbished (memenuhi spek elektris)'] as $key => $label)<option value="{{ $key }}" @selected(($form['tindak_lanjut'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></label>
            </div>
            <div class="flex items-center justify-between"><h3 class="text-sm font-bold">Material bongkaran</h3><button type="button" @click="add()" :disabled="rows.length >= 100" class="min-h-11 rounded-md border border-ink-300 px-3 text-xs font-bold dark:border-ink-600">Tambah rincian</button></div>
            <p x-show="rows.length === 0" class="text-xs text-ink-500">Tambahkan rincian material atau isi Material lainnya di bawah. Data ini terpisah dari BOQ material terpasang.</p>
            <template x-for="(row, i) in rows" :key="i">
                <div class="space-y-3 rounded-md border border-ink-200 p-3 dark:border-ink-700">
                    <div class="flex items-center justify-between"><p class="text-sm font-bold" x-text="'Rincian ' + (i + 1)"></p><button type="button" @click="rows.splice(i, 1)" class="min-h-10 px-2 text-xs font-bold text-red-600" :aria-label="'Hapus rincian ' + (i + 1)">Hapus</button></div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="text-xs font-semibold">Jenis material<select :name="`form[materials][${i}][group]`" x-model="row.group" @change="change(row)" class="mt-1 min-h-11 w-full rounded-md border border-ink-300 bg-white p-2 dark:border-ink-600 dark:bg-ink-800">@foreach ($groups as $key => $spec)<option value="{{ $key }}">{{ $spec['label'] }}</option>@endforeach</select></label>
                        <label class="text-xs font-semibold" x-show="groups[row.group].types.length">Core / tipe<select :name="`form[materials][${i}][type]`" x-model="row.type" class="mt-1 min-h-11 w-full rounded-md border border-ink-300 bg-white p-2 dark:border-ink-600 dark:bg-ink-800"><template x-for="type in groups[row.group].types" :key="type"><option :value="type" x-text="type"></option></template></select></label>
                        @foreach (['length' => 'Panjang (meter)', 'quantity' => 'Jumlah (buah)', 'year' => 'Tahun (opsional)', 'brand' => 'Merek (opsional)'] as $key => $label)
                            <label class="text-xs font-semibold" @if (in_array($key, ['length','quantity'])) x-show="groups[row.group].measure === '{{ $key }}' || groups[row.group].additional_measure === '{{ $key }}'" @endif>{{ $label }}<input :name="`form[materials][${i}][{{ $key }}]`" x-model="row.{{ $key }}" type="{{ $key === 'brand' ? 'text' : 'number' }}" @if ($key !== 'brand') min="{{ $key === 'year' ? 1900 : ($key === 'quantity' ? 1 : 0.001) }}" step="{{ $key === 'length' ? 'any' : '1' }}" @endif class="mt-1 min-h-11 w-full rounded-md border border-ink-300 bg-white p-2 dark:border-ink-600 dark:bg-ink-800"></label>
                        @endforeach
                        <label x-show="row.group === 'tiang'" class="text-xs font-semibold">Jenis tiang<select :name="`form[materials][${i}][segments]`" x-model="row.segments" class="mt-1 min-h-11 w-full rounded-md border border-ink-300 bg-white p-2 dark:border-ink-600 dark:bg-ink-800"><option value="">Pilih segmen</option><option>2 Segmen</option><option>3 Segmen</option></select></label>
                        <label x-show="row.group === 'tiang'" class="text-xs font-semibold">Kondisi tiang<select :name="`form[materials][${i}][condition]`" x-model="row.condition" class="mt-1 min-h-11 w-full rounded-md border border-ink-300 bg-white p-2 dark:border-ink-600 dark:bg-ink-800"><option value="">Pilih kondisi</option>@foreach (['Patah', 'Bengkok', 'Berkarat', 'Kropos'] as $condition)<option>{{ $condition }}</option>@endforeach</select></label>
                    </div>
                </div>
            </template>
            <div class="space-y-2">
                <label for="ba-other-materials" class="block text-sm font-bold">Material lainnya (opsional)</label>
                <textarea id="ba-other-materials" name="form[other_materials]" rows="3" maxlength="2000" aria-describedby="ba-other-materials-help" placeholder="patchcore 20 meter (1 buah)" class="w-full rounded-md border border-ink-300 bg-white p-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 dark:border-ink-600 dark:bg-ink-800">{{ $form['other_materials'] ?? '' }}</textarea>
                <p id="ba-other-materials-help" class="text-xs text-ink-500 dark:text-ink-400">Contoh: patchcore 20 meter (1 buah). Isi nama material beserta panjang atau jumlahnya, maksimal 2.000 karakter.</p>
                @error('form.other_materials')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>
            <fieldset class="space-y-3"><legend class="text-sm font-bold">Foto material bongkaran (minimal 1, maksimal 12)</legend>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">@foreach ($photos as $photo)<label class="space-y-2 rounded-md border border-ink-200 p-2 dark:border-ink-700"><img src="{{ $photo->url() }}" class="aspect-square w-full rounded object-cover" alt="Foto material bongkaran" loading="lazy"><span class="flex items-center gap-2 text-xs"><input type="checkbox" name="keep_photo_ids[]" value="{{ $photo->getKey() }}" checked>Pertahankan foto</span></label>@endforeach</div>
                <label class="block text-xs font-semibold">Tambah foto (JPG/PNG/WebP, maks. 10 MB per foto)<input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full rounded-md border border-ink-300 p-2 dark:border-ink-600"></label>
            </fieldset>
            @if (data_get($data, 'signatures.technician.path'))<p class="text-xs text-emerald-700">TTD teknisi tersimpan. Gambar ulang hanya jika perlu menggantinya.</p>@endif
            <x-ba-signature label="TTD Teknisi — Yang Menyerahkan" />
            <p class="text-xs leading-5 text-ink-500">TTD penerima dan pihak yang mengetahui dapat dilengkapi pada dokumen cetak. Pengajuan BA Bongkar diperiksa oleh Admin.</p>
        </fieldset>
        <div class="flex flex-wrap gap-2">
            @if ($hasSavedBa)<a href="{{ $summaryUrl }}" class="inline-flex min-h-11 items-center rounded-md border border-ink-300 px-4 text-sm font-bold dark:border-ink-600">Batal</a>@endif
            <button type="submit" @click="$refs.action.value = 'draft'" :disabled="choice === '' || saving" class="min-h-11 rounded-md border border-ink-300 px-4 text-sm font-bold disabled:opacity-50 dark:border-ink-600">Simpan pilihan / draft</button>
            <button type="submit" @click="$refs.action.value = 'generate'" x-show="choice === '1'" :disabled="choice !== '1' || saving" class="min-h-11 rounded-md bg-ink-900 px-4 text-sm font-bold text-white disabled:opacity-50 dark:bg-ink-700">Simpan & buat PDF</button>
            <span x-show="saving" role="status" class="self-center text-xs">Menyimpan BA…</span>
        </div>
    </form>
    @else
    <div class="rounded-2xl border border-ink-200 bg-white p-4 dark:border-ink-700 dark:bg-ink-900" x-data="{ showSubmitConfirm: false }">
        <p class="text-sm font-bold">Checklist akhir</p><p class="mt-1 text-xs text-ink-500">{{ $canSubmit ? 'Seluruh checklist lengkap. Pekerjaan siap diajukan.' : 'Lengkapi seluruh tahap dan simpan keputusan BA Bongkar sebelum mengajukan.' }}</p>
        <button type="button" @click="showSubmitConfirm = true" @disabled(! $canSubmit) class="mt-4 min-h-12 w-full rounded-xl bg-brand-600 text-sm font-extrabold text-white disabled:bg-ink-200 disabled:text-ink-400">Ajukan Approval</button>
        <x-confirm-modal state="showSubmitConfirm" title="Ajukan pekerjaan untuk approval?" message="Evidence pekerjaan dan BA Bongkar diperiksa oleh Admin." confirm-label="Ya, ajukan" :action="route('technician.projects.submit', $lop)" />
    </div>
    @endif
</section>
