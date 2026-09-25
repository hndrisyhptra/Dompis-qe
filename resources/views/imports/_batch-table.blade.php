<section class="overflow-hidden rounded-2xl border border-ink-100 bg-white shadow-sm dark:border-ink-800 dark:bg-ink-900">
    <div class="flex items-center justify-between gap-4 border-b border-ink-100 px-5 py-4 dark:border-ink-800">
        <div>
            <p class="text-[10px] font-extrabold uppercase tracking-[.16em] text-brand-600 dark:text-brand-400">Aktivitas terakhir</p>
            <h2 class="mt-1 text-base font-extrabold text-ink-900 dark:text-white">History Upload</h2>
            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Lima file terakhir beserta uploader, progres, dan hasil proses.</p>
        </div>
        <span class="shrink-0 rounded-full bg-ink-50 px-3 py-1 text-[10px] font-bold text-ink-500 dark:bg-ink-800 dark:text-ink-300">5 terakhir</span>
    </div>

    <div class="hidden grid-cols-[minmax(0,2fr)_minmax(130px,.8fr)_minmax(180px,1fr)_auto] gap-4 border-b border-ink-100 bg-ink-50/70 px-5 py-2.5 text-[9px] font-extrabold uppercase tracking-wider text-ink-400 md:grid dark:border-ink-800 dark:bg-ink-800/50">
        <span>File &amp; Uploader</span><span>Status</span><span>Progres &amp; Result</span><span class="text-right">Aksi</span>
    </div>

    <div class="divide-y divide-ink-100 dark:divide-ink-800">
        @forelse ($batches as $batch)
            @php($finished = $batch->isFinished())
            <article class="grid gap-4 px-4 py-4 transition hover:bg-ink-50/60 md:grid-cols-[minmax(0,2fr)_minmax(130px,.8fr)_minmax(180px,1fr)_auto] md:items-center md:px-5 dark:hover:bg-ink-800/30" x-data="importBatchProgress({ statusUrl: @js(route('imports.status', $batch, false)), status: @js($batch->status), total: {{ (int) $batch->total_rows }}, success: {{ (int) $batch->success_rows }}, failed: {{ (int) $batch->failed_rows }}, processed: {{ $batch->processedRows() }}, percentage: {{ $batch->progressPercentage() }}, finished: {{ $finished ? 'true' : 'false' }} })" x-init="start()">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-950/30 dark:text-brand-300"><svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-5.25Z"/><path stroke-linecap="round" d="M9 15.75h6"/></svg></span>
                    <div class="min-w-0"><p class="truncate text-xs font-extrabold text-ink-900 dark:text-white" title="{{ $batch->original_name }}">{{ $batch->original_name }}</p><p class="mt-1 truncate text-[10px] text-ink-400"><span class="font-semibold text-ink-500 dark:text-ink-300">{{ $batch->uploader?->name ?? 'System' }}</span> · {{ $batch->created_at?->format('d M Y, H:i') }}</p></div>
                </div>

                <div class="flex items-center justify-between gap-3 md:block">
                    <span class="text-[9px] font-extrabold uppercase tracking-wide" :class="status === 'failed' ? 'text-red-600 dark:text-red-300' : (status === 'partial' ? 'text-amber-600 dark:text-amber-300' : (finished ? 'text-emerald-600 dark:text-emerald-300' : 'text-blue-600 dark:text-blue-300'))" x-text="statusLabel"></span>
                    <span class="text-[10px] text-ink-400 md:hidden" x-text="`${percentage}%`"></span>
                </div>

                <div>
                    <div class="mb-1.5 flex items-center justify-between gap-3 text-[10px]"><span class="truncate font-semibold text-ink-500 dark:text-ink-400" x-text="finished ? `${success} berhasil · ${failed} gagal` : `${processed}/${total || '—'} baris diproses`"></span><strong class="hidden tabular-nums text-ink-800 md:block dark:text-ink-200" x-text="`${percentage}%`"></strong></div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full rounded-full transition-all duration-500" :class="status === 'failed' ? 'bg-red-500' : (status === 'partial' ? 'bg-amber-500' : (finished ? 'bg-emerald-500' : 'bg-blue-500'))" :style="`width:${percentage}%`"></div></div>
                </div>

                <a href="{{ route('imports.show', $batch) }}" title="Lihat hasil {{ $batch->original_name }}" class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-xl border border-ink-200 px-3 text-[10px] font-extrabold text-ink-700 transition hover:border-brand-300 hover:text-brand-700 md:justify-self-end dark:border-ink-700 dark:text-ink-200">Hasil <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5.25 6.75 6.75L9 18.75"/></svg></a>
            </article>
        @empty
            <div class="px-6 py-12 text-center"><span class="mx-auto grid h-11 w-11 place-items-center rounded-xl bg-ink-50 text-ink-400 dark:bg-ink-800"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5h10.5a3.75 3.75 0 001.332-7.257A6 6 0 007.447 9.21 4.5 4.5 0 006.75 18z"/></svg></span><p class="mt-3 text-sm font-bold text-ink-700 dark:text-ink-200">Belum ada history upload</p><p class="mt-1 text-xs text-ink-400">File pertama yang diproses akan tampil di sini.</p></div>
        @endforelse
    </div>
</section>
