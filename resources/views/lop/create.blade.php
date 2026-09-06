@extends('layouts.app')

@section('title', 'Input LOP Baru')

@section('content')
@php
    $initial = [
        'incident' => old('incident', ''), 'sto' => old('sto', ''),
        'branch' => old('branch', ''), 'area' => old('area', '3'),
        'segment' => old('segment', ''), 'program_type' => old('program_type', ''),
        'budget_type' => old('budget_type', ''),
        'job_description' => old('job_description', ''),
        'ticket_summary' => old('ticket_summary', ''),
        'datek' => old('datek', []),
        'ihld_id' => old('ihld_id', ''), 'nama_lop' => old('nama_lop', ''),
    ];
    $formAction = route('lop.store');
    $formMethod = 'POST';
    $submitLabel = 'Simpan LOP';
    $cancelUrl = route('lop.index');
@endphp

<div class="mx-auto max-w-5xl">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-brand-600 dark:text-brand-400">
                <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span> Administrasi Project
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-ink-900 dark:text-white">Input LOP Baru</h1>
            <p class="mt-1.5 max-w-2xl text-sm text-ink-500 dark:text-ink-400">Lengkapi informasi pekerjaan. Nama LOP akan tersusun otomatis dan tetap bisa Anda sesuaikan sebelum disimpan.</p>
        </div>
        @can('manage-master-data')
            <a href="{{ route('lop-name-format.edit') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm font-semibold text-ink-700 shadow-sm transition hover:border-brand-300 hover:text-brand-700 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 12h9.75m-9.75 6h9.75M3.75 6h.008v.008H3.75V6zm0 6h.008v.008H3.75V12zm0 6h.008v.008H3.75V18z" /></svg>
                Atur Format Nama
            </a>
        @endcan
    </div>
    @include('lop._form')
</div>
@endsection
