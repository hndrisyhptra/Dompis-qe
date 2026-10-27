@props(['id', 'lop'])

@php
    $insera = $lop->evidences
        ->filter(fn ($evidence) => $evidence->category === \App\Enums\EvidenceCategory::INSERA)
        ->sortByDesc('created_at')
        ->values();
@endphp

<x-modal :$id :title="'Capture Ticket Insera · '.$lop->incident" size="lg">
    <div x-data="evidenceUploader({ maxFiles: 12 })" @keydown.escape.window="closePreview()" class="space-y-5">
        <section class="rounded-2xl border border-blue-100 bg-blue-50/70 p-4 dark:border-blue-900/60 dark:bg-blue-950/20">
            <div class="flex items-start gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.88 7.6 2 8.61 2 9.8V18a2.25 2.25 0 0 0 2.25 2.25h15.5A2.25 2.25 0 0 0 22 18V9.8c0-1.19-.88-2.2-2.052-2.395a48.776 48.776 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 13.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                </span>
                <div>
                    <h4 class="text-sm font-extrabold text-ink-900 dark:text-white">Screenshot dari aplikasi Insera</h4>
                    <p class="mt-1 text-xs leading-5 text-ink-600 dark:text-ink-300">File akan tampil di Step 3 · Evidence Pra teknisi dan tetap diperiksa admin.</p>
                    <p class="mt-1 text-[10px] font-semibold text-ink-400">Maksimal 12 file sekali upload · 10 MB per file · JPG, PNG, atau WEBP</p>
                </div>
            </div>
        </section>

        @if ($insera->isNotEmpty())
            <section>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div><h4 class="text-sm font-extrabold text-ink-900 dark:text-white">Capture tersimpan</h4><p class="mt-0.5 text-xs text-ink-500">{{ $insera->count() }} file tersedia untuk teknisi.</p></div>
                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300">Tersedia</span>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach ($insera as $item)
                        @php
                            $mime = (string) data_get($item->metadata, 'mime', '');
                            $name = data_get($item->metadata, 'original_name', basename($item->file_path));
                            $isImage = str_starts_with($mime, 'image/') || in_array(strtolower(pathinfo($item->file_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp']);
                        @endphp
                        <article class="min-w-0 rounded-xl border border-ink-100 bg-white p-2 dark:border-ink-700 dark:bg-ink-900">
                            <button type="button" @click="openStoredPreview(@js($item->url()), @js($name), @js($mime), @js(data_get($item->metadata, 'size', 0)))" class="group relative grid aspect-[4/3] w-full place-items-center overflow-hidden rounded-lg bg-ink-100 dark:bg-ink-800" title="Review {{ $name }}">
                                @if ($isImage)
                                    <img src="{{ $item->thumbUrl() }}" alt="{{ $name }}" loading="lazy" class="h-full w-full object-cover">
                                @else
                                    <span class="text-center text-brand-600 dark:text-brand-300"><svg class="mx-auto h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25h-4.5A1.125 1.125 0 0 0 4.5 3.375v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-6.375Z"/></svg><span class="mt-1 block text-[10px] font-extrabold">FILE</span></span>
                                @endif
                                <span class="absolute inset-0 grid place-items-center bg-black/0 text-transparent transition group-hover:bg-black/30 group-hover:text-white"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="2.25"/></svg></span>
                            </button>
                            <p class="mt-2 truncate text-[10px] font-bold text-ink-700 dark:text-ink-200" title="{{ $name }}">{{ $name }}</p>
                            <div class="mt-1 flex items-center justify-between gap-2 text-[9px] text-ink-400"><span class="truncate">{{ $item->uploader?->name ?? 'Admin' }}</span><span class="shrink-0">{{ $item->created_at?->format('d M Y') }}</span></div>
                            <div class="mt-1.5"><x-badge :variant="$item->status->badgeVariant()">{{ $item->status->label() }}</x-badge></div>
                            @if ($item->status !== \App\Enums\EvidenceStatus::APPROVED)
                                @can('delete', $item)
                                    <form method="POST" action="{{ route('lop.evidence.destroy', [$lop, $item]) }}" class="mt-2" onsubmit="return confirm('Hapus Capture Ticket Insera ini? Jika LOP sedang menunggu approval atau ditolak, status akan kembali ke Progress.');">
                                        @csrf @method('DELETE')
                                        <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                                        <button type="submit" class="min-h-8 w-full rounded-lg border border-red-200 text-[10px] font-bold text-red-600 transition hover:bg-red-50 dark:border-red-900/60 dark:hover:bg-red-950/30">Hapus</button>
                                    </form>
                                @endcan
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <form method="POST" action="{{ route('data-lops.insera.store', $lop) }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
            <input x-ref="input" type="file" name="files[]" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" @change="selectFiles($event)">

            <button type="button" @click="$refs.input.click()" class="flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-ink-200 bg-ink-50 px-4 text-sm font-extrabold text-ink-700 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-200">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5h10.5A2.25 2.25 0 0 0 19.5 17.25V6.75A2.25 2.25 0 0 0 17.25 4.5H6.75A2.25 2.25 0 0 0 4.5 6.75v10.5A2.25 2.25 0 0 0 6.75 19.5Z"/></svg>
                Pilih screenshot
            </button>
            <p x-show="selectionError" x-text="selectionError" class="text-xs font-bold text-red-600"></p>
            @error('files')<p class="text-xs font-bold text-red-600">{{ $message }}</p>@enderror
            @error('files.*')<p class="text-xs font-bold text-red-600">{{ $message }}</p>@enderror

            <template x-if="files.length">
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-3"><p class="text-xs font-bold text-ink-700 dark:text-ink-200"><span x-text="files.length"></span> file siap diupload</p><p class="text-[10px] text-ink-400">Klik file untuk memperbesar</p></div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <template x-for="(item, index) in files" :key="`${item.name}-${index}`">
                            <article class="min-w-0 rounded-xl border border-ink-100 bg-white p-2 dark:border-ink-700 dark:bg-ink-900">
                                <button type="button" @click="openPreview(item)" class="grid aspect-[4/3] w-full place-items-center overflow-hidden rounded-lg bg-ink-100 dark:bg-ink-800">
                                    <template x-if="item.isImage"><img :src="item.url" :alt="item.name" class="h-full w-full object-cover"></template>
                                    <template x-if="!item.isImage"><span class="text-[10px] font-extrabold text-brand-600">FILE</span></template>
                                </button>
                                <p class="mt-2 truncate text-[10px] font-bold" x-text="item.name"></p>
                                <p class="mt-0.5 text-[9px] text-ink-400" x-text="item.compressing ? 'Menyiapkan gambar…' : formatSize(item.size)"></p>
                                <p x-show="item.error" x-text="item.error" class="mt-1 text-[9px] font-bold text-red-600"></p>
                                <button type="button" @click="removeFile(index)" class="mt-2 min-h-8 w-full rounded-lg border border-red-200 text-[10px] font-bold text-red-600">Hapus dari daftar</button>
                            </article>
                        </template>
                    </div>
                    <button type="submit" :disabled="!formReady" :class="formReady ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20' : 'bg-ink-200 text-ink-400 dark:bg-ink-800 dark:text-ink-500'" class="min-h-12 w-full rounded-2xl text-sm font-extrabold">Upload Capture Insera</button>
                </div>
            </template>
        </form>

        <div x-show="preview" x-cloak class="fixed inset-0 z-[110] flex items-center justify-center bg-black/90 p-4" @click.self="closePreview()">
            <div class="w-full max-w-2xl">
                <div class="mb-3 flex items-center justify-between gap-3 text-white"><p class="truncate text-sm font-bold" x-text="preview?.name"></p><button type="button" @click="closePreview()" class="grid h-10 w-10 place-items-center rounded-full bg-white/10">×</button></div>
                <template x-if="preview?.isImage"><img :src="preview.url" :alt="preview.name" class="max-h-[75vh] w-full rounded-2xl object-contain"></template>
                <template x-if="preview && !preview.isImage"><div class="overflow-hidden rounded-2xl bg-white"><iframe :src="preview.url" :title="preview.name" class="h-[70vh] w-full"></iframe></div></template>
            </div>
        </div>
    </div>
</x-modal>
