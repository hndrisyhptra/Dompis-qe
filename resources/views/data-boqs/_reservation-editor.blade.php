<div x-show="edit" x-cloak @click.self="closeEdit()" @keydown.escape.window="closeEdit()" class="fixed inset-0 z-[90] flex items-end justify-center bg-ink-950/60 p-3 text-left sm:items-center">
    <form method="POST" action="{{ route('data-boqs.reservations.update', $lop, false) }}" @submit="if (!saveConfirm) { $event.preventDefault(); saveConfirm = true; }" class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-md border border-ink-200 bg-white shadow-2xl dark:border-ink-700 dark:bg-ink-900">
        @csrf
        @method('PUT')
        <input type="hidden" name="reservation_version" value="{{ $lop->reservation_version }}">
        <div class="flex items-start justify-between gap-4 border-b border-ink-100 p-5 dark:border-ink-800">
            <div><p class="text-xs font-bold uppercase text-brand-600">Edit BOQ Aktual</p><h3 class="mt-1 font-extrabold">{{ $lop->incident }}</h3><p class="mt-1 max-w-lg text-xs text-ink-500">{{ $lop->nama_lop }}</p></div>
            <button type="button" aria-label="Tutup edit BOQ aktual" title="Tutup edit BOQ aktual" @click="closeEdit()" class="grid h-9 w-9 shrink-0 place-items-center rounded-md border border-ink-200 dark:border-ink-700">×</button>
        </div>
        <div x-ref="reservationItemsScroll" class="overflow-y-auto bg-ink-50/50 p-5 dark:bg-ink-950/30">
            <div class="mb-4 rounded-md border border-blue-200 bg-blue-50 p-3 text-xs leading-5 text-blue-700 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300">
                <p class="font-bold">{{ $summary['package_label'] }} · Reservasi teknisi</p>
                <p class="mt-1">Tambah atau hapus material dan ubah qty reservasi. Rekap aktual tetap diisi teknisi. Item dengan evidence atau rekap tidak dapat dihapus; qty tidak boleh di bawah pemakaian. Untuk QE Recovery, jasa pasangan dihitung otomatis. BOQ Plan tidak dibuat dari perubahan ini.</p>
                @if ($lop->status_lop === \App\Enums\LopStatus::WAITING_APPROVAL)<p class="mt-2 font-semibold">Jika menambahkan material baru, LOP kembali ke Progress agar teknisi melengkapi evidence dan rekap sebelum diajukan ulang.</p>@endif
            </div>
            <div class="mb-3 flex items-center justify-between text-xs"><span class="font-semibold text-ink-500" x-text="items.length + '/100 item material'"></span><span class="text-ink-400">Perubahan belum berlaku sebelum disimpan</span></div>
            <div class="space-y-3">
                <template x-for="(item, index) in items" :key="index">
                    <div class="rounded-md border border-ink-200 bg-white p-4 dark:border-ink-700 dark:bg-ink-900">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <p class="text-xs font-bold text-ink-700 dark:text-ink-200">Item <span x-text="index + 1"></span><span x-show="item.can_delete === false" class="ml-2 text-[10px] font-medium text-amber-600">Evidence / aktual tercatat</span></p>
                            <button type="button" @click="requestDrop(index)" :disabled="items.length <= 1 || item.can_delete === false" :title="item.can_delete === false ? 'Tidak dapat dihapus karena evidence atau rekap aktual sudah tercatat' : 'Hapus item material'" aria-label="Hapus item material" class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-bold text-red-600 disabled:cursor-not-allowed disabled:opacity-40 dark:border-red-900">Hapus</button>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_120px_120px]">
                            <div>
                                <template x-if="item.existing">
                                    <div>
                                        <input type="hidden" :name="'items[' + index + '][designator_id]'" :value="item.designator_id">
                                        <p class="text-xs font-extrabold" x-text="item.code || selectedCode(item.designator_id)"></p>
                                        <p class="mt-1 text-xs leading-5 text-ink-500" x-text="item.name"></p>
                                    </div>
                                </template>
                                <template x-if="!item.existing"><div>@include('data-boqs._designator-picker')</div></template>
                            </div>
                            <label class="text-[10px] font-bold uppercase text-ink-500">Qty reservasi
                                <input x-model.number="item.qty" :name="'items[' + index + '][qty]'" type="number" :min="item.qty_actual == null ? 1 : Math.max(1, Math.ceil(item.qty_actual))" step="1" max="999999999999" inputmode="numeric" required class="mt-1.5 min-h-11 w-full rounded-md border border-ink-200 bg-white px-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-950">
                            </label>
                            <div><p class="text-[10px] font-bold uppercase text-ink-500">Qty aktual</p><p class="mt-3 text-xs font-semibold" x-text="item.qty_actual == null ? 'Belum direkap' : new Intl.NumberFormat('id-ID').format(item.qty_actual)"></p><p class="mt-1 text-[10px] text-ink-400">Tidak diubah di sini</p></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        <div class="flex flex-col gap-3 border-t border-ink-100 p-5 dark:border-ink-800 sm:flex-row sm:items-center sm:justify-between">
            <button type="button" @click="add(); $nextTick(() => $refs.reservationItemsScroll.scrollTo({ top: $refs.reservationItemsScroll.scrollHeight, behavior: 'smooth' }))" :disabled="items.length >= maxItems" class="min-h-11 rounded-md border border-brand-200 px-4 text-xs font-bold text-brand-700 disabled:opacity-40 dark:border-brand-800 dark:text-brand-300">+ Tambah Item Material</button>
            <div class="flex gap-2"><button type="button" @click="closeEdit()" class="min-h-11 rounded-md border border-ink-200 px-4 text-sm font-bold dark:border-ink-700">Batal</button><button type="button" @click="if ($el.form.reportValidity()) { saveConfirm = true; $nextTick(() => $refs.confirmReservationSave.focus()); }" :disabled="items.some(item => !item.designator_id)" class="min-h-11 rounded-md bg-brand-600 px-4 text-sm font-bold text-white disabled:opacity-40">Simpan Perubahan</button></div>
        </div>
        <div x-show="dropConfirm" x-cloak @click.self="dropConfirm=false" class="fixed inset-0 z-[110] flex items-end justify-center bg-ink-950/70 p-4 sm:items-center">
            <div class="w-full max-w-sm rounded-md bg-white p-5 shadow-xl dark:bg-ink-900"><h4 class="font-extrabold">Hapus item material?</h4><p class="mt-2 text-sm leading-6 text-ink-500">Item dihapus dari daftar perubahan. Reservasi baru berubah setelah Anda menyimpan.</p><div class="mt-5 grid grid-cols-2 gap-3"><button type="button" @click="dropConfirm=false; pendingDropIndex=null" class="min-h-11 rounded-md border border-ink-200 text-sm font-bold dark:border-ink-700">Tidak</button><button type="button" @click="confirmDrop()" class="min-h-11 rounded-md bg-red-600 text-sm font-bold text-white">Ya, hapus</button></div></div>
        </div>
        <div x-show="saveConfirm" x-cloak @click.self="saveConfirm=false" class="fixed inset-0 z-[110] flex items-end justify-center bg-ink-950/70 p-4 sm:items-center">
            <div class="w-full max-w-sm rounded-md bg-white p-5 shadow-xl dark:bg-ink-900"><h4 class="font-extrabold">Simpan perubahan BOQ aktual?</h4><p class="mt-2 text-sm leading-6 text-ink-500">Reservasi teknisi akan diperbarui. Qty aktual dan evidence tetap dipertahankan. Perubahan dicatat pada riwayat LOP.</p><div class="mt-5 grid grid-cols-2 gap-3"><button type="button" @click="saveConfirm=false" class="min-h-11 rounded-md border border-ink-200 text-sm font-bold dark:border-ink-700">Tidak</button><button x-ref="confirmReservationSave" type="submit" name="confirmed" value="1" class="min-h-11 rounded-md bg-brand-600 text-sm font-bold text-white">Ya, simpan</button></div></div>
        </div>
    </form>
</div>
