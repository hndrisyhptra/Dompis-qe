@extends('layouts.technician')

@section('title', 'Notifikasi')
@section('header', 'Notifikasi')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <div><h1 class="text-lg font-extrabold">Aktivitas terbaru</h1><p class="text-xs text-ink-500">Update assignment dan review evidence.</p></div>
    <form method="POST" action="{{ route('technician.notifications.read-all') }}">@csrf<button class="text-xs font-bold text-brand-600 dark:text-brand-400">Baca semua</button></form>
</div>
<div class="space-y-3">
    @forelse ($notifications as $notification)
        <form method="POST" action="{{ route('technician.notifications.read', $notification->id) }}">@csrf
            <button class="w-full rounded-2xl border p-4 text-left {{ $notification->read_at ? 'border-ink-100 bg-white dark:border-ink-800 dark:bg-ink-900' : 'border-brand-200 bg-brand-50/60 dark:border-brand-900 dark:bg-brand-900/10' }}">
                <div class="flex gap-3"><span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $notification->read_at ? 'bg-ink-200 dark:bg-ink-700' : 'bg-brand-500' }}"></span><div><p class="text-sm font-bold">{{ $notification->data['title'] ?? 'Aktivitas project' }}</p><p class="mt-1 text-xs leading-5 text-ink-500 dark:text-ink-400">{{ $notification->data['message'] ?? '' }}</p><p class="mt-2 text-[10px] font-medium text-ink-400">{{ $notification->created_at->diffForHumans() }}</p></div></div>
            </button>
        </form>
    @empty
        <div class="rounded-2xl border border-dashed border-ink-200 py-12 text-center dark:border-ink-700"><p class="text-sm font-bold">Belum ada notifikasi</p><p class="mt-1 text-xs text-ink-500">Update project akan muncul di sini.</p></div>
    @endforelse
</div>
<div class="mt-5">{{ $notifications->links() }}</div>
@endsection
