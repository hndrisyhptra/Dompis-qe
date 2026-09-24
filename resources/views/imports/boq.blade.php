@extends('layouts.app')

@section('title', 'Import BOQ')

@section('content')
<div class="mx-auto max-w-6xl space-y-7">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div class="max-w-3xl">
        <p class="text-xs font-bold uppercase tracking-[.16em] text-brand-600">Bulk Import Data</p>
        <h1 class="mt-2 text-xl font-bold text-ink-900 dark:text-ink-50">Import BOQ via Excel</h1>
        <p class="mt-2 text-sm leading-6 text-ink-500 dark:text-ink-400">
            Upload file <code class="rounded bg-ink-100 px-1.5 py-0.5 text-xs dark:bg-ink-800">.xlsx / .xls / .csv</code> format BOQ existing.
            Sistem mencocokkan <code class="rounded bg-ink-100 px-1.5 py-0.5 text-xs dark:bg-ink-800">PROJECT : ...</code> dengan Nama LOP,
            membaca paket dari header <code class="rounded bg-ink-100 px-1.5 py-0.5 text-xs dark:bg-ink-800">TIF-n / PAKET-n</code>, lalu mengambil designator dengan VOL terisi.
            <span class="mt-1 block text-xs">VOL kosong atau 0 tidak dipakai. Awalan <code>M-</code> = material dan <code>J-</code> = jasa.</span>
        </p>
      </div>
      <a href="{{ route('bulk-import.boq.template') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl border border-ink-200 bg-white px-4 text-sm font-bold text-ink-700 shadow-sm transition hover:border-brand-300 hover:text-brand-700 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 19.5h14"/></svg>Unduh Template</a>
    </div>

    @if ($errors->any())
        <div class="max-w-2xl rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 dark:border-brand-800 dark:bg-brand-900/30">
            <p class="mb-1 text-sm font-medium text-brand-700 dark:text-brand-300">Import gagal, tidak ada data yang disimpan:</p>
            <ul class="list-inside list-disc space-y-0.5 text-sm text-brand-700 dark:text-brand-300">
                @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1.15fr_.85fr]">
      <div class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900 sm:p-6">
        <form method="POST" action="{{ route('bulk-import.boq.store') }}" enctype="multipart/form-data" x-data="importUploadForm(@js(route('bulk-import.boq.store')))" @submit.prevent="submit($event)" class="space-y-5">
            @csrf
            <div>
                <label for="file" class="mb-1.5 block text-sm font-medium text-ink-700 dark:text-ink-300">File Excel BOQ</label>
                <label for="file" class="flex min-h-40 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-ink-200 bg-ink-50/30 px-6 py-8 text-center transition hover:border-brand-400 hover:bg-brand-50/40 dark:border-ink-700 dark:bg-ink-800/30">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white text-brand-600 shadow-sm dark:bg-ink-900">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-5.25Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 15.75 12 18.75m0 0 3-3m-3 3V12"/></svg>
                    </span>
                    <span class="mt-3 min-w-0"><span class="block max-w-sm truncate text-sm font-bold text-ink-800 dark:text-white" x-text="fileName || 'Pilih file BOQ'"></span><span class="mt-1 block text-xs text-ink-400" x-text="fileName ? 'File siap diunggah' : 'XLSX, XLS, atau CSV · maksimal 10 MB'"></span></span>
                    <input type="file" id="file" name="file" accept=".xlsx,.xls,.csv" required class="sr-only" @change="selectFile($event)">
                </label>
                @error('file')<p class="mt-1.5 text-sm text-brand-600 dark:text-brand-400">{{ $message }}</p>@enderror
            </div>

            <div x-show="uploading || error" x-cloak class="rounded-xl border border-ink-100 bg-ink-50 p-3 dark:border-ink-700 dark:bg-ink-800/70"><div class="flex items-center justify-between gap-3 text-xs"><span class="font-semibold text-ink-600 dark:text-ink-300" x-text="phase"></span><strong class="tabular-nums text-brand-700 dark:text-brand-300" x-text="`${uploadPercent}%`"></strong></div><div class="mt-2 h-2 overflow-hidden rounded-full bg-white dark:bg-ink-900"><div class="h-full rounded-full bg-brand-600 transition-all duration-300" :style="`width:${uploadPercent}%`"></div></div><p x-show="error" class="mt-2 text-xs font-semibold text-red-600 dark:text-red-300" x-text="error"></p></div>
            <button :disabled="uploading" class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 text-sm font-extrabold text-white shadow-lg shadow-brand-600/20 transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60"><svg x-show="uploading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M12 3a9 9 0 0 1 9 9h-3a6 6 0 0 0-6-6V3Z"/></svg><span x-text="uploading ? 'Mengunggah…' : 'Upload & Proses BOQ'"></span></button>
        </form>
      </div>
      <aside class="rounded-2xl border border-ink-100 bg-white p-6 shadow-sm dark:border-ink-800 dark:bg-ink-900"><div class="flex items-center justify-between gap-3"><h2 class="font-bold text-ink-900 dark:text-white">Yang dibaca sistem</h2><span class="rounded-lg bg-emerald-50 px-2 py-1 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300">Otomatis</span></div><ol class="mt-5 space-y-4"><li class="flex gap-3"><span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-[10px] font-extrabold text-brand-700 dark:bg-brand-950/30 dark:text-brand-300">1</span><div><p class="text-xs font-bold text-ink-800 dark:text-ink-200">Nama Project / LOP</p><p class="mt-1 text-[11px] leading-5 text-ink-500">Dicocokkan persis dari baris PROJECT dengan Data LOP.</p></div></li><li class="flex gap-3"><span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-[10px] font-extrabold text-brand-700 dark:bg-brand-950/30 dark:text-brand-300">2</span><div><p class="text-xs font-bold text-ink-800 dark:text-ink-200">Paket dan harga</p><p class="mt-1 text-[11px] leading-5 text-ink-500">Membaca PAKET-n / TIF-n beserta harga material dan jasa.</p></div></li><li class="flex gap-3"><span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-[10px] font-extrabold text-brand-700 dark:bg-brand-950/30 dark:text-brand-300">3</span><div><p class="text-xs font-bold text-ink-800 dark:text-ink-200">Volume terpakai</p><p class="mt-1 text-[11px] leading-5 text-ink-500">Hanya item dengan VOL lebih dari nol yang dimasukkan.</p></div></li></ol></aside>
    </div>

    @include('imports._batch-table')
</div>
@endsection
