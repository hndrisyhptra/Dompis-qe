@props(['evidence'])

@php
    $mime = data_get($evidence->metadata, 'mime', '');
    $fileName = data_get($evidence->metadata, 'original_name', basename($evidence->file_path));
    $fileSize = data_get($evidence->metadata, 'size', 0);
    $fileUrl = $evidence->url();
    $thumbUrl = $evidence->thumbUrl();
    $isImage = str_starts_with($mime, 'image/') || in_array(strtolower(pathinfo($evidence->file_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp']);
    $statusBorder = match ($evidence->status) {
        \App\Enums\EvidenceStatus::APPROVED => 'border-emerald-200 dark:border-emerald-800',
        \App\Enums\EvidenceStatus::REJECTED => 'border-brand-200 dark:border-brand-800',
        default => 'border-amber-200 dark:border-amber-800',
    };
@endphp

<article class="overflow-hidden rounded-xl border {{ $statusBorder }} bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:bg-ink-900">
    <button type="button" onclick="document.getElementById('evidence-preview-{{ $evidence->id_evidence }}').showModal()" class="group relative block aspect-[4/3] w-full overflow-hidden bg-ink-950 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500/50">
        @if ($isImage)
            <img src="{{ $thumbUrl }}" alt="{{ $fileName }}" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
        @else
            <span class="grid h-full place-items-center text-center"><span><span class="block text-3xl font-extrabold text-brand-400">PDF</span><span class="mt-1 block text-[10px] text-ink-300">Preview dokumen</span></span></span>
        @endif
        <span class="absolute left-2.5 top-2.5"><x-badge :variant="$evidence->status->badgeVariant()" class="shadow-sm">{{ $evidence->status->label() }}</x-badge></span>
        <span class="absolute bottom-2.5 right-2.5 inline-flex items-center gap-1.5 rounded-lg bg-ink-950/80 px-2.5 py-1.5 text-[10px] font-bold text-white">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="2.25"/></svg>
            Preview
        </span>
    </button>

    <div class="p-3">
        <p class="truncate text-xs font-extrabold text-ink-900 dark:text-white" title="{{ $fileName }}">{{ $fileName }}</p>
        <div class="mt-1.5 flex items-center justify-between gap-2 text-[10px] text-ink-400">
            <span class="truncate">{{ $evidence->uploader?->name ?? 'Teknisi —' }}</span>
            <span class="shrink-0">{{ $evidence->created_at->format('d M, H:i') }}</span>
        </div>

        @if ($evidence->status === \App\Enums\EvidenceStatus::REJECTED && $evidence->review_note)
            <p class="mt-2 line-clamp-2 rounded-lg bg-brand-50 p-2 text-[10px] leading-4 text-brand-700 dark:bg-brand-900/20 dark:text-brand-300">{{ $evidence->review_note }}</p>
        @endif

        <div class="mt-3 grid grid-cols-2 gap-2">
            @if ($evidence->status === \App\Enums\EvidenceStatus::PENDING)
                @can('approve', $evidence)
                    <button type="button" onclick="document.getElementById('evidence-approve-{{ $evidence->id_evidence }}').showModal()" class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-2 text-[10px] font-extrabold text-white transition hover:bg-emerald-700">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.25 4.25L19 7"/></svg> Approve
                    </button>
                    <button type="button" onclick="document.getElementById('evidence-reject-{{ $evidence->id_evidence }}').showModal()" class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg border border-brand-200 px-2 text-[10px] font-extrabold text-brand-700 transition hover:bg-brand-50 dark:border-brand-800 dark:text-brand-300 dark:hover:bg-brand-900/20">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="m7 7 10 10M17 7 7 17"/></svg> Reject
                    </button>
                @endcan
            @else
                @can('resetReview', $evidence)
                    <button type="button" onclick="document.getElementById('evidence-reset-{{ $evidence->id_evidence }}').showModal()" class="col-span-2 inline-flex min-h-9 w-full items-center justify-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2 text-[10px] font-extrabold text-amber-700 transition hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.02 9.35h5.25V4.1M20.1 8.1A9 9 0 1 0 21 12"/></svg> Reset Review
                    </button>
                @endcan
            @endif

            <button type="button" onclick="document.getElementById('evidence-detail-{{ $evidence->id_evidence }}').showModal()" class="col-span-2 inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg border border-ink-200 text-[10px] font-bold text-ink-600 transition hover:bg-ink-50 dark:border-ink-700 dark:text-ink-300 dark:hover:bg-ink-800">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25h.008v.008h-.008v-.008Zm.75 8.25a7.5 7.5 0 1 0 0-15 7.5 7.5 0 0 0 0 15ZM12 10.5V15"/></svg> Detail Evidence
            </button>
        </div>
    </div>
</article>

<x-modal id="evidence-preview-{{ $evidence->id_evidence }}" title="Preview · {{ $fileName }}" size="xl">
    <div class="overflow-hidden rounded-2xl bg-ink-950">
        @if ($isImage)
            <img src="{{ $fileUrl }}" alt="{{ $fileName }}" loading="lazy" decoding="async" class="mx-auto max-h-[72vh] w-full object-contain">
        @else
            <div class="grid min-h-80 place-items-center text-center text-white"><div><p class="text-5xl font-extrabold text-brand-400">PDF</p><p class="mt-2 text-xs text-ink-300">Dokumen akan dibuka pada tab baru.</p><a href="{{ $fileUrl }}" target="_blank" class="mt-4 inline-flex rounded-xl bg-white px-4 py-2 text-xs font-bold text-ink-900">Buka dokumen</a></div></div>
        @endif
    </div>
    <div class="mt-3 flex items-center justify-between gap-3"><p class="truncate text-xs text-ink-500">{{ $evidence->designator ? $evidence->designator->code.' · '.$evidence->designator->item_name : ($evidence->category?->label() ?? 'Evidence global') }}</p><a href="{{ $fileUrl }}" target="_blank" class="shrink-0 text-xs font-bold text-brand-600 dark:text-brand-400">Buka file asli ↗</a></div>
</x-modal>

@php
    $lat = data_get($evidence->metadata, 'latitude');
    $lng = data_get($evidence->metadata, 'longitude');
    $hasGps = is_numeric($lat) && is_numeric($lng);
    $rows = [
        ['designator', 'Designator', $evidence->designator ? $evidence->designator->code.' — '.$evidence->designator->item_name : 'Evidence global'],
        ['step', 'Step / Tipe', $evidence->step->label().' · '.$evidence->type->label()],
        ['user', 'Teknisi', $evidence->uploader?->name ?? '—'],
        ['clock', 'Waktu upload', $evidence->created_at->format('d M Y · H:i')],
        ['file', 'Ukuran file', $fileSize ? number_format($fileSize / 1024, 0).' KB' : '—'],
    ];
@endphp
<x-modal id="evidence-detail-{{ $evidence->id_evidence }}" title="Detail Evidence" size="lg">
    <div class="space-y-5">
        {{-- Hero: gambar / dokumen --}}
        <div class="overflow-hidden rounded-2xl border border-ink-100 dark:border-ink-800">
            <button type="button"
                    onclick="document.getElementById('evidence-detail-{{ $evidence->id_evidence }}').close(); document.getElementById('evidence-preview-{{ $evidence->id_evidence }}').showModal();"
                    class="group relative block aspect-video w-full overflow-hidden bg-ink-950 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500/60">
                @if ($isImage)
                    <img src="{{ $thumbUrl }}" alt="{{ $fileName }}" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                @else
                    <span class="grid h-full place-items-center text-center"><span><span class="block text-4xl font-extrabold text-brand-400">PDF</span><span class="mt-1 block text-[11px] text-ink-300">Ketuk untuk membuka</span></span></span>
                @endif
                <span class="absolute left-3 top-3"><x-badge :variant="$evidence->status->badgeVariant()" class="shadow-sm">{{ $evidence->status->label() }}</x-badge></span>
                <span class="absolute bottom-3 right-3 inline-flex items-center gap-1.5 rounded-lg bg-ink-950/80 px-2.5 py-1.5 text-[11px] font-bold text-white backdrop-blur-sm">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="2.25"/></svg>
                    Perbesar
                </span>
            </button>
            <div class="flex items-center justify-between gap-3 bg-white px-4 py-3 dark:bg-ink-900">
                <div class="min-w-0">
                    <p class="truncate text-sm font-extrabold text-ink-900 dark:text-white" title="{{ $fileName }}">{{ $fileName }}</p>
                    <p class="mt-0.5 text-xs text-ink-500">{{ $evidence->category?->label() ?? $evidence->step->label() }}</p>
                </div>
                <a href="{{ $fileUrl }}" target="_blank" rel="noopener" class="shrink-0 whitespace-nowrap rounded-lg border border-ink-200 px-2.5 py-1.5 text-[11px] font-bold text-ink-600 transition hover:bg-ink-50 dark:border-ink-700 dark:text-ink-300 dark:hover:bg-ink-800">Buka asli ↗</a>
            </div>
        </div>

        {{-- Informasi --}}
        <div>
            <p class="px-1 text-[10px] font-extrabold uppercase tracking-[.14em] text-ink-400">Informasi</p>
            <dl class="mt-2 divide-y divide-ink-100 overflow-hidden rounded-2xl border border-ink-100 dark:divide-ink-800 dark:border-ink-800">
                @foreach ($rows as [$icon, $label, $value])
                    <div class="flex items-start gap-3 bg-white p-3.5 dark:bg-ink-900">
                        <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-ink-50 text-ink-400 dark:bg-ink-800">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                @switch($icon)
                                    @case('designator')<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25A2.25 2.25 0 018.25 10.5H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25A2.25 2.25 0 0113.5 8.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>@break
                                    @case('step')<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/>@break
                                    @case('user')<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>@break
                                    @case('clock')<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>@break
                                    @case('file')<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>@break
                                @endswitch
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <dt class="text-[11px] font-semibold text-ink-400">{{ $label }}</dt>
                            <dd class="mt-0.5 break-words text-sm font-bold text-ink-800 dark:text-ink-100">{{ $value }}</dd>
                        </div>
                    </div>
                @endforeach
                @if ($hasGps)
                    <div class="flex items-start gap-3 bg-white p-3.5 dark:bg-ink-900">
                        <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-ink-50 text-ink-400 dark:bg-ink-800">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <dt class="text-[11px] font-semibold text-ink-400">Lokasi upload</dt>
                            <dd class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm font-bold text-ink-800 dark:text-ink-100">
                                <span>{{ number_format((float) $lat, 5) }}, {{ number_format((float) $lng, 5) }}</span>
                                <a href="https://maps.google.com/?q={{ $lat }},{{ $lng }}" target="_blank" rel="noopener" class="text-xs font-bold text-brand-600 dark:text-brand-400">Buka peta ↗</a>
                            </dd>
                        </div>
                    </div>
                @endif
            </dl>
        </div>

        {{-- Status review --}}
        <div>
            <p class="px-1 text-[10px] font-extrabold uppercase tracking-[.14em] text-ink-400">Status review</p>
            <div class="mt-2 rounded-2xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
                <div class="flex items-center gap-2.5">
                    <x-badge :variant="$evidence->status->badgeVariant()">{{ $evidence->status->label() }}</x-badge>
                    @if ($evidence->reviewer)
                        <span class="text-xs text-ink-500">oleh <span class="font-bold text-ink-700 dark:text-ink-200">{{ $evidence->reviewer->name }}</span></span>
                    @endif
                </div>
                <p class="mt-2 text-xs text-ink-500">
                    @if ($evidence->reviewed_at)
                        {{ $evidence->reviewed_at->format('d M Y · H:i') }}
                    @else
                        Belum direview — menunggu keputusan approver.
                    @endif
                </p>
            </div>
        </div>

        @if ($evidence->note)
            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900/60 dark:bg-blue-950/30">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-blue-600 dark:text-blue-400">Catatan Teknisi</p>
                <p class="mt-1.5 text-sm leading-6 text-blue-900 dark:text-blue-200">{{ $evidence->note }}</p>
            </div>
        @endif
        @if ($evidence->review_note)
            <div class="rounded-2xl border border-brand-200 bg-brand-50 p-4 dark:border-brand-900/60 dark:bg-brand-950/30">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-brand-600 dark:text-brand-400">Alasan Reject</p>
                <p class="mt-1.5 text-sm leading-6 text-brand-800 dark:text-brand-200">{{ $evidence->review_note }}</p>
            </div>
        @endif
    </div>
</x-modal>

@if ($evidence->status === \App\Enums\EvidenceStatus::PENDING)
    @can('approve', $evidence)
        <x-modal id="evidence-approve-{{ $evidence->id_evidence }}" title="Approve Evidence">
            <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 dark:border-emerald-900/60 dark:bg-emerald-950/30">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.25 4.25L19 7"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-extrabold text-emerald-900 dark:text-emerald-100">Setujui evidence ini?</p>
                    <p class="mt-1 text-xs leading-5 text-emerald-800/80 dark:text-emerald-200/80">Evidence akan ditandai <span class="font-bold">Approved</span>. Keputusan masih dapat direset selama LOP belum selesai.</p>
                </div>
            </div>
            <dl class="mt-4 space-y-2 text-xs">
                <div class="flex justify-between gap-4"><dt class="text-ink-400">File</dt><dd class="min-w-0 truncate text-right font-bold text-ink-800 dark:text-ink-100">{{ $fileName }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-400">Kategori</dt><dd class="text-right font-bold text-ink-800 dark:text-ink-100">{{ $evidence->category?->label() ?? $evidence->step->label() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-400">Designator</dt><dd class="text-right font-bold text-ink-800 dark:text-ink-100">{{ $evidence->designator ? $evidence->designator->code : 'Evidence global' }}</dd></div>
            </dl>
            <form method="POST" action="{{ route('evidence-approval.approve', $evidence) }}" class="mt-5 flex gap-2">
                @csrf
                <button type="button" onclick="document.getElementById('evidence-approve-{{ $evidence->id_evidence }}').close()" class="min-h-10 flex-1 rounded-xl border border-ink-200 text-xs font-bold dark:border-ink-700">Batal</button>
                <button class="min-h-10 flex-1 rounded-xl bg-emerald-600 text-xs font-extrabold text-white shadow-lg shadow-emerald-600/20 hover:bg-emerald-700">Ya, Approve</button>
            </form>
        </x-modal>
    @endcan

    @can('reject', $evidence)
        <x-modal id="evidence-reject-{{ $evidence->id_evidence }}" title="Reject Evidence">
            <form method="POST" action="{{ route('evidence-approval.reject', $evidence) }}">
                @csrf
                <div class="rounded-xl bg-brand-50 p-3 text-xs leading-5 text-brand-700 dark:bg-brand-900/20 dark:text-brand-300">Alasan reject akan dikirim kepada teknisi dan ditampilkan pada evidence yang harus diperbaiki.</div>
                <label for="review-note-{{ $evidence->id_evidence }}" class="mt-4 block text-xs font-bold">Alasan penolakan</label>
                <textarea id="review-note-{{ $evidence->id_evidence }}" name="review_note" required rows="4" placeholder="Contoh: foto terlalu gelap atau objek utama tidak terlihat..." class="mt-2 w-full rounded-xl border border-ink-200 bg-white p-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800"></textarea>
                <div class="mt-4 flex gap-2"><button type="button" onclick="document.getElementById('evidence-reject-{{ $evidence->id_evidence }}').close()" class="min-h-10 flex-1 rounded-xl border border-ink-200 text-xs font-bold dark:border-ink-700">Batal</button><button class="min-h-10 flex-1 rounded-xl bg-brand-600 text-xs font-extrabold text-white">Reject & Kirim</button></div>
            </form>
        </x-modal>
    @endcan
@else
    @can('resetReview', $evidence)
        <x-modal id="evidence-reset-{{ $evidence->id_evidence }}" title="Reset Review">
            <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3.5 dark:border-amber-900/60 dark:bg-amber-950/30">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-300">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.02 9.35h5.25V4.1M20.1 8.1A9 9 0 1 0 21 12"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-extrabold text-amber-900 dark:text-amber-100">Reset keputusan ke Pending?</p>
                    <p class="mt-1 text-xs leading-5 text-amber-800/80 dark:text-amber-200/80">Status <span class="font-bold">{{ $evidence->status->label() }}</span> akan dibatalkan dan evidence kembali menunggu review. Riwayat keputusan sebelumnya tetap tercatat pada audit.</p>
                </div>
            </div>
            <dl class="mt-4 space-y-2 text-xs">
                <div class="flex justify-between gap-4"><dt class="text-ink-400">File</dt><dd class="min-w-0 truncate text-right font-bold text-ink-800 dark:text-ink-100">{{ $fileName }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-400">Direview oleh</dt><dd class="text-right font-bold text-ink-800 dark:text-ink-100">{{ $evidence->reviewer?->name ?? '—' }}</dd></div>
            </dl>
            <form method="POST" action="{{ route('evidence-approval.reset', $evidence) }}" class="mt-5 flex gap-2">
                @csrf
                <button type="button" onclick="document.getElementById('evidence-reset-{{ $evidence->id_evidence }}').close()" class="min-h-10 flex-1 rounded-xl border border-ink-200 text-xs font-bold dark:border-ink-700">Batal</button>
                <button class="min-h-10 flex-1 rounded-xl bg-amber-600 text-xs font-extrabold text-white shadow-lg shadow-amber-600/20 hover:bg-amber-700">Ya, Reset</button>
            </form>
        </x-modal>
    @endcan
@endif
