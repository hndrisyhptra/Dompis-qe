@extends('layouts.app')

@section('title', 'Format Nama LOP')

@section('content')
@php
    $sample = [
        'incident' => 'INC123456', 'sto' => 'SDA', 'branch' => 'SIDOARJO',
        'area' => '3', 'segment' => 'odp', 'program_type' => 'recovery',
        'budget_type' => '', 'job_description' => 'Penggantian BOX ODP',
        'ihld_id' => '', 'nama_lop' => '',
    ];
@endphp

<div class="mx-auto max-w-5xl" x-data="lopForm({ initial: @js($sample), template: @js(old('template', $template)), programCodes: @js($programCodes) })" x-init="$watch('template', () => regenerateName(true))">
    <div class="mb-6">
        <a href="{{ route('lop.create') }}" class="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 hover:text-brand-600 dark:text-ink-400">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg> Kembali ke Input LOP
        </a>
        <h1 class="text-2xl font-bold tracking-tight text-ink-900 dark:text-white">Format Nama LOP</h1>
        <p class="mt-1.5 max-w-2xl text-sm text-ink-500 dark:text-ink-400">Susun token sesuai standar penamaan. Format baru hanya digunakan saat membuat atau melakukan generate ulang nama LOP.</p>
    </div>

    <form method="POST" action="{{ route('lop-name-format.update') }}" class="grid gap-5 lg:grid-cols-[1fr_320px]">
        @csrf
        @method('PUT')

        <section class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900 sm:p-6">
            <div class="mb-5 flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-white">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 12h9.75m-9.75 6h9.75M3.75 6h.008v.008H3.75V6zm0 6h.008v.008H3.75V12zm0 6h.008v.008H3.75V18z" /></svg>
                </span>
                <div><h2 class="font-semibold text-ink-900 dark:text-white">Susunan format aktif</h2><p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">Gunakan underscore atau karakter lain sebagai pemisah.</p></div>
            </div>

            <label for="template" class="mb-1.5 block text-sm font-medium text-ink-700 dark:text-ink-300">Template</label>
            <textarea id="template" name="template" rows="4" x-model="template" class="w-full rounded-xl border border-ink-200 bg-ink-50 px-4 py-3 font-mono text-sm font-semibold text-ink-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-ink-700 dark:bg-ink-800 dark:text-white"></textarea>
            @error('template')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror

            <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/30">
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Preview contoh</p>
                <p class="mt-2 break-all font-mono text-sm font-bold text-emerald-900 dark:text-emerald-100" x-text="form.nama_lop || 'Lengkapi format untuk melihat preview'"></p>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('lop.create') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold text-ink-600 hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800">Batal</a>
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-brand-600/20 hover:bg-brand-700">Simpan Format</button>
            </div>
        </section>

        <aside class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900">
            <h2 class="font-semibold text-ink-900 dark:text-white">Token tersedia</h2>
            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Klik token untuk menambahkannya di akhir format.</p>
            <div class="mt-4 space-y-2">
                @foreach ($tokens as $token => $description)
                    <button type="button" @click="template += '{{ $token }}'" class="flex w-full items-center justify-between gap-3 rounded-xl border border-ink-100 px-3 py-2.5 text-left transition hover:border-brand-300 hover:bg-brand-50 dark:border-ink-700 dark:hover:bg-brand-950/30">
                        <code class="text-xs font-bold text-brand-700 dark:text-brand-300">{{ $token }}</code>
                        <span class="text-right text-xs text-ink-500 dark:text-ink-400">{{ $description }}</span>
                    </button>
                @endforeach
            </div>
            <div class="mt-4 rounded-xl bg-amber-50 p-3 text-xs leading-relaxed text-amber-800 dark:bg-amber-950/30 dark:text-amber-300">Token <code>{incident}</code> wajib ada agar setiap nama tetap mudah ditelusuri.</div>
        </aside>
    </form>
</div>
@endsection
