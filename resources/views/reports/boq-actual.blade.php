@extends('layouts.app')

@section('title', $title)

@section('content')
@php $reportRoute = 'reports.boq-actual'; @endphp

<div class="mx-auto max-w-7xl space-y-4 @if ($print) report-print @endif">

    {{-- Header --}}
    <div class="no-print flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-ink-900 dark:text-white">{{ $title }}</h1>
            <p class="mt-1 max-w-xl text-sm text-ink-500 dark:text-ink-400">Material yang benar-benar terpakai per LOP{{ $priced ? ', dinilai dengan '.$package?->name.'.' : '.' }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-xl border border-ink-200 p-0.5 dark:border-ink-700">
                @foreach (['per_lop' => 'Per LOP', 'rekap' => 'Rekap Designator'] as $val => $label)
                    <a href="{{ route($reportRoute, array_merge(request()->query(), ['view' => $val, 'page' => null])) }}"
                       class="rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $mode === $val ? 'bg-brand-600 text-white shadow-sm' : 'text-ink-500 hover:text-ink-800 dark:text-ink-300' }}">{{ $label }}</a>
                @endforeach
            </div>
            <x-badge variant="{{ $priced ? 'success' : 'neutral' }}">{{ $priced ? 'Harga: '.$package?->name : 'Tanpa harga' }}</x-badge>
            <x-badge variant="neutral">{{ $scopeLabel }}</x-badge>
        </div>
    </div>

    @if ($print)
        <div class="mb-2">
            <h1 class="text-lg font-bold">{{ $title }} — {{ $mode === 'rekap' ? 'Rekap Designator' : 'Rincian per LOP' }}</h1>
            <p class="text-xs">Lingkup: {{ $scopeLabel }}@if ($priced) · Paket KHS: {{ $package?->name }}@endif · Dicetak {{ now()->format('d M Y H:i') }}</p>
        </div>
    @endif

    @if ($scopeWarning)
        <div class="no-print rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300">
            Branch akun Anda belum dikonfigurasi — tidak ada data yang bisa ditampilkan. Hubungi admin.
        </div>
    @endif

    @include('reports._summary', ['report' => $report, 'data' => $data, 'priced' => $priced])

    @include('reports._filters')

    @if ($mode === 'rekap')
        @include('reports._rekap', ['report' => $report, 'data' => $data, 'priced' => $priced])
    @else
        @include('reports._per-lop', ['report' => $report, 'data' => $data, 'priced' => $priced])
    @endif
</div>

@if ($print)
    <script>window.addEventListener('load', () => window.print());</script>
@endif
@endsection
