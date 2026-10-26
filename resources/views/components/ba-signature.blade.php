@props(['upload' => false, 'label' => 'Tanda tangan'])
<fieldset class="space-y-2 rounded-md border border-ink-200 p-3 dark:border-ink-700" x-data="signaturePad()">
    <legend class="px-1 text-sm font-semibold">{{ $label }}</legend>
    <p class="text-xs text-ink-500">Gambar tanda tangan pada bidang putih. Gunakan Hapus untuk mengulang.</p>
    <canvas x-ref="canvas" class="h-[180px] w-full touch-none rounded-md border border-ink-300 bg-white" aria-label="Bidang {{ $label }}"
        @pointerdown.prevent="start($event)" @pointermove.prevent="move($event)" @pointerup="end()" @pointercancel="end()" @lostpointercapture="end()"></canvas>
    <input type="hidden" name="signature_data" x-ref="value">
    <button type="button" @click="clear(); if ($refs.upload) $refs.upload.value = ''" class="min-h-10 rounded-md border border-ink-300 px-3 text-xs font-semibold dark:border-ink-600">Hapus TTD</button>
    @if ($upload)
        <label class="block text-xs font-semibold">Atau upload gambar TTD (maks. 2 MB)
            <input type="file" name="signature_file" accept="image/jpeg,image/png,image/webp" x-ref="upload" @change="clear()" class="mt-2 block w-full rounded-md border border-ink-200 p-2 dark:border-ink-700">
        </label>
    @endif
    @error('signature_data')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    @error('signature_file')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
</fieldset>
