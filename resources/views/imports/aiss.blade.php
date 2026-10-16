@extends('layouts.app')
@section('title', 'Import Data AISS')
@section('content')
<div class="mx-auto max-w-6xl space-y-5">
    <header><p class="text-xs font-bold uppercase tracking-wider text-brand-600">Bulk Import Data</p><h1 class="mt-1 text-2xl font-extrabold text-ink-900 dark:text-white">Import Data AISS</h1><p class="mt-2 text-sm text-ink-500 dark:text-ink-400">Perbarui Status AISS pada LOP existing. Status Dompis dan nilai BOQ tidak berubah.</p></header>
    @if ($errors->any())<p role="alert" class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</p>@endif
    @unless ($schemaReady)<p role="alert" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">Kolom Status AISS belum tersedia. Minta administrator menjalankan migration Status AISS sebelum import.</p>@endunless
    <div class="grid gap-5 lg:grid-cols-2">
        <form method="POST" action="{{ route('bulk-import.aiss.store', [], false) }}" enctype="multipart/form-data" x-data="importUploadForm(@js(route('bulk-import.aiss.store', [], false)))" @submit.prevent="submit($event)" class="rounded-md border border-ink-200 bg-white p-5 dark:border-ink-700 dark:bg-ink-900">
            @csrf
            <h2 class="text-sm font-bold">Upload ekspor AISS</h2><p class="mt-1 text-xs leading-5 text-ink-500">XLSX atau XLS, maksimal 10 MB dan 10.000 baris. Gunakan susunan kolom asli. Khusus Superadmin dan Admin Area; perubahan tetap mengikuti scope lokasi akun.</p>
            <label for="aiss-file" class="mt-4 block text-xs font-semibold">File Excel AISS</label>
            <input id="aiss-file" name="file" type="file" accept=".xlsx,.xls" required @disabled(! $schemaReady) @change="selectFile($event)" class="mt-2 block min-h-11 w-full rounded-md border border-ink-300 p-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200 dark:border-ink-600 dark:bg-ink-800">
            <p class="mt-2 truncate text-xs text-ink-500" x-text="fileName || 'Belum ada file dipilih'"></p>
            <div x-show="uploading || error" x-cloak role="status" class="mt-4 rounded-md border border-ink-200 p-3 dark:border-ink-700">
                <div class="flex justify-between gap-3 text-xs"><span x-text="phase"></span><strong x-text="`${uploadPercent}%`"></strong></div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full bg-brand-600 transition-all" :style="`width:${uploadPercent}%`"></div></div>
                <p x-show="error" x-text="error" class="mt-2 text-xs text-red-600 dark:text-red-300"></p>
            </div>
            <button type="submit" @disabled(! $schemaReady) :disabled="uploading || @js(! $schemaReady)" class="mt-4 min-h-11 w-full rounded-md bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"><span x-text="uploading ? 'Mengunggah…' : 'Masukkan ke Antrean'"></span></button>
        </form>
        <aside class="rounded-md border border-ink-200 bg-white p-5 text-xs leading-6 text-ink-600 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-300">
            <h2 class="text-sm font-bold text-ink-900 dark:text-white">Kolom & urutan pencocokan</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-4"><li><strong>B · ID IHLD</strong> dicocokkan dengan ID IHLD LOP.</li><li>Jika belum cocok, <strong>H · NAMA PROGRAM</strong> dicocokkan dengan Nama LOP.</li><li>Jika belum cocok, <strong>W · INCIDENT</strong> dicocokkan dengan Incident LOP.</li></ol>
            <p class="mt-4 rounded-md border border-ink-200 bg-ink-50 p-3 dark:border-ink-700 dark:bg-ink-800"><strong>L · STATUS</strong> menjadi Status AISS. Jangan tertukar dengan kolom O (Status Program).</p>
            <ul class="mt-3 list-disc space-y-1 pl-4"><li>Pencocokan mengabaikan kapitalisasi dan spasi berlebih, tanpa pencarian kemiripan.</li><li>Kecocokan pertama dipakai. Data ambigu, di luar scope, arsip, status kosong, atau tanpa pasangan dilewati.</li><li>LOP yang berulang dalam file hanya diproses pada baris valid pertama.</li><li>Formula tidak diproses. Gunakan nilai teks untuk identitas dan status.</li><li>Import berikutnya mengganti Status AISS sesuai file yang diupload. Gunakan ekspor terbaru.</li><li>Hasil menampilkan nama LOP, tahap cocok, serta alasan skip. Tidak membuat LOP baru atau mengisi ID IHLD yang kosong.</li></ul>
        </aside>
    </div>
    @include('imports._batch-table')
</div>
@endsection
