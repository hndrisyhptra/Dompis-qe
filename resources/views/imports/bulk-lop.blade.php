@extends('layouts.app')

@section('title', 'Bulk Import LOP')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-xs font-bold uppercase tracking-[.16em] text-brand-600">Bulk Import Data</p><h1 class="mt-1 text-2xl font-extrabold text-ink-900 dark:text-white">Bulk Import LOP</h1><p class="mt-2 max-w-2xl text-sm text-ink-500">Buat banyak LOP dari satu file. Nama LOP yang kosong akan dibuat otomatis menggunakan format aktif.</p></div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('bulk-import.lop.template') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700">Unduh Template LOP</a>
            <a href="{{ route('bulk-import.lop.template', ['format' => 'csv']) }}" title="Unduh template CSV tanpa sheet referensi" class="inline-flex min-h-11 items-center justify-center rounded-md border border-ink-200 bg-white px-4 text-sm font-bold text-ink-700 hover:border-brand-300 hover:text-brand-700 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200">CSV</a>
        </div>
    </div>

    @if ($errors->any())<div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    @if (! $hasImportScope)<div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">Scope lokasi akun Admin belum dikonfigurasi. Hubungi Super Admin untuk mengatur Area, Region, Branch, atau Service Area.</div>@endif

    <div class="grid gap-6 lg:grid-cols-[1.15fr_.85fr]">
        <form method="POST" action="{{ route('bulk-import.lop.store', [], false) }}" enctype="multipart/form-data" x-data="importUploadForm(@js(route('bulk-import.lop.store', [], false)))" @submit.prevent="submit($event)" class="rounded-2xl border border-ink-100 bg-white p-6 shadow-sm dark:border-ink-800 dark:bg-ink-900">
            @csrf
            <h2 class="font-bold text-ink-900 dark:text-white">Upload file LOP</h2>
            <p class="mt-1 text-xs leading-5 text-ink-500">Format XLSX, XLS, atau CSV · maksimal 10 MB.</p>
            <label class="mt-5 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-ink-200 px-6 py-10 text-center transition hover:border-brand-400 hover:bg-brand-50/40 dark:border-ink-700 dark:hover:bg-brand-950/20">
                <svg class="h-9 w-9 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5h10.5a3.75 3.75 0 001.332-7.257A6 6 0 007.447 9.21 4.5 4.5 0 006.75 18z"/></svg>
                <span class="mt-3 text-sm font-bold text-ink-800 dark:text-white" x-text="fileName || 'Pilih file Bulk LOP'"></span><span class="mt-1 text-xs text-ink-400" x-text="fileName ? 'File siap dimasukkan ke antrean' : 'Klik untuk memilih file dari perangkat'"></span>
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required @disabled(! $hasImportScope) class="sr-only" @change="selectFile($event)">
            </label>
            <div x-show="uploading || error" x-cloak class="mt-4 rounded-xl border border-ink-100 bg-ink-50 p-3 dark:border-ink-700 dark:bg-ink-800/70">
                <div class="flex items-center justify-between gap-3 text-xs"><span class="font-semibold text-ink-600 dark:text-ink-300" x-text="phase"></span><strong class="tabular-nums text-brand-700 dark:text-brand-300" x-text="`${uploadPercent}%`"></strong></div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-white dark:bg-ink-900"><div class="h-full rounded-full bg-brand-600 transition-all duration-300" :style="`width:${uploadPercent}%`"></div></div>
                <p x-show="error" class="mt-2 text-xs font-semibold text-red-600 dark:text-red-300" x-text="error"></p>
            </div>
            <button @disabled(! $hasImportScope) :disabled="uploading" class="mt-5 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-600 text-sm font-extrabold text-white shadow-lg shadow-brand-600/20 hover:bg-brand-700 disabled:cursor-not-allowed disabled:bg-ink-300 disabled:shadow-none dark:disabled:bg-ink-700"><svg x-show="uploading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M12 3a9 9 0 0 1 9 9h-3a6 6 0 0 0-6-6V3Z"/></svg><span x-text="uploading ? 'Mengunggah…' : 'Masukkan ke Antrean'"></span></button>
        </form>

        <aside class="rounded-2xl border border-ink-100 bg-white p-6 shadow-sm dark:border-ink-800 dark:bg-ink-900">
            <div class="flex items-center justify-between gap-3"><h2 class="font-bold text-ink-900 dark:text-white">Panduan kolom</h2><span class="rounded-lg bg-blue-50 px-2 py-1 text-[10px] font-bold text-blue-700 dark:bg-blue-950/30 dark:text-blue-300">CSV / Excel</span></div>
            <div class="mt-4 space-y-3 text-xs leading-5 text-ink-500">
                <p><strong class="text-ink-700 dark:text-ink-200">Wajib:</strong> STO, program_type, segment, dan job_description.</p>
                <p><strong class="text-ink-700 dark:text-ink-200">Nama LOP:</strong> boleh kosong, sistem membuat nama berdasarkan format aktif.</p>
                <p><strong class="text-ink-700 dark:text-ink-200">Incident:</strong> boleh kosong untuk membuat nomor INP otomatis.</p>
                <p><strong class="text-ink-700 dark:text-ink-200">Program:</strong> QE Recovery, QE Preventive, QE Relok Utilitas, QE Relok Tiang. Huruf besar/kecil, spasi, underscore, dan tanda hubung diterima (contoh: qe_recovery, QE RELOK TIANG).</p>
                <p><strong class="text-ink-700 dark:text-ink-200">Anggaran otomatis:</strong> Relok Utilitas = CAPEX; program lainnya = OPEX. Kolom budget_type boleh kosong dan inputnya akan disesuaikan dengan program.</p>
                <p><strong class="text-ink-700 dark:text-ink-200">Segmen semua program:</strong> pilih 1–5 segmen, pisahkan dengan <code class="rounded bg-ink-100 px-1 dark:bg-ink-800">|</code>, contoh feeder|distribusi|odc|odp|tiang. Koma atau titik koma juga diterima.</p>
                <p><strong class="text-ink-700 dark:text-ink-200">Lokasi:</strong> isi kode STO aktif sesuai scope Anda. Branch dan Area boleh kosong untuk mengikuti master STO; jika diisi harus sesuai.</p>
                <p><strong class="text-ink-700 dark:text-ink-200">INCIDENT duplikat:</strong> tidak menimpa data. Hasil gagal menyebut nama LOP yang sudah memakai nomor tersebut (hanya jika berada dalam scope Anda).</p>
                <details class="rounded-md border border-ink-200 p-3 dark:border-ink-700">
                    <summary class="cursor-pointer font-bold text-ink-800 dark:text-ink-100">Cara memakai template & referensi</summary>
                    <ol class="mt-2 list-decimal space-y-1 pl-4">
                        <li>Unduh Template LOP (Excel), lalu isi sheet <strong>LOP</strong>. Ganti/hapus seluruh baris CONTOH.</li>
                        <li>Gunakan sheet <strong>Panduan</strong> dan <strong>Referensi Program, Segmen, Lokasi, Paket</strong>. Referensi lokasi mengikuti scope akun saat diunduh.</li>
                        <li>Jangan ubah header. INCIDENT, ID IHLD, Nama LOP, dan paket boleh kosong. Format identifier tetap Text.</li>
                        <li>Upload dan tunggu antrean selesai. Periksa hasil per baris; upload ulang hanya baris gagal agar data yang berhasil tidak terduplikasi.</li>
                    </ol>
                    <p class="mt-2 text-ink-400">CSV hanya memuat data LOP; gunakan panduan ini atau template Excel untuk referensi lengkap.</p>
                </details>
            </div>
        </aside>
    </div>

    @include('imports._batch-table')
</div>
@endsection
