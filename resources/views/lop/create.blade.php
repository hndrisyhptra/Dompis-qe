@extends('layouts.app')

@section('title', 'Input LOP Baru')

@section('content')
@php
    $initial = [
        'incident' => old('incident', ''), 'sto' => old('sto', ''),
        'branch' => old('branch', ''), 'area' => old('area', '3'),
        'segment' => old('segment', ''), 'segments' => old('segment', old('segments', [])),
        'program_type' => old('program_type', ''),
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

<div class="mx-auto max-w-6xl">
    <div class="mb-6 rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900 sm:p-6">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600 dark:text-brand-400">Bulk Import Data</p>
            <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-ink-900 dark:text-white">Input LOP Baru</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-ink-500 dark:text-ink-400">Masukkan identitas, lokasi, dan detail pekerjaan. Nama LOP dibuat otomatis dan dapat ditinjau sebelum disimpan.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('bulk-import.lop.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-ink-200 bg-white px-4 text-sm font-bold text-ink-700 transition hover:border-brand-300 hover:text-brand-700 dark:border-ink-700 dark:bg-ink-800 dark:text-ink-200">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                Bulk Import LOP
            </a>
        @can('manage-master-data')
            <a href="{{ route('lop-name-format.edit') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm font-semibold text-ink-700 shadow-sm transition hover:border-brand-300 hover:text-brand-700 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 12h9.75m-9.75 6h9.75M3.75 6h.008v.008H3.75V6zm0 6h.008v.008H3.75V12zm0 6h.008v.008H3.75V18z" /></svg>
                Atur Format Nama
            </a>
        @endcan
        </div>
        </div>
    </div>
    @include('lop._form')
</div>
@endsection
