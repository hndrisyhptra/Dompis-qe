@extends('layouts.guest')

@section('title', 'Sesi perlu diperbarui')

@section('content')
<main class="flex min-h-screen items-center justify-center px-5 py-10">
    <section class="w-full max-w-md rounded-md border border-ink-200 bg-white p-6 shadow-sm dark:border-ink-800 dark:bg-ink-900">
        <p class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Sesi keamanan</p>
        <h1 class="mt-2 text-xl font-bold text-ink-900 dark:text-white">Halaman perlu diperbarui</h1>
        <p class="mt-3 text-sm leading-6 text-ink-600 dark:text-ink-300">{{ $message }}</p>
        <p class="mt-3 rounded-md bg-ink-50 p-3 text-xs leading-5 text-ink-500 dark:bg-ink-800 dark:text-ink-400">Data pada permintaan ini belum disimpan. Untuk keamanan, aplikasi tidak mengirim ulang form atau file secara otomatis.</p>
        <a href="{{ $recoveryUrl }}" class="mt-5 inline-flex min-h-11 w-full items-center justify-center rounded-md bg-brand-600 px-4 text-sm font-bold text-white hover:bg-brand-700">{{ $recoveryLabel }}</a>
    </section>
</main>
@endsection
