@extends('layouts.app')

@section('title', 'Data BOQ')

@section('content')
@php
    $designatorOptions = $designators->map(fn ($designator) => [
        'id' => $designator->id_designator,
        'code' => $designator->code,
        'name' => $designator->item_name,
        'unit' => $designator->unit,
        'type' => $designator->type?->code,
    ])->values();
@endphp

<div class="mx-auto max-w-7xl space-y-5">
    @if ($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">{{ $errors->first() }}</div>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.16em] text-brand-600">Master Data</p>
            <h1 class="mt-1 text-2xl font-extrabold text-ink-900 dark:text-white">Data BOQ</h1>
            <p class="mt-2 text-sm text-ink-500">Kelola item designator dan sinkronkan kebutuhan material ke reservasi teknisi.</p>
        </div>
        <a href="{{ route('bulk-import.boq.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-brand-600 px-4 text-sm font-extrabold text-white">Import BOQ</a>
    </div>

    <form method="GET" class="rounded-2xl border border-ink-200 bg-white p-4 shadow-sm dark:border-ink-700 dark:bg-ink-900">
        <div class="mb-4 flex items-center justify-between gap-3 border-b border-ink-100 pb-3 dark:border-ink-800">
            <div><p class="text-sm font-extrabold text-ink-900 dark:text-white">Pencarian BOQ</p><p class="mt-0.5 text-xs text-ink-500">Cari berdasarkan incident atau nama LOP.</p></div>
            @if ($search)<a href="{{ route('data-boqs.index') }}" class="text-xs font-bold text-brand-600 hover:text-brand-700">Reset pencarian</a>@endif
        </div>
        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
            <label><span class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-ink-500">Incident / Nama LOP</span><input name="q" value="{{ $search }}" placeholder="Contoh: INP3124092601" class="min-h-11 w-full rounded-xl border border-ink-200 bg-white px-3 text-sm shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950"></label>
            <div class="flex items-end"><button class="min-h-11 w-full rounded-xl bg-ink-900 px-6 text-sm font-bold text-white shadow-sm transition hover:bg-ink-800 dark:bg-white dark:text-ink-900 dark:hover:bg-ink-100">Cari Data BOQ</button></div>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-ink-100 bg-white shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-ink-100 text-sm dark:divide-ink-800">
                <thead class="bg-ink-50 text-left text-[11px] uppercase text-ink-500 dark:bg-ink-950/50">
                    <tr><th class="px-5 py-3">LOP</th><th class="px-5 py-3">Paket</th><th class="px-5 py-3 text-right">Nilai Jasa</th><th class="px-5 py-3 text-right">Nilai Material</th><th class="px-5 py-3 text-right">Nilai BOQ</th><th class="px-5 py-3 text-right">Aksi</th></tr>
                </thead>
                <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                    @forelse ($boqs as $boq)
                        @php
                            $status = $boq->lop->status_lop->value;
                            $editable = in_array($status, ['draft', 'assigned', 'picked_up', 'survey', 'progress', 'rejected'], true);
                            $deletable = in_array($status, ['draft', 'assigned'], true);
                            $initialItems = $boq->items->map(fn ($item) => [
                                'designator_id' => (string) $item->designator_id,
                                'qty' => (int) round((float) $item->qty),
                                'unit_price' => (float) $item->unit_price,
                            ])->values();
                            $serviceTotal = $boq->items->where('type', 'JASA')->sum('total_price');
                            $materialTotal = $boq->items->where('type', 'MATERIAL')->sum('total_price');
                        @endphp

                        <tr x-data="boqEditor('{{ base64_encode($initialItems->toJson()) }}', '{{ base64_encode($designatorOptions->toJson()) }}')" class="transition hover:bg-ink-50/70 dark:hover:bg-ink-800/40">
                            <td class="px-5 py-4">
                                <p class="font-bold">{{ $boq->lop->incident }}</p>
                                <p class="mt-1 max-w-xs truncate text-xs text-ink-500">{{ $boq->lop->nama_lop }}</p>
                                <span class="mt-2 inline-flex rounded-full bg-ink-100 px-2 py-0.5 text-[9px] font-bold text-ink-500 dark:bg-ink-800">{{ $boq->lop->status_lop->label() }}</span>
                            </td>
                            <td class="px-5 py-4"><p class="text-xs font-bold text-ink-700 dark:text-ink-200">{{ $boq->package?->code ?: '—' }}</p><p class="mt-1 text-[10px] text-ink-400">{{ $boq->item_count }} item</p></td>
                            <td class="whitespace-nowrap px-5 py-4 text-right text-xs font-semibold">Rp {{ number_format((float) $serviceTotal, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-right text-xs font-semibold">Rp {{ number_format((float) $materialTotal, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-right font-extrabold text-ink-900 dark:text-white">Rp {{ number_format((float) $boq->grand_total, 0, ',', '.') }}</td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-1.5">
                                    <x-table-action label="Lihat detail BOQ" @click="detail=true">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="2.25"/></svg>
                                    </x-table-action>
                                    @if ($editable)
                                        <x-table-action label="Kelola item BOQ" tone="primary" @click="openEdit()">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m16.862 4.487 1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931Z"/></svg>
                                        </x-table-action>
                                    @endif
                                    @if ($deletable)
                                        <x-table-action label="Hapus seluruh BOQ" tone="danger" @click="remove=true">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 7.5h12m-10.5 0 .75 12h7.5l.75-12M9.75 7.5V4.875h4.5V7.5"/></svg>
                                        </x-table-action>
                                    @endif
                                    @unless ($editable)
                                        <x-table-action label="BOQ terkunci saat menunggu approval atau selesai" disabled><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/></svg></x-table-action>
                                    @endunless
                                </div>

                                <div x-show="detail" x-cloak @click.self="detail=false" @keydown.escape.window="detail=false" class="fixed inset-0 z-[90] flex items-end justify-center bg-ink-950/60 p-4 sm:items-center">
                                    <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-3xl border border-ink-200 bg-white p-6 shadow-2xl dark:border-ink-700 dark:bg-ink-900">
                                        <div class="flex items-start justify-between gap-4">
                                            <div><p class="text-xs font-bold uppercase text-brand-600">Detail BOQ</p><h3 class="mt-1 text-xl font-extrabold">{{ $boq->lop->incident }}</h3></div>
                                            <button type="button" aria-label="Tutup detail BOQ" title="Tutup detail BOQ" @click="detail=false" class="grid h-9 w-9 place-items-center rounded-full border border-ink-200 bg-ink-50 dark:border-ink-700 dark:bg-ink-800">×</button>
                                        </div>
                                        <div class="mt-5 overflow-x-auto rounded-xl border border-ink-100 dark:border-ink-800">
                                            <table class="min-w-full text-sm">
                                                <thead class="bg-ink-50 text-left text-xs text-ink-500 dark:bg-ink-950"><tr><th class="px-4 py-3">Designator</th><th class="px-4 py-3">Item</th><th class="px-4 py-3 text-right">Qty</th><th class="px-4 py-3 text-right">Harga</th><th class="px-4 py-3 text-right">Total</th></tr></thead>
                                                <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                                                    @foreach ($boq->items as $item)
                                                        <tr><td class="px-4 py-3 font-bold">{{ $item->designator_code }}</td><td class="px-4 py-3 text-xs text-ink-500">{{ $item->item_name }}<br>{{ $item->type }}</td><td class="px-4 py-3 text-right">{{ (int) round((float) $item->qty) }}</td><td class="whitespace-nowrap px-4 py-3 text-right">Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td><td class="whitespace-nowrap px-4 py-3 text-right font-semibold">Rp {{ number_format((float) $item->total_price, 0, ',', '.') }}</td></tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                @if ($editable)
                                    <div x-show="edit" x-cloak @click.self="closeEdit()" @keydown.escape.window="closeEdit()" class="fixed inset-0 z-[90] flex items-end justify-center bg-ink-950/60 p-3 sm:items-center">
                                        <form method="POST" action="{{ route('data-boqs.update', $boq) }}" class="flex max-h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-ink-200 bg-white shadow-2xl dark:border-ink-700 dark:bg-ink-900">
                                            @csrf
                                            @method('PUT')
                                            <div class="flex items-start justify-between gap-4 border-b border-ink-100 p-5 dark:border-ink-800">
                                                <div class="min-w-0">
                                                    <p class="text-xs font-bold uppercase text-brand-600">Kelola Item BOQ</p>
                                                    <div class="mt-1 flex flex-wrap items-center gap-2"><h3 class="font-extrabold">{{ $boq->lop->incident }}</h3><span class="rounded-full bg-ink-100 px-2.5 py-1 text-[10px] font-bold text-ink-600 dark:bg-ink-800 dark:text-ink-300" x-text="items.length + ' item'"></span></div>
                                                    @unless (in_array($status, ['draft', 'assigned'], true))
                                                        <p class="mt-1 text-[11px] text-amber-600">LOP sedang dikerjakan. Perubahan material akan langsung disinkronkan ke reservasi teknisi.</p>
                                                    @endunless
                                                </div>
                                                <button type="button" aria-label="Tutup pengelolaan BOQ" title="Tutup pengelolaan BOQ" @click="closeEdit()" class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-ink-200 bg-ink-50 dark:border-ink-700 dark:bg-ink-800">×</button>
                                            </div>

                                            <div x-ref="itemScroll" class="overflow-y-auto bg-ink-50/60 p-5 dark:bg-ink-950/30">
                                                <div class="grid gap-3 rounded-2xl border border-ink-200 bg-white p-4 dark:border-ink-700 dark:bg-ink-900 md:grid-cols-[minmax(0,320px)_1fr] md:items-center">
                                                <label class="block text-xs font-bold uppercase text-ink-500">
                                                    Paket Harga
                                                    <select name="package_id" class="mt-2 min-h-11 w-full rounded-xl border border-ink-200 bg-white px-3 text-sm shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950">
                                                        <option value="">Tanpa paket</option>
                                                        @foreach ($packages as $package)
                                                            <option value="{{ $package->id_package }}" @selected($boq->package_id === $package->id_package)>{{ $package->code }} · {{ $package->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <div class="rounded-xl border border-blue-100 bg-blue-50 p-3 text-xs leading-5 text-blue-700 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-300"><span class="font-extrabold">Tips:</span> gunakan kotak pencarian pada setiap item untuk menemukan designator berdasarkan kode, nama, satuan, atau tipe.</div>
                                                </div>

                                                <div class="mt-5 space-y-3">
                                                    <template x-for="(item, index) in items" :key="index">
                                                        <div class="rounded-2xl border border-ink-200 bg-white p-4 shadow-sm transition hover:border-ink-300 dark:border-ink-700 dark:bg-ink-900 dark:hover:border-ink-600">
                                                            <div class="mb-3 flex items-center justify-between gap-3 border-b border-ink-100 pb-3 dark:border-ink-800">
                                                                <div class="min-w-0"><p class="text-[10px] font-extrabold uppercase tracking-wider text-ink-400">Item <span x-text="index + 1"></span></p><p class="mt-0.5 truncate text-xs font-bold text-ink-700 dark:text-ink-200" x-text="selectedCode(item.designator_id)"></p></div>
                                                                <button type="button" @click="requestDrop(index)" x-show="items.length > 1" aria-label="Hapus item designator" title="Hapus item designator" class="inline-flex min-h-8 shrink-0 items-center gap-1 rounded-lg border border-red-100 px-2.5 text-[10px] font-bold text-red-600 hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-950/20">
                                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 7.5h12m-10.5 0 .75 12h7.5l.75-12M9.75 7.5V4.875h4.5V7.5"/></svg> Hapus
                                                                </button>
                                                            </div>
                                                            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_120px_180px]">
                                                                <div>
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
                                                                </div>
                                                                <label class="text-[10px] font-bold uppercase text-ink-400">
                                                                    Qty
                                                                    <input x-model.number="item.qty" :name="'items[' + index + '][qty]'" type="number" min="1" step="1" inputmode="numeric" required class="mt-1.5 min-h-11 w-full rounded-xl border border-ink-200 bg-white px-3 text-sm shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950">
                                                                </label>
                                                                <label class="text-[10px] font-bold uppercase text-ink-400">
                                                                    Harga satuan
                                                                    <span class="relative mt-1.5 block"><span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-xs font-bold text-ink-400">Rp</span><input x-model="item.unit_price" :name="'items[' + index + '][unit_price]'" type="number" min="0" step="0.01" class="min-h-11 w-full rounded-xl border border-ink-200 bg-white pl-9 pr-3 text-sm shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/15 dark:border-ink-700 dark:bg-ink-950"></span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>

                                            </div>

                                            <div class="flex flex-col gap-3 border-t border-ink-100 p-5 dark:border-ink-800 sm:flex-row sm:items-center sm:justify-between">
                                                <button type="button" @click="add(); $nextTick(() => $refs.itemScroll.scrollTo({ top: $refs.itemScroll.scrollHeight, behavior: 'smooth' }))" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-brand-200 px-4 text-xs font-extrabold text-brand-700 dark:border-brand-800 dark:text-brand-300">+ Tambah Item Designator</button>
                                                <div class="grid grid-cols-2 gap-3 sm:flex">
                                                    <button type="button" @click="closeEdit()" class="min-h-11 rounded-xl border border-ink-200 px-5 text-sm font-bold dark:border-ink-700">Batal</button>
                                                    <button type="button" @click="saveConfirm=true" :disabled="items.some(item => !item.designator_id)" class="min-h-11 rounded-xl bg-brand-600 px-6 text-sm font-extrabold text-white disabled:cursor-not-allowed disabled:bg-ink-300">Simpan Perubahan</button>
                                                </div>
                                            </div>

                                            <div x-show="dropConfirm" x-cloak class="fixed inset-0 z-[110] flex items-end justify-center bg-ink-950/70 p-4 sm:items-center" @click.self="dropConfirm=false">
                                                <div class="w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl dark:bg-ink-900">
                                                    <h4 class="text-base font-extrabold">Hapus item designator?</h4>
                                                    <p class="mt-2 text-sm leading-6 text-ink-500">Item akan dihapus dari daftar perubahan BOQ. Perubahan baru berlaku setelah BOQ disimpan.</p>
                                                    <div class="mt-6 grid grid-cols-2 gap-3">
                                                        <button type="button" @click="dropConfirm=false; pendingDropIndex=null" class="min-h-11 rounded-xl border border-ink-200 text-sm font-bold dark:border-ink-700">Tidak</button>
                                                        <button type="button" @click="confirmDrop()" class="min-h-11 rounded-xl bg-red-600 text-sm font-extrabold text-white">Ya, hapus</button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div x-show="saveConfirm" x-cloak class="fixed inset-0 z-[110] flex items-end justify-center bg-ink-950/70 p-4 sm:items-center" @click.self="saveConfirm=false">
                                                <div class="w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl dark:bg-ink-900">
                                                    <h4 class="text-base font-extrabold">Simpan perubahan BOQ?</h4>
                                                    <p class="mt-2 text-sm leading-6 text-ink-500">Pastikan designator, quantity, dan harga sudah benar. Aktivitas ini akan dicatat pada riwayat BOQ.</p>
                                                    <div class="mt-6 grid grid-cols-2 gap-3">
                                                        <button type="button" @click="saveConfirm=false" class="min-h-11 rounded-xl border border-ink-200 text-sm font-bold dark:border-ink-700">Tidak</button>
                                                        <button type="submit" class="min-h-11 rounded-xl bg-brand-600 text-sm font-extrabold text-white">Ya, simpan</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                @endif

                                @if ($deletable)
                                    <x-confirm-modal state="remove" title="Hapus seluruh BOQ?" message="Seluruh item BOQ dan reservasi draft yang berasal dari BOQ akan dihapus." :action="route('data-boqs.destroy', $boq)" method="DELETE" confirm-label="Ya, hapus BOQ" cancel-label="Tidak" tone="danger" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-14 text-center text-ink-400">Belum ada LOP yang memiliki BOQ.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $boqs->links() }}
</div>
@endsection
