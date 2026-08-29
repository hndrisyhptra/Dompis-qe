@extends('layouts.app')

@section('title', 'User Management')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">User Management</h1>
            <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">Kelola akun pengguna, role, dan branch.</p>
        </div>
        <a href="{{ route('users.create') }}">
            <x-button type="button" class="!w-auto px-4">+ Tambah User</x-button>
        </a>
    </div>

    <form method="GET" action="{{ route('users.index') }}" class="flex flex-col sm:flex-row gap-3 mb-4">
        <input
            type="text"
            name="q"
            value="{{ $q }}"
            placeholder="Cari nama, username, atau NIK..."
            class="flex-1 rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 placeholder:text-ink-400 dark:placeholder:text-ink-500 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition"
        >
        <select name="role" class="rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition">
            <option value="">Semua Role</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected($roleFilter == $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-4 py-2.5 text-sm font-medium text-ink-700 dark:text-ink-200 hover:bg-ink-50 dark:hover:bg-ink-700 transition">
            Filter
        </button>
    </form>

    <x-table>
        <thead class="bg-ink-50 dark:bg-ink-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Nama</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Username / NIK</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Role</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Branch</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-ink-100 dark:divide-ink-700">
            @forelse ($users as $user)
                <tr>
                    <td class="px-4 py-3 text-ink-900 dark:text-ink-50 font-medium">{{ $user->name }}</td>
                    <td class="px-4 py-3 text-ink-600 dark:text-ink-300">
                        {{ $user->username }}
                        @if ($user->nik)
                            <span class="text-ink-400 dark:text-ink-500">· {{ $user->nik }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <x-badge variant="info">{{ $user->role?->name ?? '—' }}</x-badge>
                    </td>
                    <td class="px-4 py-3 text-ink-600 dark:text-ink-300">{{ $user->branch?->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <x-badge :variant="$user->isActive() ? 'success' : 'neutral'">
                            {{ $user->isActive() ? 'Aktif' : 'Nonaktif' }}
                        </x-badge>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('users.show', $user) }}" class="text-sm text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 font-medium">Detail</a>
                        <span class="text-ink-300 dark:text-ink-600 mx-1">·</span>
                        <a href="{{ route('users.edit', $user) }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50 font-medium">Edit</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-ink-400 dark:text-ink-500 text-sm">Belum ada user.</td>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
</div>
@endsection
