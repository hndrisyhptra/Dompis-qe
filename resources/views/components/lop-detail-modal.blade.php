@props(['id', 'lop'])

@php
    $summary = $lop->progress_summary;

    // Konteks cepat di bawah judul.
    $chips = array_values(array_filter([
        $lop->program_type->label(),
        $lop->branch ?: null,
        $lop->budget_type?->label(),
    ]));

    // Rincian record - satu daftar yang bisa dipindai, bukan grid kotak.
    $details = [
        'STO' => $lop->sto ?: '—',
        'Area' => $lop->area ? 'Area '.$lop->area : '—',
        'Segmen' => $lop->segment?->label() ?? '—',
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
@endphp

<x-modal :id="$id" title="Detail LOP" size="lg">
    <div class="space-y-6">

        {{-- Identitas + status + konteks --}}
        <div>
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[.16em] text-brand-600 dark:text-brand-400">{{ $lop->incident }}</p>
                    <h3 class="mt-1.5 text-lg font-extrabold leading-snug text-ink-900 dark:text-white">{{ $lop->nama_lop }}</h3>
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

            <p class="mt-3 text-xs text-ink-500 dark:text-ink-400">
                Dibuat oleh {{ $lop->creator?->name ?? '—' }} &middot; {{ $lop->created_at->format('d M Y, H:i') }}
            </p>
        </div>

        {{-- Progress --}}
        <div class="rounded-2xl border border-ink-100 bg-ink-50/60 p-4 dark:border-ink-800 dark:bg-ink-800/40">
            <div class="flex items-end justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-ink-900 dark:text-white">Progress pekerjaan</p>
                    <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">
                        {{ $summary['completed_steps'] }}/{{ $summary['total_steps'] }} step &middot; {{ $summary['evidence_count'] }} evidence &middot; {{ $summary['review_label'] }}
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

        {{-- Rincian --}}
        <div>
            <p class="mb-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">Rincian</p>
            <dl class="overflow-hidden rounded-2xl border border-ink-100 dark:border-ink-800">
                @foreach ($details as $label => $value)
                    <div class="flex items-baseline justify-between gap-4 px-4 py-2.5 {{ ! $loop->last ? 'border-b border-ink-100 dark:border-ink-800' : '' }} {{ $loop->index % 2 ? 'bg-ink-50/40 dark:bg-ink-800/30' : '' }}">
                        <dt class="shrink-0 text-xs text-ink-500 dark:text-ink-400">{{ $label }}</dt>
                        <dd class="min-w-0 break-words text-right text-sm font-semibold text-ink-800 dark:text-ink-100">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- Deskripsi --}}
        <div>
            <p class="mb-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">Deskripsi pekerjaan</p>
            <p class="whitespace-pre-line text-sm leading-6 text-ink-700 dark:text-ink-100">{{ $lop->job_description ?: 'Belum ada deskripsi pekerjaan.' }}</p>
        </div>

        {{-- Rekap material --}}
        @php $materialItems = $lop->materialReservation?->items ?? collect(); @endphp
        @if ($materialItems->isNotEmpty())
            <div>
                <p class="mb-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">Material</p>
                <dl class="overflow-hidden rounded-2xl border border-ink-100 dark:border-ink-800">
                    @foreach ($materialItems as $item)
                        @php $sisa = $item->sisa(); @endphp
                        <div class="flex items-baseline justify-between gap-4 px-4 py-2.5 {{ ! $loop->last ? 'border-b border-ink-100 dark:border-ink-800' : '' }} {{ $loop->index % 2 ? 'bg-ink-50/40 dark:bg-ink-800/30' : '' }}">
                            <dt class="min-w-0"><span class="text-sm font-semibold text-ink-800 dark:text-ink-100">{{ $item->designator?->code ?? '—' }}</span><span class="ml-2 text-xs text-ink-400">{{ $item->designator?->item_name }}</span></dt>
                            <dd class="shrink-0 text-right text-xs">
                                <span class="text-ink-500 dark:text-ink-400">Resv {{ (float) $item->qty }}{{ $item->qty_actual !== null ? ' · Pakai '.(float) $item->qty_actual : '' }} {{ $item->designator?->unit }}</span>
                                @if ($item->qty_actual === null)
                                    <span class="ml-1 rounded bg-ink-100 px-1.5 py-0.5 font-bold text-ink-500 dark:bg-ink-700 dark:text-ink-300">belum direkap</span>
                                @elseif ($sisa > 0)
                                    <span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 font-bold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">sisa {{ $sisa }}</span>
                                @else
                                    <span class="ml-1 rounded bg-emerald-50 px-1.5 py-0.5 font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">penuh</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif

        {{-- Ringkasan tiket --}}
        @if (filled($lop->ticket_summary))
            <div>
                <p class="mb-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">Ringkasan tiket</p>
                <pre class="overflow-x-auto whitespace-pre-wrap rounded-2xl border border-ink-100 bg-ink-50/60 p-4 font-mono text-xs leading-6 text-ink-700 dark:border-ink-800 dark:bg-ink-800/40 dark:text-ink-100">{{ $lop->ticket_summary }}</pre>
            </div>
        @endif

        {{-- Datek terdampak --}}
        @php($datek = $lop->datek)
        @if (filled($datek))
            <div>
                <p class="mb-1.5 text-xs font-semibold text-ink-500 dark:text-ink-400">Datek terdampak</p>
                <div class="space-y-2.5 rounded-2xl border border-ink-100 p-4 dark:border-ink-800">
                    @foreach ([
                        'ODC' => $datek['odc'] ?? [],
                        'ODP' => $datek['odp'] ?? [],
                        'Kabel' => $datek['kabel'] ?? [],
                        'IP' => $datek['ip'] ?? [],
                    ] as $label => $items)
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

                    @php($meta = collect([
                        'OLT terdampak' => ! empty($datek['olt']) ? 'Ya' : null,
                        'RCA' => $datek['rca'] ?? null,
                        'EST' => $datek['est'] ?? null,
                        'PIC' => trim(($datek['pic']['nama'] ?? '').' '.($datek['pic']['telp'] ?? '')) ?: null,
                    ])->filter())
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
    </div>
</x-modal>
