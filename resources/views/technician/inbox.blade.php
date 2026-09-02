@extends('layouts.technician')

@section('title', 'Inbox Project')
@section('header', 'Inbox Project')

@section('content')
<div class="flex rounded-2xl bg-ink-100 p-1 dark:bg-ink-900">
    <a href="{{ route('technician.inbox', ['tab' => 'active']) }}" class="flex-1 rounded-xl px-4 py-2.5 text-center text-xs font-bold {{ $tab === 'active' ? 'bg-white text-brand-600 shadow-sm dark:bg-ink-800 dark:text-brand-400' : 'text-ink-500' }}">Active</a>
    <a href="{{ route('technician.inbox', ['tab' => 'complete']) }}" class="flex-1 rounded-xl px-4 py-2.5 text-center text-xs font-bold {{ $tab === 'complete' ? 'bg-white text-brand-600 shadow-sm dark:bg-ink-800 dark:text-brand-400' : 'text-ink-500' }}">Complete</a>
</div>

<form method="GET" class="mt-4 space-y-3">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="relative">
        <svg class="absolute left-3.5 top-3.5 h-4 w-4 text-ink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z"/></svg>
        <input name="q" value="{{ $search }}" placeholder="Cari incident, nama project, atau STO..." class="min-h-11 w-full rounded-xl border border-ink-100 bg-white pl-10 pr-4 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-ink-800 dark:bg-ink-900">
    </div>
    @if ($tab === 'active')
        <select name="status" onchange="this.form.submit()" class="min-h-11 w-full rounded-xl border border-ink-100 bg-white px-3 text-sm dark:border-ink-800 dark:bg-ink-900">
            <option value="">Semua status aktif</option>
            @foreach (\App\Enums\LopStatus::cases() as $status)
                @if ($status !== \App\Enums\LopStatus::COMPLETED)<option value="{{ $status->value }}" @selected($statusFilter === $status->value)>{{ $status->label() }}</option>@endif
            @endforeach
        </select>
    @endif
</form>

<div class="mt-5 space-y-3">
    @forelse ($projects as $project)
        <x-technician-project-card :project="$project" />
    @empty
        <div class="rounded-2xl border border-dashed border-ink-200 bg-white px-6 py-10 text-center dark:border-ink-700 dark:bg-ink-900">
            <p class="text-sm font-bold">Project tidak ditemukan</p>
            <p class="mt-1 text-xs text-ink-500">Ubah kata kunci atau filter pencarian.</p>
        </div>
    @endforelse
</div>

<div class="mt-5">{{ $projects->links() }}</div>
@endsection
