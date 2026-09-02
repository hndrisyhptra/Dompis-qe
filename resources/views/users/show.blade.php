@extends('layouts.app')

@section('title', $user->name)

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">{{ $user->name }}</h1>
            <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">
                {{ $user->username }} ·
                <x-badge variant="info">{{ $user->role?->name ?? '—' }}</x-badge>
                <x-badge :variant="$user->isActive() ? 'success' : 'neutral'">
                    {{ $user->isActive() ? 'Aktif' : 'Nonaktif' }}
                </x-badge>
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('users.edit', $user) }}">
                <x-button type="button" class="!w-auto px-4">Edit</x-button>
            </a>

            @if ($user->isActive())
                <form method="POST" action="{{ route('users.deactivate', $user) }}"
                      onsubmit="return confirm('Nonaktifkan user ini?');">
                    @csrf
                    <button type="submit" class="rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-4 py-2.5 text-sm font-medium text-ink-700 dark:text-ink-200 hover:bg-ink-50 dark:hover:bg-ink-700 transition">
                        Nonaktifkan
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('users.activate', $user) }}"
                      onsubmit="return confirm('Aktifkan user ini?');">
                    @csrf
                    <button type="submit" class="rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-4 py-2.5 text-sm font-medium text-ink-700 dark:text-ink-200 hover:bg-ink-50 dark:hover:bg-ink-700 transition">
                        Aktifkan
                    </button>
                </form>
            @endif

            <form method="POST" action="{{ route('users.destroy', $user) }}"
                  onsubmit="return confirm('Hapus user ini? Data masih bisa dipulihkan oleh Super Admin.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg border border-brand-200 dark:border-brand-800 bg-white dark:bg-ink-800 px-4 py-2.5 text-sm font-medium text-brand-600 dark:text-brand-400 hover:bg-brand-50 dark:hover:bg-brand-900/30 transition">
                    Hapus
                </button>
            </form>
        </div>
    </div>

    <x-card>
        <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50 mb-4">Detail Akun</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
            <div>
                <dt class="text-ink-500 dark:text-ink-400">NIK</dt>
                <dd class="text-ink-900 dark:text-ink-50 mt-0.5">{{ $user->nik ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-ink-500 dark:text-ink-400">Email</dt>
                <dd class="text-ink-900 dark:text-ink-50 mt-0.5">{{ $user->email ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-ink-500 dark:text-ink-400">No. Telepon</dt>
                <dd class="text-ink-900 dark:text-ink-50 mt-0.5">{{ $user->phone ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-ink-500 dark:text-ink-400">Branch</dt>
                <dd class="text-ink-900 dark:text-ink-50 mt-0.5">{{ $user->branch?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-ink-500 dark:text-ink-400">Login Terakhir</dt>
                <dd class="text-ink-900 dark:text-ink-50 mt-0.5">{{ $user->last_login_at?->format('d M Y H:i') ?? 'Belum pernah' }}</dd>
            </div>
        </dl>
    </x-card>

    <x-card>
        <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-50 mb-4">Riwayat Perubahan</h2>

        @forelse ($user->historyEntries as $entry)
            <div class="flex items-start gap-3 py-3 border-b border-ink-100 dark:border-ink-700 last:border-0">
                <div class="w-2 h-2 rounded-full bg-brand-500 mt-1.5 shrink-0"></div>
                <div>
                    <p class="text-sm text-ink-900 dark:text-ink-50">
                        {{ $entry->note ?? $entry->event_type }}
                        <span class="text-ink-400 dark:text-ink-500">— oleh {{ $entry->actor?->name ?? 'Sistem' }}</span>
                    </p>
                    <p class="text-xs text-ink-400 dark:text-ink-500 mt-0.5">{{ $entry->created_at->format('d M Y H:i') }}</p>
                </div>
            </div>
        @empty
            <p class="text-sm text-ink-400 dark:text-ink-500">Belum ada riwayat.</p>
        @endforelse
    </x-card>
</div>
@endsection
