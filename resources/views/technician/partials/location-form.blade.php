@php
    $locationValues = [
        'latitude' => old('latitude', $state['survey']?->latitude ?? ''),
        'longitude' => old('longitude', $state['survey']?->longitude ?? ''),
        'accuracy' => old('accuracy', $state['survey']?->accuracy ?? ''),
        'source' => old('location_source', $state['survey']?->location_source ?? 'manual'),
        'dirty' => session()->hasOldInput('latitude') || session()->hasOldInput('longitude'),
        'coordinates' => old('coordinates', ''),
    ];
@endphp
<div x-data="technicianLocation(@js($locationValues))" class="rounded-2xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
    <div class="flex items-start justify-between gap-3">
        <div><h3 class="text-sm font-bold">Tag lokasi pekerjaan <span class="ml-1 rounded-md bg-ink-100 px-2 py-0.5 text-[9px] font-semibold text-ink-500 dark:bg-ink-800 dark:text-ink-300">Opsional</span></h3><p class="mt-1 text-xs leading-5 text-ink-500">Boleh langsung lanjut tanpa lokasi. Jika diisi, simpan terlebih dahulu.</p></div>
        @if ($state['survey'])<span x-show="!dirty" class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300">Tersimpan</span>@endif
    </div>
    <button type="button" @click="locate()" :disabled="locating" class="mt-4 min-h-11 w-full rounded-xl bg-ink-900 text-xs font-bold text-white disabled:opacity-50 dark:bg-ink-700"><span x-text="locating ? 'Mengambil lokasi…' : 'Gunakan lokasi saat ini'"></span></button>
    <p x-show="error" x-cloak x-text="error" role="alert" class="mt-2 text-xs text-brand-600"></p>
    <a href="#technician-pra-photos" class="mt-3 inline-flex min-h-10 items-center text-xs font-bold text-brand-600">Lanjut tanpa mengisi lokasi ↓</a>
    <form method="POST" action="{{ route('technician.projects.location', $lop, false) }}" novalidate class="mt-3 space-y-3">
        @csrf @method('PUT')
        <input type="hidden" name="accuracy" value="{{ $locationValues['accuracy'] }}" x-model="accuracy">
        <input type="hidden" name="location_source" value="{{ $locationValues['source'] }}" x-model="source">
        <div>
            <label for="location-coordinates" class="text-[10px] font-bold uppercase tracking-wide text-ink-500">Tempel koordinat (Latitude, Longitude)</label>
            <input id="location-coordinates" name="coordinates" value="{{ $locationValues['coordinates'] }}" type="text" x-model="coordinates" @input="applyCoordinates()" maxlength="150" placeholder="-7.257472, 112.752090" autocomplete="off" :aria-invalid="!!coordinateError" aria-describedby="location-coordinate-help location-coordinate-error" class="mt-1 min-h-11 w-full rounded-xl border border-ink-200 bg-white px-3 text-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 dark:border-ink-700 dark:bg-ink-800">
            @error('coordinates')<p class="mt-1 text-xs text-brand-600">{{ $message }}</p>@enderror
            <p id="location-coordinate-help" class="mt-1 text-[10px] leading-5 text-ink-500">Tempel dua angka sekaligus, dipisah koma atau spasi. Gunakan titik untuk desimal.</p>
            <p id="location-coordinate-error" x-show="coordinateError" x-cloak x-text="coordinateError" role="alert" class="mt-1 text-xs text-brand-600"></p>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div><label for="location-latitude" class="text-[10px] font-bold text-ink-500">LATITUDE</label><input id="location-latitude" name="latitude" value="{{ $locationValues['latitude'] }}" x-model="latitude" @input="manualChanged()" placeholder="-7.257472" class="mt-1 min-h-11 w-full rounded-xl border border-ink-200 bg-white px-3 text-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 dark:border-ink-700 dark:bg-ink-800">@error('latitude')<p class="mt-1 text-xs text-brand-600">{{ $message }}</p>@enderror</div>
            <div><label for="location-longitude" class="text-[10px] font-bold text-ink-500">LONGITUDE</label><input id="location-longitude" name="longitude" value="{{ $locationValues['longitude'] }}" x-model="longitude" @input="manualChanged()" placeholder="112.752090" class="mt-1 min-h-11 w-full rounded-xl border border-ink-200 bg-white px-3 text-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 dark:border-ink-700 dark:bg-ink-800">@error('longitude')<p class="mt-1 text-xs text-brand-600">{{ $message }}</p>@enderror</div>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <button type="submit" class="min-h-11 rounded-xl border border-ink-200 text-xs font-bold dark:border-ink-700">Simpan / lewati lokasi</button>
            <a :href="mapUrl" @click="if (!hasCoordinates) $event.preventDefault()" :aria-disabled="!hasCoordinates" :class="!hasCoordinates && 'pointer-events-none opacity-40'" target="_blank" rel="noopener noreferrer" class="grid min-h-11 place-items-center rounded-xl bg-ink-50 text-xs font-bold text-ink-600 dark:bg-ink-800 dark:text-ink-300">Buka peta</a>
        </div>
        <p x-show="dirty && hasCoordinates && !coordinateError" x-cloak class="text-[10px] font-semibold text-amber-600 dark:text-amber-400">Koordinat belum disimpan. Klik Simpan lokasi untuk merekamnya.</p>
    </form>
</div>
