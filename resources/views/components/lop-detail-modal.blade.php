@props(['id', 'lop'])

@php
    $summary = $lop->progress_summary;

    $chips = array_values(array_filter([
        $lop->program_type->label(),
        $lop->branch ?: null,
        $lop->budget_type?->label(),
    ]));

    $details = [
        'STO' => $lop->sto ?: '—',
        'Area' => $lop->area ? 'Area '.$lop->area : '—',
        'Segmen' => $lop->segmentLabel() ?: '—',
        'Jenis Anggaran' => $lop->budget_type?->label() ?? 'Tidak berlaku',
        'ID IHLD' => $lop->ihld_id ?: 'Belum tersedia',
        'Terakhir diperbarui' => $lop->updated_at->diffForHumans(),
    ];

    $barColor = match ($summary['review_key'] ?? null) {
        'rejected' => 'bg-brand-500',
        'approved' => 'bg-emerald-500',
        'waiting_review' => 'bg-amber-400',
        default => 'bg-blue-500',
    };

    $canViewReport = auth()->user()?->hasPermission('reporting');
@endphp

<x-modal :id="$id" title="Detail LOP" size="xl">
    <div x-data="detailLaporanTab({{ $lop->id_qe_lops }})" class="flex flex-col">
        {{-- Header + Tab bar (sticky) --}}
        <div class="sticky -top-5 -mx-5 z-10 -mt-5 border-b border-ink-100 bg-white/95 px-5 py-4 backdrop-blur dark:border-ink-700 dark:bg-ink-900/95">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[.16em] text-brand-600 dark:text-brand-400">{{ $lop->incident }}</p>
                    <h3 class="mt-1 text-[15px] font-extrabold leading-snug text-ink-900 dark:text-white md:text-lg">{{ $lop->nama_lop }}</h3>
                </div>
                <x-badge :variant="$lop->status_lop->badgeVariant()" class="shrink-0">{{ $lop->status_lop->label() }}</x-badge>
            </div>

            @if (count($chips))
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach ($chips as $chip)
                        <span class="rounded-lg bg-ink-100 px-2.5 py-1 text-xs font-semibold text-ink-600 dark:bg-ink-800 dark:text-ink-300">{{ $chip }}</span>
                    @endforeach
                </div>
            @endif

            <p class="mt-2 text-xs text-ink-500 dark:text-ink-400">
                Dibuat oleh {{ $lop->creator?->name ?? '—' }} · {{ $lop->created_at->format('d M Y, H:i') }}
            </p>

            {{-- Tabs --}}
            <div class="mt-4 flex gap-1 rounded-full bg-ink-100 p-1 dark:bg-ink-800 w-fit max-w-full overflow-x-auto">
                <button type="button" @click="tab='overview'" :class="tab==='overview' ? 'bg-white shadow-sm text-ink-900 dark:bg-ink-700 dark:text-white' : 'text-ink-500 dark:text-ink-400'"
                        class="whitespace-nowrap rounded-full px-3.5 py-1.5 text-xs font-bold transition">Overview</button>
                @if ($canViewReport)
                    <button type="button" @click="tab='material'; fetchIfNeeded(type)" :class="tab==='material' ? 'bg-white shadow-sm text-ink-900 dark:bg-ink-700 dark:text-white' : 'text-ink-500 dark:text-ink-400'"
                            class="whitespace-nowrap rounded-full px-3.5 py-1.5 text-xs font-bold transition">Material & Laporan</button>
                @endif
                <button type="button" @click="tab='tiket'" :class="tab==='tiket' ? 'bg-white shadow-sm text-ink-900 dark:bg-ink-700 dark:text-white' : 'text-ink-500 dark:text-ink-400'"
                        class="whitespace-nowrap rounded-full px-3.5 py-1.5 text-xs font-bold transition">Tiket & Jaringan</button>
            </div>
        </div>

        <div class="space-y-6 pt-4">
            {{-- Tab: Overview --}}
            <div x-show="tab==='overview'" x-transition class="space-y-6">
                {{-- Progress --}}
                <div class="rounded-2xl border border-ink-100 bg-ink-50/60 p-4 dark:border-ink-800 dark:bg-ink-800/40">
                    <div class="flex items-end justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-ink-900 dark:text-white">Progress pekerjaan</p>
                            <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">
                                {{ $summary['completed_steps'] }}/{{ $summary['total_steps'] }} step · {{ $summary['evidence_count'] }} evidence · {{ $summary['review_label'] }}
                            </p>
                        </div>
                        <span class="shrink-0 text-2xl font-extrabold tabular-nums text-ink-900 dark:text-white">{{ $summary['percentage'] }}%</span>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-ink-200 dark:bg-ink-700">
                        <div class="h-full rounded-full transition-all {{ $barColor }}" style="width: {{ $summary['percentage'] }}%"></div>
                    </div>
                </div>

                {{-- Teknisi --}}
                <div class="flex items-center gap-3 rounded-2xl border p-4 {{ $lop->activeAssignment ? 'border-blue-100 bg-blue-50/70 dark:border-blue-900/50 dark:bg-blue-950/30' : 'border-ink-100 bg-white dark:border-ink-800 dark:bg-ink-900' }}">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-sm font-extrabold text-white {{ $lop->activeAssignment ? 'bg-blue-600' : 'bg-ink-300 dark:bg-ink-700' }}">
                        {{ mb_strtoupper(mb_substr($lop->activeAssignment?->technician?->name ?? '?', 0, 1)) }}
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-ink-500 dark:text-ink-400">Teknisi aktif</p>
                        <p class="mt-0.5 truncate text-sm font-bold text-ink-900 dark:text-white">{{ $lop->activeAssignment?->technician?->name ?? 'Belum ditugaskan' }}</p>
                        @if ($lop->activeAssignment)
                            <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">Ditugaskan {{ $lop->activeAssignment->assigned_at->diffForHumans() }}</p>
                        @endif
                    </div>
                </div>

                {{-- Rincian — 2 kolom agar lebih padat, tidak 6 baris vertikal --}}
                <div>
                    <p class="mb-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">Rincian</p>
                    <dl class="grid grid-cols-1 gap-px overflow-hidden rounded-2xl border border-ink-100 bg-ink-100 dark:border-ink-800 dark:bg-ink-800 sm:grid-cols-2">
                        @foreach ($details as $label => $value)
                            <div class="flex items-baseline justify-between gap-3 bg-white px-4 py-3 dark:bg-ink-900">
                                <dt class="shrink-0 text-xs text-ink-500 dark:text-ink-400">{{ $label }}</dt>
                                <dd class="min-w-0 break-words text-right text-sm font-semibold text-ink-800 dark:text-ink-100">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                {{-- Deskripsi --}}
                <div>
                    <p class="mb-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">Deskripsi pekerjaan</p>
                    <p class="whitespace-pre-line rounded-2xl border border-ink-100 bg-white p-4 text-sm leading-6 text-ink-700 dark:border-ink-800 dark:bg-ink-900 dark:text-ink-100">{{ $lop->job_description ?: 'Belum ada deskripsi pekerjaan.' }}</p>
                </div>
            </div>

            {{-- Tab: Material & Laporan (tanpa scroll horizontal, view only) --}}
            @if ($canViewReport)
                <div x-show="tab==='material'" x-transition class="space-y-5">
                    {{-- Sub-toggle BOQ / Sisa --}}
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex rounded-full bg-ink-100 p-1 dark:bg-ink-800">
                            <button type="button" @click="switchType('boq')" :class="type==='boq' ? 'bg-white shadow-sm text-ink-900 dark:bg-ink-700 dark:text-white' : 'text-ink-500 dark:text-ink-400'"
                                    class="rounded-full px-3.5 py-1.5 text-xs font-bold transition">BOQ Actual</button>
                            <button type="button" @click="switchType('sisa')" :class="type==='sisa' ? 'bg-white shadow-sm text-ink-900 dark:bg-ink-700 dark:text-white' : 'text-ink-500 dark:text-ink-400'"
                                    class="rounded-full px-3.5 py-1.5 text-xs font-bold transition">Sisa Material</button>
                        </div>
                        <p class="text-xs text-ink-500 dark:text-ink-400" x-show="packageInfo?.name">
                            Paket: <span class="font-semibold text-ink-700 dark:text-ink-200" x-text="packageInfo?.name"></span>
                        </p>
                        <p class="text-xs text-ink-400" x-show="!packageInfo?.name">Tanpa paket (tanpa harga)</p>
                    </div>

                    {{-- Loading --}}
                    <div x-show="loading" class="space-y-2">
                        <div class="h-4 w-32 animate-pulse rounded bg-ink-100 dark:bg-ink-800"></div>
                        <div class="space-y-2">
                            <template x-for="i in 3"><div class="h-20 animate-pulse rounded-xl bg-ink-50 dark:bg-ink-800/50"></div></template>
                        </div>
                    </div>

                    {{-- Error --}}
                    <div x-show="!loading && error" x-cloak class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                        <p class="font-bold">Gagal memuat laporan</p>
                        <p class="mt-1" x-text="error"></p>
                        <button type="button" @click="retry()" class="mt-3 rounded-lg bg-white px-3 py-1.5 text-xs font-bold text-red-700 border border-red-200 hover:bg-red-50">Coba lagi</button>
                    </div>

                    {{-- Empty --}}
                    <div x-show="!loading && !error && lines.length===0" x-cloak class="rounded-2xl border border-dashed border-ink-200 bg-ink-50/60 p-6 text-center dark:border-ink-700 dark:bg-ink-800/30">
                        <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-white dark:bg-ink-700">
                            <svg class="h-5 w-5 text-ink-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5"/></svg>
                        </div>
                        <p class="mt-3 text-sm font-bold text-ink-900 dark:text-white">Belum ada material submitted</p>
                        <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Teknisi belum merekap qty aktual (Step 5) untuk LOP ini.</p>
                    </div>

                    {{-- Summary tiles --}}
                    <div x-show="!loading && !error && lines.length>0" x-cloak class="grid grid-cols-2 gap-3">
                        <div class="rounded-xl border border-ink-100 bg-ink-50/60 p-3 dark:border-ink-800 dark:bg-ink-800/30">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-ink-500">Qty Plan</p>
                            <p class="mt-1 text-sm font-extrabold text-ink-900 dark:text-white" x-text="formatNumber(grand.qty)"></p>
                        </div>
                        <div class="rounded-xl border border-ink-100 bg-ink-50/60 p-3 dark:border-ink-800 dark:bg-ink-800/30">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-ink-500">Qty Actual</p>
                            <p class="mt-1 text-sm font-extrabold text-ink-900 dark:text-white" x-text="formatNumber(grand.qty_actual)"></p>
                        </div>
                        <div x-show="type==='sisa'" class="rounded-xl border border-amber-100 bg-amber-50/60 p-3 dark:border-amber-900/30 dark:bg-amber-950/20">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-amber-700 dark:text-amber-300">Sisa</p>
                            <p class="mt-1 text-sm font-extrabold text-amber-700 dark:text-amber-300" x-text="formatNumber(grand.sisa)"></p>
                        </div>
                        <div x-show="priced" class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3 dark:border-emerald-900/30 dark:bg-emerald-950/20">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300" x-text="type==='boq' ? 'Total Actual' : 'Nilai Sisa'"></p>
                            <p class="mt-1 text-sm font-extrabold text-emerald-700 dark:text-emerald-300" x-text="formatMoney(type==='boq' ? grand.total_actual : grand.nilai_sisa)"></p>
                        </div>
                    </div>

                    <p x-show="!loading && grand.not_recapped_count>0" class="text-xs text-amber-600 dark:text-amber-400"><span x-text="grand.not_recapped_count"></span> item belum direkap.</p>
                    <p x-show="!loading && grand.price_missing_count>0" class="text-xs text-red-600 dark:text-red-400"><span x-text="grand.price_missing_count"></span> designator belum ada harga KHS.</p>

                    {{-- Card list — tanpa tabel, tanpa scroll horizontal --}}
                    <div x-show="!loading && !error && lines.length>0" x-cloak class="space-y-2.5">
                        <template x-for="line in lines" :key="line.designator_id">
                            <div class="rounded-xl border border-ink-100 bg-white p-3 dark:border-ink-700 dark:bg-ink-900">
                                <div class="flex gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-mono text-xs font-bold text-ink-900 dark:text-white" x-text="line.designator_code"></p>
                                        <p class="mt-0.5 line-clamp-2 text-xs leading-5 text-ink-600 dark:text-ink-300" x-text="line.designator_name || '—'"></p>
                                        <p class="mt-1 inline-flex rounded-full bg-ink-100 px-2 py-0.5 text-[11px] font-semibold text-ink-600 dark:bg-ink-800 dark:text-ink-300" x-text="line.unit || '—'"></p>
                                    </div>
                                    <div class="shrink-0 text-right tabular-nums">
                                        <p class="text-xs"><span class="text-ink-400">Plan </span><span class="font-bold text-ink-900 dark:text-white" x-text="formatNumber(line.qty)"></span></p>
                                        <p class="mt-1 text-xs">
                                            <span class="text-ink-400">Actual </span>
                                            <span :class="line.qty_actual==null ? 'text-ink-400' : 'font-bold text-ink-900 dark:text-white'" x-text="line.qty_actual==null ? '—' : formatNumber(line.qty_actual)"></span>
                                            <span x-show="line.qty_actual==null" class="ml-1 rounded bg-ink-100 px-1.5 py-0.5 text-[10px] font-bold text-ink-500 dark:bg-ink-700">belum direkap</span>
                                        </p>
                                        <p x-show="type==='sisa'" class="mt-1 text-xs font-bold" :class="(line.sisa||0)>0 ? 'text-amber-600' : 'text-emerald-600'">
                                            Sisa <span x-text="formatNumber(line.sisa)"></span>
                                        </p>
                                        <p x-show="priced" class="mt-1 text-[11px] text-ink-500 dark:text-ink-400">
                                            <span x-show="line.price_missing">harga belum diset</span>
                                            <span x-show="!line.price_missing" x-text="'Rp ' + formatMoney(line.price).replace('Rp ','') + ' · ' + formatMoney(type==='boq' ? line.total_actual : line.nilai_sisa)"></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div class="flex justify-between rounded-xl bg-ink-900 px-4 py-3 text-xs font-bold text-white dark:bg-white dark:text-ink-900">
                            <span>Subtotal</span>
                            <span class="tabular-nums" x-text="formatNumber(grand.qty) + ' · ' + formatNumber(grand.qty_actual) + (type==='sisa' ? ' · ' + formatNumber(grand.sisa) : '')"></span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Tab: Tiket & Jaringan --}}
            <div x-show="tab==='tiket'" x-transition class="space-y-5">
                @if (filled($lop->ticket_summary))
                    <div>
                        <p class="mb-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">Ringkasan tiket</p>
                        <pre class="overflow-x-auto whitespace-pre-wrap rounded-2xl border border-ink-100 bg-ink-50/60 p-4 font-mono text-xs leading-6 text-ink-700 dark:border-ink-800 dark:bg-ink-800/40 dark:text-ink-100">{{ $lop->ticket_summary }}</pre>
                    </div>
                @endif

                @php($datek = $lop->datek)
                @if (filled($datek))
                    <div>
                        <p class="mb-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">Datek terdampak</p>
                        <div class="space-y-2.5 rounded-2xl border border-ink-100 p-4 dark:border-ink-800">
                            @foreach (['ODC' => $datek['odc'] ?? [], 'ODP' => $datek['odp'] ?? [], 'Kabel' => $datek['kabel'] ?? [], 'IP' => $datek['ip'] ?? []] as $label => $items)
                                @if (! empty($items))
                                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1.5">
                                        <span class="w-10 shrink-0 text-xs text-ink-400">{{ $label }}</span>
                                        @foreach ($items as $item)
                                            <span class="rounded-md bg-ink-100 px-2 py-0.5 font-mono text-xs text-ink-700 dark:bg-ink-800 dark:text-ink-100">{{ $item }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                            @if (! empty($datek['gpon']))
                                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1.5">
                                    <span class="w-10 shrink-0 text-xs text-ink-400">GPON</span>
                                    @foreach ($datek['gpon'] as $g)
                                        <span class="rounded-md bg-ink-100 px-2 py-0.5 font-mono text-xs text-ink-700 dark:bg-ink-800 dark:text-ink-100">{{ $g['name'] ?? '—' }}@if (! empty($g['ip'])) &middot; {{ $g['ip'] }}@endif @if (! empty($g['ports'])) &middot; port {{ implode(', ', $g['ports']) }}@endif</span>
                                    @endforeach
                                </div>
                            @endif
                            @php($meta = collect(['OLT terdampak' => ! empty($datek['olt']) ? 'Ya' : null, 'RCA' => $datek['rca'] ?? null, 'EST' => $datek['est'] ?? null, 'PIC' => trim(($datek['pic']['nama'] ?? '').' '.($datek['pic']['telp'] ?? '')) ?: null])->filter())
                            @if ($meta->isNotEmpty())
                                <div class="grid gap-x-4 gap-y-1 border-t border-ink-100 pt-2.5 text-xs sm:grid-cols-2 dark:border-ink-800">
                                    @foreach ($meta as $label => $value)
                                        <div class="flex justify-between gap-3">
                                            <span class="text-ink-400">{{ $label }}</span>
                                            <span class="text-right font-medium text-ink-700 dark:text-ink-100">{{ $value }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @if (!filled($lop->ticket_summary) && !filled($datek))
                    <div class="rounded-2xl border border-dashed border-ink-200 bg-ink-50/60 p-6 text-center text-sm text-ink-500 dark:border-ink-700 dark:bg-ink-800/30">
                        Tidak ada data tiket / jaringan untuk LOP ini.
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-modal>
