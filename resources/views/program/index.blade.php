@extends('layouts.app')

@section('title', 'Pemetaan Program')

@section('content')
@php
    $carry = array_filter([
        'region' => $regionFilter,
        'branch' => $branchFilter,
    ], fn ($v) => $v !== '' && $v !== null);
@endphp
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <p class="text-xs font-bold uppercase tracking-[.16em] text-brand-600 dark:text-brand-400">Monitoring · {{ $scopeLabel }}</p>
        <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-ink-900 dark:text-white">Pemetaan Program</h1>
        <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Sebaran LOP berdasarkan jenis Program dan status pekerjaannya.</p>
    </div>

    @if ($scopeWarning)
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300">
            Akun Anda belum terhubung ke branch, jadi tidak ada data Program yang bisa ditampilkan. Hubungi admin untuk mengatur branch akun Anda.
        </div>
    @endif

    {{-- Filter region / branch: hanya SUPER_ADMIN (role lain terkunci ke branch akun) --}}
    @if ($canFilterLocation)
        <form method="GET" action="{{ route('program.index') }}" class="mb-6 grid gap-3 sm:grid-cols-[200px_200px_auto]">
            <select name="region" onchange="this.form.branch.value=''; this.form.submit()"
                    class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm dark:border-ink-700 dark:bg-ink-800">
                <option value="">Semua region</option>
                @foreach ($regions as $region)
                    <option value="{{ $region }}" @selected($regionFilter === $region)>{{ $region }}</option>
                @endforeach
            </select>
            <select name="branch" onchange="this.form.submit()"
                    class="min-h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm dark:border-ink-700 dark:bg-ink-800">
                <option value="">Semua branch</option>
                @foreach ($branches->when($regionFilter, fn ($items) => $items->where('region', $regionFilter)) as $branch)
                    <option value="{{ $branch->name }}" @selected($branchFilter === $branch->name)>{{ $branch->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button class="min-h-10 rounded-xl bg-ink-900 px-4 text-sm font-bold text-white dark:bg-brand-600">Terapkan</button>
                @if ($regionFilter || $branchFilter)
                    <a href="{{ route('program.index') }}" class="grid min-h-10 place-items-center rounded-xl border border-ink-200 px-3 text-sm font-bold text-ink-500 dark:border-ink-700">Reset</a>
                @endif
            </div>
        </form>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($programSummaries as $program)
            <div class="flex flex-col rounded-2xl border border-ink-100 bg-white p-5 shadow-sm dark:border-ink-800 dark:bg-ink-900">
                <div class="flex items-baseline justify-between gap-2">
                    <h2 class="font-bold text-ink-900 dark:text-white">{{ $program['label'] }}</h2>
                    <span class="text-2xl font-extrabold text-ink-900 dark:text-white">{{ $program['total'] }}</span>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                    @foreach ($program['buckets'] as $key => $count)
                        <div class="flex items-center justify-between">
                            <dt class="text-ink-500 dark:text-ink-400">{{ \App\Http\Controllers\ProgramController::BUCKETS[$key]['label'] }}</dt>
                            <dd class="font-bold text-ink-800 dark:text-ink-200">{{ $count }}</dd>
                        </div>
                    @endforeach
                </dl>
                <a href="{{ route('program.show', [$program['slug'], ...$carry]) }}"
                   class="mt-5 inline-flex items-center justify-center gap-1.5 rounded-xl bg-ink-900 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-ink-800 dark:bg-brand-600 dark:hover:bg-brand-700">
                    Lihat detail
                </a>
            </div>
        @endforeach
    </div>
</div>
@endsection
