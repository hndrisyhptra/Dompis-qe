@extends('layouts.technician')

@section('title', 'Dashboard Teknisi')
@section('header', 'Dashboard Teknisi')

@section('content')
<section class="rounded-3xl bg-ink-900 p-5 text-white shadow-xl shadow-ink-900/10 dark:bg-ink-900">
    <p class="text-xs font-medium text-ink-300">Semangat Pagi...!</p>
    <h1 class="mt-1 text-xl font-extrabold">{{ auth()->user()->name }}</h1>
    <p class="mt-2 max-w-xs text-xs leading-5 text-ink-300">Pastikan lokasi dan evidence setiap tahap lengkap sebelum project diajukan.</p>
    <div class="mt-5 grid grid-cols-3 gap-2">
        <div class="rounded-2xl bg-white/8 p-3"><p class="text-xl font-extrabold">{{ $activeCount }}</p><p class="mt-1 text-[10px] text-ink-300">Aktif</p></div>
        <div class="rounded-2xl bg-white/8 p-3"><p class="text-xl font-extrabold">{{ $approvalCount }}</p><p class="mt-1 text-[10px] text-ink-300">Approval</p></div>
        <div class="rounded-2xl {{ $attentionCount ? 'bg-brand-600' : 'bg-white/8' }} p-3"><p class="text-xl font-extrabold">{{ $attentionCount }}</p><p class="mt-1 text-[10px] text-ink-200">Perlu aksi</p></div>
    </div>
</section>

<section class="mt-6">
    <div class="mb-3 flex items-end justify-between">
        <div><p class="text-[11px] font-bold uppercase tracking-[.14em] text-brand-600 dark:text-brand-400">Prioritas hari ini</p><h2 class="mt-1 text-lg font-extrabold">Project aktif</h2></div>
        <a href="{{ route('technician.inbox') }}" class="text-xs font-bold text-brand-600 dark:text-brand-400">Lihat semua</a>
    </div>
    <div class="space-y-3">
        @forelse ($projects as $project)
            <x-technician-project-card :project="$project" />
        @empty
            <div class="rounded-2xl border border-dashed border-ink-200 bg-white px-6 py-10 text-center dark:border-ink-700 dark:bg-ink-900">
                <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-300">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                </div>
                <h3 class="mt-3 text-sm font-bold">Tidak ada project aktif</h3>
                <p class="mt-1 text-xs text-ink-500">Assignment baru akan muncul di sini.</p>
            </div>
        @endforelse
    </div>
</section>

<section class="mt-6 grid grid-cols-2 gap-3">
    <a href="{{ route('technician.inbox', ['tab' => 'complete']) }}" class="rounded-2xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
        <span class="grid h-9 w-9 place-items-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751"/></svg></span>
        <p class="mt-3 text-lg font-extrabold">{{ $completedCount }}</p><p class="text-xs text-ink-500">Project selesai</p>
    </a>
    <a href="{{ route('technician.notifications') }}" class="rounded-2xl border border-ink-100 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
        <span class="grid h-9 w-9 place-items-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022"/></svg></span>
        <p class="mt-3 text-lg font-extrabold">{{ $unreadCount }}</p><p class="text-xs text-ink-500">Notif belum dibaca</p>
    </a>
</section>
@endsection
