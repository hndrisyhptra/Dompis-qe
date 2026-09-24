@extends('layouts.app')

@section('title', 'Hasil Import')

@section('content')
@php($variant = match($batch->status) { 'completed' => 'success', 'partial' => 'warning', 'failed' => 'danger', 'processing' => 'info', default => 'neutral' })
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-xs font-bold uppercase tracking-[.16em] text-brand-600">Import Result</p><h1 class="mt-1 text-2xl font-extrabold text-ink-900 dark:text-white">{{ $batch->original_name }}</h1><p class="mt-2 text-sm text-ink-500">ID batch {{ $batch->uuid }}</p></div>
        <a href="{{ $batch->type === 'boq' ? route('bulk-import.boq.index') : route('bulk-import.lop.index') }}" class="inline-flex min-h-11 items-center rounded-xl border border-ink-200 bg-white px-4 text-sm font-bold text-ink-700 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-200">Kembali</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900"><p class="text-xs font-bold uppercase text-ink-400">Status</p><div class="mt-3"><x-badge :variant="$variant">{{ ucfirst($batch->status) }}</x-badge></div></div>
        <div class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900"><p class="text-xs font-bold uppercase text-ink-400">Total Baris</p><p class="mt-2 text-2xl font-extrabold">{{ $batch->total_rows }}</p></div>
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-5 dark:border-emerald-900 dark:bg-emerald-950/20"><p class="text-xs font-bold uppercase text-emerald-600">Berhasil</p><p class="mt-2 text-2xl font-extrabold text-emerald-700">{{ $batch->success_rows }}</p></div>
        <div class="rounded-2xl border border-red-100 bg-red-50/60 p-5 dark:border-red-900 dark:bg-red-950/20"><p class="text-xs font-bold uppercase text-red-600">Gagal</p><p class="mt-2 text-2xl font-extrabold text-red-700">{{ $batch->failed_rows }}</p></div>
    </div>

    @if($batch->type === 'boq' && data_get($batch->metadata, 'project_name'))
        <div class="grid gap-3 rounded-2xl border border-brand-200 bg-brand-50/60 p-4 dark:border-brand-900/60 dark:bg-brand-950/20 sm:grid-cols-[1fr_auto] sm:items-center sm:p-5">
            <div class="min-w-0">
                <p class="text-[11px] font-extrabold uppercase tracking-[.14em] text-brand-600 dark:text-brand-400">Hasil deteksi file</p>
                <p class="mt-1 truncate text-sm font-bold text-ink-900 dark:text-white">{{ data_get($batch->metadata, 'project_name') }}</p>
                <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Nama PROJECT berhasil dicocokkan dengan Data LOP.</p>
            </div>
            <div class="rounded-xl border border-brand-200 bg-white px-4 py-3 dark:border-brand-900/60 dark:bg-ink-900">
                <p class="text-[10px] font-bold uppercase tracking-wide text-ink-400">Paket KHS</p>
                <p class="mt-0.5 text-sm font-extrabold text-brand-700 dark:text-brand-300">PAKET-{{ data_get($batch->metadata, 'package_code') }}</p>
            </div>
        </div>
    @endif

    @include('imports._live-progress')
    @if($batch->error_message)<div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $batch->error_message }}</div>@endif

    <div class="overflow-hidden rounded-2xl border border-ink-100 bg-white shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <div class="border-b border-ink-100 px-5 py-4 dark:border-ink-800"><h2 class="font-bold">Detail per Baris</h2></div>
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-ink-100 text-sm dark:divide-ink-800"><thead class="bg-ink-50 text-left text-[11px] uppercase text-ink-500 dark:bg-ink-950/50"><tr><th class="px-5 py-3">Baris</th><th class="px-5 py-3">Referensi</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Keterangan</th></tr></thead><tbody class="divide-y divide-ink-100 dark:divide-ink-800">@forelse($rows as $row)<tr><td class="px-5 py-4 font-mono text-xs">{{ $row->row_number }}</td><td class="px-5 py-4 font-semibold">{{ $row->reference ?: '—' }}</td><td class="px-5 py-4"><x-badge :variant="$row->status === 'success' ? 'success' : 'danger'">{{ ucfirst($row->status) }}</x-badge></td><td class="max-w-xl px-5 py-4 text-xs leading-5 text-ink-500">{{ $row->message }}</td></tr>@empty<tr><td colspan="4" class="px-5 py-12 text-center text-ink-400">Hasil baris belum tersedia.</td></tr>@endforelse</tbody></table></div>
    </div>
    {{ $rows->links() }}
</div>
@endsection
