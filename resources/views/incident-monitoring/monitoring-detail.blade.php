@extends('layouts.app')

@section('title', 'Detail '.$cellLabel.' · Monitoring Tiket')

@section('content')
    <style>
        .incident-ticket-table { border-collapse: separate; border-spacing: 0; }
        .incident-ticket-table th:not(:last-child),
        .incident-ticket-table td:not(:last-child) { border-right: 1px solid rgb(226 232 240); }
        .incident-ticket-table tbody tr:not(:last-child) > td { border-bottom: 1px solid rgb(226 232 240); }
        .dark .incident-ticket-table th:not(:last-child),
        .dark .incident-ticket-table td:not(:last-child) { border-right-color: rgb(55 65 81); }
        .dark .incident-ticket-table tbody tr:not(:last-child) > td { border-bottom-color: rgb(55 65 81); }
    </style>
    <div class="mx-auto max-w-7xl space-y-4">
        <a href="{{ route('incident-monitoring.exclusions') }}" class="inline-flex min-h-10 items-center gap-1 text-sm font-semibold text-brand-600 hover:underline focus-visible:outline-2 focus-visible:outline-brand-500 dark:text-brand-300">← Kembali ke Monitoring Incident</a>
        <section class="rounded-md border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900 sm:p-5">
            @include('incident-monitoring._monitoring-detail')
        </section>
    </div>
@endsection
