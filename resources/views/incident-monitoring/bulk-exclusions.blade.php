@extends('layouts.app')

@section('title', 'Exclude Massal')

@section('content')
<div class="mx-auto max-w-7xl space-y-5" x-data="{ uniqueCount(value) { return new Set(value.split(/[\n,]+/).map(item => item.trim().toUpperCase()).filter(Boolean)).size } }">
    <header>
        <h1 class="text-2xl font-semibold tracking-tight text-ink-900 dark:text-white">Exclude Massal</h1>
        <p class="mt-1.5 text-sm text-ink-600 dark:text-ink-300">Cocokkan daftar incident dari luar aplikasi, tinjau hasilnya, lalu exclude tiket yang memenuhi syarat.</p>
    </header>

    @include('incident-monitoring._tabs')

    @if (session('status'))
        <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">{{ session('status') }}</div>
    @endif

    @if ($preview === null)
        <section class="rounded-md border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900 sm:p-6">
            <h2 class="text-base font-semibold text-ink-900 dark:text-white">Masukkan daftar incident</h2>
            <p class="mt-1 text-sm text-ink-600 dark:text-ink-300">Tempel satu incident per baris atau pisahkan dengan koma. Maksimal 500 ticket per proses. Baris kosong dan duplikat akan diabaikan.</p>
            <form method="POST" action="{{ route('incident-monitoring.bulk-exclusions.preview', [], false) }}" class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(240px,1fr)]">
                @csrf
                <label>
                    <span class="mb-1.5 block text-xs font-extrabold uppercase tracking-wider text-ink-500">Daftar nomor incident</span>
                    <textarea name="incidents_text" required rows="9" placeholder="INC-12345&#10;INC-67890" class="w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-900">{{ old('incidents_text', $incidentsText) }}</textarea>
                    @error('incidents_text')<span class="mt-1 block text-sm text-red-700 dark:text-red-300" role="alert">{{ $message }}</span>@enderror
                    @error('incidents')<span class="mt-1 block text-sm text-red-700 dark:text-red-300" role="alert">{{ $message }}</span>@enderror
                </label>
                <div class="space-y-4">
                    <label class="block">
                        <span class="mb-1.5 block text-xs font-extrabold uppercase tracking-wider text-ink-500">Alasan exclude</span>
                        <select name="reason" required class="min-h-11 w-full rounded-md border border-ink-200 bg-white px-3 text-sm shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-900">
                            <option value="">Pilih alasan</option>
                            @foreach ($excludeReasons as $value => $label)
                                <option value="{{ $value }}" @selected(old('reason', $selectedReason) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('reason')<span class="mt-1 block text-sm text-red-700 dark:text-red-300" role="alert">Pilih alasan exclude yang tersedia.</span>@enderror
                    </label>
                    <div class="rounded-md bg-ink-50 p-4 text-sm leading-6 text-ink-600 dark:bg-ink-800/60 dark:text-ink-300">
                        Sistem akan memeriksa ulang tiket Dompis, status LOP termasuk arsip, status exclude, jenis/tanggal tiket, dan scope akun sebelum menyimpan.
                    </div>
                    <button type="submit" class="min-h-11 w-full rounded-md bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-brand-500">Tinjau daftar</button>
                </div>
            </form>
        </section>
    @else
        @php
            $previewLabels = [
                'eligible' => ['Siap di-exclude', 'success'],
                'already_excluded' => ['Sudah di-exclude', 'neutral'],
                'has_lop' => ['Sudah memiliki LOP', 'info'],
                'not_found' => ['Tidak ditemukan / di luar periode', 'warning'],
                'out_of_scope' => ['Di luar scope akun', 'warning'],
            ];
        @endphp
        <section aria-labelledby="bulk-preview-title" class="space-y-4 rounded-md border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="bulk-preview-title" class="text-base font-semibold text-ink-900 dark:text-white">Hasil pencocokan</h2>
                    <p class="mt-1 text-sm text-ink-600 dark:text-ink-300">Alasan yang dipilih: <strong>{{ $excludeReasons[$selectedReason] ?? $selectedReason }}</strong>. Hanya kategori <strong>Siap di-exclude</strong> yang akan disimpan.</p>
                </div>
                <a href="{{ route('incident-monitoring.bulk-exclusions', [], false) }}" class="inline-flex min-h-10 items-center rounded-md border border-ink-200 px-3 text-sm font-semibold text-ink-700 hover:bg-ink-50 dark:border-ink-700 dark:text-ink-200 dark:hover:bg-ink-800">Ubah daftar</a>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                @foreach ($previewLabels as $key => [$label, $variant])
                    <div class="rounded-md border border-ink-100 p-4 dark:border-ink-800">
                        <p class="text-xs font-semibold text-ink-500 dark:text-ink-400">{{ $label }}</p>
                        <p class="mt-2 text-2xl font-bold tabular-nums text-ink-900 dark:text-white">{{ count($preview[$key]) }}</p>
                    </div>
                @endforeach
            </div>

            <div class="space-y-2">
                @foreach ($previewLabels as $key => [$label, $variant])
                    @if (count($preview[$key]) > 0)
                        <details class="rounded-md border border-ink-100 dark:border-ink-800">
                            <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-ink-800 dark:text-ink-100">{{ $label }} · {{ count($preview[$key]) }}</summary>
                            <ul class="max-h-52 space-y-1 overflow-y-auto border-t border-ink-100 px-4 py-3 text-sm text-ink-600 dark:border-ink-800 dark:text-ink-300">
                                @foreach ($preview[$key] as $incident)<li class="font-mono">{{ $incident }}</li>@endforeach
                            </ul>
                        </details>
                    @endif
                @endforeach
            </div>

            <form id="apply-bulk-incident-exclude" x-ref="bulkForm" method="POST" action="{{ route('incident-monitoring.bulk-exclusions.apply', [], false) }}">
                @csrf
                <input type="hidden" name="incidents_text" value="{{ $incidentsText }}">
                <input type="hidden" name="reason" value="{{ $selectedReason }}">
                <button type="button" @click="$refs.bulkExcludeConfirm.showModal()" @disabled(count($preview['eligible']) === 0) class="min-h-11 rounded-md bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-brand-500 disabled:cursor-not-allowed disabled:opacity-50">Exclude {{ count($preview['eligible']) }} tiket</button>
            </form>

            <dialog x-ref="bulkExcludeConfirm" aria-labelledby="bulk-exclude-title" aria-describedby="bulk-exclude-description" @click.self="$el.close()"
                    class="fixed inset-0 m-auto max-h-[90vh] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-md border border-ink-200 bg-white p-0 text-ink-900 shadow-2xl backdrop:bg-ink-950/60 dark:border-ink-700 dark:bg-ink-900 dark:text-white">
                <div class="p-6">
                    <h2 id="bulk-exclude-title" class="text-lg font-extrabold">Konfirmasi exclude massal</h2>
                    <p id="bulk-exclude-description" class="mt-2 text-sm leading-6 text-ink-600 dark:text-ink-300">{{ count($preview['eligible']) }} tiket akan di-exclude dengan alasan <strong>{{ $excludeReasons[$selectedReason] ?? $selectedReason }}</strong>. Sistem memeriksa ulang kelayakan dan scope sebelum menyimpan.</p>
                    <div class="mt-6 grid grid-cols-2 gap-3">
                        <form method="dialog"><button type="submit" class="min-h-11 w-full rounded-md border border-ink-200 text-sm font-bold text-ink-700 hover:bg-ink-50 dark:border-ink-700 dark:text-ink-200 dark:hover:bg-ink-800">Batal</button></form>
                        <button type="button" @click="$refs.bulkForm.requestSubmit()" class="min-h-11 rounded-md bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-brand-500">Ya, exclude tiket</button>
                    </div>
                </div>
            </dialog>
        </section>
    @endif
</div>
@endsection
