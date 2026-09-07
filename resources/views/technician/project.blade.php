@extends('layouts.technician')

@section('title', $lop->incident)
@section('header', $lop->incident)

@section('content')
@php
    $evidenceFor = function (string $category, ?int $designatorId = null) use ($state) {
        return $state['evidences']->filter(function ($evidence) use ($category, $designatorId) {
            return $evidence->category?->value === $category
                && ($designatorId === null || $evidence->designator_id === $designatorId);
        });
    };
@endphp

    <a href="{{ route('technician.inbox') }}"
    class="inline-flex items-center gap-2 rounded-lg bg-ink-100 px-3 py-2 text-sm font-bold text-ink-600 transition hover:bg-ink-200 dark:bg-ink-800 dark:text-ink-300 dark:hover:bg-ink-700">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"> 
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Back
    </a>


<section class="mt-4 rounded-3xl bg-ink-900 p-5 text-white shadow-xl shadow-ink-900/10">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0"><p class="text-xs font-bold uppercase tracking-[.12em] text-brand-300">{{ $lop->incident }}</p><h1 class="mt-2 wrap-break-word text-lg font-extrabold leading-6">{{ $lop->nama_lop }}</h1></div>
        <x-badge :variant="$lop->status_lop->badgeVariant()" class="shrink-0">{{ $lop->status_lop->label() }}</x-badge>
    </div>
    <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
        <div class="rounded-2xl bg-white/8 p-3"><p class="text-ink-400">STO</p><p class="mt-1 font-bold">{{ $lop->sto ?: '—' }}</p></div>
        <div class="rounded-2xl bg-white/8 p-3"><p class="text-ink-400">Branch</p><p class="mt-1 font-bold">{{ $lop->branch ?: '—' }}</p></div>
    </div>
</section>

@if ($lop->status_lop === \App\Enums\LopStatus::ASSIGNED)
    <section class="mt-5 rounded-3xl border border-brand-100 bg-white p-5 text-center dark:border-brand-900 dark:bg-ink-900">
        <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-900/20 dark:text-brand-300">
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751"/></svg>
        </div>
        <h2 class="mt-4 text-lg font-extrabold">Siap mulai pekerjaan?</h2>
        <p class="mx-auto mt-2 max-w-xs text-xs leading-5 text-ink-500">Pickup menandai bahwa Work Order sudah Anda terima dan membuka workflow lapangan.</p>
        <form method="POST" action="{{ route('technician.projects.pickup', $lop) }}" class="mt-5">@csrf
            <button class="min-h-12 w-full rounded-2xl bg-brand-600 px-5 text-sm font-extrabold text-white shadow-lg shadow-brand-600/20 active:scale-[.99]">Pickup Order</button>
        </form>
    </section>
@elseif ($lop->status_lop === \App\Enums\LopStatus::REJECTED)
    <section class="mt-5 rounded-3xl border border-brand-200 bg-brand-50 p-5 dark:border-brand-900 dark:bg-brand-900/10">
        <h2 class="text-base font-extrabold text-brand-700 dark:text-brand-300">Project perlu diperbaiki</h2>
        <p class="mt-2 text-xs leading-5 text-brand-700/80 dark:text-brand-300/80">Review alasan penolakan di bawah, upload file pengganti, lalu buka kembali workflow.</p>

        <div class="mt-4">
            <x-technician-evidence-uploader :lop="$lop" category="pre" title="Review evidence pekerjaan"
                description="Tap setiap file untuk melihat foto dan status review terkini."
                :existing="$state['evidences']" :allow-upload="false" />
        </div>

        <form method="POST" action="{{ route('technician.projects.resume', $lop) }}" class="mt-4">@csrf<button class="min-h-12 w-full rounded-2xl bg-brand-600 text-sm font-extrabold text-white">Mulai perbaikan</button></form>
    </section>
