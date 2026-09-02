@extends('layouts.app')

@section('title', 'Master Data')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Master Data</h1>
        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">Pusat pengelolaan data referensi aplikasi. Hanya untuk SUPER_ADMIN.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($entities as $entity)
            <a href="{{ route($entity['route']) }}"
               class="group flex flex-col justify-between rounded-2xl border border-ink-100 bg-white p-5 shadow-sm transition hover:border-brand-300 hover:shadow-md dark:border-ink-800 dark:bg-ink-900 dark:hover:border-brand-700">
                <div>
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="font-semibold text-ink-900 dark:text-ink-50">{{ $entity['label'] }}</h2>
                        @if (! is_null($entity['count']))
                            <x-badge variant="neutral">{{ number_format($entity['count']) }}</x-badge>
                        @endif
                    </div>
                    <p class="mt-1.5 text-xs text-ink-500 dark:text-ink-400">{{ $entity['desc'] }}</p>
                </div>
                <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-600 group-hover:gap-2 dark:text-brand-400">
                    Kelola
                    <svg class="h-4 w-4 transition-all" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                </span>
            </a>
        @endforeach
    </div>
</div>
@endsection
