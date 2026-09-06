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
                    <form method="POST" action="{{ route('evidence-approval.approve', $evidence) }}" onsubmit="return confirm('Approve evidence ini?');">
                        @csrf
                        <button class="inline-flex min-h-9 w-full items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-2 text-[10px] font-extrabold text-white transition hover:bg-emerald-700">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.25 4.25L19 7"/></svg> Approve
                        </button>
                    </form>
                    <button type="button" onclick="document.getElementById('evidence-reject-{{ $evidence->id_evidence }}').showModal()" class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg border border-brand-200 px-2 text-[10px] font-extrabold text-brand-700 transition hover:bg-brand-50 dark:border-brand-800 dark:text-brand-300 dark:hover:bg-brand-900/20">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="m7 7 10 10M17 7 7 17"/></svg> Reject
                    </button>
                @endcan
            @else
                @can('resetReview', $evidence)
                    <form method="POST" action="{{ route('evidence-approval.reset', $evidence) }}" class="col-span-2" onsubmit="return confirm('Reset keputusan evidence ini ke Pending?');">
                        @csrf
                        <button class="inline-flex min-h-9 w-full items-center justify-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2 text-[10px] font-extrabold text-amber-700 transition hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.02 9.35h5.25V4.1M20.1 8.1A9 9 0 1 0 21 12"/></svg> Reset Review
                        </button>
                    </form>
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

<x-modal id="evidence-detail-{{ $evidence->id_evidence }}" title="Detail Evidence" size="lg">
    <div class="flex items-start gap-3 rounded-xl bg-ink-50 p-3 dark:bg-ink-800">
        <span class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-ink-950">@if($isImage)<img src="{{ $thumbUrl }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">@else<span class="grid h-full place-items-center text-[10px] font-bold text-brand-400">PDF</span>@endif</span>
        <div class="min-w-0"><p class="truncate text-sm font-extrabold text-ink-900 dark:text-white">{{ $fileName }}</p><p class="mt-1 text-xs text-ink-500">{{ $evidence->category?->label() ?? $evidence->step->label() }}</p><div class="mt-2"><x-badge :variant="$evidence->status->badgeVariant()">{{ $evidence->status->label() }}</x-badge></div></div>
    </div>
    <dl class="mt-4 grid gap-3 text-xs sm:grid-cols-2">
        @foreach ([
            ['Designator', $evidence->designator ? $evidence->designator->code.' — '.$evidence->designator->item_name : 'Evidence global'],
            ['Step / Tipe', $evidence->step->label().' · '.$evidence->type->label()],
            ['Teknisi', $evidence->uploader?->name ?? '—'],
            ['Waktu upload', $evidence->created_at->format('d M Y H:i')],
            ['Ukuran file', $fileSize ? number_format($fileSize / 1024, 0).' KB' : '—'],
            ['Reviewer', $evidence->reviewer?->name ?? 'Belum direview'],
        ] as [$label, $value])
            <div class="rounded-xl border border-ink-100 p-3 dark:border-ink-700"><dt class="text-[10px] font-bold uppercase tracking-wider text-ink-400">{{ $label }}</dt><dd class="mt-1.5 break-words font-bold text-ink-800 dark:text-ink-100">{{ $value }}</dd></div>
        @endforeach
    </dl>
    @if ($evidence->note)<div class="mt-3 rounded-xl bg-blue-50 p-3 text-xs leading-5 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300"><p class="text-[10px] font-bold uppercase tracking-wider">Catatan Teknisi</p><p class="mt-1">{{ $evidence->note }}</p></div>@endif
    @if ($evidence->review_note)<div class="mt-3 rounded-xl bg-brand-50 p-3 text-xs leading-5 text-brand-700 dark:bg-brand-900/20 dark:text-brand-300"><p class="text-[10px] font-bold uppercase tracking-wider">Alasan Reject</p><p class="mt-1">{{ $evidence->review_note }}</p></div>@endif
</x-modal>

@if ($evidence->status === \App\Enums\EvidenceStatus::PENDING)
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
@endif
