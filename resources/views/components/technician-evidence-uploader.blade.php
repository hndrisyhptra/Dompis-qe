@props(['lop', 'category', 'title', 'description', 'designatorId' => null, 'existing' => collect(), 'allowUpload' => true])

@php
    $startsExpanded = $existing->isEmpty();
    $pendingCount = $existing->filter(fn ($item) => $item->status === \App\Enums\EvidenceStatus::PENDING)->count();
    $approvedCount = $existing->filter(fn ($item) => $item->status === \App\Enums\EvidenceStatus::APPROVED)->count();
    $rejectedCount = $existing->filter(fn ($item) => $item->status === \App\Enums\EvidenceStatus::REJECTED)->count();
@endphp

<div x-data="evidenceUploader({
        expanded: @js($startsExpanded),
        endpoint: @js($allowUpload ? route('technician.projects.evidence.file', $lop) : null),
        category: @js($category),
        type: 'PHOTO',
        designatorId: @js($designatorId),
     })" @keydown.escape.window="closePreview()"
     class="overflow-hidden rounded-2xl border border-ink-100 bg-white dark:border-ink-800 dark:bg-ink-900">
    <button type="button" @click="expanded = !expanded" :aria-expanded="expanded"
            class="flex w-full items-center justify-between gap-3 p-4 text-left transition active:bg-ink-50 dark:active:bg-ink-800/60">
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold text-ink-900 dark:text-white">{{ $title }}</span>
            <span class="mt-1 block line-clamp-1 text-xs leading-5 text-ink-500 dark:text-ink-400">{{ $description }}</span>
            @if ($existing->isNotEmpty())
                <span class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] font-bold">
                    @if ($pendingCount)<span class="text-amber-600 dark:text-amber-400"><span class="mr-1 inline-block h-2 w-2 rounded-full bg-amber-400"></span>{{ $pendingCount }} pending</span>@endif
                    @if ($approvedCount)<span class="text-emerald-600 dark:text-emerald-400"><span class="mr-1 inline-block h-2 w-2 rounded-full bg-emerald-500"></span>{{ $approvedCount }} approve</span>@endif
                    @if ($rejectedCount)<span class="text-brand-600 dark:text-brand-400"><span class="mr-1 inline-block h-2 w-2 rounded-full bg-brand-600"></span>{{ $rejectedCount }} reject</span>@endif
                </span>
            @endif
        </span>
        <span class="flex shrink-0 items-center gap-2">
            @if ($existing->isNotEmpty())
                <span class="rounded-full bg-ink-100 px-2.5 py-1 text-[10px] font-bold text-ink-600 dark:bg-ink-800 dark:text-ink-300">{{ $existing->count() }} file</span>
            @else
                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">Belum ada</span>
            @endif
            <svg class="h-5 w-5 text-ink-400 transition-transform duration-200" :class="expanded && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25L12 15.75 4.5 8.25"/>
            </svg>
        </span>
    </button>

    <div x-show="expanded" x-cloak x-transition.opacity class="border-t border-ink-100 p-4 dark:border-ink-800">

    @if ($existing->isNotEmpty())
        <div class="mt-3">
            <div class="mb-2 flex items-center justify-between gap-3">
                <p class="text-[10px] font-bold uppercase tracking-wider text-ink-400">Evidence tersimpan</p>
                <p class="text-[10px] text-ink-400">Tap foto untuk preview</p>
            </div>
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
            @foreach ($existing as $evidence)
                @php
                    $mime = data_get($evidence->metadata, 'mime', '');
                    $fileName = data_get($evidence->metadata, 'original_name', basename($evidence->file_path));
                    $fileSize = data_get($evidence->metadata, 'size', 0);
                    $fileUrl = $evidence->url();
                    $thumbUrl = $evidence->thumbUrl();
                    $isImage = str_starts_with($mime, 'image/') || in_array(strtolower(pathinfo($evidence->file_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp']);
                    $statusStyle = match ($evidence->status->value) {
                        'approved' => 'border-emerald-300 dark:border-emerald-800',
                        'rejected' => 'border-brand-300 dark:border-brand-800',
                        default => 'border-amber-300 dark:border-amber-800',
                    };
                    $statusLabelStyle = match ($evidence->status->value) {
                        'approved' => 'bg-emerald-500 text-white',
                        'rejected' => 'bg-brand-600 text-white',
                        default => 'bg-amber-400 text-amber-950',
                    };
                @endphp

                <article class="min-w-0 rounded-xl border bg-white p-1.5 {{ $statusStyle }} dark:bg-ink-900">
                    <button type="button"
                            @click="openStoredPreview(@js($fileUrl), @js($fileName), @js($mime), @js($fileSize))"
                            class="group relative block aspect-square w-full overflow-hidden rounded-lg bg-ink-100 text-left dark:bg-ink-800">
                        <span class="absolute inset-0">
                            @if ($isImage)
                                <img src="{{ $thumbUrl }}" alt="{{ $fileName }}" loading="lazy" decoding="async" class="h-full w-full object-cover">
                            @else
                                <span class="grid h-full w-full place-items-center text-center text-brand-600 dark:text-brand-300"><span><svg class="mx-auto h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5A3.375 3.375 0 0010.125 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9a9 9 0 00-9-9z"/></svg><span class="mt-1 block text-[9px] font-bold">PDF</span></span></span>
                            @endif
                        </span>
                        <span class="absolute left-1.5 top-1.5 rounded-md px-1.5 py-0.5 text-[8px] font-extrabold shadow-sm {{ $statusLabelStyle }}">
                            {{ $evidence->status->label() }}
                        </span>
                        <span class="absolute inset-0 grid place-items-center bg-black/0 text-transparent transition group-hover:bg-black/25 group-hover:text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </span>
                    </button>
                    <p class="mt-1.5 truncate px-0.5 text-[9px] font-bold text-ink-700 dark:text-ink-200" title="{{ $fileName }}">{{ $fileName }}</p>

                    @if ($evidence->status->value === 'rejected')
                        @can('replace', $evidence)
                            <button type="button" onclick="document.getElementById('technician-replace-{{ $evidence->id_evidence }}').showModal()"
                                    class="mt-1.5 min-h-7 w-full rounded-lg bg-brand-600 px-1 text-[9px] font-extrabold text-white">Reupload</button>
                            <x-modal id="technician-replace-{{ $evidence->id_evidence }}" title="Perbaiki Evidence">
                                <div class="rounded-xl bg-brand-50 p-3 dark:bg-brand-900/20">
                                    <p class="text-[10px] font-extrabold uppercase tracking-wide text-brand-600 dark:text-brand-400">Alasan ditolak</p>
                                    <p class="mt-1 text-xs leading-5 text-brand-800 dark:text-brand-200">{{ $evidence->review_note ?: 'Evidence belum memenuhi ketentuan reviewer.' }}</p>
                                </div>
                                <form method="POST" action="{{ route('technician.projects.evidence.replace', [$lop, $evidence]) }}"
                                      enctype="multipart/form-data" class="mt-3" x-data="evidenceUploader()">
                                    @csrf
                                    @method('PUT')
                                    <input x-ref="input" type="file" name="file" accept="image/jpeg,image/png,image/webp,application/pdf"
                                           class="sr-only" @change="selectFiles($event)">
                                    <template x-if="files.length && files[0].compressing">
                                        <p class="text-[10px] font-semibold text-ink-400">Mengompres foto…</p>
                                    </template>
                                    <template x-if="!files.length">
                                        <button type="button" @click="$refs.input.click()"
                                                class="grid min-h-11 w-full place-items-center rounded-xl bg-brand-600 px-4 text-xs font-extrabold text-white shadow-sm">
                                            Upload ulang evidence
                                        </button>
                                    </template>
                                    <template x-if="files.length">
                                        <div class="rounded-xl border border-brand-200 bg-white p-3 dark:border-brand-800 dark:bg-ink-900">
                                            <div class="flex items-center gap-3">
                                                <template x-if="files[0].isImage"><img :src="files[0].url" :alt="files[0].name" class="h-16 w-16 rounded-lg object-cover"></template>
                                                <template x-if="!files[0].isImage"><span class="grid h-16 w-16 place-items-center rounded-lg bg-ink-100 text-xs font-bold text-brand-600 dark:bg-ink-800">PDF</span></template>
                                                <div class="min-w-0 flex-1"><p class="truncate text-xs font-bold" x-text="files[0].name"></p><p class="mt-1 text-[10px] text-ink-400" x-text="formatSize(files[0].size)"></p></div>
                                                <button type="button" @click="removeFile(0)" class="text-xs font-bold text-brand-600">Batal</button>
                                            </div>
                                            <p x-show="files[0].error" x-text="files[0].error" class="mt-2 text-[10px] font-bold text-brand-600"></p>
                                            <button type="submit" :disabled="!formReady"
                                                    :class="formReady ? 'bg-brand-600 text-white' : 'bg-ink-200 text-ink-400 dark:bg-ink-800 dark:text-ink-500'"
                                                    class="mt-3 min-h-11 w-full rounded-xl text-xs font-extrabold">Ganti & kirim ulang</button>
                                        </div>
                                    </template>
                                </form>
                            </x-modal>
                        @endcan
                    @endif
                </article>
            @endforeach
            </div>
        </div>
    @endif

    @if ($allowUpload)
    <div class="{{ $existing->isNotEmpty() ? 'mt-4' : '' }} space-y-3">
        <div class="rounded-xl border-2 border-dashed border-ink-200 bg-ink-50 p-3 dark:border-ink-700 dark:bg-ink-800/60">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/20 dark:text-brand-300">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.88 7.6 2 8.61 2 9.8V18a2.25 2.25 0 002.25 2.25h15.5A2.25 2.25 0 0022 18V9.8c0-1.19-.88-2.2-2.052-2.395a48.776 48.776 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 13.5a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </span>
                <span class="min-w-0"><span class="block text-xs font-bold text-ink-700 dark:text-ink-200">Tambah evidence</span><span class="mt-0.5 block text-[10px] leading-4 text-ink-400">Pilih, review, hapus jika perlu, lalu upload.</span></span>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" @click="$refs.camera.click()" class="min-h-10 rounded-xl bg-brand-600 px-3 text-[11px] font-extrabold text-white">Ambil foto</button>
                <button type="button" @click="$refs.picker.click()" class="min-h-10 rounded-xl border border-ink-200 bg-white px-3 text-[11px] font-extrabold text-ink-700 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200">Galeri / file</button>
            </div>
            <input x-ref="camera" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" class="sr-only" @change="selectQueue($event)">
            <input x-ref="picker" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="sr-only" @change="selectQueue($event)">
        </div>

        <template x-if="queueActive">
            <div class="space-y-2">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-[10px] font-bold text-ink-500 dark:text-ink-300"><span x-text="queue.length"></span> file dipilih</p>
                    <p class="text-[10px] text-ink-400" x-show="queueSelectedCount">Tap foto untuk memperbesar</p>
                </div>
                <p class="text-[10px] text-ink-400" x-show="queueBusy">Jangan tutup halaman sampai semua selesai. Halaman akan dimuat ulang otomatis.</p>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                <template x-for="item in queue" :key="item.id">
                    <div class="min-w-0 rounded-xl border border-ink-100 bg-white p-1.5 dark:border-ink-700 dark:bg-ink-900">
                        <button type="button" @click="openPreview(item)" class="relative grid aspect-square w-full place-items-center overflow-hidden rounded-lg bg-ink-100 dark:bg-ink-800" aria-label="Review file">
                            <template x-if="item.previewUrl"><img :src="item.previewUrl" class="h-full w-full object-cover" alt=""></template>
                            <template x-if="!item.previewUrl"><span class="text-[9px] font-bold text-brand-600">PDF</span></template>
                        </button>
                        <div class="min-w-0 px-0.5 pt-1.5">
                            <p class="truncate text-[9px] font-bold text-ink-800 dark:text-ink-100" x-text="item.name"></p>
                            <p class="mt-0.5 text-[9px] text-ink-400" x-text="formatSize(item.size)"></p>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800">
                                <div class="h-full rounded-full transition-all"
                                     :class="item.status === 'error' ? 'bg-brand-600' : (item.status === 'done' ? 'bg-emerald-500' : 'bg-blue-500')"
                                     :style="`width: ${item.status === 'done' ? 100 : (item.status === 'uploading' ? item.progress : (item.status === 'compressing' ? 8 : 0))}%`"></div>
                            </div>
                            <p class="mt-1 text-[9px]"
                               :class="item.status === 'error' ? 'text-brand-600 font-bold' : 'text-ink-400'"
                               x-text="({selected:'Siap diupload', queued:'Menunggu…', compressing:'Mengompres…', uploading:`Mengunggah ${item.progress}%`, done:'Selesai', error:item.error})[item.status]"></p>
                        </div>
                        <button type="button" x-show="item.status === 'error'" @click="retry(item)"
                                class="mt-1 min-h-7 w-full rounded-lg bg-brand-600 px-1 text-[9px] font-extrabold text-white">Ulangi</button>
                        <button type="button" x-show="item.status === 'selected' || item.status === 'queued' || item.status === 'error'" @click="removeQueued(item)"
                                class="mt-1 flex min-h-7 w-full items-center justify-center gap-1 rounded-lg border border-ink-100 text-[9px] font-bold text-ink-500 hover:border-brand-200 hover:text-brand-600 dark:border-ink-700" aria-label="Hapus file">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg> Hapus
                        </button>
                    </div>
                </template>
                </div>
                <button type="button" x-show="queueSelectedCount" @click="startUploads()"
                        class="min-h-11 w-full rounded-xl bg-brand-600 px-4 text-xs font-extrabold text-white shadow-sm">
                    Upload <span x-text="queueSelectedCount"></span> evidence
                </button>
                <p x-show="queueFailed" class="text-[10px] font-semibold text-brand-600">Sebagian gagal — perbaiki sinyal lalu tekan Ulangi.</p>
            </div>
        </template>
    </div>

    <noscript>
        <form method="POST" action="{{ route('technician.projects.evidence', $lop) }}" enctype="multipart/form-data" class="mt-3 space-y-2">
            @csrf
            <input type="hidden" name="category" value="{{ $category }}">
            <input type="hidden" name="type" value="PHOTO">
            @if ($designatorId)<input type="hidden" name="designator_id" value="{{ $designatorId }}">@endif
            <input type="file" name="files[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="w-full text-xs">
            <button type="submit" class="min-h-11 w-full rounded-xl bg-brand-600 px-4 text-sm font-bold text-white">Upload evidence</button>
        </form>
    </noscript>
    @endif
    </div>

    <div x-show="preview" x-cloak x-transition.opacity
         class="fixed inset-0 z-[80] flex items-center justify-center bg-black/90 p-4"
         role="dialog" aria-modal="true" @click.self="closePreview()">
        <div class="w-full max-w-lg">
            <div class="mb-3 flex items-center justify-between gap-3 text-white">
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold" x-text="preview?.name"></p>
                    <p class="mt-0.5 text-xs text-white/60" x-text="preview ? formatSize(preview.size) : ''"></p>
                </div>
                <button type="button" @click="closePreview()" class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-white/10" aria-label="Tutup review">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <template x-if="preview?.isImage">
                <img :src="preview.url" :alt="preview.name" class="max-h-[72vh] w-full rounded-2xl object-contain">
            </template>
            <template x-if="preview && !preview.isImage">
                <div class="grid min-h-64 place-items-center rounded-2xl bg-white p-8 text-center text-ink-800">
                    <div><svg class="mx-auto h-14 w-14 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5A3.375 3.375 0 0010.125 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9a9 9 0 00-9-9z"/></svg><p class="mt-3 text-sm font-bold">Dokumen PDF siap diupload</p><p class="mt-1 text-xs text-ink-500" x-text="preview.name"></p></div>
                </div>
            </template>
        </div>
    </div>
</div>