@elseif (in_array($lop->status_lop, [\App\Enums\LopStatus::WAITING_APPROVAL, \App\Enums\LopStatus::COMPLETED], true))
    <section class="mt-5 rounded-3xl border border-ink-100 bg-white p-6 text-center dark:border-ink-800 dark:bg-ink-900">
        <div class="mx-auto grid h-14 w-14 place-items-center rounded-full {{ $lop->status_lop === \App\Enums\LopStatus::COMPLETED ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-300' : 'bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-300' }}">
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623"/></svg>
        </div>
        <h2 class="mt-4 text-lg font-extrabold">{{ $lop->status_lop === \App\Enums\LopStatus::COMPLETED ? 'Project selesai' : 'Menunggu approval' }}</h2>
        <p class="mt-2 text-xs leading-5 text-ink-500">{{ $lop->status_lop === \App\Enums\LopStatus::COMPLETED ? 'Seluruh workflow dan approval telah selesai.' : 'Evidence sedang diperiksa. Anda akan menerima notifikasi hasil review.' }}</p>
        <div class="mt-5 grid grid-cols-2 gap-3"><div class="rounded-xl bg-ink-50 p-3 dark:bg-ink-800"><p class="text-lg font-extrabold">{{ $state['items']->count() }}</p><p class="text-[10px] text-ink-500">Material</p></div><div class="rounded-xl bg-ink-50 p-3 dark:bg-ink-800"><p class="text-lg font-extrabold">{{ $state['evidences']->count() }}</p><p class="text-[10px] text-ink-500">Evidence</p></div></div>
    </section>
    <section class="mt-5">
        <x-technician-evidence-uploader :lop="$lop" category="pre" title="Review evidence pekerjaan"
            description="Tap setiap file untuk melihat foto dan status review terkini."
            :existing="$state['evidences']" :allow-upload="false" />
    </section>
