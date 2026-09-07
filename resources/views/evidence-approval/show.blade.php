@extends('layouts.app')

@section('title', 'Review Evidence')

@section('content')
@php
    $mime = data_get($evidence->metadata, 'mime', '');
    $fileName = data_get($evidence->metadata, 'original_name', basename($evidence->file_path));
    $fileSize = data_get($evidence->metadata, 'size', 0);
    $fileUrl = $evidence->url();
    $isImage = str_starts_with($mime, 'image/') || in_array(strtolower(pathinfo($evidence->file_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp']);
@endphp

<div class="mx-auto max-w-7xl space-y-6">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div><a href="{{ route('evidence-approval.index') }}" class="text-xs font-bold text-ink-500 hover:text-brand-600">← Kembali ke approval</a><p class="mt-4 text-xs font-extrabold uppercase tracking-[.16em] text-brand-600">{{ $evidence->lop->incident }}</p><h1 class="mt-1 text-2xl font-extrabold text-ink-900 dark:text-white">Review Evidence</h1><p class="mt-1 text-sm text-ink-500">{{ $evidence->lop->nama_lop }}</p></div>
        <x-badge :variant="$evidence->status->badgeVariant()" class="!px-3 !py-1">{{ $evidence->status->label() }}</x-badge>
    </header>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,.55fr)]">
        <section class="overflow-hidden rounded-3xl border border-ink-100 bg-white shadow-sm dark:border-ink-800 dark:bg-ink-900">
            <div class="flex items-center justify-between gap-3 border-b border-ink-100 px-5 py-4 dark:border-ink-800"><div><h2 class="text-sm font-extrabold">Preview file</h2><p class="mt-1 max-w-md truncate text-xs text-ink-400">{{ $fileName }}</p></div><a href="{{ $fileUrl }}" target="_blank" class="rounded-xl border border-ink-200 px-3 py-2 text-xs font-bold dark:border-ink-700">Buka asli</a></div>
            <div class="bg-ink-950">@if ($isImage)<img src="{{ $fileUrl }}" alt="{{ $fileName }}" loading="lazy" decoding="async" class="mx-auto max-h-[68vh] w-full object-contain">@else<div class="grid min-h-[420px] place-items-center text-center text-white"><div><p class="text-6xl font-extrabold text-brand-400">PDF</p><p class="mt-3 text-sm text-ink-300">Preview PDF dibuka pada tab baru.</p><a href="{{ $fileUrl }}" target="_blank" class="mt-5 inline-flex rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-ink-900">Buka dokumen</a></div></div>@endif</div>
        </section>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900">
                <h2 class="text-sm font-extrabold">Informasi Evidence</h2>
                <dl class="mt-4 space-y-3 text-xs">
                    @foreach ([
                        ['Kategori', $evidence->category?->label() ?? $evidence->step->label()],
                        ['Step / Tipe', $evidence->step->label().' · '.$evidence->type->label()],
                        ['Designator', $evidence->designator ? $evidence->designator->code.' — '.$evidence->designator->item_name : 'Evidence global'],
                        ['Teknisi', $evidence->uploader?->name ?? '—'],
                        ['Waktu upload', $evidence->created_at->format('d M Y H:i')],
                        ['Ukuran', $fileSize ? number_format($fileSize / 1024, 0).' KB' : '—'],
                    ] as [$label, $value])
                        <div class="flex items-start justify-between gap-4 border-b border-ink-100 pb-3 last:border-0 last:pb-0 dark:border-ink-800"><dt class="shrink-0 text-ink-400">{{ $label }}</dt><dd class="text-right font-bold text-ink-800 dark:text-ink-100">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                @if ($evidence->note)<div class="mt-4 rounded-xl bg-ink-50 p-3 dark:bg-ink-800"><p class="text-[10px] font-bold uppercase text-ink-400">Catatan Teknisi</p><p class="mt-1 text-xs leading-5">{{ $evidence->note }}</p></div>@endif
            </section>

            @if ($evidence->status === \App\Enums\EvidenceStatus::PENDING)
                <section class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900">
                    <h2 class="text-sm font-extrabold">Keputusan Review</h2><p class="mt-1 text-xs leading-5 text-ink-500">Pastikan objek, kualitas foto, dan kesesuaian designator sudah benar.</p>
                    <button type="button" onclick="document.getElementById('approve-evidence').showModal()" class="mt-4 min-h-11 w-full rounded-xl bg-emerald-600 text-sm font-extrabold text-white shadow-lg shadow-emerald-600/20">Approve Evidence</button>
                    <button type="button" onclick="document.getElementById('reject-evidence').showModal()" class="mt-3 min-h-11 w-full rounded-xl border border-brand-200 text-sm font-extrabold text-brand-600 dark:border-brand-800">Reject Evidence</button>
                </section>
            @else
                <section class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900"><div class="flex items-center justify-between"><h2 class="text-sm font-extrabold">Hasil Review</h2><x-badge :variant="$evidence->status->badgeVariant()">{{ $evidence->status->label() }}</x-badge></div>@if($evidence->review_note)<div class="mt-4 rounded-xl bg-brand-50 p-3 text-xs leading-5 text-brand-700 dark:bg-brand-900/20 dark:text-brand-300">{{ $evidence->review_note }}</div>@endif<p class="mt-3 text-[11px] text-ink-400">{{ $evidence->reviewer?->name }} · {{ $evidence->reviewed_at?->format('d M Y H:i') }}</p></section>
            @endif
        </aside>
    </div>

    @if ($evidence->status === \App\Enums\EvidenceStatus::PENDING)
        <x-modal id="approve-evidence" title="Approve Evidence">
            <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/30">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.25 4.25L19 7"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-extrabold text-emerald-900 dark:text-emerald-100">Setujui evidence ini?</p>
                    <p class="mt-1 text-xs leading-5 text-emerald-800/80 dark:text-emerald-200/80">Evidence <span class="font-bold">{{ $fileName }}</span> akan ditandai Approved. Keputusan masih dapat direset selama LOP belum selesai.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('evidence-approval.approve', $evidence) }}" class="mt-5 flex gap-2">
                @csrf
                <button type="button" onclick="document.getElementById('approve-evidence').close()" class="min-h-11 flex-1 rounded-xl border border-ink-200 text-sm font-bold dark:border-ink-700">Batal</button>
                <button class="min-h-11 flex-1 rounded-xl bg-emerald-600 text-sm font-extrabold text-white shadow-lg shadow-emerald-600/20 hover:bg-emerald-700">Ya, Approve</button>
            </form>
        </x-modal>

        <x-modal id="reject-evidence" title="Reject Evidence">
            <form method="POST" action="{{ route('evidence-approval.reject', $evidence) }}">@csrf<div class="rounded-xl bg-brand-50 p-3 text-xs leading-5 text-brand-700 dark:bg-brand-900/20 dark:text-brand-300">Alasan akan langsung dikirim kepada teknisi dan ditampilkan pada evidence yang perlu diperbaiki.</div><label class="mt-4 block text-xs font-bold">Alasan penolakan</label><textarea name="review_note" required rows="4" placeholder="Contoh: foto terlalu gelap, objek utama tidak terlihat..." class="mt-2 w-full rounded-xl border border-ink-200 bg-white p-3 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800"></textarea><div class="mt-4 flex gap-2"><button type="button" onclick="document.getElementById('reject-evidence').close()" class="min-h-11 flex-1 rounded-xl border border-ink-200 text-sm font-bold dark:border-ink-700">Batal</button><button class="min-h-11 flex-1 rounded-xl bg-brand-600 text-sm font-extrabold text-white">Reject & kirim</button></div></form>
        </x-modal>
    @endif
</div>
@endsection
