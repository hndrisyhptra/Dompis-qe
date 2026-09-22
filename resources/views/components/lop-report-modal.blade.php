@props(['id' => 'lop-report-modal'])

{{-- Global per-LOP Laporan modal — BOQ Actual & Sisa Material dipindah dari sidebar ke aksi LOP.
     Dipakai di lop/index & lop/history (single instance, di-trigger via $dispatch('open-lop-report')).
     Data diambil via fetch JSON ke /reports/lop/{id}/boq-actual?json=1 atau sisa-material. --}}
<div x-data="lopReportModal()" @open-lop-report.window="openReport($event.detail)" x-cloak>
    <dialog x-ref="dialog" @click.self="$refs.dialog.close()" @close="closeReport()"
            class="m-0 max-h-[90vh] w-full max-w-4xl overflow-hidden rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-black/30 open:flex open:flex-col dark:bg-ink-900 max-md:max-w-[95vw]">
        {{-- Header --}}
        <div class="flex shrink-0 items-start justify-between gap-4 border-b border-ink-100 bg-white px-5 py-4 dark:border-ink-800 dark:bg-ink-900 sm:px-6">
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-[.16em] text-brand-600 dark:text-brand-400" x-text="lopInfo.incident || '—'"></p>
                <h3 class="mt-1 text-base font-extrabold leading-snug text-ink-900 dark:text-white">
                    <span x-text="title"></span>
                    <span class="ml-2 text-xs font-semibold text-ink-500 dark:text-ink-400" x-text="lopInfo.nama_lop ? '· ' + lopInfo.nama_lop : ''"></span>
                </h3>
                <p class="mt-1 text-xs text-ink-500 dark:text-ink-400" x-show="lopInfo.branch || lopInfo.program">
                    <span x-text="lopInfo.branch || ''"></span>
                    <span x-show="lopInfo.branch && lopInfo.program"> · </span>
                    <span x-text="lopInfo.program || ''"></span>
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                {{-- Tabs BOQ / Sisa --}}
                <div class="hidden sm:flex rounded-full bg-ink-100 p-1 dark:bg-ink-800">
                    <button type="button" @click="switchReport('boq')" :class="reportType==='boq' ? 'bg-white shadow-sm text-ink-900 dark:bg-ink-700 dark:text-white' : 'text-ink-500 dark:text-ink-400'"
                            class="rounded-full px-3 py-1.5 text-xs font-bold transition">BOQ Actual</button>
                    <button type="button" @click="switchReport('sisa')" :class="reportType==='sisa' ? 'bg-white shadow-sm text-ink-900 dark:bg-ink-700 dark:text-white' : 'text-ink-500 dark:text-ink-400'"
                            class="rounded-full px-3 py-1.5 text-xs font-bold transition">Sisa Material</button>
                </div>
                <button type="button" @click="$refs.dialog.close()" class="grid h-8 w-8 place-items-center rounded-full bg-ink-100 text-ink-500 hover:bg-ink-200 dark:bg-ink-800 dark:text-ink-400">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Tabs mobile --}}
        <div class="flex sm:hidden border-b border-ink-100 dark:border-ink-800">
            <button type="button" @click="switchReport('boq')" :class="reportType==='boq' ? 'border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'border-transparent text-ink-500'"
                    class="flex-1 border-b-2 px-3 py-2.5 text-xs font-bold">BOQ Actual</button>
            <button type="button" @click="switchReport('sisa')" :class="reportType==='sisa' ? 'border-brand-600 text-brand-600 dark:border-brand-400 dark:text-brand-400' : 'border-transparent text-ink-500'"
                    class="flex-1 border-b-2 px-3 py-2.5 text-xs font-bold">Sisa Material</button>
        </div>

        {{-- Body --}}
        <div class="min-h-0 flex-1 overflow-y-auto p-0">
            {{-- Loading --}}
            <div x-show="loading" class="p-6 space-y-3">
                <div class="h-4 w-32 animate-pulse rounded bg-ink-100 dark:bg-ink-800"></div>
                <div class="space-y-2">
                    <template x-for="i in 4"><div class="h-10 animate-pulse rounded-lg bg-ink-50 dark:bg-ink-800/50"></div></template>
                </div>
            </div>

            {{-- Error --}}
            <div x-show="!loading && error" x-cloak class="p-6">
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                    <p class="font-bold">Gagal memuat laporan</p>
                    <p class="mt-1" x-text="error"></p>
                    <button type="button" @click="retry()" class="mt-3 rounded-lg bg-white px-3 py-1.5 text-xs font-bold text-red-700 border border-red-200 hover:bg-red-50">Coba lagi</button>
                </div>
            </div>

            {{-- Empty --}}
            <div x-show="!loading && !error && isEmpty()" x-cloak class="p-8 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-ink-100 dark:bg-ink-800">
                    <svg class="h-6 w-6 text-ink-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m16.5 0V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v1.5m16.5 0h-16.5"/></svg>
                </div>
                <p class="mt-3 text-sm font-bold text-ink-900 dark:text-white">Belum ada material submitted</p>
                <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Teknisi belum menyelesaikan Step 5 rekap qty aktual untuk LOP ini, atau LOP belum memiliki reservasi material.</p>
            </div>

            {{-- Table --}}
            <div x-show="!loading && !error && !isEmpty()" x-cloak class="p-4 sm:p-5">
                {{-- Summary tiles (grand) --}}
                <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-ink-100 bg-ink-50/60 p-3 dark:border-ink-800 dark:bg-ink-800/30">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-ink-500 dark:text-ink-400">Qty Plan</p>
                        <p class="mt-1 text-sm font-extrabold text-ink-900 dark:text-white" x-text="formatNumber(grand.qty)"></p>
                    </div>
                    <div class="rounded-xl border border-ink-100 bg-ink-50/60 p-3 dark:border-ink-800 dark:bg-ink-800/30">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-ink-500 dark:text-ink-400">Qty Actual</p>
                        <p class="mt-1 text-sm font-extrabold text-ink-900 dark:text-white" x-text="formatNumber(grand.qty_actual)"></p>
                    </div>
                    <div class="rounded-xl border border-ink-100 bg-ink-50/60 p-3 dark:border-ink-800 dark:bg-ink-800/30" x-show="reportType==='sisa'">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-ink-500 dark:text-ink-400">Sisa</p>
                        <p class="mt-1 text-sm font-extrabold text-amber-600 dark:text-amber-400" x-text="formatNumber(grand.sisa)"></p>
                    </div>
                    <div class="rounded-xl border border-ink-100 bg-ink-50/60 p-3 dark:border-ink-800 dark:bg-ink-800/30" x-show="priced">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-ink-500 dark:text-ink-400" x-text="reportType==='boq' ? 'Total Actual' : 'Nilai Sisa'"></p>
                        <p class="mt-1 text-sm font-extrabold text-emerald-600 dark:text-emerald-400" x-text="formatMoney(reportType==='boq' ? grand.total_actual : grand.nilai_sisa)"></p>
                    </div>
                </div>

                <p x-show="grand.not_recapped_count > 0" class="mb-3 text-xs text-amber-600 dark:text-amber-400">
                    <span x-text="grand.not_recapped_count"></span> item belum direkap qty aktual.
                </p>
                <p x-show="grand.price_missing_count > 0" class="mb-3 text-xs text-red-600 dark:text-red-400">
                    <span x-text="grand.price_missing_count"></span> designator belum ada harga KHS (paket: <span x-text="packageInfo?.name || '—'"></span>).
                </p>

                <div class="overflow-hidden rounded-xl border border-ink-100 dark:border-ink-800">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left text-xs">
                            <thead class="bg-ink-50 dark:bg-ink-800/50">
                                <tr class="border-b border-ink-100 dark:border-ink-800">
                                    <th class="px-3 py-2.5 font-bold text-ink-600 dark:text-ink-300">Designator</th>
                                    <th class="px-3 py-2.5 font-bold text-ink-600 dark:text-ink-300">Uraian</th>
                                    <th class="px-3 py-2.5 text-center font-bold text-ink-600 dark:text-ink-300">Sat</th>
                                    <th class="px-3 py-2.5 text-right font-bold text-ink-600 dark:text-ink-300">Qty Plan</th>
                                    <th class="px-3 py-2.5 text-right font-bold text-ink-600 dark:text-ink-300">Qty Actual</th>
                                    <th x-show="reportType==='sisa'" class="px-3 py-2.5 text-right font-bold text-ink-600 dark:text-ink-300">Sisa</th>
                                    <th x-show="priced" class="px-3 py-2.5 text-right font-bold text-ink-600 dark:text-ink-300">Harga KHS</th>
                                    <th x-show="priced" class="px-3 py-2.5 text-right font-bold text-ink-600 dark:text-ink-300" x-text="reportType==='boq' ? 'Total Actual' : 'Nilai Sisa'"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-100 dark:divide-ink-800">
                                <template x-for="line in lines" :key="line.designator_id + '-' + line.qty">
                                    <tr class="hover:bg-ink-50/60 dark:hover:bg-ink-800/30">
                                        <td class="px-3 py-2.5 font-mono font-bold text-ink-900 dark:text-white" x-text="line.designator_code"></td>
                                        <td class="px-3 py-2.5 text-ink-700 dark:text-ink-200" x-text="line.designator_name || '—'"></td>
                                        <td class="px-3 py-2.5 text-center text-ink-500" x-text="line.unit || '—'"></td>
                                        <td class="px-3 py-2.5 text-right tabular-nums" x-text="formatNumber(line.qty)"></td>
                                        <td class="px-3 py-2.5 text-right tabular-nums">
                                            <span x-text="line.qty_actual == null ? '—' : formatNumber(line.qty_actual)"></span>
                                            <span x-show="line.qty_actual == null" class="ml-1 rounded bg-ink-100 px-1 py-0.5 text-[10px] font-bold text-ink-500 dark:bg-ink-700">belum direkap</span>
                                        </td>
                                        <td x-show="reportType==='sisa'" class="px-3 py-2.5 text-right tabular-nums" :class="(line.sisa||0)>0 ? 'text-amber-600 font-bold' : 'text-emerald-600'">
                                            <span x-text="line.sisa == null ? '—' : formatNumber(line.sisa)"></span>
                                        </td>
                                        <td x-show="priced" class="px-3 py-2.5 text-right tabular-nums" x-text="line.price_missing ? '—' : formatMoney(line.price)"></td>
                                        <td x-show="priced" class="px-3 py-2.5 text-right tabular-nums font-bold" :class="line.price_missing ? 'text-ink-400' : 'text-emerald-700 dark:text-emerald-300'">
                                            <span x-text="line.price_missing ? 'harga belum diset' : formatMoney(reportType==='boq' ? line.total_actual : line.nilai_sisa)"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-ink-50 font-bold dark:bg-ink-800/50" x-show="lines.length">
                                <tr class="border-t-2 border-ink-200 dark:border-ink-700">
                                    <td colspan="3" class="px-3 py-2.5 text-right text-ink-700 dark:text-ink-200">Subtotal</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums" x-text="formatNumber(grand.qty)"></td>
                                    <td class="px-3 py-2.5 text-right tabular-nums" x-text="formatNumber(grand.qty_actual)"></td>
                                    <td x-show="reportType==='sisa'" class="px-3 py-2.5 text-right tabular-nums" x-text="formatNumber(grand.sisa)"></td>
                                    <td x-show="priced" class="px-3 py-2.5"></td>
                                    <td x-show="priced" class="px-3 py-2.5 text-right tabular-nums text-emerald-700 dark:text-emerald-300" x-text="formatMoney(reportType==='boq' ? grand.total_actual : grand.nilai_sisa)"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t border-ink-100 bg-ink-50/60 px-5 py-3 dark:border-ink-800 dark:bg-ink-900/50 sm:px-6">
            <p class="text-xs text-ink-500 dark:text-ink-400" x-show="!loading && !error">
                Paket: <span class="font-semibold text-ink-700 dark:text-ink-200" x-text="packageInfo?.name || 'Tanpa paket (tanpa harga)'"></span>
            </p>
            <div class="flex flex-wrap items-center gap-2 ml-auto">
                <a :href="exportUrl('csv')" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3 py-2 text-xs font-bold text-ink-700 hover:bg-ink-50 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-200">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                    CSV
                </a>
                <a :href="exportUrl('xlsx')" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3 py-2 text-xs font-bold text-ink-700 hover:bg-ink-50 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-200">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-3-3v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    XLSX
                </a>
                <button type="button" @click="doPrint()" class="inline-flex items-center gap-1.5 rounded-lg bg-ink-900 px-3 py-2 text-xs font-bold text-white hover:bg-ink-800 dark:bg-white dark:text-ink-900">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18h12M6 9h12M7 9V6a1 1 0 011-1h8a1 1 0 011 1v3"/></svg>
                    Cetak
                </button>
                <button type="button" @click="$refs.dialog.close()" class="rounded-lg bg-white px-3 py-2 text-xs font-bold text-ink-600 border border-ink-200 hover:bg-ink-50 dark:bg-ink-800 dark:text-ink-300 dark:border-ink-700">Tutup</button>
            </div>
        </div>
    </dialog>
</div>