@else
    <section class="mt-5 rounded-2xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
        <div class="mb-4 flex items-center justify-between"><div><p class="text-[10px] font-bold uppercase tracking-wider text-brand-600 dark:text-brand-400">Field workflow</p><h2 class="mt-1 text-sm font-extrabold">Step {{ $step }} dari 5</h2></div><span class="text-[10px] font-semibold text-ink-400">{{ collect([$state['step1Complete'], $state['step2Complete'], $state['step3Complete'], $state['step4Complete'], $state['step5Complete']])->filter()->count() }}/5 lengkap</span></div>
        <x-lop-progress-stepper :current="$step" :state="$state" />
    </section>

    @if ($step === 1)
        @php
            $materialRows = $state['items']->map(fn ($item) => ['designator_id' => (string) $item->designator_id, 'qty' => (string) $item->qty])->values();
            if ($materialRows->isEmpty()) $materialRows = collect([['designator_id' => '', 'qty' => 1]]);
            $materialOptions = $designators->map(fn ($d) => [
                'id' => (string) $d->id_designator,
                'label' => $d->code.' — '.$d->item_name.' ('.$d->unit.')',
                's' => mb_strtolower($d->code.' '.$d->item_name),
            ])->values();
        @endphp
        <section class="mt-5" x-data="{ items: {{ Illuminate\Support\Js::from($materialRows) }}, materials: {{ Illuminate\Support\Js::from($materialOptions) }} }">
            <div class="mb-3"><p class="text-[11px] font-bold uppercase tracking-[.14em] text-brand-600 dark:text-brand-400">Step 1</p><h2 class="mt-1 text-lg font-extrabold">Reservasi material</h2><p class="mt-1 text-xs leading-5 text-ink-500">Pilih item yang akan digunakan dan masukkan jumlah kebutuhannya.</p></div>
            <form method="POST" action="{{ route('technician.projects.materials', $lop) }}" class="space-y-3">@csrf @method('PUT')
                <template x-for="(item, index) in items" :key="index">
                    <div class="rounded-2xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
                        <div class="flex items-center justify-between"><p class="text-xs font-bold">Material <span x-text="index + 1"></span></p><button type="button" @click="items.splice(index, 1)" x-show="items.length > 1" class="text-xs font-bold text-brand-600">Hapus</button></div>
                        <div class="relative mt-3"
                             x-data="{ open: false, q: '', limit: 8, all: materials, get matches() { const t = this.q.trim().toLowerCase(); return t ? this.all.filter(x => x.s.includes(t)) : this.all; } }"
                             @click.outside="open = false" @keydown.escape="open = false">
                            <input type="hidden" :name="`items[${index}][designator_id]`" :value="item.designator_id">
                            <button type="button" @click="open = ! open"
                                    class="min-h-12 w-full rounded-xl border border-ink-100 bg-white px-3 py-2 text-left text-xs dark:border-ink-700 dark:bg-ink-800"
                                    :class="item.designator_id ? 'text-ink-900 dark:text-ink-50' : 'text-ink-400'">
                                <span class="line-clamp-2" x-text="(all.find(m => String(m.id) === String(item.designator_id)) || {}).label || 'Pilih item designator'"></span>
                            </button>
                            <div x-show="open" x-cloak x-transition.opacity
                                 class="absolute left-0 right-0 z-20 mt-2 rounded-xl border border-ink-100 bg-white p-2 shadow-xl dark:border-ink-700 dark:bg-ink-800">
                                <input type="text" x-model="q" placeholder="Cari kode atau nama material…"
                                       x-ref="mq" x-effect="if (open) $nextTick(() => $refs.mq.focus())"
                                       class="min-h-10 w-full rounded-lg border border-ink-100 bg-ink-50 px-3 text-xs dark:border-ink-700 dark:bg-ink-900">
                                <ul class="mt-2 max-h-44 space-y-0.5 overflow-y-auto">
                                    <template x-for="m in matches.slice(0, limit)" :key="m.id">
                                        <li>
                                            <button type="button" @click="item.designator_id = String(m.id); open = false; q = ''"
                                                    class="block w-full rounded-lg px-3 py-2 text-left text-xs hover:bg-brand-50 dark:hover:bg-ink-700"
                                                    :class="String(m.id) === String(item.designator_id) ? 'bg-brand-50 font-bold text-brand-700 dark:bg-ink-700 dark:text-brand-300' : 'text-ink-700 dark:text-ink-200'"
                                                    x-text="m.label"></button>
                                        </li>
                                    </template>
                                    <li x-show="matches.length === 0" class="px-3 py-4 text-center text-xs text-ink-400">Tidak ada material yang cocok.</li>
                                    <li x-show="matches.length > limit" class="px-3 pt-2 text-center text-[11px] text-ink-400"
                                        x-text="`+${matches.length - limit} lainnya — ketik untuk mempersempit`"></li>
                                </ul>
                            </div>
                        </div>
                        <div class="mt-3"><label class="text-[10px] font-bold uppercase tracking-wide text-ink-400">Quantity</label><input type="number" min="0.001" step="0.001" x-model="item.qty" :name="`items[${index}][qty]`" required class="mt-1 min-h-12 w-full rounded-xl border border-ink-100 bg-white px-3 text-sm font-bold dark:border-ink-700 dark:bg-ink-800"></div>
                    </div>
                </template>
                <button type="button" @click="items.push({ designator_id: '', qty: 1 })" class="min-h-11 w-full rounded-2xl border-2 border-dashed border-ink-200 text-xs font-bold text-ink-600 dark:border-ink-700 dark:text-ink-300">+ Tambah material</button>
                <button type="submit" :disabled="items.some(i => ! i.designator_id)" :class="items.some(i => ! i.designator_id) ? 'bg-ink-200 text-ink-400 dark:bg-ink-800 dark:text-ink-500' : 'bg-brand-600 text-white shadow-lg shadow-brand-600/20'" class="min-h-12 w-full rounded-2xl text-sm font-extrabold transition">Simpan &amp; lanjut Material Tiba</button>
            </form>
        </section>
    @elseif ($step === 2)
        <section class="mt-5 space-y-4">
            <div><p class="text-[11px] font-bold uppercase tracking-[.14em] text-brand-600 dark:text-brand-400">Step 2</p><h2 class="mt-1 text-lg font-extrabold">Evidence Material Tiba</h2><p class="mt-1 text-xs leading-5 text-ink-500">Foto material yang sudah tiba di lokasi sebelum pekerjaan dimulai. Minimal 1 foto.</p></div>
            <x-technician-evidence-uploader :lop="$lop" category="material_arrival" title="Material tiba" description="Foto material yang sudah tersedia di lokasi. Cukup 1 foto." :existing="$evidenceFor('material_arrival')" />
            <a href="{{ route('technician.projects.show', [$lop, 'step' => 3]) }}" class="grid min-h-12 place-items-center rounded-2xl {{ $state['step2Complete'] ? 'bg-brand-600 text-white' : 'pointer-events-none bg-ink-200 text-ink-400 dark:bg-ink-800 dark:text-ink-500' }} text-sm font-extrabold">Lanjut Evidence Pra</a>
        </section>
    @elseif ($step === 3)
        <section class="mt-5 space-y-4">
            <div><p class="text-[11px] font-bold uppercase tracking-[.14em] text-brand-600 dark:text-brand-400">Step 3</p><h2 class="mt-1 text-lg font-extrabold">Evidence Pra</h2><p class="mt-1 text-xs leading-5 text-ink-500">Tag lokasi pekerjaan, foto sebab/kondisi awal pekerjaan, dan capture tiket Insera.</p></div>
            <div x-data="{ latitude: '{{ $state['survey']?->latitude }}', longitude: '{{ $state['survey']?->longitude }}', accuracy: '{{ $state['survey']?->accuracy }}', source: '{{ $state['survey']?->location_source ?? 'manual' }}', locating: false, error: '', locate() { this.locating = true; this.error = ''; if (!navigator.geolocation) { this.error='GPS tidak didukung perangkat.'; this.locating=false; return; } navigator.geolocation.getCurrentPosition(p => { this.latitude=p.coords.latitude.toFixed(7); this.longitude=p.coords.longitude.toFixed(7); this.accuracy=p.coords.accuracy.toFixed(2); this.source='gps'; this.locating=false; }, () => { this.error='Lokasi gagal diambil. Aktifkan izin GPS atau isi manual.'; this.locating=false; }, { enableHighAccuracy: true, timeout: 15000 }); } }"
                 class="rounded-2xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
                <div class="flex items-start justify-between gap-3"><div><h3 class="text-sm font-bold">Tag lokasi pekerjaan</h3><p class="mt-1 text-xs text-ink-500">Gunakan GPS atau masukkan koordinat manual.</p></div>@if($state['survey'])<span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300">Tersimpan</span>@endif</div>
                <button type="button" @click="locate()" :disabled="locating" class="mt-4 min-h-11 w-full rounded-xl bg-ink-900 text-xs font-bold text-white dark:bg-ink-700"><span x-text="locating ? 'Mengambil lokasi…' : 'Gunakan lokasi saat ini'"></span></button>
                <p x-show="error" x-text="error" class="mt-2 text-xs text-brand-600"></p>
                <form method="POST" action="{{ route('technician.projects.location', $lop) }}" class="mt-3 space-y-3">@csrf @method('PUT')
                    <input type="hidden" name="accuracy" x-model="accuracy"><input type="hidden" name="location_source" x-model="source">
                    <div class="grid grid-cols-2 gap-2"><div><label class="text-[10px] font-bold text-ink-400">LATITUDE</label><input name="latitude" x-model="latitude" @input="source='manual'" required class="mt-1 min-h-11 w-full rounded-xl border border-ink-100 bg-white px-2 text-xs dark:border-ink-700 dark:bg-ink-800"></div><div><label class="text-[10px] font-bold text-ink-400">LONGITUDE</label><input name="longitude" x-model="longitude" @input="source='manual'" required class="mt-1 min-h-11 w-full rounded-xl border border-ink-100 bg-white px-2 text-xs dark:border-ink-700 dark:bg-ink-800"></div></div>
                    <div class="grid grid-cols-2 gap-2"><button class="min-h-11 rounded-xl border border-ink-200 text-xs font-bold dark:border-ink-700">Simpan lokasi</button><a :href="latitude && longitude ? `https://maps.google.com/?q=${latitude},${longitude}` : '#'" target="_blank" class="grid min-h-11 place-items-center rounded-xl bg-ink-50 text-xs font-bold text-ink-600 dark:bg-ink-800 dark:text-ink-300">Buka peta</a></div>
                </form>
            </div>

            <x-technician-evidence-uploader :lop="$lop" category="pre" title="Foto sebab/kondisi awal pekerjaan" description="Beberapa foto kondisi awal area pekerjaan sebelum instalasi." :existing="$evidenceFor('pre')" />

            <x-technician-evidence-uploader :lop="$lop" category="insera" title="Capture Ticket Insera" description="Screenshot detail tiket dari aplikasi Insera." :existing="$evidenceFor('insera')" />

            <form method="POST" action="{{ route('technician.projects.survey-complete', $lop) }}">@csrf<button class="min-h-12 w-full rounded-2xl {{ $state['step3Complete'] ? 'bg-brand-600 text-white' : 'bg-ink-200 text-ink-400 dark:bg-ink-800 dark:text-ink-500' }} text-sm font-extrabold">Selesaikan Survey & Lanjut Progress</button></form>
        </section>
    @elseif ($step === 4)
        <section class="mt-5 space-y-4">
            <div><p class="text-[11px] font-bold uppercase tracking-[.14em] text-brand-600 dark:text-brand-400">Step 4</p><h2 class="mt-1 text-lg font-extrabold">Evidence Progress</h2><p class="mt-1 text-xs leading-5 text-ink-500">Dokumentasi proses instalasi untuk setiap material yang direservasi.</p></div>
            @foreach ($state['items'] as $item)
                <x-technician-evidence-uploader :lop="$lop" category="progress" :designator-id="$item->designator_id"
                    :title="'Progress · '.$item->designator->code" :description="$item->designator->item_name.' · Qty '.(float)$item->qty.' '.$item->designator->unit"
                    :existing="$evidenceFor('progress', $item->designator_id)" />
            @endforeach
            <div class="rounded-2xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
                <div class="flex items-center justify-between"><div><p class="text-sm font-bold">Checklist progress</p><p class="mt-1 text-xs text-ink-500">{{ $state['missingProgress']->isEmpty() ? 'Semua designator sudah memiliki evidence progress.' : $state['missingProgress']->count().' designator belum lengkap.' }}</p></div><span class="grid h-9 w-9 place-items-center rounded-full {{ $state['step4Complete'] ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-300' : 'bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-300' }}">{{ $state['step4Complete'] ? '✓' : '!' }}</span></div>
            </div>
            <a href="{{ route('technician.projects.show', [$lop, 'step' => 5]) }}" class="grid min-h-12 place-items-center rounded-2xl {{ $state['step4Complete'] ? 'bg-brand-600 text-white' : 'pointer-events-none bg-ink-200 text-ink-400 dark:bg-ink-800 dark:text-ink-500' }} text-sm font-extrabold">Lanjut Evidence After</a>
        </section>
    @elseif ($step === 5)
        <section class="mt-5 space-y-4">
            <div><p class="text-[11px] font-bold uppercase tracking-[.14em] text-brand-600 dark:text-brand-400">Step 5</p><h2 class="mt-1 text-lg font-extrabold">Evidence After</h2><p class="mt-1 text-xs leading-5 text-ink-500">Lengkapi hasil akhir untuk setiap material yang digunakan, foto slot port, dan rekap material.</p></div>
            @foreach ($state['items'] as $item)
                <x-technician-evidence-uploader :lop="$lop" category="after" :designator-id="$item->designator_id"
                    :title="'After · '.$item->designator->code" :description="$item->designator->item_name.' · Qty '.(float)$item->qty.' '.$item->designator->unit"
                    :existing="$evidenceFor('after', $item->designator_id)" />
            @endforeach

            <x-technician-evidence-uploader :lop="$lop" category="slot_port" title="Slot Port" description="Foto posisi slot port pada ODP/ODC tempat koneksi diterminasi. Minimal 1 foto." :existing="$evidenceFor('slot_port')" />

            {{-- Rekap qty material aktual yang terpakai per designator --}}
            @php
                $usageRows = $state['items']->map(fn ($item) => [
                    'designator_id' => (string) $item->designator_id,
                    'code' => $item->designator->code,
                    'name' => $item->designator->item_name,
                    'unit' => $item->designator->unit,
                    'reserved' => (float) $item->qty,
                    'actual' => $item->qty_actual !== null ? (float) $item->qty_actual : '',
                ])->values();
            @endphp
            <section x-data="{ usage: {{ Illuminate\Support\Js::from($usageRows) }}, get invalid() { return this.usage.some(u => u.actual === '' || u.actual === null || Number(u.actual) < 0 || Number(u.actual) > u.reserved); } }"
                     class="rounded-2xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
                <p class="text-sm font-bold">Rekap material terpakai</p>
                <p class="mt-1 text-xs text-ink-500">Isi qty yang benar-benar terpakai per material. Maksimal sebesar qty reservasi.</p>
                <form method="POST" action="{{ route('technician.projects.material-usage', $lop) }}" class="mt-3 space-y-2.5">@csrf @method('PUT')
                    <template x-for="(u, i) in usage" :key="u.designator_id">
                        <div class="rounded-xl border border-ink-100 p-3 dark:border-ink-800">
                            <input type="hidden" :name="`usage[${i}][designator_id]`" :value="u.designator_id">
                            <p class="text-xs font-bold" x-text="u.code"></p>
                            <p class="mt-0.5 text-[11px] text-ink-500" x-text="u.name"></p>
                            <div class="mt-2 flex items-end gap-3">
                                <div class="min-w-0">
                                    <label class="text-[10px] font-bold uppercase tracking-wide text-ink-400">Terpakai</label>
                                    <input type="number" min="0" step="0.001" :max="u.reserved" x-model="u.actual" required
                                           :name="`usage[${i}][qty_actual]`"
                                           class="mt-1 min-h-11 w-28 rounded-xl border bg-white px-2 text-sm font-bold dark:bg-ink-800"
                                           :class="(u.actual !== '' && Number(u.actual) > u.reserved) ? 'border-brand-400' : 'border-ink-100 dark:border-ink-700'">
                                </div>
                                <div class="pb-2 text-[11px] text-ink-500">
                                    <span>Reservasi <span class="font-bold" x-text="u.reserved"></span> <span x-text="u.unit"></span></span>
                                    <template x-if="u.actual !== '' && Number(u.actual) >= 0 && Number(u.actual) < u.reserved">
                                        <span class="ml-2 rounded bg-amber-50 px-1.5 py-0.5 font-bold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">Sisa <span x-text="+(u.reserved - Number(u.actual)).toFixed(3)"></span> <span x-text="u.unit"></span></span>
                                    </template>
                                    <template x-if="u.actual !== '' && Number(u.actual) === u.reserved">
                                        <span class="ml-2 rounded bg-emerald-50 px-1.5 py-0.5 font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">Terpakai penuh</span>
                                    </template>
                                    <template x-if="u.actual !== '' && Number(u.actual) > u.reserved">
                                        <span class="ml-2 rounded bg-brand-50 px-1.5 py-0.5 font-bold text-brand-700 dark:bg-brand-950/40 dark:text-brand-300">Melebihi reservasi</span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                    <button type="submit" :disabled="invalid"
                            :class="invalid ? 'bg-ink-200 text-ink-400 dark:bg-ink-800 dark:text-ink-500' : 'bg-ink-900 text-white dark:bg-ink-700'"
                            class="min-h-11 w-full rounded-xl text-xs font-extrabold transition">Simpan rekap material</button>
                </form>
            </section>

            <div class="rounded-2xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
                <div class="flex items-center justify-between"><div><p class="text-sm font-bold">Checklist akhir</p><p class="mt-1 text-xs text-ink-500">{{ $state['missingAfter']->isEmpty() ? 'Evidence after lengkap.' : $state['missingAfter']->count().' designator belum ada evidence after.' }} {{ $state['slotPortComplete'] ? 'Foto slot port lengkap.' : 'Foto slot port belum ada.' }} {{ $state['materialUsageComplete'] ? 'Rekap material lengkap.' : 'Rekap qty material belum lengkap.' }}</p></div><span class="grid h-9 w-9 place-items-center rounded-full {{ $state['step5Complete'] ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-300' : 'bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-300' }}">{{ $state['step5Complete'] ? '✓' : '!' }}</span></div>
                <form method="POST" action="{{ route('technician.projects.submit', $lop) }}" class="mt-4" onsubmit="return confirm('Ajukan seluruh evidence untuk approval?');">@csrf<button class="min-h-12 w-full rounded-2xl {{ $state['step2Complete'] && $state['step3Complete'] && $state['step4Complete'] && $state['step5Complete'] ? 'bg-brand-600 text-white shadow-lg shadow-brand-600/20' : 'bg-ink-200 text-ink-400 dark:bg-ink-800 dark:text-ink-500' }} text-sm font-extrabold">Ajukan Approval</button></form>
            </div>
        </section>
    @endif
@endif
@endsection
