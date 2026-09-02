@extends('layouts.app')

@section('title', 'Branch')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-ink-900 dark:text-ink-50">Branch</h1>
            <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">Cabang / witel operasional di bawah tiap region.</p>
        </div>
        <a href="{{ route('branches.create') }}">
            <x-button type="button" class="!w-auto px-4">+ Tambah Branch</x-button>
        </a>
    </div>

    <form method="GET" action="{{ route('branches.index') }}" class="flex gap-3 mb-4">
        <input type="text" name="q" value="{{ $q }}" placeholder="Cari kode atau nama branch..."
            class="flex-1 rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-3.5 py-2.5 text-sm text-ink-900 dark:text-ink-50 placeholder:text-ink-400 dark:placeholder:text-ink-500 shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition">
        <button type="submit" class="rounded-lg border border-ink-100 dark:border-ink-700 bg-white dark:bg-ink-800 px-4 py-2.5 text-sm font-medium text-ink-700 dark:text-ink-200 hover:bg-ink-50 dark:hover:bg-ink-700 transition">Cari</button>
    </form>

    <x-table>
        <thead class="bg-ink-50 dark:bg-ink-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Kode</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Nama</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Region</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">User</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-ink-500 dark:text-ink-400">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-ink-100 dark:divide-ink-700">
            @forelse ($branches as $branch)
                <tr>
                    <td class="px-4 py-3 text-ink-900 dark:text-ink-50 font-medium">{{ $branch->code }}</td>
                    <td class="px-4 py-3 text-ink-600 dark:text-ink-300">{{ $branch->name }}</td>
                    <td class="px-4 py-3 text-ink-600 dark:text-ink-300">{{ $branch->regionRef?->name ?? $branch->region ?? '—' }}</td>
                    <td class="px-4 py-3 text-ink-600 dark:text-ink-300">{{ $branch->users_count }}</td>
                    <td class="px-4 py-3">
                        <x-badge :variant="$branch->is_active ? 'success' : 'neutral'">{{ $branch->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('branches.edit', $branch) }}" class="text-sm text-ink-600 dark:text-ink-300 hover:text-ink-900 dark:hover:text-ink-50 font-medium">Edit</a>
                        <span class="text-ink-300 dark:text-ink-600 mx-1">·</span>
                        <form method="POST" action="{{ route('branches.destroy', $branch) }}" class="inline" onsubmit="return confirm('Hapus branch ini?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-sm text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 font-medium">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-ink-400 dark:text-ink-500 text-sm">Belum ada branch.</td></tr>
            @endforelse
        </tbody>
    </x-table>

    <div class="mt-4">{{ $branches->links() }}</div>
</div>
@endsection
