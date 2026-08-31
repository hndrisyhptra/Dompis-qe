@props(['id', 'lop'])

@php($summary = $lop->progress_summary)

<x-modal :id="$id" title="Detail LOP" size="lg">
    <div class="space-y-5">
        <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-ink-950 via-ink-900 to-brand-950 p-5 text-white">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-extrabold uppercase tracking-[.16em] text-brand-300">{{ $lop->incident }}</p>
                    <h3 class="mt-2 text-lg font-extrabold leading-6">{{ $lop->nama_lop }}</h3>
                    <p class="mt-2 text-xs text-ink-300">Dibuat oleh {{ $lop->creator?->name ?? '—' }} · {{ $lop->created_at->format('d M Y, H:i') }}</p>
                </div>
                <x-badge :variant="$lop->status_lop->badgeVariant()">{{ $lop->status_lop->label() }}</x-badge>
            </div>
            <div class="mt-5">
                <div class="mb-2 flex items-center justify-between text-xs"><span class="text-ink-300">Progress pekerjaan</span><strong>{{ $summary['percentage'] }}%</strong></div>
                <div class="h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-brand-400" style="width: {{ $summary['percentage'] }}%"></div></div>
                <p class="mt-2 text-[10px] text-ink-400">{{ $summary['completed_steps'] }} dari 4 step · {{ $summary['evidence_count'] }} evidence</p>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach ([
                ['STO', $lop->sto ?: '—'],
                ['Branch', $lop->branch ?: '—'],
                ['Area', $lop->area ? 'Area '.$lop->area : '—'],
                ['Segmen', $lop->segment?->label() ?? '—'],
                ['WBS', $lop->wbs_type->label()],
                ['Anggaran', $lop->budget_type?->label() ?? 'Tidak berlaku'],
                ['ID IHLD', $lop->ihld_id ?: 'Belum tersedia'],
                ['Review', $summary['review_label']],
                ['Update terakhir', $lop->updated_at->diffForHumans()],
            ] as [$label, $value])
                <div class="rounded-xl border border-ink-100 bg-ink-50 p-3 dark:border-ink-700 dark:bg-ink-800">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-ink-400">{{ $label }}</p>
                    <p class="mt-1.5 break-words text-sm font-bold text-ink-900 dark:text-white">{{ $value }}</p>
                </div>
            @endforeach
        </section>

        <section class="rounded-xl border border-ink-100 p-4 dark:border-ink-700">
            <p class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Deskripsi Pekerjaan</p>
            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-ink-700 dark:text-ink-200">{{ $lop->job_description ?: 'Belum ada deskripsi pekerjaan.' }}</p>
        </section>

        <section class="flex items-center gap-3 rounded-xl border border-blue-100 bg-blue-50 p-4 dark:border-blue-900/50 dark:bg-blue-950/30">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-blue-600 text-sm font-extrabold text-white">{{ mb_strtoupper(mb_substr($lop->activeAssignment?->technician?->name ?? '?', 0, 1)) }}</span>
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-300">Teknisi aktif</p>
                <p class="mt-1 truncate text-sm font-bold text-ink-900 dark:text-white">{{ $lop->activeAssignment?->technician?->name ?? 'Belum ditugaskan' }}</p>
                @if ($lop->activeAssignment)<p class="mt-1 text-xs text-ink-500">Ditugaskan {{ $lop->activeAssignment->assigned_at->diffForHumans() }}</p>@endif
            </div>
        </section>
    </div>
</x-modal>
