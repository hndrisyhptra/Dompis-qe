@extends('layouts.app')

@section('title', 'Edit LOP')

@section('content')
@php
    $initial = [
        'incident' => old('incident', $lop->incident), 'sto' => old('sto', $lop->sto),
        'branch' => old('branch', $lop->branch), 'area' => old('area', $lop->area ?? '3'),
        'segment' => old('segment', $lop->segment?->value ?? ''),
        'wbs_type' => old('wbs_type', $lop->wbs_type->value),
        'budget_type' => old('budget_type', $lop->budget_type?->value ?? ''),
        'job_description' => old('job_description', $lop->job_description),
        'ihld_id' => old('ihld_id', $lop->ihld_id),
        'nama_lop' => old('nama_lop', $lop->nama_lop),
    ];
    $formAction = route('lop.update', $lop);
    $formMethod = 'PUT';
    $submitLabel = 'Simpan Perubahan';
    $cancelUrl = route('lop.index');
@endphp

<div class="mx-auto max-w-5xl">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="{{ route('lop.index') }}" class="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 hover:text-brand-600 dark:text-ink-400">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg> Kembali ke detail
            </a>
            <h1 class="text-2xl font-bold tracking-tight text-ink-900 dark:text-white">Edit Data LOP</h1>
            <p class="mt-1.5 text-sm text-ink-500 dark:text-ink-400"><span class="font-semibold text-ink-700 dark:text-ink-200">{{ $lop->incident }}</span> · {{ $lop->nama_lop }}</p>
        </div>
        @can('manage-master-data')
            <a href="{{ route('lop-name-format.edit') }}" class="inline-flex items-center justify-center rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm font-semibold text-ink-700 shadow-sm transition hover:border-brand-300 hover:text-brand-700 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200">Atur Format Nama</a>
        @endcan
    </div>
    @include('lop._form')
</div>
@endsection
