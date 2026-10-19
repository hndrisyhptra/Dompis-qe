@extends('layouts.app')

@section('title', 'Detail LOP '.$lop->incident)

@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <header>
        <a href="{{ $returnUrl }}" class="inline-flex min-h-10 items-center gap-2 rounded-md border border-ink-200 bg-white px-3 text-xs font-bold text-ink-600 hover:bg-ink-50 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-300">← Kembali ke halaman asal</a>
        <h1 class="mt-4 text-xl font-extrabold text-ink-900 dark:text-white">Detail LOP</h1>
        <p class="mt-1 text-xs text-ink-500">Informasi pekerjaan untuk LOP yang dipilih.</p>
    </header>
    <x-lop-detail-modal id="lop-record-detail" :lop="$lop" :standalone="true" />
</div>
@endsection
