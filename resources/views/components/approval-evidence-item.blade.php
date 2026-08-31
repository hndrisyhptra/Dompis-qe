@props(['evidence'])

@php
    $mime = data_get($evidence->metadata, 'mime', '');
    $fileName = data_get($evidence->metadata, 'original_name', basename($evidence->file_path));
    $fileSize = data_get($evidence->metadata, 'size', 0);
    $fileUrl = \Illuminate\Support\Facades\Storage::url($evidence->file_path);
    $isImage = str_starts_with($mime, 'image/') || in_array(strtolower(pathinfo($evidence->file_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']);
    $statusBorder = match ($evidence->status) {
        \App\Enums\EvidenceStatus::APPROVED => 'border-emerald-200 dark:border-emerald-800',
        \App\Enums\EvidenceStatus::REJECTED => 'border-brand-200 dark:border-brand-800',
        default => 'border-amber-200 dark:border-amber-800',
    };
@endphp

<article x-data="{ open: false, rejectOpen: false }" class="overflow-hidden rounded-2xl border {{ $statusBorder }} bg-white shadow-sm dark:bg-ink-900">
    <button type="button" @click="open = !open" :aria-expanded="open" class="flex w-full items-center gap-3 p-3 text-left transition hover:bg-ink-50 dark:hover:bg-ink-800/50">
        <span class="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-ink-950">@if($isImage)<img src="{{ $fileUrl }}" alt="{{ $fileName }}" class="h-full w-full object-cover">@else<span class="grid h-full place-items-center text-xs font-extrabold text-brand-400">PDF</span>@endif</span>
        <span class="min-w-0 flex-1"><span class="flex flex-wrap items-center gap-2"><span class="text-sm font-extrabold text-ink-900 dark:text-white">{{ $evidence->category?->label() ?? $evidence->step->label() }}</span><x-badge :variant="$evidence->status->badgeVariant()">{{ $evidence->status->label() }}</x-badge></span><span class="mt-1 block truncate text-xs text-ink-500">{{ $evidence->designator ? $evidence->designator->code.' · '.$evidence->designator->item_name : 'Evidence global' }}</span><span class="mt-1 block truncate text-[10px] text-ink-400">{{ $fileName }} · {{ $evidence->created_at->format('d M Y H:i') }}</span></span>
        <svg class="h-5 w-5 shrink-0 text-ink-400 transition-transform" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25L12 15.75 4.5 8.25"/></svg>
    </button>

    <div x-show="open" x-cloak x-transition.opacity class="border-t border-ink-100 p-4 dark:border-ink-800">
        <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_280px]">
            <a href="{{ $fileUrl }}" target="_blank" class="overflow-hidden rounded-2xl bg-ink-950">@if($isImage)<img src="{{ $fileUrl }}" alt="{{ $fileName }}" class="max-h-[520px] w-full object-contain">@else<span class="grid min-h-64 place-items-center text-center text-white"><span><span class="block text-5xl font-extrabold text-brand-400">PDF</span><span class="mt-3 block text-xs">Tap untuk membuka dokumen</span></span></span>@endif</a>
            <div class="space-y-3">
                <div class="rounded-xl bg-ink-50 p-3 text-xs dark:bg-ink-800"><div class="flex justify-between gap-3"><span class="text-ink-400">Teknisi</span><strong class="text-right">{{ $evidence->uploader?->name ?? '—' }}</strong></div><div class="mt-2 flex justify-between gap-3"><span class="text-ink-400">Ukuran</span><strong>{{ $fileSize ? number_format($fileSize / 1024, 0).' KB' : '—' }}</strong></div>@if($evidence->note)<div class="mt-3 border-t border-ink-200 pt-3 dark:border-ink-700"><span class="text-ink-400">Catatan teknisi</span><p class="mt-1 leading-5">{{ $evidence->note }}</p></div>@endif</div>

                @if ($evidence->status === \App\Enums\EvidenceStatus::PENDING)
                    @can('approve', $evidence)
                        <form method="POST" action="{{ route('evidence-approval.approve', $evidence) }}" onsubmit="return confirm('Approve evidence ini?');">@csrf<button class="min-h-11 w-full rounded-xl bg-emerald-600 text-sm font-extrabold text-white shadow-lg shadow-emerald-600/15">Approve Evidence</button></form>
                        <button type="button" @click="rejectOpen = !rejectOpen" class="min-h-11 w-full rounded-xl border border-brand-200 text-sm font-extrabold text-brand-600 dark:border-brand-800">Reject Evidence</button>
                        <form x-show="rejectOpen" x-cloak x-transition method="POST" action="{{ route('evidence-approval.reject', $evidence) }}" class="rounded-xl bg-brand-50 p-3 dark:bg-brand-900/10">@csrf<label class="text-xs font-bold text-brand-700 dark:text-brand-300">Alasan penolakan</label><textarea name="review_note" required rows="3" placeholder="Jelaskan bagian yang perlu diperbaiki..." class="mt-2 w-full rounded-lg border border-brand-200 bg-white p-3 text-xs outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-brand-800 dark:bg-ink-900"></textarea><button class="mt-2 min-h-10 w-full rounded-lg bg-brand-600 text-xs font-extrabold text-white">Kirim Reject</button></form>
                    @endcan
                @else
                    <div class="rounded-xl border border-ink-100 p-3 dark:border-ink-700"><div class="flex items-center justify-between"><span class="text-xs font-bold">Hasil Review</span><x-badge :variant="$evidence->status->badgeVariant()">{{ $evidence->status->label() }}</x-badge></div>@if($evidence->review_note)<p class="mt-3 text-xs leading-5 text-brand-700 dark:text-brand-300">{{ $evidence->review_note }}</p>@endif<p class="mt-2 text-[10px] text-ink-400">{{ $evidence->reviewer?->name }} · {{ $evidence->reviewed_at?->format('d M Y H:i') }}</p></div>
                @endif

                <a href="{{ route('evidence-approval.show', $evidence) }}" class="grid min-h-10 place-items-center rounded-xl border border-ink-200 text-xs font-bold text-ink-600 dark:border-ink-700 dark:text-ink-300">Buka detail evidence</a>
            </div>
        </div>
    </div>
</article>
